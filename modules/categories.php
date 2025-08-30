<?php
// categories.php
session_start();
include '../includes/db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Handle add category
if (isset($_POST['add_category'])) {
    $name = $_POST['name'];

    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM categories WHERE id = $id");
}

$categories = $conn->query("SELECT * FROM categories ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Categories - Crescent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container mt-4">
    <h3>Category Management</h3>

    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-10">
            <input type="text" name="name" class="form-control" placeholder="Category Name" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_category" class="btn btn-success w-100">Add Category</button>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $categories->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row['name'] ?></td>
                    <td>
                        <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
