<?php
// admin/dashboard.php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard – Crescent Exclusive Fabric</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>
<body>

<?php include "../includes/navbar.php" ?>
<div class="container py-4">
  <h1 class="mb-4">Dashboard</h1>
  <div class="row g-4">
    <?php
    $cards = [
      ['title'=>'Categories','text'=>'Manage product categories','link'=>'../modules/categories.php','color'=>'primary'],
      ['title'=>'Products','text'=>'Manage all fabric items','link'=>'../modules/products.php','color'=>'success'],
      ['title'=>'Suppliers','text'=>'Supplier information','link'=>'../modules/suppliers.php','color'=>'warning'],
      ['title'=>'Customers','text'=>'Customer records','link'=>'../modules/customers.php','color'=>'info'],
      ['title'=>'Purchases','text'=>'Record new purchases','link'=>'../modules/purchases.php','color'=>'secondary'],
      ['title'=>'Sales','text'=>'Record new sales','link'=>'../modules/sales.php','color'=>'dark'],
      ['title'=>'Returns','text'=>'Handle returns','link'=>'../modules/returns.php','color'=>'danger'],
      ['title'=>'Dues','text'=>'Manage unpaid invoices','link'=>'../modules/dues.php','color'=>'outline-primary'],
    ];
    foreach ($cards as $c): ?>
      <div class="col-md-3">
        <div class="card border-<?= $c['color'] ?> h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><?= $c['title'] ?></h5>
            <p class="card-text flex-grow-1"><?= $c['text'] ?></p>
            <a href="<?= $c['link'] ?>"
               class="btn btn-<?= $c['color'] ?> btn-sm mt-auto">Go</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>
