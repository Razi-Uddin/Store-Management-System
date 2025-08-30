<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// ADD supplier
if (isset($_POST['add_supplier'])) {
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact, address) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $contact, $address);
    $stmt->execute();
}

// UPDATE supplier
if (isset($_POST['edit_supplier'])) {
    $id = $_POST['supplier_id'];
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("UPDATE suppliers SET name=?, contact=?, address=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $contact, $address, $id);
    $stmt->execute();
}

// DELETE supplier
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM suppliers WHERE id = $id");
}

// GET supplier for editing
$edit_supplier = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM suppliers WHERE id = $id");
    $edit_supplier = $result->fetch_assoc();
}

// SEARCH + PAGINATION
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "";
$params = [];
$bind = "";

if ($search !== '') {
    $where = "WHERE name LIKE ?";
    $params[] = "%$search%";
    $bind = "s";
}

// COUNT total
$count_sql = "SELECT COUNT(*) as total FROM suppliers $where";
$count_stmt = $conn->prepare($count_sql);
if ($search !== '') {
    $count_stmt->bind_param($bind, ...$params);
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// FETCH suppliers
$sql = "SELECT * FROM suppliers $where ORDER BY id DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);

if ($search !== '') {
    $params[] = $offset;
    $params[] = $limit;
    $bind .= "ii";
    $stmt->bind_param($bind, ...$params);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}

$stmt->execute();
$suppliers = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Suppliers - Crescent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container mt-4">
    <h3>Supplier Management</h3>

    <!-- Add/Edit Supplier Form -->
    <form method="POST" class="row g-3 mb-4">
        <input type="hidden" name="supplier_id" value="<?= $edit_supplier['id'] ?? '' ?>">
        <div class="col-md-3">
            <input type="text" name="name" class="form-control" placeholder="Supplier Name" value="<?= $edit_supplier['name'] ?? '' ?>" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="contact" class="form-control" placeholder="Contact" value="<?= $edit_supplier['contact'] ?? '' ?>" required>
        </div>
        <div class="col-md-4">
            <input type="text" name="address" class="form-control" placeholder="Address" value="<?= $edit_supplier['address'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <?php if ($edit_supplier): ?>
                <button type="submit" name="edit_supplier" class="btn btn-warning w-100">Update</button>
            <?php else: ?>
                <button type="submit" name="add_supplier" class="btn btn-success w-100">Add</button>
            <?php endif; ?>
        </div>
    </form>

    <!-- Search Filter -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search by name" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-primary">Search</button>
        </div>
    </form>

    <!-- Supplier Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $suppliers->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['contact']) ?></td>
                    <td><?= htmlspecialchars($row['address']) ?></td>
                    <td>
                        <a href="?edit=<?= $row['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="?delete=<?= $row['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this supplier?')">Delete</a>
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
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
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
