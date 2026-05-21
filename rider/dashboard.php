<?php
require_once '../db.php';
session_start();

// Check if user is a rider
if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: ../login.php');
    exit;
}

// Fetch rider data
$stmt = $pdo->prepare("SELECT * FROM riders WHERE Rider_Email = ?");
$stmt->execute([$_SESSION['user']]);
$riderData = $stmt->fetch();

if (!$riderData || !in_array($riderData['Rider_Status'], ['active', 'offline'])) {
    header('Location: ../login.php');
    exit;
}

// Check if bank info is complete
$hasBankInfo = !empty($riderData['Rider_BankName']) && !empty($riderData['Rider_BankAccNo']) && !empty($riderData['Rider_BankAccName']);
$bankError = '';

// Handle Status Toggle
if (isset($_POST['toggle_status'])) {
    $currentStatus = $riderData['Rider_Status'];
    
    if ($currentStatus === 'offline') {
        if ($hasBankInfo) {
            $newStatus = 'active';
            $updateStmt = $pdo->prepare("UPDATE riders SET Rider_Status = ? WHERE Rider_Id = ?");
            $updateStmt->execute([$newStatus, $riderData['Rider_Id']]);
            header('Location: dashboard.php');
            exit;
        } else {
            $bankError = "Please complete your Bank Account information in Profile Settings before going online.";
        }
    } else {
        $newStatus = 'offline';
        $updateStmt = $pdo->prepare("UPDATE riders SET Rider_Status = ? WHERE Rider_Id = ?");
        $updateStmt->execute([$newStatus, $riderData['Rider_Id']]);
        header('Location: dashboard.php');
        exit;
    }
}

// Fetch Today's Stats
$todayDate = date('Y-m-d');
$statsStmt = $pdo->prepare("SELECT COUNT(*) as trips, SUM(Order_Total * 0.1) as earnings FROM orders WHERE Rider_Id = ? AND Order_Status = 'delivered' AND DATE(Order_Date) = ?");
$statsStmt->execute([$riderData['Rider_Id'], $todayDate]);
$stats = $statsStmt->fetch();

$todayTrips = $stats['trips'] ?? 0;
$todayEarnings = $stats['earnings'] ?? 0;

