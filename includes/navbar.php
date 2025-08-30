<style>
  .navbar-brand,.navbar-text, .logout{
    color: gold;
  }
  .nav-link{
    color: white;
  }
</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark no-print">
  <div class="container-fluid">
    <a class="navbar-brand" href="../admin/dashboard.php">Crescent Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
      data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false"
      aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/categories.php">Categories</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/products.php">Products</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/suppliers.php">Suppliers</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/purchases.php">Purchases</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/sales.php">Sell</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/returns.php">Returns</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/dues.php">Dues</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/payment_history.php">Payment History</a></li>
        <li class="nav-item"><a class="nav-link" href="../modules/customer_history.php">Customer History</a></li>

      </ul>
      <div class="d-flex">
        <span class="navbar-text me-3">
          Welcome, <?= htmlspecialchars($_SESSION['admin']); ?>
        </span>
        <a href="../admin/logout.php" class="btn btn-outline-light btn-sm logout">Logout</a>
      </div>
    </div>
  </div>
</nav>
