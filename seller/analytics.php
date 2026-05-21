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

// 1. Total Revenue (Delivered only)
$stmt = $pdo->prepare("SELECT SUM(Order_Total) FROM orders WHERE Seller_Id = ? AND Order_Status = 'delivered'");
$stmt->execute([$seller_id]);
$total_revenue = $stmt->fetchColumn() ?: 0;

// 2. Best Sellers (Top 5 items by total quantity sold)
$stmt = $pdo->prepare("
    SELECT oi.Food_Name, SUM(oi.Quantity) as total_qty, SUM(oi.Quantity * oi.Price) as total_sales
    FROM order_items oi
    JOIN orders o ON oi.Order_Id = o.Order_Id
    WHERE o.Seller_Id = ? AND o.Order_Status = 'delivered'
    GROUP BY oi.Food_Name
    ORDER BY total_qty DESC
    LIMIT 5
");
$stmt->execute([$seller_id]);
$best_sellers = $stmt->fetchAll();

// 3. Revenue Trend (Last 7 days)
$stmt = $pdo->prepare("
    SELECT DATE(Order_Date) as order_date, SUM(Order_Total) as daily_revenue
    FROM orders
    WHERE Seller_Id = ? AND Order_Status = 'delivered' AND Order_Date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(Order_Date)
    ORDER BY order_date ASC
");
$stmt->execute([$seller_id]);
$revenue_trends = $stmt->fetchAll();

// Prepare data for trend display
$trend_data = [];
$today = new DateTime();
for ($i = 6; $i >= 0; $i--) {
    $date = (clone $today)->modify("-$i days")->format('Y-m-d');
    $trend_data[$date] = 0;
}
foreach ($revenue_trends as $row) {
    $trend_data[$row['order_date']] = (float)$row['daily_revenue'];
}

// 4. Order Stats
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN Order_Status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN Order_Status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
    FROM orders WHERE Seller_Id = ?");
$stmt->execute([$seller_id]);
$order_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo htmlspecialchars($sellerData['Merch_Name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .analytics-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 32px; }
        
        .card { background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .card h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 24px; }
        
        .stat-banner { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
        .banner-item { background: #fff; padding: 24px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); }
        .banner-item label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .banner-item .value { font-size: 28px; font-weight: 800; color: #0f172a; margin-top: 8px; }
        
        .best-seller-row { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #f1f5f9; }
        .best-seller-row:last-child { border-bottom: none; }
        .bs-name { font-weight: 600; color: #0f172a; }
        .bs-stats { text-align: right; }
        .bs-qty { font-size: 14px; color: #64748b; }
        .bs-amount { font-weight: 700; color: #f97316; font-size: 15px; }

        .trend-bar-container { display: flex; align-items: flex-end; gap: 12px; height: 200px; padding-top: 20px; }
        .trend-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .trend-bar { width: 100%; background: #f97316; border-radius: 6px 6px 0 0; transition: height 0.3s; min-height: 2px; }
        .trend-label { font-size: 10px; color: #94a3b8; font-weight: 600; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php SellerNavigation::render('analytics'); ?>

        <main class="main-content">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Sales Analytics</h1>
                <p style="color: #64748b;">Overview of your store's performance and trends.</p>
            </div>

            <div class="stat-banner">
                <div class="banner-item">
                    <label>Total Revenue</label>
                    <div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="banner-item">
                    <label>Success Rate</label>
                    <div class="value">
                        <?php 
                        $total = $order_stats['total_orders'] ?: 1;
                        echo round(($order_stats['completed_orders'] / $total) * 100); 
                        ?>%
                    </div>
                </div>
                <div class="banner-item">
                    <label>Total Orders</label>
                    <div class="value"><?php echo number_format($order_stats['total_orders']); ?></div>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="card">
                    <h3>Revenue Trend (Last 7 Days)</h3>
                    <div class="trend-bar-container">
                        <?php 
                        $max_rev = max(array_values($trend_data)) ?: 1;
                        foreach ($trend_data as $date => $revenue): 
                            $height = ($revenue / $max_rev) * 100;
                        ?>
                            <div class="trend-bar-wrapper">
                                <div style="font-size: 10px; color: #64748b;">₱<?php echo number_format($revenue, 0); ?></div>
                                <div class="trend-bar" style="height: <?php echo $height; ?>%;"></div>
                                <div class="trend-label"><?php echo date('D', strtotime($date)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <h3>Best Sellers</h3>
                    <?php if (empty($best_sellers)): ?>
                        <p style="text-align: center; color: #94a3b8; padding: 40px 0;">Not enough data yet.</p>
                    <?php else: ?>
                        <?php foreach ($best_sellers as $item): ?>
                            <div class="best-seller-row">
                                <div class="bs-name"><?php echo htmlspecialchars($item['Food_Name']); ?></div>
                                <div class="bs-stats">
                                    <div class="bs-qty"><?php echo $item['total_qty']; ?> sold</div>
                                    <div class="bs-amount">₱<?php echo number_format($item['total_sales'], 2); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
