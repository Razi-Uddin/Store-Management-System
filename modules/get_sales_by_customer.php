<?php
include '../includes/db.php';

if (isset($_GET['customer_id'])) {
    $customer_id = intval($_GET['customer_id']);
    $stmt = $conn->prepare("SELECT id FROM sales WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }

    echo json_encode($sales);
}
?>
