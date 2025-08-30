<?php
// index.php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin/login.php");
    exit();
}

// Fetch counts with error checking
$products_count = ($result = $conn->query("SELECT COUNT(*) AS total FROM products")) && ($row = $result->fetch_assoc()) ? $row['total'] : 0;
$customers_count = ($result = $conn->query("SELECT COUNT(*) AS total FROM customers")) && ($row = $result->fetch_assoc()) ? $row['total'] : 0;
$sales_count = ($result = $conn->query("SELECT COUNT(*) AS total FROM sales")) && ($row = $result->fetch_assoc()) ? $row['total'] : 0;
$sales_returns_count = ($result = $conn->query("SELECT COUNT(*) AS total FROM sales_returns")) && ($row = $result->fetch_assoc()) ? $row['total'] : 0;
$purchases_count = ($result = $conn->query("SELECT COUNT(*) AS total FROM purchases")) && ($row = $result->fetch_assoc()) ? $row['total'] : 0;
$purchases_returns_count = ($result = $conn->query("SELECT COUNT(*) AS total FROM purchase_returns")) && ($row = $result->fetch_assoc()) ? $row['total'] : 0;

// Get total sales revenue
$total_sales_price = 0;
$sales_query = $conn->query("SELECT SUM(total_amount) AS total_sales_price FROM sales");
if ($sales_query && $row = $sales_query->fetch_assoc()) {
    $total_sales_price = $row['total_sales_price'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard - Crescent Exclusive Fabric</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<style>
  .navbar-brand,.navbar-text, .logout{
    color: gold;
  }
  .nav-link{
    color: white;
  }
</style>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="admin/dashboard.php">Crescent Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
      data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false"
      aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/categories.php">Categories</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/products.php">Products</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/suppliers.php">Suppliers</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/purchases.php">Purchases</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/sales.php">Sell</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/returns.php">Returns</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/dues.php">Dues</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/payment_history.php">Payment History</a></li>
        <li class="nav-item"><a class="nav-link" href="modules/customer_history.php">Customer History</a></li>
      </ul>
      <div class="d-flex">
        <span class="navbar-text me-3">
          Welcome, <?= htmlspecialchars($_SESSION['admin']); ?>
        </span>
        <a href="admin/logout.php" class="btn btn-outline-light btn-sm logout">Logout</a>
      </div>
    </div>
  </div>
</nav>

<div class="container mt-4">
  <h3>Welcome to the Dashboard, Admin</h3>

  <div class="row mt-4">
    <div class="col-md-4">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h5 class="card-title">Total Products</h5>
          <p class="card-text"><?= $products_count ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h5 class="card-title">Total Customers</h5>
          <p class="card-text"><?= $customers_count ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h5 class="card-title">Total Sales</h5>
          <p class="card-text"><?= $sales_count ?></p>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-4">
      <div class="card bg-danger text-white">
        <div class="card-body">
          <h5 class="card-title">Sales Returns</h5>
          <p class="card-text"><?= $sales_returns_count ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h5 class="card-title">Total Purchases</h5>
          <p class="card-text"><?= $purchases_count ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-secondary text-white">
        <div class="card-body">
          <h5 class="card-title">Purchases Returns</h5>
          <p class="card-text"><?= $purchases_returns_count ?></p>
        </div>
      </div>
    </div>
  </div>



  <div class="row mt-4">
    <div class="col-md-4">
      <a href="modules/sales.php" class="btn btn-primary w-100">View Sales</a>
    </div>
    <div class="col-md-4">
      <a href="modules/purchases.php" class="btn btn-primary w-100">View Purchases</a>
    </div>
    <div class="col-md-4">
      <a href="modules/returns.php" class="btn btn-warning w-100">View Returns</a>
    </div>
  </div>
<br>
    <!-- Total Sales Revenue Card -->
  <div class="row mt-12">
    <div class="col-md-12">
      <div class="card bg-dark text-white">
        <div class="card-body">
          <h5 class="card-title">Total Sales Revenue</h5>
          <p class="card-text">Rs. <?= number_format($total_sales_price, 2) ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Bar Chart -->
  <div class="row mt-5">
    <div class="col-md-12">
      <h4>Analytics Overview</h4>
      <canvas id="analyticsChart"></canvas>
    </div>
  </div>
</div>

<!-- Chart Script -->
<script>
  const ctx = document.getElementById('analyticsChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Products', 'Customers', 'Sales', 'Sales Returns', 'Purchases', 'Purchase Returns'],
      datasets: [{
        label: 'Total Count',
        data: [<?= $products_count ?>, <?= $customers_count ?>, <?= $sales_count ?>, <?= $sales_returns_count ?>, <?= $purchases_count ?>, <?= $purchases_returns_count ?>],
        backgroundColor: [
          '#17a2b8',
          '#28a745',
          '#ffc107',
          '#dc3545',
          '#007bff',
          '#6c757d'
        ],
        borderColor: '#343a40',
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true,
          precision: 0
        }
      }
    }
  });
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
