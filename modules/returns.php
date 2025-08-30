<?php
// modules/returns.php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../admin/login.php");
    exit();
}

$error_sale = '';
$error_purchase = '';

// Fetch dropdown data
$sales     = $conn->query(
    "SELECT s.id, c.name AS customer, s.date
     FROM sales s
     JOIN customers c ON s.customer_id = c.id
     ORDER BY s.date DESC"
);
$purchases = $conn->query(
    "SELECT p.id, s.name AS supplier, p.date
     FROM purchases p
     JOIN suppliers s ON p.supplier_id = s.id
     ORDER BY p.date DESC"
);
$products  = $conn->query("SELECT * FROM products");
// --- Handle new Sales Return ---
if (isset($_POST['return_sale'])) {
    $sale_id    = (int)$_POST['sale_id'];
    $product_id = (int)$_POST['product_id'];
    $qty        = (int)$_POST['quantity'];

    // total sold
    $stmt = $conn->prepare(
        "SELECT quantity FROM sale_items WHERE sale_id = ? AND product_id = ?"
    );
    $stmt->bind_param("ii", $sale_id, $product_id);
    $stmt->execute();
    $stmt->bind_result($sold_qty);
    $stmt->fetch();
    $stmt->close();

    // total already returned
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(quantity),0) FROM sales_returns WHERE sale_id = ? AND product_id = ?"
    );
    $stmt->bind_param("ii", $sale_id, $product_id);
    $stmt->execute();
    $stmt->bind_result($returned_qty);
    $stmt->fetch();
    $stmt->close();

    $available = $sold_qty - $returned_qty;
    if ($qty > 0 && $qty <= $available) {
        $stmt = $conn->prepare(
            "INSERT INTO sales_returns (sale_id, product_id, quantity, return_date) VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param("iii", $sale_id, $product_id, $qty);
        $stmt->execute();
        $stmt->close();

        $conn->query(
            "UPDATE products SET stock = stock + $qty WHERE id = $product_id"
        );
    } else {
        $error_sale = "Return quantity exceeds available quantity. Available quantity for return: $available.";
    }
}

// --- Handle new Purchase Return ---
if (isset($_POST['return_purchase'])) {
    $purchase_id = (int)$_POST['purchase_id'];
    $product_id  = (int)$_POST['product_id'];
    $qty         = (int)$_POST['quantity'];

    // total purchased
    $stmt = $conn->prepare(
        "SELECT quantity FROM purchase_items WHERE purchase_id = ? AND product_id = ?"
    );
    $stmt->bind_param("ii", $purchase_id, $product_id);
    $stmt->execute();
    $stmt->bind_result($purch_qty);
    $stmt->fetch();
    $stmt->close();

    // total already returned
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(quantity),0) FROM purchase_returns WHERE purchase_id = ? AND product_id = ?"
    );
    $stmt->bind_param("ii", $purchase_id, $product_id);
    $stmt->execute();
    $stmt->bind_result($returned_purch_qty);
    $stmt->fetch();
    $stmt->close();

    $avail_purch = $purch_qty - $returned_purch_qty;
    if ($qty > 0 && $qty <= $avail_purch) {
        $stmt = $conn->prepare(
            "INSERT INTO purchase_returns (purchase_id, product_id, quantity, return_date) VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param("iii", $purchase_id, $product_id, $qty);
        $stmt->execute();
        $stmt->close();

        $conn->query(
            "UPDATE products SET stock = stock - $qty WHERE id = $product_id"
        );
    } else {
        $error_purchase = "Return quantity must be between 1 and $avail_purch.";
    }
}

