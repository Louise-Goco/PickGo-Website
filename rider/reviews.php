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

if (!$riderData) {
    header('Location: ../login.php');
    exit;
}

$rider_id = $riderData['Rider_Id'];

// Calculate Average Rating & Counts
$stmt = $pdo->prepare("SELECT AVG(Rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE Rider_Id = ?");
$stmt->execute([$rider_id]);
$stats = $stmt->fetch();
$avg_rating = round($stats['avg_rating'] ?: 0, 1);
$total_reviews = $stats['total_reviews'];

// Breakdown by star rating
$breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if ($total_reviews > 0) {
    $stmt = $pdo->prepare("SELECT Rating, COUNT(*) as count FROM reviews WHERE Rider_Id = ? GROUP BY Rating");
    $stmt->execute([$rider_id]);
    while ($row = $stmt->fetch()) {
        $breakdown[$row['Rating']] = $row['count'];
    }
}

// Fetch all reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, o.Order_Date 
    FROM reviews r 
    JOIN users u ON r.Customer_Id = u.id 
    JOIN orders o ON r.Order_Id = o.Order_Id 
    WHERE r.Rider_Id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$rider_id]);
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - PickGo Rider</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --bg: #f8fafc;
            --card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --star: #f59e0b;
        }
        body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--text-main); margin: 0; }
        .page-container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .back-link:hover { color: var(--primary); }

        .header-title { font-size: 32px; font-weight: 800; color: var(--text-main); margin: 0 0 8px 0; letter-spacing: -0.025em; }
        .header-subtitle { color: var(--text-muted); font-size: 16px; margin: 0; }

        /* Overview Banner */
        .overview-card { background: var(--card); border-radius: 24px; padding: 36px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); margin-top: 32px; margin-bottom: 40px; display: grid; grid-template-columns: auto 1fr; gap: 48px; align-items: center; }
        @media (max-width: 640px) { .overview-card { grid-template-columns: 1fr; gap: 32px; text-align: center; } }
        
        .big-rating-box { display: flex; flex-direction: column; align-items: center; }
        .big-rating { font-size: 64px; font-weight: 800; color: var(--text-main); line-height: 1; margin-bottom: 8px; }
        .big-stars { font-size: 24px; color: var(--star); letter-spacing: 2px; margin-bottom: 8px; }
        .review-count { font-size: 14px; font-weight: 600; color: var(--text-muted); }

        /* Rating Bars */
        .bars-container { display: flex; flex-direction: column; gap: 12px; width: 100%; }
        .bar-row { display: flex; align-items: center; gap: 16px; }
        .bar-label { width: 50px; font-size: 14px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 4px; }
        .bar-track { flex: 1; height: 10px; background: #f1f5f9; border-radius: 99px; overflow: hidden; position: relative; }
        .bar-fill { height: 100%; background: var(--star); border-radius: 99px; transition: width 0.5s ease-out; }
        .bar-count { width: 40px; font-size: 14px; font-weight: 600; color: var(--text-muted); text-align: right; }

        /* Review List */
        .reviews-feed { display: flex; flex-direction: column; gap: 20px; }
        .review-item { background: var(--card); border-radius: 20px; padding: 28px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .review-item:hover { transform: translateY(-2px); }
        .review-item-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .reviewer-info { display: flex; align-items: center; gap: 12px; }
        .reviewer-avatar { width: 42px; height: 42px; border-radius: 50%; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; }
        .reviewer-name { font-weight: 700; font-size: 16px; color: var(--text-main); margin-bottom: 2px; }
        .review-date { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .star-display { color: var(--star); font-size: 16px; letter-spacing: 1px; }
        .review-body { font-size: 15px; color: #334155; line-height: 1.6; margin: 0; }
        .order-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f0fdf4; color: #166534; font-size: 12px; font-weight: 700; border-radius: 8px; margin-top: 16px; }

        .empty-reviews { background: var(--card); padding: 64px 20px; text-align: center; border-radius: 24px; border: 2px dashed #cbd5e1; color: var(--text-muted); margin-top: 24px; }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="top-nav">
            <a href="dashboard.php" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Dashboard
            </a>
            <div style="display: flex; align-items: center; gap: 16px;">
                <span style="font-weight: 700; font-size: 15px; color: var(--text-main);"><?php echo htmlspecialchars($riderData['Rider_Fname'] . ' ' . $riderData['Rider_Lname']); ?></span>
                <img src="../<?php echo $riderData['Rider_Photo'] ?? 'uploads/default_avatar.png'; ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
            </div>
        </div>

        <div>
            <h1 class="header-title">My Ratings & Reviews</h1>
            <p class="header-subtitle">See what customers are saying about your delivery service.</p>
        </div>

        <div class="overview-card">
            <div class="big-rating-box">
                <div class="big-rating"><?php echo number_format($avg_rating, 1); ?></div>
                <div class="big-stars">
                    <?php 
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= round($avg_rating) ? '★' : '☆';
                    }
                    ?>
                </div>
                <div class="review-count"><?php echo $total_reviews; ?> total reviews</div>
            </div>

            <div class="bars-container">
                <?php for ($star = 5; $star >= 1; $star--): 
                    $count = $breakdown[$star];
                    $percentage = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
                ?>
                    <div class="bar-row">
                        <div class="bar-label"><?php echo $star; ?> <span style="color: var(--star);">★</span></div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                        <div class="bar-count"><?php echo $count; ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 24px; color: var(--text-main);">Recent Feedback</h2>

        <?php if (empty($reviews)): ?>
            <div class="empty-reviews">
                <div style="font-size: 48px; margin-bottom: 16px;">⭐</div>
                <h3 style="font-size: 18px; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No reviews yet</h3>
                <p style="margin: 0;">Complete more delivery trips to start earning customer feedback and ratings!</p>
            </div>
        <?php else: ?>
            <div class="reviews-feed">
                <?php foreach ($reviews as $r): 
                    $initials = strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1));
                ?>
                    <div class="review-item">
                        <div class="review-item-header">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar"><?php echo $initials; ?></div>
                                <div>
                                    <div class="reviewer-name"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></div>
                                    <div class="review-date"><?php echo date('M d, Y • h:i A', strtotime($r['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="star-display">
                                <?php for ($i = 1; $i <= 5; $i++) echo $i <= $r['Rating'] ? '★' : '☆'; ?>
                            </div>
                        </div>
                        <?php if (!empty($r['Comment'])): ?>
                            <p class="review-body">"<?php echo nl2br(htmlspecialchars($r['Comment'])); ?>"</p>
                        <?php else: ?>
                            <p class="review-body" style="font-style: italic; color: #94a3b8;">No comment provided.</p>
                        <?php endif; ?>
                        
                        <div class="order-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Order #<?php echo $r['Order_Id']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
