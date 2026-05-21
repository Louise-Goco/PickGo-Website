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

// Today's Orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE Seller_Id = ? AND DATE(Order_Date) = CURDATE()");
$stmt->execute([$seller_id]);
$todays_orders = $stmt->fetchColumn();

// Total Revenue (Only completed/delivered orders)
$stmt = $pdo->prepare("SELECT SUM(Order_Total) FROM orders WHERE Seller_Id = ? AND Order_Status = 'delivered'");
$stmt->execute([$seller_id]);
$total_revenue = $stmt->fetchColumn() ?: 0;

// Active Listings
$stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE Seller_Id = ? AND Item_Status = 'available'");
$stmt->execute([$seller_id]);
$active_listings = $stmt->fetchColumn();

// Pending Payouts
$stmt = $pdo->prepare("SELECT SUM(Amount) FROM payouts WHERE User_Type = 'seller' AND User_Id = ? AND Payout_Status = 'pending'");
$stmt->execute([$seller_id]);
$pending_payouts = $stmt->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - <?php echo htmlspecialchars($sellerData['Merch_Name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        body { background: #f8fafc; }
        .main-content { padding: 40px; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        
        .welcome-card { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            padding: 40px; 
            border-radius: 32px; 
            color: #fff;
            margin-bottom: 40px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        .welcome-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(249, 115, 22, 0.1);
            filter: blur(80px);
            border-radius: 50%;
        }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-top: 32px; }
        .stat-card { 
            background: rgba(255, 255, 255, 0.05); 
            padding: 24px; 
            border-radius: 24px; 
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.08); }
        .stat-card h3 { font-size: 12px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .stat-card .value { font-size: 32px; font-weight: 800; color: #fff; letter-spacing: -1px; }
        
        .table-container { 
            background: #fff; 
            padding: 32px; 
            border-radius: 32px; 
            border: 1px solid rgba(0,0,0,0.03); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .table-title { font-size: 20px; font-weight: 800; color: #0f172a; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        th { padding: 12px 20px; color: #94a3b8; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 20px; background: #fcfcfc; transition: background 0.2s; }
        tr:hover td { background: #f8fafc; }
        td:first-child { border-radius: 16px 0 0 16px; }
        td:last-child { border-radius: 0 16px 16px 0; }
        
        .status-badge { 
            padding: 6px 12px; 
            border-radius: 10px; 
            font-size: 11px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-preparing { background: #e0f2fe; color: #0369a1; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php SellerNavigation::render('dashboard'); ?>

        <main class="main-content">
            <div class="header-section">
                <div>
                    <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Store Overview</h1>
                    <p style="color: #64748b;">Managing <strong><?php echo htmlspecialchars($sellerData['Merch_Name']); ?></strong></p>
                </div>
                <div style="display: flex; gap: 16px;">
                    <a href="../customer/dashboard.php" style="padding: 10px 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; text-decoration: none; color: #0f172a; font-weight: 600; font-size: 14px;">Switch to Customer</a>
                </div>
            </div>

        <div class="welcome-card">
            <h2 style="font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 8px;">Welcome back, <?php echo htmlspecialchars($sellerData['Sellr_Fname']); ?>!</h2>
            <p style="color: #e2e8f0; margin-bottom: 24px;">Your store is currently <strong>active</strong> and visible to customers.</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Today's Orders</h3>
                    <div class="value"><?php echo number_format($todays_orders); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Listings</h3>
                    <div class="value"><?php echo number_format($active_listings); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Payouts</h3>
                    <div class="value">₱<?php echo number_format($pending_payouts, 2); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Store Rating</h3>
                    <div class="value"><?php echo number_format($sellerData['Sellr_Rating'], 1); ?> ★</div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">Recent Orders</h3>
                <a href="manage_orders.php" style="color: #f97316; font-weight: 700; text-decoration: none; font-size: 14px;">View All &rarr;</a>
            </div>
            
            <?php
            // Fetch 5 most recent orders
            $stmt = $pdo->prepare("SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON o.Customer_Id = u.id WHERE o.Seller_Id = ? ORDER BY o.Order_Date DESC LIMIT 5");
            $stmt->execute([$seller_id]);
            $recent_orders = $stmt->fetchAll();
            ?>
 
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_orders) > 0): ?>
                        <?php foreach ($recent_orders as $order): 
                            $status_class = 'status-' . strtolower($order['Order_Status']);
                        ?>
                            <tr>
                                <td style="font-weight: 700; color: #0f172a;">#<?php echo $order['Order_Id']; ?></td>
                                <td style="color: #475569; font-weight: 600;"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td style="font-weight: 800; color: #0f172a;">₱<?php echo number_format($order['Order_Total'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo str_replace('_', ' ', $order['Order_Status']); ?>
                                    </span>
                                </td>
                                <td style="color: #94a3b8; font-size: 13px; font-weight: 500;"><?php echo date('M d, Y', strtotime($order['Order_Date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 60px 0; text-align: center; color: #94a3b8; font-weight: 600;">No orders found yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
