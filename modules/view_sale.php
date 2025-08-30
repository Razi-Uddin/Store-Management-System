<?php
// modules/view_sale.php
session_start();
include '../includes/db.php';

// 1) Auth check
if (!isset($_SESSION['admin'])) {
    header("Location: ../admin/login.php");
    exit();
}

// 2) Validate & fetch sale ID
if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid sale ID.");
}
$sale_id = (int)$_GET['id'];

// 3) Fetch sale master with customer (including balances)
$stmt = $conn->prepare(
    "SELECT s.id, s.date, s.total_amount, s.payment_type,
            c.name AS customer_name, c.contact, c.address, 
            c.previous_balance, c.current_balance
     FROM sales s
     JOIN customers c ON s.customer_id = c.id
     WHERE s.id = ?"
);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    die("Sale not found.");
}

// 4) Fetch sale items
$stmt = $conn->prepare(
    "SELECT si.product_id, si.quantity, si.sell_price, p.name
     FROM sale_items si
     JOIN products p ON si.product_id = p.id
     WHERE si.sale_id = ?"
);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>View Sale #<?= $sale['id'] ?> – Crescent Exclusive Fabric</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    @media print {
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body class="bg-light">
<?php include "../includes/navbar.php" ?>
<div class="container my-5 bg-white p-4 shadow-sm">
  <div class="row mb-4">
    <div class="col-md-6">
      <h2>Invoice #<?= $sale['id'] ?></h2>
      <p>
        <strong>Date:</strong> <?= $sale['date'] ?><br>
        <strong>Payment Type:</strong> <?= htmlspecialchars($sale['payment_type']) ?>
      </p>
    </div>
    <div class="col-md-6 text-end">
      <h5>Customer Details</h5>
      <p>
        <strong><?= htmlspecialchars($sale['customer_name']) ?></strong><br>
        <?= htmlspecialchars($sale['contact']) ?><br>
        <?= nl2br(htmlspecialchars($sale['address'])) ?>
      </p>
    </div>
  </div>

  <table class="table table-bordered mb-4">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Product</th>
        <th class="text-center">Qty</th>
        <th class="text-end">Unit Price</th>
        <th class="text-end">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $i = 1;
      $total = 0;
      while ($row = $items->fetch_assoc()):
          $subtotal = $row['quantity'] * $row['sell_price'];
          $total += $subtotal;
      ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td class="text-center"><?= $row['quantity'] ?></td>
          <td class="text-end"><?= number_format($row['sell_price'],2) ?></td>
          <td class="text-end"><?= number_format($subtotal,2) ?></td>
        </tr>
      <?php endwhile; ?>
      <tr>
        <td colspan="4" class="text-end"><strong>Total:</strong></td>
        <td class="text-end"><strong><?= number_format($total,2) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <div class="mb-4">
    <p>
      <strong>Previous Balance:</strong> Rs. <?= number_format($sale['previous_balance'], 2) ?><br>
      <strong>Current Balance:</strong> Rs. <?= number_format($sale['current_balance'], 2) ?>
    </p>
  </div>

  <div class="d-flex justify-content-between no-print">
    <a href="sales.php" class="btn btn-secondary">← Back to Sales</a>
    <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
