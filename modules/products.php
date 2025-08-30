<?php
// products.php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Fetch categories
$categories = $conn->query("SELECT * FROM categories");

// Handle Add Product
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $cost_price = $_POST['cost_price'];
    $sell_price = $_POST['sell_price'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("INSERT INTO products (name, category_id, cost_price, sell_price, stock) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siddi", $name, $category_id, $cost_price, $sell_price, $stock);
    $stmt->execute();
}

// Handle Edit Product
if (isset($_POST['update_product'])) {
    $id = $_POST['product_id'];
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $cost_price = $_POST['cost_price'];
    $sell_price = $_POST['sell_price'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("UPDATE products SET name=?, category_id=?, cost_price=?, sell_price=?, stock=? WHERE id=?");
    $stmt->bind_param("siddii", $name, $category_id, $cost_price, $sell_price, $stock, $id);
    $stmt->execute();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM products WHERE id = $id");
}

// Edit fetch
$edit_mode = false;
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_data = $edit_stmt->get_result()->fetch_assoc();
    $edit_mode = true;
}

// Pagination + Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "";
$params = [];
$bind_types = "";

if ($search !== '') {
    $where = "WHERE p.name LIKE ?";
    $params[] = "%$search%";
    $bind_types .= "s";
}

$count_sql = "SELECT COUNT(*) as total FROM products p JOIN categories c ON p.category_id = c.id $where";
$count_stmt = $conn->prepare($count_sql);
if ($search !== '') $count_stmt->bind_param($bind_types, ...$params);
$count_stmt->execute();
$total_result = $count_stmt->get_result()->fetch_assoc();
$total_products = $total_result['total'];
$total_pages = ceil($total_products / $limit);

$product_sql = "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id $where ORDER BY p.id DESC LIMIT ?, ?";
$product_stmt = $conn->prepare($product_sql);
if ($search !== '') {
    $params[] = $offset;
    $params[] = $limit;
    $bind_types .= "ii";
    $product_stmt->bind_param($bind_types, ...$params);
} else {
    $product_stmt->bind_param("ii", $offset, $limit);
}
$product_stmt->execute();
$products = $product_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products - Crescent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container mt-4">
    <h3><?= $edit_mode ? 'Edit Product' : 'Product Management' ?></h3>

    <!-- Add/Edit Form -->
    <form method="POST" class="row g-3 mb-4">
        <?php if ($edit_mode): ?>
            <input type="hidden" name="product_id" value="<?= $edit_data['id'] ?>">
        <?php endif; ?>
        <div class="col-md-2">
            <input type="text" name="name" class="form-control" placeholder="Product Name" value="<?= $edit_data['name'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php
                $categories->data_seek(0);
                while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= $cat['id'] ?>" <?= (isset($edit_data['category_id']) && $edit_data['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= $cat['name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="cost_price" class="form-control" placeholder="Cost Price" value="<?= $edit_data['cost_price'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="sell_price" class="form-control" placeholder="Sell Price" value="<?= $edit_data['sell_price'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <input type="number" name="stock" class="form-control" placeholder="Stock" value="<?= $edit_data['stock'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="<?= $edit_mode ? 'update_product' : 'add_product' ?>" class="btn btn-<?= $edit_mode ? 'primary' : 'success' ?> w-100">
                <?= $edit_mode ? 'Update' : 'Add Product' ?>
            </button>
        </div>
    </form>

    <!-- Search Filter -->
    <form method="GET" class="mb-3 row g-2">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search by product name">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-primary">Search</button>
        </div>
    </form>

    <!-- Product Table -->
    <table class="table table-bordered">
        <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Category</th>
            <th>Cost Price</th>
            <th>Sell Price</th>
            <th>Stock</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php while($row = $products->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category_name']) ?></td>
                <td><?= number_format($row['cost_price'], 2) ?></td>
                <td><?= number_format($row['sell_price'], 2) ?></td>
                <td><?= $row['stock'] ?></td>
                <td>
                    <a href="?edit=<?= $row['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?delete=<?= $row['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
</body>
</html>
