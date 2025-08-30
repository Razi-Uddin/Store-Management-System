<?php
include '../includes/db.php';

if (isset($_GET['sale_id'])) {
    $sale_id = intval($_GET['sale_id']);

    $stmt = $conn->prepare("SELECT current_balance FROM dues WHERE sale_id = ?");
    if (!$stmt) {
        echo json_encode(['current_balance' => 0, 'error' => $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        echo json_encode(['balance' => $data['current_balance']]);
    } else {
        echo json_encode(['balance' => 0]);
    }
}
?>
