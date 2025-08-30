<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// ===== DELETE DUE =====
if (isset($_GET['delete_id'])) {
    $due_id = (int)$_GET['delete_id'];

    $due_stmt = $conn->prepare("SELECT * FROM dues WHERE id = ?");
    $due_stmt->bind_param("i", $due_id);
    $due_stmt->execute();
    $due = $due_stmt->get_result()->fetch_assoc();

    if ($due) {
        $customer_id = $due['customer_id'];
        $amount_received = $due['amount_received'];
        $sale_id = $due['sale_id'];

        $cust_stmt = $conn->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?");
        $cust_stmt->bind_param("di", $amount_received, $customer_id);
        $cust_stmt->execute();

        $conn->query("DELETE FROM dues WHERE id = $due_id");

        echo "<script>alert('Due deleted successfully.'); window.location.href='dues.php';</script>";
        exit();
    }
}

// ===== UPDATE DUE =====
if (isset($_POST['edit_due'])) {
    $due_id = (int)$_POST['due_id'];
    $sale_amount = (float)$_POST['sale_amount'];
    $amount_received = (float)$_POST['amount_received'];

    $due_stmt = $conn->prepare("SELECT * FROM dues WHERE id = ?");
    $due_stmt->bind_param("i", $due_id);
    $due_stmt->execute();
    $due = $due_stmt->get_result()->fetch_assoc();

    if ($due) {
        $customer_id = $due['customer_id'];
        $old_received = $due['amount_received'];
        $sale_id = $due['sale_id'];

        $diff = $amount_received - $old_received;
        $new_balance = $due['current_balance'] - $diff;

        $update_due = $conn->prepare("UPDATE dues SET sale_amount = ?, amount_received = ?, current_balance = ? WHERE id = ?");
        $update_due->bind_param("dddi", $sale_amount, $amount_received, $new_balance, $due_id);
        $update_due->execute();

        $update_customer = $conn->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?");
        $update_customer->bind_param("di", $diff, $customer_id);
        $update_customer->execute();

        $update_payment = $conn->prepare("UPDATE payments SET amount_paid = ?, payment_date = NOW() WHERE sale_id = ?");
        $update_payment->bind_param("di", $amount_received, $sale_id);
        $update_payment->execute();

        echo "<script>alert('Due updated successfully.'); window.location.href='dues.php';</script>";
        exit();
    }
}

// ===== PAGINATION + SEARCH =====
$limit = 10;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = '';
if ($search != '') {
    $search = $conn->real_escape_string($search);
    $search_sql = "WHERE c.name LIKE '%$search%' OR d.sale_id LIKE '%$search%'";
}

// Count total dues
$count_query = $conn->query("
    SELECT COUNT(*) as total 
    FROM dues d 
    JOIN customers c ON d.customer_id = c.id 
    $search_sql
");
$total_rows = $count_query->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch dues
$dues = $conn->query("
    SELECT d.id, d.sale_id, d.sale_amount, d.amount_received, d.previous_balance, 
           d.current_balance, d.created_at, c.name AS customer
    FROM dues d
    JOIN customers c ON d.customer_id = c.id
    $search_sql
    ORDER BY d.id DESC
    LIMIT $limit OFFSET $offset
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dues</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container py-4">
    <h2 class="mb-4">Dues List</h2>

    <!-- Search Form -->
    <form method="GET" class="row g-3 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search by customer or sale ID" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <!-- Table -->
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Sale ID</th>
                <th>Customer</th>
                <th>Sale Amount</th>
                <th>Amount Received</th>
                <th>Previous Balance</th>
                <th>Current Balance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($dues->num_rows > 0): ?>
            <?php while ($d = $dues->fetch_assoc()): ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= date('d-m-Y h:i A', strtotime($d['created_at'])) ?></td>
                    <td><?= $d['sale_id'] ?></td>
                    <td><?= htmlspecialchars($d['customer']) ?></td>
                    <td><?= number_format($d['sale_amount'], 2) ?></td>
                    <td><?= number_format($d['amount_received'], 2) ?></td>
                    <td><?= number_format($d['previous_balance'], 2) ?></td>
                    <td><?= number_format($d['current_balance'], 2) ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning btn-edit" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editModal"
                            data-id="<?= $d['id'] ?>"
                            data-sale="<?= $d['sale_amount'] ?>"
                            data-received="<?= $d['amount_received'] ?>"
                        >Edit</button>
                        <a href="dues.php?delete_id=<?= $d['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="9" class="text-center">No dues found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Previous</a>
                </li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Due</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="due_id" id="due_id">
            <div class="mb-3">
                <label>Sale Amount</label>
                <input type="number" step="0.01" name="sale_amount" id="sale_amount" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Amount Received</label>
                <input type="number" step="0.01" name="amount_received" id="amount_received" class="form-control" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" name="edit_due" class="btn btn-primary">Update</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".btn-edit").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("due_id").value = this.dataset.id;
            document.getElementById("sale_amount").value = this.dataset.sale;
            document.getElementById("amount_received").value = this.dataset.received;
        });
    });
});
</script>
</body>
</html>
