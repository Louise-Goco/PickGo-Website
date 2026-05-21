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

// 1. Calculate Average Rating
$stmt = $pdo->prepare("SELECT AVG(Rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE Seller_Id = ?");
$stmt->execute([$seller_id]);
$stats = $stmt->fetch();
$avg_rating = round($stats['avg_rating'] ?: 0, 1);
$total_reviews = $stats['total_reviews'];

// 2. Fetch Store Reviews (Item_Id IS NULL)
$stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name 
                      FROM reviews r 
                      JOIN users u ON r.Customer_Id = u.id 
                      WHERE r.Seller_Id = ? AND r.Item_Id IS NULL 
                      ORDER BY r.created_at DESC");
$stmt->execute([$seller_id]);
$store_reviews = $stmt->fetchAll();

// 3. Fetch Product Reviews (Item_Id IS NOT NULL)
$stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name, i.Item_Name 
                      FROM reviews r 
                      JOIN users u ON r.Customer_Id = u.id 
                      JOIN items i ON r.Item_Id = i.Item_Id 
                      WHERE r.Seller_Id = ? AND r.Item_Id IS NOT NULL 
                      ORDER BY r.created_at DESC");
$stmt->execute([$seller_id]);
$product_reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - <?php echo htmlspecialchars($sellerData['Merch_Name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .stats-banner { background: #fff; padding: 32px; border-radius: 24px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 40px; display: flex; align-items: center; gap: 40px; }
        .rating-big { font-size: 48px; font-weight: 800; color: #0f172a; display: flex; align-items: baseline; gap: 8px; }
        .stars { color: #f97316; font-size: 24px; letter-spacing: 2px; }
        
        .reviews-section { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
        .card { background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .card h3 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        
        .review-item { padding: 20px 0; border-bottom: 1px solid #f1f5f9; }
        .review-item:last-child { border-bottom: none; }
        .review-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .reviewer-name { font-weight: 700; color: #0f172a; font-size: 15px; }
        .review-date { font-size: 12px; color: #94a3b8; }
        .review-rating { color: #f59e0b; font-size: 14px; margin-bottom: 8px; }
        .review-comment { font-size: 14px; color: #475569; line-height: 1.5; }
        .product-tag { display: inline-block; padding: 4px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 11px; font-weight: 600; color: #64748b; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php SellerNavigation::render('reviews'); ?>

        <main class="main-content">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Customer Reviews</h1>
                <p style="color: #64748b;">Listen to your customers and improve your service.</p>
            </div>

            <div class="stats-banner">
                <div class="rating-big">
                    <?php echo $avg_rating; ?>
                    <span style="font-size: 18px; color: #94a3b8; font-weight: 500;">/ 5.0</span>
                </div>
                <div>
                    <div class="stars">
                        <?php 
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $avg_rating ? '★' : '☆';
                        }
                        ?>
                    </div>
                    <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Based on <strong><?php echo $total_reviews; ?></strong> total reviews</p>
                </div>
            </div>

            <div class="reviews-section">
                <!-- Store Reviews -->
                <div class="card">
                    <h3>Store Experience <span style="font-size: 14px; font-weight: 500; color: #94a3b8;"><?php echo count($store_reviews); ?></span></h3>
                    <?php if (empty($store_reviews)): ?>
                        <p style="text-align: center; color: #94a3b8; padding: 40px 0;">No store reviews yet.</p>
                    <?php else: ?>
                        <?php foreach ($store_reviews as $r): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-name"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></div>
                                    <div class="review-date"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                                </div>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++) echo $i <= $r['Rating'] ? '★' : '☆'; ?>
                                </div>
                                <p class="review-comment"><?php echo nl2br(htmlspecialchars($r['Comment'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Product Reviews -->
                <div class="card">
                    <h3>Product Feedback <span style="font-size: 14px; font-weight: 500; color: #94a3b8;"><?php echo count($product_reviews); ?></span></h3>
                    <?php if (empty($product_reviews)): ?>
                        <p style="text-align: center; color: #94a3b8; padding: 40px 0;">No product reviews yet.</p>
                    <?php else: ?>
                        <?php foreach ($product_reviews as $r): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-name"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></div>
                                    <div class="review-date"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                                </div>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++) echo $i <= $r['Rating'] ? '★' : '☆'; ?>
                                </div>
                                <p class="review-comment"><?php echo nl2br(htmlspecialchars($r['Comment'])); ?></p>
                                <span class="product-tag"><?php echo htmlspecialchars($r['Item_Name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
