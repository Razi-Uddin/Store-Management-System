<?php
// modules/customer_history.php
session_start();
include '../includes/db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: ../admin/login.php");
    exit();
}

// 1) Fetch all customers for dropdown
$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
if (!$customers) {
    die("Error fetching customers: " . $conn->error);
}

$sales = null;
if (!empty($_GET['customer_id'])) {
    $cid = (int)$_GET['customer_id'];

    // 2) Fetch all sales for this customer
    $stmt = $conn->prepare(
      "SELECT id, date, total_amount, payment_type
       FROM sales
       WHERE customer_id = ?
       ORDER BY date DESC"
    );
    if (!$stmt) {
      die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $sales = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"      content="width=device-width,initial-scale=1">
  <title>Customer Purchase History</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container py-4">

  <h3>Customer Purchase History</h3>

  <!-- Customer Selector -->
  <form method="GET" class="row g-3 mb-4">
    <div class="col-md-6">
      <select name="customer_id" class="form-select" required>
        <option value="">-- Choose Customer --</option>
        <?php while($c = $customers->fetch_assoc()): ?>
          <option value="<?= $c['id'] ?>"
            <?= (isset($cid) && $cid=== (int)$c['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">View History</button>
    </div>
  </form>

  <?php if ($sales): ?>
    <h5>Sales for <?= htmlspecialchars($_GET['customer_id'] ? $_GET['customer_id'] : '') ?></h5>
    <table class="table table-bordered">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $sales->fetch_assoc()): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= $row['date'] ?></td>
          <td><?= number_format($row['total_amount'],2) ?></td>
          <td><?= $row['payment_type'] ?></td>
          <td>
            <a href="view_sale.php?id=<?= $row['id'] ?>"
               class="btn btn-sm btn-info">View</a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  <?php elseif (isset($cid)): ?>
    <div class="alert alert-warning">No sales found for this customer.</div>
  <?php endif; ?>

  <a href="../admin/dashboard.php" class="btn btn-secondary mt-3">← Back to Dashboard</a>
</div>

<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>
