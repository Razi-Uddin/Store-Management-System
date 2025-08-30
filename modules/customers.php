<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Handle Add
if (isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $previous_balance = $_POST['previous_balance'];
    $current_balance = $_POST['current_balance'];

    $stmt = $conn->prepare("INSERT INTO customers (name, contact, address, previous_balance, current_balance) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdd", $name, $contact, $address, $previous_balance, $current_balance);
    $stmt->execute();
}

// Handle Edit
if (isset($_POST['edit_customer'])) {
    $id = $_POST['customer_id'];
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $previous_balance = $_POST['previous_balance'];
    $current_balance = $_POST['current_balance'];

    $stmt = $conn->prepare("UPDATE customers SET name=?, contact=?, address=?, previous_balance=?, current_balance=? WHERE id=?");
    $stmt->bind_param("sssddi", $name, $contact, $address, $previous_balance, $current_balance, $id);
    $stmt->execute();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM customers WHERE id = $id");
}

// Handle Edit Load
$edit_customer = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $res = $conn->query("SELECT * FROM customers WHERE id = $id");
    $edit_customer = $res->fetch_assoc();
}

// Pagination + Search
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

// Count total
$count_sql = "SELECT COUNT(*) AS total FROM customers $where";
$count_stmt = $conn->prepare($count_sql);
if ($search !== '') {
    $count_stmt->bind_param($bind, ...$params);
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated
$sql = "SELECT * FROM customers $where ORDER BY id DESC LIMIT ?, ?";
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
$customers = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customers - Crescent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container mt-4">
    <h3>Customer Management</h3>

    <!-- Add / Edit Form -->
    <form method="POST" class="row g-3 mb-4">
        <input type="hidden" name="customer_id" value="<?= $edit_customer['id'] ?? '' ?>">
        <div class="col-md-2">
            <input type="text" name="name" class="form-control" placeholder="Name" value="<?= $edit_customer['name'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <input type="text" name="contact" class="form-control" placeholder="Contact" value="<?= $edit_customer['contact'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <input type="text" name="address" class="form-control" placeholder="Address" value="<?= $edit_customer['address'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="previous_balance" class="form-control" placeholder="Previous Balance" value="<?= $edit_customer['previous_balance'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="current_balance" class="form-control" placeholder="Current Balance" value="<?= $edit_customer['current_balance'] ?? '' ?>" required>
        </div>
        <div class="col-md-2">
            <?php if ($edit_customer): ?>
                <button type="submit" name="edit_customer" class="btn btn-warning w-100">Update</button>
            <?php else: ?>
                <button type="submit" name="add_customer" class="btn btn-success w-100">Add</button>
            <?php endif; ?>
        </div>
    </form>

    <!-- Search -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search by name" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-primary">Search</button>
        </div>
    </form>

    <!-- Customer Table -->
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Previous Balance</th>
                <th>Current Balance</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $customers->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['contact']) ?></td>
                    <td><?= htmlspecialchars($row['address']) ?></td>
                    <td><?= number_format($row['previous_balance'], 2) ?></td>
                    <td><?= number_format($row['current_balance'], 2) ?></td>
                    <td>
                        <a href="?edit=<?= $row['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="?delete=<?= $row['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this customer?')">Delete</a>
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
