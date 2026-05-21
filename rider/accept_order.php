<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'rider') {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = $data['order_id'] ?? null;

if (!$orderId) {
    exit(json_encode(['success' => false, 'message' => 'Invalid Order ID']));
}

// Get rider ID
$stmt = $pdo->prepare("SELECT Rider_Id FROM riders WHERE Rider_Email = ?");
$stmt->execute([$_SESSION['user']]);
$rider = $stmt->fetch();

if (!$rider) {
    exit(json_encode(['success' => false, 'message' => 'Rider not found']));
}

try {
    // Attempt to claim the order or batch (only if it's still unassigned)
    if (is_string($orderId) && strpos($orderId, 'BATCH-') === 0) {
        $stmt = $pdo->prepare("UPDATE orders SET Rider_Id = ?, Order_Status = 'ready_for_pickup' WHERE Batch_Id = ? AND Rider_Id IS NULL");
        $stmt->execute([$rider['Rider_Id'], $orderId]);
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET Rider_Id = ?, Order_Status = 'ready_for_pickup' WHERE Order_Id = ? AND Rider_Id IS NULL");
        $stmt->execute([$rider['Rider_Id'], $orderId]);
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order already taken or unavailable']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
