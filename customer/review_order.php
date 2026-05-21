<?php
require_once '../db.php';
require_once 'Navigation.php';
session_start();

// Check if user is logged in as customer
if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

// Fetch customer ID
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND user_type = 'customer'");
$stmt->execute([$_SESSION['user']]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: ../login.php');
    exit;
}
$customer_id = $user['id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_order_id = intval($_POST['order_id']);
    $store_rating = intval($_POST['store_rating'] ?? 5);
    $store_comment = trim($_POST['store_comment'] ?? '');
    $rider_rating = intval($_POST['rider_rating'] ?? 5);
    $rider_comment = trim($_POST['rider_comment'] ?? '');
    $tip_amount = floatval($_POST['tip_amount'] ?? 0);
    
    // Fetch order to get Seller_Id and Rider_Id
    $stmt = $pdo->prepare("SELECT o.Seller_Id, o.Rider_Id FROM orders o WHERE o.Order_Id = ? AND o.Customer_Id = ?");
    $stmt->execute([$post_order_id, $customer_id]);
    $orderData = $stmt->fetch();
    
    if ($orderData) {
        $seller_id = $orderData['Seller_Id'];
        $rider_id = $orderData['Rider_Id'];
        
        // Insert Store Review
        $stmt = $pdo->prepare("INSERT INTO reviews (Order_Id, Customer_Id, Seller_Id, Rating, Comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$post_order_id, $customer_id, $seller_id, $store_rating, $store_comment]);
        
        // Update Seller Rating
        $stmt = $pdo->prepare("UPDATE sellers SET Sellr_Rating = (SELECT AVG(Rating) FROM reviews WHERE Seller_Id = ? AND Item_Id IS NULL) WHERE Seller_Id = ?");
        $stmt->execute([$seller_id, $seller_id]);
        
        // Insert Rider Review if rider exists
        if ($rider_id) {
            $stmt = $pdo->prepare("INSERT INTO reviews (Order_Id, Customer_Id, Rider_Id, Rating, Comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$post_order_id, $customer_id, $rider_id, $rider_rating, $rider_comment]);
            
            // Update Rider Rating
            $stmt = $pdo->prepare("UPDATE riders SET Rider_Rating = (SELECT AVG(Rating) FROM reviews WHERE Rider_Id = ?) WHERE Rider_Id = ?");
            $stmt->execute([$rider_id, $rider_id]);
            
            // Handle Tip
            if ($tip_amount > 0) {
                $stmt = $pdo->prepare("UPDATE orders SET Rider_Earnings = Rider_Earnings + ? WHERE Order_Id = ?");
                $stmt->execute([$tip_amount, $post_order_id]);
            }
        }
        
        header("Location: my_orders.php?review=success");
        exit;
    }
}

// Fetch order details for display
$stmt = $pdo->prepare("
    SELECT o.*, m.Merch_Name, m.Merch_Logo, m.Merch_Type, 
           r.Rider_Id, r.Rider_Fname, r.Rider_Lname, r.Rider_Photo
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    LEFT JOIN riders r ON o.Rider_Id = r.Rider_Id
    WHERE o.Order_Id = ? AND o.Customer_Id = ?
");
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: my_orders.php');
    exit;
}

$merch_logo = $order['Merch_Logo'] ? '../' . $order['Merch_Logo'] : 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&q=80&w=150&h=150';
$rider_photo = $order['Rider_Photo'] ? '../' . $order['Rider_Photo'] : 'https://images.unsplash.com/photo-1599566150163-29194dcaad36?auto=format&fit=crop&q=80&w=150&h=150';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate & Review Order #<?php echo $order['Order_Id']; ?> - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .page-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        
        .header-actions { margin-bottom: 32px; text-align: center; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.2s; background: #f1f5f9; padding: 10px 18px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .back-link:hover { color: #f97316; background: #fff7ed; border-color: #f97316; transform: translateX(-2px); }
        
        h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        .subtitle { color: #64748b; font-size: 16px; margin-top: 8px; }

        /* Review Sections */
        .review-card { background: #fff; border-radius: 24px; padding: 40px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); margin-bottom: 32px; display: flex; flex-direction: column; align-items: center; text-align: center; }
        
        .entity-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 16px; border: 2px solid #e2e8f0; }
        .entity-name { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
        .entity-role { font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px; }

        /* Star Rating UI */
        .stars-container { display: flex; gap: 8px; flex-direction: row-reverse; justify-content: center; margin-bottom: 24px; }
        .star-input { display: none; }
        .star-label { cursor: pointer; color: #cbd5e1; transition: color 0.2s, transform 0.2s; }
        .star-label:hover, .star-label:hover ~ .star-label, .star-input:checked ~ .star-label { color: #f97316; }
        .star-label:hover { transform: scale(1.1); }
        .star-label svg { width: 40px; height: 40px; fill: currentColor; }

        /* Form Inputs */
        .form-group { width: 100%; text-align: left; margin-bottom: 24px; }
        .form-label { display: block; font-size: 15px; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
        .form-textarea { width: 100%; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 15px; font-family: 'Inter', sans-serif; transition: border-color 0.2s; box-sizing: border-box; background: #f8fafc; resize: vertical; min-height: 120px; }
        .form-textarea:focus { outline: none; border-color: #f97316; background: #fff; box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }

        /* Tip Section */
        .tip-options { display: flex; gap: 12px; margin-bottom: 24px; width: 100%; }
        .tip-btn { flex: 1; padding: 12px; border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; font-weight: 600; font-size: 16px; color: #0f172a; cursor: pointer; transition: all 0.2s; }
        .tip-btn:hover { border-color: #cbd5e1; background: #f8fafc; }
        .tip-btn.active { border-color: #f97316; background: #fff7ed; color: #ea580c; }

        /* Submit */
        .submit-btn { width: 100%; padding: 16px; background: #0f172a; color: #fff; border: none; border-radius: 12px; font-size: 18px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.2s, transform 0.2s; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 40px; }
        .submit-btn:hover { background: #1e293b; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); }
    </style>
</head>
<body>
    <div class="page-container">
        <?php Navigation::render(); ?>

        <div style="margin-top: 24px; margin-bottom: 24px;">
            <a href="my_orders.php" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Orders
            </a>
        </div>

        <div class="header-actions">
            <h1>Rate your Experience</h1>
            <p class="subtitle">Your feedback helps us improve and rewards great service!</p>
        </div>

        <form action="review_order.php?id=<?php echo $order['Order_Id']; ?>" method="POST">
            <input type="hidden" name="order_id" value="<?php echo $order['Order_Id']; ?>">
            <input type="hidden" name="tip_amount" id="tipAmount" value="0">

            <!-- Rate Store -->
            <div class="review-card">
                <img src="<?php echo $merch_logo; ?>" alt="<?php echo htmlspecialchars($order['Merch_Name']); ?>" class="entity-avatar">
                <div class="entity-role"><?php echo htmlspecialchars($order['Merch_Type'] ?: 'Store'); ?></div>
                <h2 class="entity-name"><?php echo htmlspecialchars($order['Merch_Name']); ?></h2>
                
                <div class="stars-container">
                    <input type="radio" name="store_rating" id="store-star5" value="5" class="star-input" checked>
                    <label for="store-star5" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                    
                    <input type="radio" name="store_rating" id="store-star4" value="4" class="star-input">
                    <label for="store-star4" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                    
                    <input type="radio" name="store_rating" id="store-star3" value="3" class="star-input">
                    <label for="store-star3" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                    
                    <input type="radio" name="store_rating" id="store-star2" value="2" class="star-input">
                    <label for="store-star2" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                    
                    <input type="radio" name="store_rating" id="store-star1" value="1" class="star-input">
                    <label for="store-star1" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                </div>

                <div class="form-group">
                    <label class="form-label">Write a review (Optional)</label>
                    <textarea name="store_comment" class="form-textarea" placeholder="How was the food? Was it packaged well?"></textarea>
                </div>
            </div>

            <!-- Rate Rider -->
            <?php if ($order['Rider_Id']): ?>
                <div class="review-card">
                    <img src="<?php echo $rider_photo; ?>" alt="<?php echo htmlspecialchars($order['Rider_Fname']); ?>" class="entity-avatar">
                    <div class="entity-role">Delivery Rider</div>
                    <h2 class="entity-name"><?php echo htmlspecialchars($order['Rider_Fname'] . ' ' . $order['Rider_Lname']); ?></h2>
                    
                    <div class="stars-container">
                        <input type="radio" name="rider_rating" id="rider-star5" value="5" class="star-input" checked>
                        <label for="rider-star5" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                        
                        <input type="radio" name="rider_rating" id="rider-star4" value="4" class="star-input">
                        <label for="rider-star4" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                        
                        <input type="radio" name="rider_rating" id="rider-star3" value="3" class="star-input">
                        <label for="rider-star3" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                        
                        <input type="radio" name="rider_rating" id="rider-star2" value="2" class="star-input">
                        <label for="rider-star2" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                        
                        <input type="radio" name="rider_rating" id="rider-star1" value="1" class="star-input">
                        <label for="rider-star1" class="star-label"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                    </div>

                    <div class="form-group" style="margin-bottom: 32px;">
                        <label class="form-label" style="text-align: center; margin-bottom: 16px;">Add a tip for excellent service?</label>
                        <div class="tip-options">
                            <button type="button" class="tip-btn active" onclick="selectTip(this, 0)">No Tip</button>
                            <button type="button" class="tip-btn" onclick="selectTip(this, 20)">₱20</button>
                            <button type="button" class="tip-btn" onclick="selectTip(this, 50)">₱50</button>
                            <button type="button" class="tip-btn" onclick="selectTip(this, 100)">₱100</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Write a review (Optional)</label>
                        <textarea name="rider_comment" class="form-textarea" placeholder="Was the rider fast and polite?"></textarea>
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="submit-btn">Submit Feedback</button>
        </form>
    </div>

    <script>
        function selectTip(element, amount) {
            document.querySelectorAll('.tip-btn').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('tipAmount').value = amount;
        }
    </script>
</body>
</html>
