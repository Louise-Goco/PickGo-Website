<?php
session_start();
require_once 'db.php';

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT user_type FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['user']]);
    $user_type = $stmt->fetchColumn();
    
    if ($user_type === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    } else {
        header('Location: customer/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PickGo | Best Food & Grocery Delivery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
    <style>
        body { display: block; background-image: none; background-color: #f8fafc; }
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        .nav-bar { 
            width: 100%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            gap: 20px; 
            background: #ffffff; 
            padding: 15px 40px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            box-sizing: border-box;
        }
        .nav-brand { font-size: 24px; font-weight: 800; color: #f97316; letter-spacing: -0.5px; text-decoration: none; }
        .nav-links { display: flex; gap: 40px; }
        .nav-actions { display: flex; gap: 20px; align-items: center; }
        .nav-links a, .nav-actions a { color: #0f172a; text-decoration: none; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .nav-links a:hover, .nav-actions a:hover { color: #f97316; }
        
        .nav-spacer { height: 75px; }

        .btn-login { padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .btn-register { padding: 10px 20px; border-radius: 10px; background: #f97316; color: #fff !important; }
        .btn-register:hover { background: #ea580c !important; }

        .hero-section { 
            background: linear-gradient(rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.6)), url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&q=80&w=1200&h=600');
            background-size: cover;
            background-position: center;
            border-radius: 24px;
            padding: 100px 40px;
            color: white;
            text-align: center;
            margin-top: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .hero-section h1 { font-size: 56px; font-weight: 800; margin-bottom: 20px; letter-spacing: -1px; }
        .hero-section p { font-size: 22px; opacity: 0.9; margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto; line-height: 1.6; }
        
        .hero-btns { display: flex; gap: 16px; justify-content: center; }
        .hero-btn { padding: 16px 32px; border-radius: 12px; font-weight: 700; font-size: 16px; text-decoration: none; transition: all 0.2s; }
        .hero-btn-primary { background: #f97316; color: white; }
        .hero-btn-primary:hover { background: #ea580c; transform: translateY(-2px); }
        .hero-btn-secondary { background: white; color: #0f172a; }
        .hero-btn-secondary:hover { background: #f8fafc; transform: translateY(-2px); }

        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-top: 48px; }
        .card { background: #ffffff; border: 1px solid rgba(0,0,0,0.05); border-radius: 16px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .card h3 { margin: 0; color: #0f172a; font-size: 22px; font-weight: 700; }
        .card p { color: #64748b; line-height: 1.5; margin: 12px 0 24px 0; font-size: 16px;}
        .card-link { color: #f97316; display: inline-flex; align-items: center; text-decoration: none; font-weight: 700; font-size: 16px; }
        .card-link:hover { text-decoration: underline; }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 64px; margin-bottom: 24px; }
        .section-header h2 { font-size: 28px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.5px; }
        .section-header a { color: #f97316; text-decoration: none; font-weight: 600; font-size: 15px; }
        .section-header a:hover { text-decoration: underline; }
        
        .horizontal-scroll { display: flex; gap: 24px; overflow-x: auto; padding: 8px 4px 24px 4px; scrollbar-width: none; }
        .horizontal-scroll::-webkit-scrollbar { display: none; }
        
        .item-card { min-width: 280px; background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: all 0.3s ease; cursor: pointer; flex: 0 0 auto; }
        .item-card:hover { transform: translateY(-6px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .item-image { width: 100%; height: 200px; object-fit: cover; }
        .item-info { padding: 20px; }
        .item-info h4 { margin: 0 0 4px 0; font-size: 18px; color: #0f172a; font-weight: 700; }
        .item-info p { margin: 0; color: #64748b; font-size: 14px; }
        .item-price { margin-top: 16px; font-weight: 800; color: #f97316; font-size: 18px; }
        
        .seller-card { min-width: 200px; text-align: center; background: #fff; border-radius: 20px; padding: 32px 24px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: all 0.3s ease; cursor: pointer; flex: 0 0 auto; }
        .seller-card:hover { transform: translateY(-6px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .seller-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin: 0 auto 16px auto; }
        .seller-card h4 { margin: 0 0 4px 0; font-size: 18px; color: #0f172a; font-weight: 700; }
        .seller-card p { margin: 0; color: #64748b; font-size: 14px; }

        .cta-section { background: #0f172a; border-radius: 24px; padding: 60px; color: white; text-align: center; margin-top: 64px; }
        .cta-section h2 { font-size: 32px; font-weight: 800; margin-bottom: 16px; }
        .cta-section p { font-size: 18px; opacity: 0.8; margin-bottom: 32px; }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero-section { padding: 60px 20px; }
            .hero-section h1 { font-size: 36px; }
            .hero-section p { font-size: 18px; }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <a href="index.php" class="nav-brand">PickGo</a>
        <div class="nav-links">
            <a href="customer/browse_items.php">Explore Foods</a>
            <a href="customer/browse_stores.php">Browse Stores</a>
            <a href="seller_register.php">Become a Seller</a>
            <a href="rider_register.php">Ride with Us</a>
        </div>
        <div class="nav-actions">
            <a href="login.php" class="btn-login">Login</a>
            <a href="register.php" class="btn-register">Sign Up</a>
        </div>
    </nav>
    <div class="nav-spacer"></div>

    <div class="dashboard-container">
        <div class="hero-section">
            <h1>Cravings delivered to your doorstep.</h1>
            <p>Order from your favorite restaurants and stores in just a few clicks. Fast, fresh, and reliable.</p>
            <div class="hero-btns">
                <a href="customer/browse_items.php" class="hero-btn hero-btn-primary">Order Now</a>
                <a href="register.php" class="hero-btn hero-btn-secondary">Join PickGo</a>
            </div>
        </div>

        <div class="cards">
            <div class="card">
                <h3>Vast Selection</h3>
                <p>From local favorites to big brands, we have it all. Explore thousands of items.</p>
                <a href="customer/browse_items.php" class="card-link">Explore Foods &rarr;</a>
            </div>
            <div class="card">
                <h3>Top Rated Stores</h3>
                <p>Only the best Stores make it to our list. Quality guaranteed every time.</p>
                <a href="customer/browse_stores.php" class="card-link">View Stores &rarr;</a>
            </div>
            <div class="card">
                <h3>Partner with Us</h3>
                <p>Grow your business or earn on your own schedule. Join the PickGo family.</p>
                <a href="seller_register.php" class="card-link">Learn More &rarr;</a>
            </div>
        </div>

        <!-- Featured Foods -->
        <div class="section-header">
            <h2>Featured Foods</h2>
            <a href="customer/browse_items.php">See All</a>
        </div>
        <div class="horizontal-scroll">
            <?php
            $stmt = $pdo->prepare("
                SELECT i.*, m.Merch_Name 
                FROM items i
                JOIN sellers s ON i.Seller_Id = s.Seller_Id
                JOIN merchants m ON s.Merch_Id = m.Merch_Id
                WHERE i.Item_Status = 'available' 
                AND s.Sellr_Status = 'active'
                AND m.Merch_Status = 'active'
                ORDER BY RAND()
                LIMIT 6
            ");
            $stmt->execute();
            $featured_items = $stmt->fetchAll();
            
            if ($featured_items):
                foreach ($featured_items as $item):
            ?>
                <div class="item-card" onclick="window.location.href='customer/browse_items.php?id=<?php echo $item['Item_Id']; ?>'">
                    <img src="<?php echo $item['Item_Image'] ? $item['Item_Image'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80&w=400&h=300'; ?>" class="item-image" alt="<?php echo htmlspecialchars($item['Item_Name']); ?>">
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($item['Item_Name']); ?></h4>
                        <p><?php echo htmlspecialchars($item['Merch_Name']); ?></p>
                        <div class="item-price">₱<?php echo number_format($item['Item_Price'], 2); ?></div>
                    </div>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <p style="color: #64748b; padding: 20px;">No featured items available yet.</p>
            <?php endif; ?>
        </div>

        <!-- Featured Stores -->
        <div class="section-header">
            <h2>Featured Stores</h2>
            <a href="customer/browse_stores.php">See All</a>
        </div>
        <div class="horizontal-scroll">
            <?php
            $stmt = $pdo->prepare("
                SELECT * FROM merchants 
                WHERE Merch_Status = 'active' 
                ORDER BY RAND() 
                LIMIT 8
            ");
            $stmt->execute();
            $featured_sellers = $stmt->fetchAll();
            
            if ($featured_sellers):
                foreach ($featured_sellers as $seller):
            ?>
                <div class="seller-card" onclick="window.location.href='customer/view_store.php?id=<?php echo $seller['Merch_Id']; ?>'">
                    <img src="<?php echo $seller['Merch_Logo'] ? $seller['Merch_Logo'] : 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&q=80&w=200&h=200'; ?>" class="seller-avatar" alt="<?php echo htmlspecialchars($seller['Merch_Name']); ?>">
                    <h4><?php echo htmlspecialchars($seller['Merch_Name']); ?></h4>
                    <p><?php echo htmlspecialchars($seller['Merch_Type']); ?></p>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <p style="color: #64748b; padding: 20px;">No featured sellers available yet.</p>
            <?php endif; ?>
        </div>

        <div class="cta-section">
            <h2>Ready to eat?</h2>
            <p>Join thousands of happy customers and get your favorite food delivered today.</p>
            <div class="hero-btns">
                <a href="register.php" class="hero-btn hero-btn-primary">Create an Account</a>
                <a href="login.php" class="hero-btn hero-btn-secondary" style="background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3);">Sign In</a>
            </div>
        </div>
    </div>

    <footer style="background: #ffffff; border-top: 1px solid #e2e8f0; padding: 60px 20px 20px; margin-top: 80px;">
        <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; margin-bottom: 40px;">
            <div>
                <h3 style="color: #f97316; font-size: 24px; font-weight: 800; margin: 0 0 16px 0; letter-spacing: -0.5px;">PickGo</h3>
                <p style="color: #64748b; font-size: 15px; line-height: 1.6; margin: 0;">The best food and grocery delivery service. Fast, fresh, and reliable directly to your doorstep.</p>
            </div>
            <div>
                <h4 style="color: #0f172a; font-weight: 700; font-size: 16px; margin: 0 0 16px 0;">Company</h4>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="#" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">About Us</a>
                    <a href="#" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Careers</a>
                    <a href="#" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Blog</a>
                </div>
            </div>
            <div>
                <h4 style="color: #0f172a; font-weight: 700; font-size: 16px; margin: 0 0 16px 0;">For Users</h4>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="customer/browse_items.php" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Explore Foods</a>
                    <a href="customer/browse_stores.php" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Browse Stores</a>
                    <a href="login.php" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Customer Login</a>
                </div>
            </div>
            <div>
                <h4 style="color: #0f172a; font-weight: 700; font-size: 16px; margin: 0 0 16px 0;">Partner with Us</h4>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="seller_register.php" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Become a Seller</a>
                    <a href="rider_register.php" style="color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Ride with Us</a>
                </div>
            </div>
        </div>
        <div style="max-width: 1200px; margin: 0 auto; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <p style="color: #64748b; font-size: 14px; margin: 0;">&copy; <?php echo date('Y'); ?> PickGo Delivery. All rights reserved.</p>
            <div style="display: flex; gap: 16px;">
                <a href="#" style="color: #64748b; text-decoration: none; font-size: 14px;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Privacy Policy</a>
                <a href="#" style="color: #64748b; text-decoration: none; font-size: 14px;" onmouseover="this.style.color='#f97316'" onmouseout="this.style.color='#64748b'">Terms of Service</a>
            </div>
        </div>
    </footer>
</body>
</html>
