<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['sale_items'])) {
    $_SESSION['sale_items'] = [];
}

// Fetch customers and products
$customers = $conn->query("SELECT * FROM customers ORDER BY name");
$products = $conn->query("SELECT * FROM products ORDER BY name");

// Handle item addition
if (isset($_POST['add_item'])) {
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $product_name = $_POST['product_name'];

    $_SESSION['sale_items'][] = [
        'product_id' => $product_id,
        'product_name' => $product_name,
        'quantity' => $quantity,
        'price' => $price,
    ];
}

// Final sale submit
if (isset($_POST['submit_sale'])) {
    $customer_id = $_POST['customer_id'];
    $payment_type = $_POST['payment_type'];
    $amount_received = (float)$_POST['amount_received'];

    // Calculate total
    $total = 0;
    foreach ($_SESSION['sale_items'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    // Get previous balance
    $customer_result = $conn->query("SELECT previous_balance, current_balance FROM customers WHERE id = $customer_id");
    $customer_data = $customer_result->fetch_assoc();
    $previous_balance = $customer_data['current_balance'];

    // Calculate current balance
    $new_balance = ($previous_balance + $total) - $amount_received;

    // Insert into sales
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, date, total_amount, payment_type) VALUES (?, NOW(), ?, ?)");
    $stmt->bind_param("ids", $customer_id, $total, $payment_type);
    $stmt->execute();
    $sale_id = $stmt->insert_id;
    $stmt->close();

    // Insert sale items
    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, sell_price) VALUES (?, ?, ?, ?)");
    foreach ($_SESSION['sale_items'] as $item) {
        $stmt->bind_param("iiid", $sale_id, $item['product_id'], $item['quantity'], $item['price']);
        $stmt->execute();
        // Update stock
        $conn->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id = {$item['product_id']}");
    }
    $stmt->close();

    // Insert into payments
    $stmt = $conn->prepare("INSERT INTO payments (sale_id, amount_paid, payment_date, method) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("ids", $sale_id, $amount_received, $payment_type);
    $stmt->execute();
    $stmt->close();

    // Dues if not fully paid
    $due_amount = $total - $amount_received;
    if ($due_amount > 0) {
        $stmt = $conn->prepare("INSERT INTO dues (sale_id, customer_id, sale_amount, amount_received, previous_balance, current_balance, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iidddd", $sale_id, $customer_id, $total, $amount_received, $previous_balance, $new_balance);
        $stmt->execute();
        $stmt->close();
    }

    // Update customer's balances
    $stmt = $conn->prepare("UPDATE customers SET previous_balance = ?, current_balance = ? WHERE id = ?");
    $stmt->bind_param("ddi", $previous_balance, $new_balance, $customer_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['sale_items'] = [];
    header("Location: sales.php?success=1");
    exit();
}

// Fetch existing sales
$sales = $conn->query("SELECT s.id, s.date, c.name AS customer, s.total_amount, s.payment_type
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    ORDER BY s.id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales - Crescent Exclusive Fabric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- ✅ DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
<?php include "../includes/navbar.php" ?>
<div class="container mt-4">
    <h3>New Sale</h3>

    <!-- Add Item Form -->
    <form method="POST" class="row g-3 mb-3">
        <div class="col-md-3">
            <select name="product_id" class="form-select" required onchange="updateProductName(this)">
                <option value="">Select Product</option>
                <?php $products->data_seek(0); while ($p = $products->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['sell_price'] ?>">
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <input type="hidden" name="product_name" id="product_name_input">
        </div>
        <div class="col-md-2">
            <input type="number" name="quantity" class="form-control" placeholder="Qty" min="1" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="price" class="form-control" placeholder="Price" required>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_item" class="btn btn-primary w-100">Add Item</button>
        </div>
    </form>

    <!-- Cart Preview -->
    <?php if (!empty($_SESSION['sale_items'])): ?>
        <div class="mb-3">
            <h5>Items Added:</h5>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php $total = 0; foreach ($_SESSION['sale_items'] as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= number_format($item['price'], 2) ?></td>
                            <td><?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                        <?php $total += $item['price'] * $item['quantity']; ?>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3"><strong>Total</strong></td>
                        <td><strong><?= number_format($total, 2) ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <!-- Final Sale Form -->
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" step="0.01" name="amount_received" class="form-control" placeholder="Amount Received" required>
                </div>
                <div class="col-md-2">
                    <select name="payment_type" class="form-select" required>
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="Unpaid">Unpaid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="submit_sale" class="btn btn-success w-100">Submit Sale</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- ✅ Sales History Table with DataTables -->
    <h3>Sales History</h3>
    <table id="salesTable" class="table table-bordered table-striped">
        <thead>
            <tr><th>#</th><th>Date</th><th>Customer</th><th>Total</th><th>Payment</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php while ($row = $sales->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['date'] ?></td>
                <td><?= htmlspecialchars($row['customer']) ?></td>
                <td><?= number_format($row['total_amount'], 2) ?></td>
                <td><?= $row['payment_type'] ?></td>
                <td><a href="view_sale.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ✅ jQuery + DataTables JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
function updateProductName(select) {
    const selected = select.selectedOptions[0];
    document.getElementById('product_name_input').value = selected.dataset.name;
    document.querySelector('input[name="price"]').value = selected.dataset.price || '';
}

// ✅ Initialize DataTables
$(document).ready(function () {
    $('#salesTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 20, 50],
        ordering: true,
        searching: true,
        language: {
            search: "Search:"
        }
    });
});
</script>
</body>
</html>
