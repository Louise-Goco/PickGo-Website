<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'rider') {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? '';

if (!empty($order_id)) {
    if (!isset($_SESSION['rejected_orders'])) {
        $_SESSION['rejected_orders'] = [];
    }
    if (!in_array($order_id, $_SESSION['rejected_orders'])) {
        $_SESSION['rejected_orders'][] = $order_id;
    }
}

echo json_encode(['success' => true]);
