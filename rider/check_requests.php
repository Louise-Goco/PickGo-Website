<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'rider') {
    exit(json_encode(['available' => false]));
}

// Check if rider is active
$stmt = $pdo->prepare("SELECT Rider_Id, Rider_Status FROM riders WHERE Rider_Email = ?");
$stmt->execute([$_SESSION['user']]);
$rider = $stmt->fetch();

if (!$rider || $rider['Rider_Status'] !== 'active') {
    exit(json_encode(['available' => false]));
}

// Check if rider already has an active order
$stmt = $pdo->prepare("SELECT Order_Id FROM orders WHERE Rider_Id = ? AND Order_Status IN ('ready_for_pickup', 'on_the_way')");
$stmt->execute([$rider['Rider_Id']]);
if ($stmt->fetch()) {
    exit(json_encode(['available' => false])); // Already busy
}

// Find available orders (unassigned and ready for pickup)
$rejected_orders = $_SESSION['rejected_orders'] ?? [];
$not_in_clause = '';
$params = [];

if (!empty($rejected_orders)) {
    $placeholders = implode(',', array_fill(0, count($rejected_orders), '?'));
    $not_in_clause = "AND COALESCE(o.Batch_Id, o.Order_Id) NOT IN ($placeholders)";
    $params = $rejected_orders;
}

$stmt = $pdo->prepare("
    SELECT MAX(o.Batch_Id) as Batch_Id, COALESCE(o.Batch_Id, o.Order_Id) as Grp_Id,
           SUM(o.Order_Total) as Total_Amount,
           GROUP_CONCAT(DISTINCT m.Merch_Name SEPARATOR ', ') as Store_Names,
           GROUP_CONCAT(DISTINCT m.Merch_Address SEPARATOR '; ') as Pickup_Addresses,
           o.Delivery_Address
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    WHERE o.Rider_Id IS NULL 
      AND o.Order_Status = 'ready_for_pickup'
      AND (o.Batch_Id IS NULL OR NOT EXISTS (
          SELECT 1 FROM orders o2 
          WHERE o2.Batch_Id = o.Batch_Id AND o2.Order_Status != 'ready_for_pickup'
      ))
      $not_in_clause
    GROUP BY COALESCE(o.Batch_Id, o.Order_Id), o.Delivery_Address
    ORDER BY MIN(o.Order_Date) ASC
    LIMIT 1
");
$stmt->execute($params);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if ($batch) {
    $earnings = $batch['Total_Amount'] * 0.1; // 10% commission
    echo json_encode([
        'available' => true,
        'order' => [
            'id' => $batch['Grp_Id'],
            'pickup' => $batch['Store_Names'],
            'pickup_address' => $batch['Pickup_Addresses'],
            'delivery_address' => $batch['Delivery_Address'],
            'earnings' => number_format($earnings, 2),
            'total' => number_format($batch['Total_Amount'], 2)
        ]
    ]);
} else {
    echo json_encode(['available' => false]);
}