// Fetch Active Deliveries
$activeStmt = $pdo->prepare("
    SELECT o.*, m.Merch_Name, m.Merch_Address as Store_Address 
    FROM orders o 
    JOIN sellers s ON o.Seller_Id = s.Seller_Id 
    JOIN merchants m ON s.Merch_Id = m.Merch_Id 
    WHERE o.Rider_Id = ? AND o.Order_Status IN ('ready_for_pickup', 'on_the_way') 
    ORDER BY o.Order_Id ASC
");
$activeStmt->execute([$riderData['Rider_Id']]);
$activeOrders = $activeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Available Delivery Trips (if online and no active deliveries)
$availableOrders = [];
if (empty($activeOrders) && $riderData['Rider_Status'] === 'active') {
    $availStmt = $pdo->prepare("
        SELECT MAX(o.Batch_Id) as Batch_Id, COALESCE(o.Batch_Id, o.Order_Id) as Grp_Id,
               SUM(o.Order_Total) as Total_Amount,
               GROUP_CONCAT(DISTINCT m.Merch_Name SEPARATOR ', ') as Merch_Name,
               GROUP_CONCAT(DISTINCT m.Merch_Address SEPARATOR '; ') as Store_Address,
               o.Delivery_Address,
               GROUP_CONCAT(o.Order_Id) as Order_Ids
        FROM orders o 
        JOIN sellers s ON o.Seller_Id = s.Seller_Id 
        JOIN merchants m ON s.Merch_Id = m.Merch_Id 
        WHERE o.Rider_Id IS NULL 
          AND o.Order_Status = 'ready_for_pickup'
          AND (o.Batch_Id IS NULL OR NOT EXISTS (
              SELECT 1 FROM orders o2 
              WHERE o2.Batch_Id = o.Batch_Id AND o2.Order_Status != 'ready_for_pickup'
          ))
        GROUP BY COALESCE(o.Batch_Id, o.Order_Id), o.Delivery_Address
        ORDER BY MIN(o.Order_Date) ASC
    ");
    $availStmt->execute();
    $availableOrders = $availStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --bg: #f8fafc;
            --card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }
        body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--text-main); }
        .dashboard-container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }
        
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        
        .status-toggle-card { 
            background: var(--card); 
            padding: 24px; 
            border-radius: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1; transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 4px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(30px); }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px; }
        .stat-card { 
            background: var(--card); 
            padding: 24px; 
            border-radius: 20px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-label { font-size: 14px; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-main); }
        .stat-value span { font-size: 16px; font-weight: 500; color: var(--text-muted); margin-left: 4px; }

        .active-delivery-card {
            background: #fff;
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
            border: 2px solid var(--primary);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.1);
        }
        .badge-active {
            background: #dcfce7;
            color: #166534;
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .delivery-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 24px; }
        .info-item label { display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
        .info-item p { font-weight: 600; font-size: 16px; }

        .btn-action {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 24px;
            transition: background 0.2s;
        }
        .btn-action:hover { background: var(--primary-dark); }
        
        .empty-state {
            background: var(--card);
            padding: 48px;
            border-radius: 24px;
            text-align: center;
            color: var(--text-muted);
            border: 2px dashed #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header-section">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; letter-spacing: -0.025em;">PickGo Rider</h1>
                <p style="color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($riderData['Rider_Fname']); ?></p>
            </div>
            <div style="display: flex; align-items: center; gap: 24px;">
                <a href="reviews.php" style="color: var(--text-main); font-weight: 600; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    My Reviews
                </a>
                <a href="profile.php" style="color: var(--text-main); font-weight: 600; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Profile Settings
                </a>
                <a href="../logout.php" style="color: #ef4444; font-weight: 600; text-decoration: none; font-size: 14px;">Sign Out</a>
            </div>
        </div>

        <?php if ($bankError): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 16px; border-radius: 16px; margin-bottom: 24px; border: 1px solid #fecaca; display: flex; align-items: center; gap: 12px; font-weight: 500;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?php echo $bankError; ?>
            </div>
        <?php endif; ?>

        <?php if (!$hasBankInfo): ?>
            <div style="background: #fffbeb; color: #92400e; padding: 20px; border-radius: 20px; margin-bottom: 24px; border: 1px solid #fef3c7; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="background: #fef3c7; padding: 10px; border-radius: 12px; color: #d97706;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    </div>
                    <div>
                        <p style="font-weight: 700; margin-bottom: 2px;">Bank Info Required</p>
                        <p style="font-size: 13px; opacity: 0.9;">You must set up your bank account to start receiving requests.</p>
                    </div>
                </div>
                <a href="profile.php" style="background: #d97706; color: white; text-decoration: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 13px;">Set Up Now</a>
            </div>
        <?php endif; ?>

        <div class="status-toggle-card">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo $riderData['Rider_Status'] === 'active' ? '#10b981' : '#94a3b8'; ?>; box-shadow: 0 0 0 4px <?php echo $riderData['Rider_Status'] === 'active' ? '#dcfce7' : '#f1f5f9'; ?>;"></div>
                <div>
                    <p style="font-weight: 700; font-size: 16px;"><?php echo $riderData['Rider_Status'] === 'active' ? 'You are Online' : 'You are Offline'; ?></p>
                    <p style="font-size: 13px; color: var(--text-muted);"><?php echo $riderData['Rider_Status'] === 'active' ? 'Receiving delivery requests' : 'Toggle to start working'; ?></p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="toggle_status" value="1">
                <label class="toggle-switch">
                    <input type="checkbox" onchange="this.form.submit()" <?php echo $riderData['Rider_Status'] === 'active' ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Trips Today</p>
                <p class="stat-value"><?php echo $todayTrips; ?><span>trips</span></p>
            </div>
            <a href="earnings.php" class="stat-card" style="text-decoration: none; display: block; transition: transform 0.2s;">
                <p class="stat-label">Earnings Today</p>
                <p class="stat-value">₱<?php echo number_format($todayEarnings, 2); ?></p>
                <p style="font-size: 11px; color: var(--primary); font-weight: 700; margin-top: 8px;">View Detailed Earnings →</p>
            </a>
            <div class="stat-card">
                <p class="stat-label">Rider Rating</p>
                <p class="stat-value"><?php echo number_format($riderData['Rider_Rating'], 1); ?><span>★</span></p>
            </div>
        </div>

        <?php if (!empty($activeOrders)): ?>
            <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-block; width: 10px; height: 10px; background: #3b82f6; border-radius: 50%; box-shadow: 0 0 0 3px #dbeafe;"></span>
                Active Deliveries (<?php echo count($activeOrders); ?> Store<?php echo count($activeOrders)>1?'s':''; ?>)
            </h2>
            <?php foreach ($activeOrders as $activeOrder): ?>
                <div class="active-delivery-card" style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="badge-active">Active Delivery</span>
                            <?php if (!empty($activeOrder['Batch_Id'])): ?>
                                <span style="font-size: 13px; font-weight: 600; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; color: #475569;">Batch <?php echo htmlspecialchars($activeOrder['Batch_Id']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 14px; color: var(--text-muted); font-weight: 700;">Order #<?php echo $activeOrder['Order_Id']; ?></p>
                    </div>
                    
                    <div class="delivery-info-grid">
                        <div class="info-item">
                            <label>Pickup From</label>
                            <p><?php echo htmlspecialchars($activeOrder['Merch_Name']); ?></p>
                            <p style="font-weight: 400; font-size: 13px; color: var(--text-muted); margin-top: 4px;"><?php echo htmlspecialchars($activeOrder['Store_Address']); ?></p>
                        </div>
                        <div class="info-item">
                            <label>Deliver To</label>
                            <p><?php echo htmlspecialchars($activeOrder['Delivery_Address']); ?></p>
                        </div>
                    </div>

                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <label style="display: block; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Payment</label>
                            <p style="font-weight: 700; color: var(--primary);">₱<?php echo number_format($activeOrder['Order_Total'], 2); ?> (<?php echo $activeOrder['Payment_Method']; ?>)</p>
                        </div>
                        <a href="order_details.php?id=<?php echo $activeOrder['Order_Id']; ?>" class="btn-action" style="text-decoration: none; display: inline-block; width: auto; margin-top: 0; padding: 12px 24px;">View Full Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php if ($riderData['Rider_Status'] === 'active' && !empty($availableOrders)): ?>
                <div style="margin-bottom: 32px;">
                    <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-block; width: 10px; height: 10px; background: #10b981; border-radius: 50%; box-shadow: 0 0 0 3px #dcfce7;"></span>
                        Available Delivery Trips
                    </h2>
                    <div style="display: grid; gap: 20px;">
                        <?php foreach ($availableOrders as $order): 
                            $est_earnings = $order['Total_Amount'] * 0.1;
                        ?>
                            <div style="background: #fff; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; padding: 24px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#10b981'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='none'">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                        <span style="background: #f0fdf4; color: #166534; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700;"><?php echo !empty($order['Batch_Id']) ? 'Batch Delivery (Multi-Store)' : ('Order #' . $order['Grp_Id']); ?></span>
                                        <span style="color: #10b981; font-weight: 800; font-size: 18px;">Earn ₱<?php echo number_format($est_earnings, 2); ?></span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                                        <div>
                                            <p style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin: 0 0 4px 0;">Pickup From</p>
                                            <p style="font-weight: 700; font-size: 15px; margin: 0 0 2px 0; color: var(--text-main);"><?php echo htmlspecialchars($order['Merch_Name']); ?></p>
                                            <p style="font-size: 13px; color: var(--text-muted); margin: 0;"><?php echo htmlspecialchars($order['Store_Address']); ?></p>
                                        </div>
                                        <div>
                                            <p style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin: 0 0 4px 0;">Deliver To</p>
                                            <p style="font-weight: 600; font-size: 14px; margin: 0; color: var(--text-main);"><?php echo htmlspecialchars($order['Delivery_Address']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="acceptSpecificOrder('<?php echo $order['Grp_Id']; ?>', this)" style="background: #10b981; color: white; border: none; padding: 14px 28px; border-radius: 14px; font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);">Pick Up Order</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 16px;">🛵</div>
                    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No active deliveries</h3>
                    <p><?php echo $riderData['Rider_Status'] === 'active' ? 'Looking for nearby delivery trips...' : 'Go online to start receiving requests.'; ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Notification Modal for New Requests -->
    <div id="requestModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: white; width: 100%; max-width: 450px; border-radius: 28px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: slideUp 0.3s ease-out;">
            <div style="background: var(--primary); padding: 24px; text-align: center; color: white;">
                <div style="font-size: 40px; margin-bottom: 12px;">🔔</div>
                <h2 style="font-size: 20px; font-weight: 800;">New Delivery Request!</h2>
            </div>
            <div style="padding: 32px;">
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Pickup From</label>
                    <p id="reqPickup" style="font-weight: 700; font-size: 16px; margin-bottom: 4px;"></p>
                    <p id="reqPickupAddr" style="font-size: 13px; color: var(--text-muted);"></p>
                </div>
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Deliver To</label>
                    <p id="reqDeliveryAddr" style="font-weight: 700; font-size: 16px;"></p>
                </div>
                <div style="background: #f0fdf4; padding: 16px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #dcfce7;">
                    <span style="font-weight: 600; color: #166534;">Estimated Earnings</span>
                    <span id="reqEarnings" style="font-weight: 800; font-size: 20px; color: #10b981;"></span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 32px;">
                    <button id="rejectBtn" onclick="rejectOrder()" style="padding: 14px; border-radius: 14px; border: 1px solid #fee2e2; background: #fef2f2; font-weight: 700; cursor: pointer; color: #ef4444; transition: all 0.2s;">Reject Order</button>
                    <button id="acceptBtn" style="padding: 14px; border-radius: 14px; border: none; background: var(--primary); color: white; font-weight: 700; cursor: pointer; transition: all 0.2s;">Pick Up Order</button>
                </div>
                <button onclick="dismissRequest()" style="width: 100%; margin-top: 16px; padding: 14px; border-radius: 14px; border: 1px solid #cbd5e1; background: #f8fafc; color: #475569; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Back to Dashboard
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>

    <script>
        let currentOrderId = null;
        let isOnline = <?php echo $riderData['Rider_Status'] === 'active' ? 'true' : 'false'; ?>;
        let hasActiveOrder = <?php echo !empty($activeOrders) ? 'true' : 'false'; ?>;
        let ignoredOrderIds = new Set();

        function checkNewRequests() {
            if (!isOnline || hasActiveOrder) return;

            fetch('check_requests.php')
                .then(res => res.json())
                .then(data => {
                    if (data.available && !ignoredOrderIds.has(data.order.id)) {
                        showRequest(data.order);
                    }
                });
        }

        function showRequest(order) {
            currentOrderId = order.id;
            document.getElementById('reqPickup').innerText = order.pickup;
            document.getElementById('reqPickupAddr').innerText = order.pickup_address;
            document.getElementById('reqDeliveryAddr').innerText = order.delivery_address;
            document.getElementById('reqEarnings').innerText = '₱' + order.earnings;
            document.getElementById('requestModal').style.display = 'flex';
        }

        function closeRequest() {
            document.getElementById('requestModal').style.display = 'none';
        }

        function dismissRequest() {
            if (currentOrderId) {
                ignoredOrderIds.add(currentOrderId);
            }
            closeRequest();
        }

        function rejectOrder() {
            const rejectBtn = document.getElementById('rejectBtn');
            rejectBtn.disabled = true;
            rejectBtn.innerText = 'Rejecting...';
            
            fetch('reject_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: currentOrderId })
            })
            .then(res => res.json())
            .then(data => {
                closeRequest();
                rejectBtn.disabled = false;
                rejectBtn.innerText = 'Reject Order';
            });
        }

        document.getElementById('acceptBtn').onclick = function() {
            this.disabled = true;
            this.innerText = 'Accepting...';
            
            fetch('accept_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: currentOrderId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message);
                    closeRequest();
                    this.disabled = false;
                    this.innerText = 'Pick Up Order';
                }
            });
        };

        function acceptSpecificOrder(orderId, btn) {
            btn.disabled = true;
            btn.innerText = 'Accepting...';
            
            fetch('accept_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Order unavailable');
                    btn.disabled = false;
                    btn.innerText = 'Pick Up Order';
                }
            });
        }

        // Check for new requests immediately on load, then poll every 1 second
        checkNewRequests();
        setInterval(checkNewRequests, 1000);
    </script>
</body>
</html>
