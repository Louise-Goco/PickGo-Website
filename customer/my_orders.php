<?php
require_once '../config.php';
require_once 'Navigation.php';

// Handle Order Cancellation
if (isset($_POST['cancel_order'])) {
    $order_id_to_cancel = $_POST['order_id'];
    $stmt = $pdo->prepare("UPDATE orders SET Order_Status = 'cancelled' WHERE Order_Id = ? AND Customer_Id = ? AND Order_Status IN ('pending', 'preparing')");
    $stmt->execute([$order_id_to_cancel, $user['id']]);
    header("Location: my_orders.php?msg=cancelled");
    exit;
}

// Helper to fetch order items
function getOrderItems($pdo, $orderId) {
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE Order_Id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Active Orders
$stmt = $pdo->prepare("
    SELECT o.*, m.Merch_Id, m.Merch_Name, m.Merch_Logo
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    WHERE o.Customer_Id = ? AND o.Order_Status IN ('pending', 'preparing', 'ready_for_pickup', 'on_the_way')
    ORDER BY o.Order_Date DESC
");
$stmt->execute([$user['id']]);
$active_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch History Orders
$stmt = $pdo->prepare("
    SELECT o.*, m.Merch_Id, m.Merch_Name, m.Merch_Logo
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    WHERE o.Customer_Id = ? AND o.Order_Status IN ('delivered', 'cancelled')
    ORDER BY o.Order_Date DESC
");
$stmt->execute([$user['id']]);
$history_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .page-container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }

        .orders-header { margin-top: 40px; margin-bottom: 30px; }
        .orders-header h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; }
        .orders-header p { color: #64748b; margin: 0; font-size: 16px; }

        /* Tabs */
        .tabs { display: flex; gap: 32px; border-bottom: 1px solid #e2e8f0; margin-bottom: 32px; }
        .tab { padding: 12px 0; font-size: 16px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; text-decoration: none; }
        .tab:hover { color: #0f172a; }
        .tab.active { color: #f97316; border-bottom-color: #f97316; }

        /* Order Card */
        .order-card { background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; }
        .order-card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); transform: translateY(-2px); }
        .order-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
        .order-id { font-weight: 700; color: #0f172a; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .order-date { color: #64748b; font-size: 14px; font-weight: 500; margin-top: 4px; }
        
        .order-status-badge { padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .status-preparing { background: #e0f2fe; color: #0369a1; }
        .status-ready { background: #fef9c3; color: #854d0e; }
        .status-delivery { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .order-body { padding: 24px; display: flex; gap: 24px; align-items: flex-start; }
        .store-avatar { width: 72px; height: 72px; border-radius: 12px; object-fit: cover; background: #e2e8f0; border: 1px solid rgba(0,0,0,0.05); }
        .order-details { flex: 1; }
        .store-name { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; }
        .order-items { color: #475569; font-size: 15px; margin: 0; line-height: 1.5; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .item-qty { font-weight: 600; color: #0f172a; margin-right: 8px; }
        
        .order-footer { padding: 20px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .order-total { display: flex; flex-direction: column; }
        .order-total-label { font-size: 13px; color: #64748b; font-weight: 500; }
        .order-total-price { font-size: 20px; font-weight: 700; color: #0f172a; }
        
        .order-actions { display: flex; gap: 12px; align-items: center; }
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; border: 1px solid transparent; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary { background: #f97316; color: #fff; }
        .btn-primary:hover { background: #ea580c; }
        .btn-outline { background: #fff; color: #0f172a; border-color: #e2e8f0; }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

        /* Progress Bar (Active Orders) */
        .progress-container { margin-top: 20px; width: 100%; }
        .progress-bar { height: 6px; background: #f1f5f9; border-radius: 4px; overflow: hidden; display: flex; width: 100%; }
        .progress-fill { background: #f97316; height: 100%; transition: width 0.3s ease; border-radius: 4px; }
        .progress-steps { display: flex; justify-content: space-between; margin-top: 10px; font-size: 12px; color: #94a3b8; font-weight: 500; width: 100%; gap: 8px; }
        .step { flex: 1; text-align: center; white-space: nowrap; }
        .step:first-child { text-align: left; }
        .step:last-child { text-align: right; }
        .step.active { color: #f97316; font-weight: 700; }
        .step.completed { color: #0f172a; font-weight: 600; }

        /* Section Visibility */
        .orders-section { display: none; }
        .orders-section.active { display: block; animation: fadeIn 0.3s ease; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .order-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .order-body { flex-direction: column; }
            .order-footer { flex-direction: column; gap: 16px; align-items: stretch; text-align: center; }
            .order-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <?php Navigation::render(); ?>

        <div class="orders-header">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled'): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #fecaca; font-weight: 600;">
                    Order successfully cancelled.
                </div>
            <?php endif; ?>
            <h1>My Orders</h1>
            <p>Track your ongoing orders and view your order history.</p>
        </div>

        <div class="tabs">
            <a href="#" class="tab active" onclick="switchTab('active-orders', this); return false;">Active Orders (<?php echo count($active_orders); ?>)</a>
            <a href="#" class="tab" onclick="switchTab('order-history', this); return false;">Order History (<?php echo count($history_orders); ?>)</a>
        </div>

        <!-- ACTIVE ORDERS SECTION -->
        <div id="active-orders" class="orders-section active">
            <?php if (empty($active_orders)): ?>
                <div class="order-card" style="padding: 40px 20px; text-align: center; color: #64748b;">
                    <p style="font-size: 16px; margin: 0;">You have no active orders right now.</p>
                    <a href="browse_items.php" class="btn btn-primary" style="margin-top: 16px;">Explore Foods</a>
                </div>
            <?php else: ?>
                <?php foreach ($active_orders as $order): 
                    $status_label = ucfirst(str_replace('_', ' ', $order['Order_Status']));
                    if ($order['Order_Status'] === 'on_the_way') {
                        $status_class = 'status-delivery';
                        $progress_w = '75%';
                    } elseif ($order['Order_Status'] === 'ready_for_pickup') {
                        $status_class = 'status-ready';
                        $progress_w = '50%';
                    } else {
                        $status_class = 'status-preparing';
                        $progress_w = '25%';
                    }
                ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    Order #<?php echo $order['Order_Id']; ?>
                                </div>
                                <div class="order-date"><?php echo date('M j, Y, g:i A', strtotime($order['Order_Date'])); ?></div>
                            </div>
                            <div class="order-status-badge <?php echo $status_class; ?>">
                                <?php echo $status_label; ?>
                            </div>
                        </div>
                        <div class="order-body">
                            <img src="<?php echo $order['Merch_Logo'] ? '../' . $order['Merch_Logo'] : 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&q=80&w=200&h=200'; ?>" alt="<?php echo htmlspecialchars($order['Merch_Name']); ?>" class="store-avatar">
                            <div class="order-details">
                                <h3 class="store-name"><?php echo htmlspecialchars($order['Merch_Name']); ?></h3>
                                <div class="order-items">
                                    <?php 
                                    $items = getOrderItems($pdo, $order['Order_Id']);
                                    foreach ($items as $item): 
                                    ?>
                                        <div class="item-row">
                                            <span><span class="item-qty"><?php echo $item['Quantity']; ?>x</span> <?php echo htmlspecialchars($item['Food_Name']); ?></span>
                                            <span>₱<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress_w; ?>;"></div>
                                    </div>
                                    <div class="progress-steps">
                                        <span class="step completed">Confirmed</span>
                                        <span class="step <?php echo ($order['Order_Status'] === 'preparing' ? 'active' : 'completed'); ?>">Preparing</span>
                                        <span class="step <?php echo ($order['Order_Status'] === 'ready_for_pickup' ? 'active' : (in_array($order['Order_Status'], ['on_the_way', 'delivered']) ? 'completed' : '')); ?>">Ready for Pickup</span>
                                        <span class="step <?php echo ($order['Order_Status'] === 'on_the_way' ? 'active' : ''); ?>">On the way</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="order-footer">
                            <div class="order-total">
                                <span class="order-total-label">Total Amount</span>
                                <span class="order-total-price">₱<?php echo number_format($order['Order_Total'], 2); ?></span>
                            </div>
                            <div class="order-actions">
                                <?php if (in_array($order['Order_Status'], ['preparing', 'ready_for_pickup', 'on_the_way'])): ?>
                                    <a href="track_order.php?id=<?php echo $order['Order_Id']; ?>" class="btn btn-outline"><?php echo !empty($order['Rider_Id']) ? 'Track Rider & Order' : 'Track Status'; ?></a>
                                <?php endif; ?>
                                <?php if (in_array($order['Order_Status'], ['pending', 'preparing'])): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['Order_Id']; ?>">
                                        <button type="submit" name="cancel_order" class="btn btn-outline" style="border-color: #fca5a5; color: #ef4444;" onclick="return confirm('Are you sure you want to cancel this order?');">Cancel Order</button>
                                    </form>
                                <?php endif; ?>
                                <a href="view_store.php?id=<?php echo $order['Merch_Id']; ?>" class="btn btn-primary">Store Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ORDER HISTORY SECTION -->
        <div id="order-history" class="orders-section">
            <?php if (empty($history_orders)): ?>
                <div class="order-card" style="padding: 40px 20px; text-align: center; color: #64748b;">
                    <p style="font-size: 16px; margin: 0;">You have no completed or cancelled orders.</p>
                </div>
            <?php else: ?>
                <?php foreach ($history_orders as $order): 
                    $status_label = ucfirst(str_replace('_', ' ', $order['Order_Status']));
                    $status_class = ($order['Order_Status'] === 'delivered') ? 'status-completed' : 'status-cancelled';
                ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    Order #<?php echo $order['Order_Id']; ?>
                                </div>
                                <div class="order-date"><?php echo date('M j, Y, g:i A', strtotime($order['Order_Date'])); ?></div>
                            </div>
                            <div class="order-status-badge <?php echo $status_class; ?>">
                                <?php echo $status_label; ?>
                            </div>
                        </div>
                        <div class="order-body">
                            <img src="<?php echo $order['Merch_Logo'] ? '../' . $order['Merch_Logo'] : 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&q=80&w=200&h=200'; ?>" alt="<?php echo htmlspecialchars($order['Merch_Name']); ?>" class="store-avatar">
                            <div class="order-details">
                                <h3 class="store-name"><?php echo htmlspecialchars($order['Merch_Name']); ?></h3>
                                <div class="order-items">
                                    <?php 
                                    $items = getOrderItems($pdo, $order['Order_Id']);
                                    foreach ($items as $item): 
                                    ?>
                                        <div class="item-row">
                                            <span><span class="item-qty"><?php echo $item['Quantity']; ?>x</span> <?php echo htmlspecialchars($item['Food_Name']); ?></span>
                                            <span>₱<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="order-footer">
                            <div class="order-total">
                                <span class="order-total-label">Total Amount</span>
                                <span class="order-total-price">₱<?php echo number_format($order['Order_Total'], 2); ?></span>
                            </div>
                            <div class="order-actions">
                                <?php if ($order['Order_Status'] === 'delivered'): ?>
                                    <a href="review_order.php?id=<?php echo $order['Order_Id']; ?>" class="btn btn-outline">Write a Review</a>
                                    <a href="view_store.php?id=<?php echo $order['Merch_Id']; ?>" class="btn btn-primary">Reorder</a>
                                <?php else: ?>
                                    <a href="view_store.php?id=<?php echo $order['Merch_Id']; ?>" class="btn btn-outline">View Store</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(tabId, element) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            element.classList.add('active');
            
            document.querySelectorAll('.orders-section').forEach(section => section.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        }
    </script>
</body>
</html>
