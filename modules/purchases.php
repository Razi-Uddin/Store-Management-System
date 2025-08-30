<?php
// purchases.php
session_start();
include '../includes/db.php';

// Auth check
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Search & Pagination setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

// Fetch suppliers & products
$suppliers = $conn->query("SELECT * FROM suppliers");
$products  = $conn->query("SELECT * FROM products");
if (!$suppliers || !$products) {
    die("Error fetching dropdown data: " . $conn->error);
}

// Add new purchase
if (isset($_POST['add_purchase'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $product_id  = (int)$_POST['product_id'];
    $quantity    = (int)$_POST['quantity'];
    $cost_price  = (float)$_POST['price'];
    $total_amt   = $quantity * $cost_price;

    $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, date, total_amount) VALUES (?, NOW(), ?)");
    if (!$stmt) die("Prepare failed (purchases): " . $conn->error);
    $stmt->bind_param("id", $supplier_id, $total_amt);
    if (!$stmt->execute()) die("Execute failed (purchases): " . $stmt->error);
    $purchase_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)");
    if (!$stmt) die("Prepare failed (items): " . $conn->error);
    $stmt->bind_param("iiid", $purchase_id, $product_id, $quantity, $cost_price);
    if (!$stmt->execute()) die("Execute failed (items): " . $stmt->error);
    $stmt->close();

    $conn->query("UPDATE products SET stock = stock + $quantity WHERE id = $product_id") or die("Stock update failed: " . $conn->error);
    echo "<div class='alert alert-success'>Purchase recorded successfully!</div>";
}

// Delete purchase
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $conn->query("DELETE FROM purchase_items WHERE purchase_id = $pid");
    $conn->query("DELETE FROM purchases WHERE id = $pid");
    echo "<div class='alert alert-warning'>Purchase deleted.</div>";
}

// Build SQL
$where = $search ? "WHERE s.name LIKE '%$search%' OR pr.name LIKE '%$search%'" : "";
$total_res = $conn->query("SELECT COUNT(*) AS total FROM purchase_items pi JOIN purchases p ON pi.purchase_id = p.id JOIN suppliers s ON p.supplier_id = s.id JOIN products pr ON pi.product_id = pr.id $where");
$total_rows = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT p.id AS purchase_id, p.date AS purchase_date, s.name AS supplier, pr.name AS product, pi.quantity, pi.cost_price, p.total_amount
        FROM purchase_items pi
        JOIN purchases p ON pi.purchase_id = p.id
        JOIN suppliers s ON p.supplier_id = s.id
        JOIN products pr ON pi.product_id = pr.id
        $where
        ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$purchases = $conn->query($sql) or die("Error fetching purchases: " . $conn->error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Purchase Transactions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container mt-4">
  <h3 class="mb-4">Purchase Transactions</h3>

  <!-- New Purchase Form -->
  <form method="POST" class="row g-3 mb-4">
    <div class="col-md-3">
      <select name="supplier_id" class="form-select" required>
        <option value="">Select Supplier</option>
        <?php $suppliers->data_seek(0); while($s = $suppliers->fetch_assoc()): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select name="product_id" class="form-select" required>
        <option value="">Select Product</option>
        <?php $products->data_seek(0); while($p = $products->fetch_assoc()): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="number" name="quantity" min="1" class="form-control" placeholder="Quantity" required>
    </div>
    <div class="col-md-2">
      <input type="number" step="0.01" name="price" class="form-control" placeholder="Cost Price" required>
    </div>
    <div class="col-md-2">
      <button type="submit" name="add_purchase" class="btn btn-success w-100">Add Purchase</button>
    </div>
  </form>

  <!-- Search -->
  <form method="GET" class="input-group mb-3">
    <input type="text" name="search" class="form-control" placeholder="Search by Supplier or Product" value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-outline-secondary" type="submit">Search</button>
  </form>

  <!-- Purchases Table -->
  <table class="table table-bordered">
    <thead>
      <tr>
        <th>#</th>
        <th>Date</th>
        <th>Supplier</th>
        <th>Product</th>
        <th>Qty</th>
        <th>Cost Price</th>
        <th>Total Amt</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php while($row = $purchases->fetch_assoc()): ?>
        <tr>
          <td><?= $row['purchase_id'] ?></td>
          <td><?= $row['purchase_date'] ?></td>
          <td><?= htmlspecialchars($row['supplier']) ?></td>
          <td><?= htmlspecialchars($row['product']) ?></td>
          <td><?= $row['quantity'] ?></td>
          <td><?= number_format($row['cost_price'], 2) ?></td>
          <td><?= number_format($row['total_amount'], 2) ?></td>
          <td>
            <a href="purchases.php?delete=<?= $row['purchase_id'] ?>" onclick="return confirm('Delete this purchase?')" class="btn btn-sm btn-danger">Delete</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <nav>
    <ul class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
