<?php
require_once '../config.php';
require_once 'Navigation.php';

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: my_orders.php');
    exit;
}

// Fetch order details
$stmt = $pdo->prepare("
    SELECT o.*, m.Merch_Name, m.Merch_Address, r.Rider_Fname, r.Rider_Lname, r.Rider_VehicleType, r.Rider_PlateNumber, r.Rider_Photo, r.Rider_Phone
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    LEFT JOIN riders r ON o.Rider_Id = r.Rider_Id
    WHERE o.Order_Id = ? AND o.Customer_Id = ?
");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: my_orders.php');
    exit;
}

// Calculate ETA and status descriptions
$status = $order['Order_Status'];
$step_prep = '';
$step_ready = '';
$step_del = '';
$step_comp = '';

if ($status === 'pending' || $status === 'preparing') {
    $eta_time = '25 Min';
    $eta_desc = 'Store is preparing your order';
    $step_prep = 'active';
} elseif ($status === 'ready_for_pickup') {
    $eta_time = '20 Min';
    $eta_desc = 'Store ready, waiting for rider pickup';
    $step_prep = 'completed';
    $step_ready = 'active';
} elseif ($status === 'on_the_way') {
    $eta_time = '15 Min';
    $eta_desc = 'Rider is on the way';
    $step_prep = 'completed';
    $step_ready = 'completed';
    $step_del = 'active';
} elseif ($status === 'delivered') {
    $eta_time = 'Delivered';
    $eta_desc = 'Enjoy your food!';
    $step_prep = 'completed';
    $step_ready = 'completed';
    $step_del = 'completed';
    $step_comp = 'completed';
} else {
    $eta_time = 'Cancelled';
    $eta_desc = 'Order was cancelled';
    $step_prep = '';
    $step_del = '';
    $step_comp = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order #<?php echo htmlspecialchars($order['Order_Id']); ?> - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .page-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }

        .header-actions { margin-top: 40px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 16px; transition: color 0.2s; }
        .back-link:hover { color: #f97316; }
        .order-id-badge { background: #f8fafc; padding: 8px 16px; border-radius: 20px; font-weight: 700; color: #0f172a; font-size: 15px; border: 1px solid #e2e8f0; }

        .tracking-layout { display: grid; grid-template-columns: 3fr 2fr; gap: 32px; height: calc(100vh - 240px); min-height: 600px; }
        
        /* Map Area */
        .map-area { background: #e2e8f0; border-radius: 24px; position: relative; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
        .map-image { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
        .live-badge { position: absolute; top: 24px; left: 24px; background: #fff; padding: 8px 16px; border-radius: 20px; font-weight: 700; color: #ef4444; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-size: 14px; }
        .pulse { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: pulse-animation 1.5s infinite; }
        
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Status Area */
        .status-area { background: #fff; border-radius: 24px; padding: 32px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; overflow-y: auto; }
        
        .eta-section { text-align: center; margin-bottom: 32px; padding-bottom: 32px; border-bottom: 1px solid #f1f5f9; }
        .eta-label { font-size: 15px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .eta-time { font-size: 48px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1; }
        .eta-desc { font-size: 16px; color: #f97316; font-weight: 600; margin-top: 8px; }

        /* Timeline */
        .timeline { flex: 1; padding-left: 16px; }
        .timeline-item { position: relative; padding-bottom: 32px; padding-left: 32px; }
        .timeline-item:last-child { padding-bottom: 0; }
        
        /* The Line */
        .timeline-item::before { content: ''; position: absolute; left: 6px; top: 24px; bottom: -8px; width: 2px; background: #e2e8f0; }
        .timeline-item:last-child::before { display: none; }
        
        /* The Dot */
        .timeline-dot { position: absolute; left: 0; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: #e2e8f0; border: 3px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; z-index: 2; transition: all 0.3s; }
        
        /* States */
        .timeline-item.completed .timeline-dot { background: #f97316; box-shadow: 0 0 0 1px #f97316; }
        .timeline-item.completed::before { background: #f97316; }
        .timeline-item.active .timeline-dot { background: #fff; border: 3px solid #f97316; box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.2); width: 16px; height: 16px; left: -1px; top: 3px; }
        
        .timeline-content h4 { margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #0f172a; }
        .timeline-item:not(.completed):not(.active) .timeline-content h4 { color: #94a3b8; }
        .timeline-time { font-size: 13px; color: #64748b; font-weight: 500; }

        /* Rider Info */
        .rider-card { margin-top: 32px; padding: 20px; background: #f8fafc; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #e2e8f0; }
        .rider-details { display: flex; align-items: center; gap: 16px; }
        .rider-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .rider-info h3 { margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #0f172a; }
        .rider-meta { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; font-weight: 500; }
        .rider-rating { display: flex; align-items: center; gap: 4px; color: #eab308; font-weight: 600; }
        
        .contact-btn { width: 48px; height: 48px; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #0f172a; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .contact-btn:hover { background: #f97316; color: #fff; border-color: #f97316; }

        @media (max-width: 900px) {
            .tracking-layout { grid-template-columns: 1fr; height: auto; }
            .map-area { height: 400px; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <?php Navigation::render(); ?>

        <div class="header-actions">
            <a href="my_orders.php" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Orders
            </a>
            <div class="order-id-badge">Order #<?php echo htmlspecialchars($order['Order_Id']); ?></div>
        </div>

        <div class="tracking-layout">
            <!-- Map Area -->
            <div class="map-area">
                <div class="live-badge">
                    <div class="pulse"></div>
                    Live Tracking
                </div>
                <img src="https://images.unsplash.com/photo-1524661135-423995f22d0b?auto=format&fit=crop&q=80&w=1200&h=800" alt="Map View" class="map-image">
                
                <div style="position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="#f97316" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                
                <div style="position: absolute; top: 60%; left: 30%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center;">
                    <div style="background: #0f172a; color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700; margin-bottom: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">Rider</div>
                    <div style="width: 24px; height: 24px; background: #0f172a; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>
                </div>
            </div>

            <!-- Status Tracking Area -->
            <div class="status-area">
                <div class="eta-section">
                    <div class="eta-label">Estimated Delivery Time</div>
                    <div class="eta-time"><?php echo $eta_time; ?></div>
                    <div class="eta-desc"><?php echo htmlspecialchars($eta_desc); ?></div>
                </div>

                <div class="timeline">
                    <!-- Status 1 -->
                    <div class="timeline-item completed">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>Order Confirmed</h4>
                            <div class="timeline-time"><?php echo date('g:i A', strtotime($order['Order_Date'])); ?></div>
                        </div>
                    </div>
                    
                    <!-- Status 2 -->
                    <div class="timeline-item <?php echo $step_prep; ?>">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>Preparing Order</h4>
                            <div class="timeline-time"><?php echo in_array($status, ['preparing', 'ready_for_pickup', 'on_the_way', 'delivered']) ? 'In Progress' : 'Pending'; ?></div>
                        </div>
                    </div>

                    <!-- Status 2.5 -->
                    <div class="timeline-item <?php echo $step_ready; ?>">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>Ready for Pickup</h4>
                            <div class="timeline-time"><?php echo in_array($status, ['ready_for_pickup', 'on_the_way', 'delivered']) ? 'Food is ready' : 'Waiting for store'; ?></div>
                        </div>
                    </div>

                    <!-- Status 3 -->
                    <div class="timeline-item <?php echo $step_del; ?>">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>Out for Delivery</h4>
                            <div class="timeline-time"><?php echo in_array($status, ['on_the_way', 'delivered']) ? 'On the way' : 'Pending rider pickup'; ?></div>
                        </div>
                    </div>

                    <!-- Status 4 -->
                    <div class="timeline-item <?php echo $step_comp; ?>">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>Order Delivered</h4>
                            <div class="timeline-time"><?php echo ($status === 'delivered') ? 'Delivered successfully' : 'Estimated ~30 mins'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Rider Card -->
                <?php if ($order['Rider_Id']): ?>
                    <div class="rider-card">
                        <div class="rider-details">
                            <img src="<?php echo $order['Rider_Photo'] ? '../' . $order['Rider_Photo'] : 'https://images.unsplash.com/photo-1599566150163-29194dcaad36?auto=format&fit=crop&q=80&w=100&h=100'; ?>" alt="<?php echo htmlspecialchars($order['Rider_Fname']); ?>" class="rider-avatar">
                            <div class="rider-info">
                                <h3><?php echo htmlspecialchars($order['Rider_Fname'] . ' ' . $order['Rider_Lname']); ?></h3>
                                <div class="rider-meta">
                                    <span><?php echo htmlspecialchars($order['Rider_VehicleType'] . ' • ' . $order['Rider_PlateNumber']); ?></span>
                                    <span>|</span>
                                    <span class="rider-rating">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        4.9
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <?php if (!empty($order['Rider_Phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($order['Rider_Phone']); ?>" class="contact-btn" title="Call Rider" style="text-decoration: none; display: flex;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                </a>
                            <?php endif; ?>
                            <button class="contact-btn" title="Message Rider" onclick="alert('Messaging feature coming soon!')">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="rider-card" style="justify-content: center; text-align: center; color: #64748b; font-weight: 500;">
                        <span>Waiting for a rider to be assigned to your order...</span>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
