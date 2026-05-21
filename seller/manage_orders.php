<?php
require_once '../db.php';
require_once 'Navigation.php';
session_start();

// Check if user is a seller
if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit;
}

// Fetch seller and merchant data
$stmt = $pdo->prepare("SELECT s.*, m.* FROM sellers s JOIN merchants m ON s.Merch_Id = m.Merch_Id WHERE m.Merch_Email = ?");
$stmt->execute([$_SESSION['user']]);
$sellerData = $stmt->fetch();

if (!$sellerData || $sellerData['Sellr_Status'] !== 'active') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $sellerData['Seller_Id'];
$success = '';
$error = '';

// Handle Order Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];
    $new_status = '';

    if ($action === 'confirm') $new_status = 'preparing';
    elseif ($action === 'reject') $new_status = 'cancelled';
    elseif ($action === 'dispatch') $new_status = 'ready_for_pickup';

    if ($new_status) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET Order_Status = ? WHERE Order_Id = ? AND Seller_Id = ?");
            $stmt->execute([$new_status, $order_id, $seller_id]);
            $success = "Order #$order_id status updated to $new_status!";
        } catch (PDOException $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// Fetch all orders for this seller
$stmt = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.phone_number 
                      FROM orders o 
                      JOIN users u ON o.Customer_Id = u.id 
                      WHERE o.Seller_Id = ? 
                      ORDER BY o.Order_Date DESC");
$stmt->execute([$seller_id]);
$orders = $stmt->fetchAll();

// Fetch items for these orders
$order_items = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'Order_Id');
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE Order_Id IN ($placeholders)");
    $stmt->execute($order_ids);
    $items_raw = $stmt->fetchAll();
    foreach ($items_raw as $item) {
        $order_items[$item['Order_Id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - <?php echo htmlspecialchars($sellerData['Merch_Name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .order-card { background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); margin-bottom: 24px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .order-header { padding: 20px 24px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .order-body { padding: 24px; display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
        
        .item-list { list-style: none; padding: 0; margin: 0; }
        .item-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; font-size: 14px; }
        .item-row:last-child { border-bottom: none; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-preparing { background: #e0f2fe; color: #0369a1; }
        .badge-ready_for_pickup { background: #fef3c7; color: #d97706; }
        .badge-on_the_way { background: #f0fdf4; color: #166534; }
        .badge-delivered { background: #f1f5f9; color: #475569; }
        .badge-cancelled { background: #fef2f2; color: #991b1b; }
        
        .action-btns { display: flex; gap: 12px; margin-top: 20px; }
        .btn { padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #f97316; color: #fff; }
        .btn-outline { background: #fff; border: 1px solid #e2e8f0; color: #475569; }
        .btn-danger { background: #fff; border: 1px solid #fee2e2; color: #ef4444; }
        .btn:hover { transform: translateY(-1px); }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php SellerNavigation::render('orders'); ?>

        <main class="main-content">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Store Orders</h1>
                <p style="color: #64748b;">Manage incoming orders and update preparation status.</p>
            </div>

            <?php if ($success): ?>
                <div style="background: #f0fdf4; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #dcfce7;"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <div style="text-align: center; padding: 80px; background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05);">
                    <p style="color: #64748b;">No orders found for your store.</p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <span style="font-weight: 700; color: #0f172a;">Order #<?php echo $order['Order_Id']; ?></span>
                                <span style="margin-left: 12px; color: #64748b; font-size: 13px;"><?php echo date('M d, Y H:i', strtotime($order['Order_Date'])); ?></span>
                            </div>
                            <span class="badge badge-<?php echo $order['Order_Status']; ?>"><?php echo str_replace('_', ' ', $order['Order_Status']); ?></span>
                        </div>
                        <div class="order-body">
                            <div>
                                <h4 style="font-size: 14px; color: #64748b; margin-bottom: 12px; text-transform: uppercase;">Items</h4>
                                <ul class="item-list">
                                    <?php if (isset($order_items[$order['Order_Id']])): ?>
                                        <?php foreach ($order_items[$order['Order_Id']] as $item): ?>
                                            <li class="item-row">
                                                <span><?php echo $item['Quantity']; ?>x <?php echo htmlspecialchars($item['Food_Name']); ?></span>
                                                <span style="font-weight: 600;">₱<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                                <div style="margin-top: 16px; text-align: right; font-weight: 800; font-size: 18px; color: #f97316;">
                                    Total: ₱<?php echo number_format($order['Order_Total'], 2); ?>
                                </div>
                            </div>
                            <div style="border-left: 1px solid #f1f5f9; padding-left: 40px;">
                                <h4 style="font-size: 14px; color: #64748b; margin-bottom: 12px; text-transform: uppercase;">Customer</h4>
                                <div style="font-weight: 700; color: #0f172a; margin-bottom: 4px;"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                                <div style="font-size: 13px; color: #64748b; margin-bottom: 16px;"><?php echo htmlspecialchars($order['phone_number']); ?></div>
                                
                                <h4 style="font-size: 14px; color: #64748b; margin-bottom: 8px; text-transform: uppercase;">Delivery Address</h4>
                                <div style="font-size: 13px; color: #475569; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($order['Delivery_Address'])); ?></div>

                                <div class="action-btns">
                                    <?php if ($order['Order_Status'] === 'pending'): ?>
                                        <form method="POST" style="display: flex; gap: 10px; width: 100%;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['Order_Id']; ?>">
                                            <button type="submit" name="action" value="confirm" class="btn btn-primary" style="flex: 2;">Confirm (Start Preparing)</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger" style="flex: 1;">Reject</button>
                                        </form>
                                    <?php elseif ($order['Order_Status'] === 'preparing'): ?>
                                        <form method="POST" style="width: 100%;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['Order_Id']; ?>">
                                            <button type="submit" name="action" value="dispatch" class="btn btn-primary" style="width: 100%;">Ready for Dispatch (Notify Rider)</button>
                                        </form>
                                    <?php elseif ($order['Order_Status'] === 'ready_for_pickup'): ?>
                                        <span style="color: #64748b; font-size: 13px; font-weight: 600; padding: 8px 0; display: block;">Waiting for rider pickup...</span>
                                    <?php elseif ($order['Order_Status'] === 'on_the_way'): ?>
                                        <span style="color: #10b981; font-size: 13px; font-weight: 600; padding: 8px 0; display: block;">Rider is out for delivery</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