// --- Handle deletion of sales return ---
if (isset($_POST['delete_sales_return_id'])) {
    $id = (int)$_POST['delete_sales_return_id'];
    $stmt = $conn->prepare("SELECT product_id, quantity FROM sales_returns WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($pid, $q);
    if ($stmt->fetch()) {
        $stmt->close();
        $conn->query("DELETE FROM sales_returns WHERE id = $id");
        $conn->query("UPDATE products SET stock = stock - $q WHERE id = $pid");
    }
}

// --- Handle deletion of purchase return ---
if (isset($_POST['delete_purchase_return_id'])) {
    $id = (int)$_POST['delete_purchase_return_id'];
    $stmt = $conn->prepare("SELECT product_id, quantity FROM purchase_returns WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($pid, $q);
    if ($stmt->fetch()) {
        $stmt->close();
        $conn->query("DELETE FROM purchase_returns WHERE id = $id");
        $conn->query("UPDATE products SET stock = stock + $q WHERE id = $pid");
    }
}
// --- Handle update of sales return ---
if (isset($_POST['update_sales_return'])) {
    $id      = (int)$_POST['return_id'];
    $new_qty = (int)$_POST['new_quantity'];

    // fetch old and identifiers
    $stmt = $conn->prepare("SELECT sale_id, product_id, quantity FROM sales_returns WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($sid, $pid, $old_qty);
    $stmt->fetch();
    $stmt->close();

    // get sold and returned (excluding this)
    $stmt = $conn->prepare("SELECT quantity FROM sale_items WHERE sale_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $sid, $pid);
    $stmt->execute();
    $stmt->bind_result($sold_qty);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) FROM sales_returns WHERE sale_id = ? AND product_id = ? AND id <> ?");
    $stmt->bind_param("iii", $sid, $pid, $id);
    $stmt->execute();
    $stmt->bind_result($ret_qty);
    $stmt->fetch();
    $stmt->close();

    $avail    = $sold_qty - $ret_qty;
    
    if ($new_qty > 0 && $new_qty <= $avail) {
        $diff = $new_qty - $old_qty;
        $conn->query("UPDATE sales_returns SET quantity = $new_qty WHERE id = $id");
        $conn->query("UPDATE products SET stock = stock + $diff WHERE id = $pid");
    } else {
        $error_sale = "Return quantity must be between 1 and $avail.";
    }

    header("Location: returns.php"); exit;
}
// --- Handle update of purchase return ---
if (isset($_POST['update_purchase_return'])) {
    $id      = (int)$_POST['return_id'];
    $new_qty = (int)$_POST['new_quantity'];

    // Fetch old values
    $stmt = $conn->prepare("SELECT purchase_id, product_id, quantity FROM purchase_returns WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($purch_id, $pid, $old_qty);
    $stmt->fetch();
    $stmt->close();

    // Fetch purchase quantity
    $stmt = $conn->prepare("SELECT quantity FROM purchase_items WHERE purchase_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $purch_id, $pid);
    $stmt->execute();
    $stmt->bind_result($purch_qty);
    $stmt->fetch();
    $stmt->close();

    // Fetch total returned (excluding this return)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) FROM purchase_returns WHERE purchase_id = ? AND product_id = ? AND id <> ?");
    $stmt->bind_param("iii", $purch_id, $pid, $id);
    $stmt->execute();
    $stmt->bind_result($ret_pqs);
    $stmt->fetch();
    $stmt->close();

    $avail_p = $purch_qty - $ret_pqs;

    if ($new_qty > 0 && $new_qty <= $avail_p) {
        $diff = $old_qty - $new_qty;
        $conn->query("UPDATE purchase_returns SET quantity = $new_qty WHERE id = $id");
        $conn->query("UPDATE products SET stock = stock + $diff WHERE id = $pid");
    } else {
        $error_purchase = "Return quantity must be between 1 and $avail_p.";
    }

    header("Location: returns.php"); exit;
}

// Fetch all returns for display
$sales_returns     = $conn->query(
    "SELECT sr.id, sr.return_date, sr.sale_id, c.name AS customer, p.name AS product, sr.quantity
     FROM sales_returns sr
     JOIN sales s ON sr.sale_id = s.id
     JOIN customers c ON s.customer_id = c.id
     JOIN products p ON sr.product_id = p.id
     ORDER BY sr.return_date DESC"
);
$purchase_returns  = $conn->query(
    "SELECT pr.id, pr.return_date, pr.purchase_id, s.name AS supplier, p.name AS product, pr.quantity
     FROM purchase_returns pr
     JOIN purchases pu ON pr.purchase_id = pu.id
     JOIN suppliers s ON pu.supplier_id = s.id
     JOIN products p ON pr.product_id = p.id
     ORDER BY pr.return_date DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Returns Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php include "../includes/navbar.php" ?>
  <div class="container mt-4">
    <h3>Returns Management</h3>
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#sales" style="color: black;">Sales Returns</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#purchases" style="color: black;">Purchase Returns</a></li>
    </ul>
    <div class="tab-content">
      <!-- Sales Returns -->
       
      <div class="tab-pane fade show active" id="sales">
        <?php if($error_sale): ?><div class="alert alert-danger"><?= $error_sale ?></div><?php endif; ?>
        <form method="POST" class="row g-3 mb-4">
          <div class="col-md-3">
            <select name="sale_id" class="form-select" required>
              <option value="">Select Sale</option>
              <?php while($s = $sales->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>">#<?= $s['id'] ?> — <?= htmlspecialchars($s['customer']) ?> (<?= $s['date'] ?>)</option>
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
            <input type="number" name="quantity" class="form-control" placeholder="Qty" min="1" required>
          </div>
          <div class="col-md-2">
            <button type="submit" name="return_sale" class="btn btn-warning w-100">Return Sale</button>
          </div>
        </form>
        <h5>Previous Sales Returns</h5>
        <table class="table table-sm table-bordered">
          <thead><tr><th>#</th><th>Date</th><th>Sale ID</th><th>Customer</th><th>Product</th><th>Qty</th><th>Actions</th></tr></thead>
          <tbody>
          <?php while($r = $sales_returns->fetch_assoc()): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= $r['return_date'] ?></td>
              <td><?= $r['sale_id'] ?></td>
              <td><?= htmlspecialchars($r['customer']) ?></td>
              <td><?= htmlspecialchars($r['product']) ?></td>
              <td><?= $r['quantity'] ?></td>
              <td>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                  <input type="hidden" name="delete_sales_return_id" value="<?= $r['id'] ?>">
                  <button class="btn btn-sm btn-danger">Delete</button>
                </form>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editSale<?= $r['id'] ?>">Edit</button>
                <div class="modal fade" id="editSale<?= $r['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                      <input type="hidden" name="return_id" value="<?= $r['id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Return #<?= $r['id'] ?></h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <label>New Quantity</label>
                        <input type="number" name="new_quantity" value="<?= $r['quantity'] ?>"
                               class="form-control" min="1" required>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" name="update_sales_return" class="btn btn-success">Save</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <!-- Purchase Returns -->
      <div class="tab-pane fade" id="purchases">
        <?php if($error_purchase): ?><div class="alert alert-danger"><?= $error_purchase ?></div><?php endif; ?>
        <form method="POST" class="row g-3 mb-4">
          <div class="col-md-3">
            <select name="purchase_id" class="form-select" required>
              <option value="">Select Purchase</option>
              <?php while($pr = $purchases->fetch_assoc()): ?>
                <option value="<?= $pr['id'] ?>">#<?= $pr['id'] ?> — <?= htmlspecialchars($pr['supplier']) ?> (<?= $pr['date'] ?>)</option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-3">
            <?php $products->data_seek(0); ?>
            <select name="product_id" class="form-select" required>
              <option value="">Select Product</option>
              <?php while($p = $products->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-2">
            <input type="number" name="quantity" class="form-control" placeholder="Qty" min="1" required>
          </div>
          <div class="col-md-2">
            <button type="submit" name="return_purchase" class="btn btn-warning w-100">Return Purchase</button>
          </div>
        </form>
        <h5>Previous Purchase Returns</h5>
        <table class="table table-sm table-bordered">
          <thead><tr><th>#</th><th>Date</th><th>Purchase ID</th><th>Supplier</th><th>Product</th><th>Qty</th><th>Actions</th></tr></thead>
          <tbody>
          <?php while($pr = $purchase_returns->fetch_assoc()): ?>
            <tr>
              <td><?= $pr['id'] ?></td>
              <td><?= $pr['return_date'] ?></td>
              <td><?= $pr['purchase_id'] ?></td>
              <td><?= htmlspecialchars($pr['supplier']) ?></td>
              <td><?= htmlspecialchars($pr['product']) ?></td>
              <td><?= $pr['quantity'] ?></td>
              <td>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                  <input type="hidden" name="delete_purchase_return_id" value="<?= $pr['id'] ?>">
                  <button class="btn btn-sm btn-danger">Delete</button>
                </form>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPurch<?= $pr['id'] ?>">Edit</button>
                <div class="modal fade" id="editPurch<?= $pr['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                      <input type="hidden" name="return_id" value="<?= $pr['id'] ?>">
                      <div class="modal-header"> <h5 class="modal-title">Edit Return #<?= $pr['id'] ?></h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <label>New Quantity</label>
                        <input type="number" name="new_quantity" value="<?= $pr['quantity'] ?>"
                               class="form-control" min="1" required>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" name="update_purchase_return" class="btn btn-success">Save</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
