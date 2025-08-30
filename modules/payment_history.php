<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$default_per_page = 10;
$per_page_options = [10, 20, 50];
$per_page = isset($_GET['limit']) && in_array((int)$_GET['limit'], $per_page_options) ? (int)$_GET['limit'] : $default_per_page;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$start = ($page - 1) * $per_page;

$search_query = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'];
    $sale_id = $_POST['sale_id'];
    $amount_paid = floatval($_POST['amount_paid']);
    $method = $_POST['method'];
    $payment_date = date('Y-m-d');

    $due_stmt = $conn->prepare("SELECT current_balance FROM dues WHERE sale_id = ?");
    $due_stmt->bind_param("i", $sale_id);
    $due_stmt->execute();
    $due_result = $due_stmt->get_result();
    $due = $due_result->fetch_assoc();
    $previous_balance = floatval($due['current_balance']);
    $current_balance = $previous_balance - $amount_paid;

    $stmt = $conn->prepare("INSERT INTO payments (customer_id, sale_id, amount_paid, previous_balance, current_balance, method, payment_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiddsss", $customer_id, $sale_id, $amount_paid, $previous_balance, $current_balance, $method, $payment_date);
    $stmt->execute();

    $update_due = $conn->prepare("UPDATE dues SET current_balance = ? WHERE sale_id = ?");
    $update_due->bind_param("di", $current_balance, $sale_id);
    $update_due->execute();

    $update_cust = $conn->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?");
    $update_cust->bind_param("di", $amount_paid, $customer_id);
    $update_cust->execute();
}

$customers_stmt = $conn->prepare("SELECT id, name FROM customers ORDER BY name ASC");
$customers_stmt->execute();
$customers_result = $customers_stmt->get_result();
$customers = $customers_result->fetch_all(MYSQLI_ASSOC);

$total_query = "SELECT COUNT(*) as total FROM payments p JOIN customers c ON p.customer_id = c.id";
$params = [];
$where = "";

if ($search_query !== "") {
    $where = " WHERE c.name LIKE ?";
    $params[] = "%$search_query%";
    $total_stmt = $conn->prepare($total_query . $where);
    $total_stmt->bind_param("s", ...$params);
} else {
    $total_stmt = $conn->prepare($total_query);
}

$total_stmt->execute();
$total_result = $total_stmt->get_result()->fetch_assoc();
$total_pages = ceil($total_result['total'] / $per_page);

$query = "SELECT p.*, c.name AS customer_name FROM payments p JOIN customers c ON p.customer_id = c.id";
if ($search_query !== "") {
    $query .= $where;
}
$query .= " ORDER BY p.id DESC LIMIT ?, ?";

$stmt = $conn->prepare($query);
if ($search_query !== "") {
    $types = "sii";
    $bind_params = array_merge([$search_query], [$start, $per_page]);
    $stmt->bind_param($types, ...$bind_params);
} else {
    $stmt->bind_param("ii", $start, $per_page);
}

$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);

// Monthly summary
$summary_stmt = $conn->prepare("SELECT SUM(amount_paid) AS total_paid FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$total_paid_this_month = $summary['total_paid'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<style>
    @media print {
        body * {
            visibility: hidden; /* Hide everything by default */
        }

        #paymentTable, 
        #paymentTable * {
            visibility: visible; /* Show only the table */
        }

        #paymentTable {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
        }
    }
</style>

<body class="bg-light">
<?php include "../includes/navbar.php" ?>
<div class="container mt-5">
    <h2 class="text-center mb-4">Add Payment</h2>
    <form method="POST" class="bg-white p-4 rounded border mb-5">
        <div class="row g-3">
            <div class="col-md-4">
                <label>Customer</label>
                <select name="customer_id" id="customer_id" class="form-select" onchange="filterSalesByCustomer()" required>
                    <option value="">Select Customer</option>
                    <?php foreach ($customers as $cust): ?>
                        <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label>Sale ID</label>
                <select name="sale_id" id="sale_id" class="form-select" onchange="fetchBalance()" required>
                    <option value="">Select Sale</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Amount Paid</label>
                <input type="number" name="amount_paid" step="0.01" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label>Method</label>
                <select name="method" class="form-select" required>
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>
        </div>
        <div class="mt-3 text-end">
            <button type="submit" class="btn btn-primary">Add Payment</button>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="get" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Search customer" value="<?= htmlspecialchars($search_query) ?>">
            <input type="hidden" name="limit" value="<?= $per_page ?>">
            <button class="btn btn-outline-primary">Search</button>
        </form>

        <form method="get" class="d-flex">
            <label class="me-2">Show</label>
            <select name="limit" onchange="this.form.submit()" class="form-select">
                <?php foreach ($per_page_options as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt == $per_page ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="page" value="<?= $page ?>">
        </form>
    </div>

    <div class="text-end mb-2">
        <button onclick="exportToExcel()" class="btn btn-success btn-sm">Export to Excel</button>
        <button onclick="window.print()" class="btn btn-secondary btn-sm">Print</button>
    </div>

    <h5 class="text-primary">Total Paid in <?= date('F Y') ?>: PKR <?= number_format($total_paid_this_month, 2) ?></h5>

    <table class="table table-bordered table-striped mt-3" id="paymentTable">
        <thead class="table-dark">
            <tr>
                <th>ID</th><th>Customer</th><th>Sale ID</th><th>Paid Amount</th><th>Previous Balance</th><th>Current Balance</th><th>Method</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= $payment['id'] ?></td>
                    <td><?= htmlspecialchars($payment['customer_name']) ?></td>
                    <td><?= $payment['sale_id'] ?></td>
                    <td><?= number_format($payment['amount_paid'], 2) ?></td>
                    <td><?= number_format($payment['previous_balance'], 2) ?></td>
                    <td><?= number_format($payment['current_balance'], 2) ?></td>
                    <td><?= htmlspecialchars($payment['method']) ?></td>
                    <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&limit=<?= $per_page ?><?= $search_query ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<script>
    function fetchBalance() {
        const saleId = document.getElementById('sale_id').value;
        if (saleId) {
            fetch('get_due_balance.php?sale_id=' + saleId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('previous_balance').value = data.balance || 0;
                });
        }
    }

    function filterSalesByCustomer() {
        const customerId = document.getElementById('customer_id').value;
        const saleSelect = document.getElementById('sale_id');
        saleSelect.innerHTML = '<option value="">Loading...</option>';
        fetch('get_sales_by_customer.php?customer_id=' + customerId)
            .then(response => response.json())
            .then(data => {
                saleSelect.innerHTML = '<option value="">Select Sale</option>';
                data.forEach(sale => {
                    saleSelect.innerHTML += `<option value="${sale.id}">Sale #${sale.id}</option>`;
                });
            });
    }

    function exportToExcel() {
        const table = document.getElementById("paymentTable");
        const workbook = XLSX.utils.table_to_book(table, {sheet: "Payments"});
        XLSX.writeFile(workbook, "PaymentHistory.xlsx");
    }
</script>
</body>
</html>
