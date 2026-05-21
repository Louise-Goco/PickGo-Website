<?php
require_once '../config.php';
require_once 'Navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PickGo Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        body { display: block; background-image: none; background-color: #f8fafc; }
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        /* Footer Styling */
        .footer { background: #ffffff; border-top: 1px solid #e2e8f0; padding: 60px 20px 20px; margin-top: 80px; width: 100%; box-sizing: border-box; }
        .footer-content { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; margin-bottom: 40px; }
        .footer-brand { color: #f97316; font-size: 24px; font-weight: 800; margin: 0 0 16px 0; letter-spacing: -0.5px; text-decoration: none; display: inline-block; }
        .footer-heading { color: #0f172a; font-weight: 700; font-size: 16px; margin: 0 0 16px 0; }
        .footer-links { display: flex; flex-direction: column; gap: 12px; }
        .footer-links a { color: #64748b; text-decoration: none; font-size: 15px; transition: color 0.2s; }
        .footer-links a:hover { color: #f97316; }
        .footer-bottom { max-width: 1200px; margin: 0 auto; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .footer-bottom p { color: #64748b; font-size: 14px; margin: 0; }
        .footer-bottom-links { display: flex; gap: 16px; }
        .footer-bottom-links a { color: #64748b; text-decoration: none; font-size: 14px; transition: color 0.2s; }
        .footer-bottom-links a:hover { color: #f97316; }

        .welcome-section { margin-top: 40px; color: #0f172a; }
        .welcome-section h1 { font-size: 32px; font-weight: 700; margin-bottom: 8px;}
        .welcome-section p { color: #64748b; font-size: 16px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-top: 30px; }
        .card { background: #ffffff; border: 1px solid rgba(0,0,0,0.05); border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .card h3 { margin-top: 0; color: #0f172a; font-size: 20px; font-weight: 600; }
        .card p { color: #64748b; line-height: 1.5; margin-top: 10px; font-size: 15px;}
        .card-link { color: #f97316; margin-top: 16px; display: inline-flex; align-items: center; text-decoration: none; font-weight: 600; font-size: 15px; }
        .card-link:hover { text-decoration: underline; }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 48px; margin-bottom: 24px; }
        .section-header h2 { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; }
        .section-header a { color: #f97316; text-decoration: none; font-weight: 600; font-size: 15px; }
        .section-header a:hover { text-decoration: underline; }
        .horizontal-scroll { display: flex; gap: 24px; overflow-x: auto; padding-bottom: 16px; scrollbar-width: none; }
        .horizontal-scroll::-webkit-scrollbar { display: none; }
        .item-card { min-width: 260px; background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; flex: 0 0 auto; }
        .item-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .item-image { width: 100%; height: 180px; background: #e2e8f0; object-fit: cover; }
        .item-info { padding: 16px; }
        .item-info h4 { margin: 0 0 4px 0; font-size: 18px; color: #0f172a; font-weight: 600; }
        .item-info p { margin: 0; color: #64748b; font-size: 14px; }
        .item-price { margin-top: 12px; font-weight: 700; color: #f97316; font-size: 16px; }
        
        .seller-card { min-width: 180px; text-align: center; background: #fff; border-radius: 16px; padding: 24px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s; cursor: pointer; flex: 0 0 auto; }
        .seller-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .seller-avatar { width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; margin: 0 auto 12px auto; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .seller-card h4 { margin: 0 0 4px 0; font-size: 16px; color: #0f172a; }
        .seller-card p { margin: 0; color: #64748b; font-size: 13px; }

        .order-card { background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 16px; transition: transform 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); cursor: pointer; }
        .order-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .order-info { flex: 1; }
        .order-info h4 { margin: 0 0 4px 0; font-size: 18px; color: #0f172a; font-weight: 600; }
        .order-info p { margin: 0; color: #64748b; font-size: 14px; }
        .order-status { padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; margin-top: 8px; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-processing { background: #fef9c3; color: #854d0e; }
        .order-price-container { text-align: right; }
        .order-price { font-weight: 700; color: #0f172a; font-size: 18px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php Navigation::render(); ?>
        
        <div class="welcome-section">
            <?php if (isset($_GET['order_success'])): ?>
                <div style="background: #dcfce7; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; border: 1px solid #bbf7d0;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span style="font-weight: 600;">Order placed successfully! Your food is on the way.</span>
                </div>
            <?php endif; ?>
            <h1>Welcome, <?php echo htmlspecialchars($user['display_name'] ?: $user['first_name']); ?>!</h1>
            <p>What would you like to order today?</p>
        </div>

        <div class="cards">
            <div class="card">
                <h3>Browse Items</h3>
                <p>Explore a variety of food items. Find your cravings fast.</p>
                <a href="browse_items.php" class="card-link">Browse Items &rarr;</a>
            </div>
            <div class="card">
                <h3>Browse Stores</h3>
                <p>Discover nearby restaurants and stores.</p>
                <a href="browse_stores.php" class="card-link">Browse Stores &rarr;</a>
            </div>
            <div class="card">
                <h3>My Orders</h3>
                <p>Track your active orders or view history.</p>
                <a href="my_orders.php" class="card-link">View Orders &rarr;</a>
            </div>
        </div>

        <div class="section-header">
            <h2>Featured Foods</h2>
            <a href="browse_items.php">See All</a>
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
                LIMIT 4
            ");
            $stmt->execute();
            $featured_items = $stmt->fetchAll();
            
            if ($featured_items):
                foreach ($featured_items as $item):
            ?>
                <div class="item-card" onclick="window.location.href='browse_items.php?id=<?php echo $item['Item_Id']; ?>'">
                    <img src="<?php echo $item['Item_Image'] ? '../' . $item['Item_Image'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80&w=400&h=300'; ?>" class="item-image" alt="<?php echo htmlspecialchars($item['Item_Name']); ?>">
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

        <div class="section-header">
            <h2>Featured Stores</h2>
            <a href="browse_stores.php">See All</a>
        </div>
        <div class="horizontal-scroll">
            <?php
            $stmt = $pdo->prepare("
                SELECT * FROM merchants 
                WHERE Merch_Status = 'active' 
                ORDER BY RAND() 
                LIMIT 5
            ");
            $stmt->execute();
            $featured_sellers = $stmt->fetchAll();
            
            if ($featured_sellers):
                foreach ($featured_sellers as $seller):
            ?>
                <div class="seller-card" onclick="window.location.href='view_store.php?id=<?php echo $seller['Merch_Id']; ?>'">
                    <img src="<?php echo $seller['Merch_Logo'] ? '../' . $seller['Merch_Logo'] : 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&q=80&w=200&h=200'; ?>" class="seller-avatar" alt="<?php echo htmlspecialchars($seller['Merch_Name']); ?>">
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

        <div class="section-header">
            <h2>Recent Orders</h2>
            <a href="my_orders.php">View History</a>
        </div>
        <div class="recent-orders">
            <?php
            $stmt = $pdo->prepare("
                SELECT o.*, m.Merch_Name, 
                (SELECT GROUP_CONCAT(Food_Name SEPARATOR ', ') FROM order_items WHERE Order_Id = o.Order_Id) as items_summary
                FROM orders o
                JOIN sellers s ON o.Seller_Id = s.Seller_Id
                JOIN merchants m ON s.Merch_Id = m.Merch_Id
                WHERE o.Customer_Id = ?
                ORDER BY o.Order_Date DESC
                LIMIT 3
            ");
            $stmt->execute([$user['id']]);
            $recent_orders = $stmt->fetchAll();
            
            if ($recent_orders):
                foreach ($recent_orders as $order):
                    $status_class = '';
                    switch($order['Order_Status']) {
                        case 'delivered': $status_class = 'status-completed'; break;
                        case 'pending': case 'preparing': case 'on_the_way': $status_class = 'status-processing'; break;
                        case 'cancelled': $status_class = ''; break; // Add a cancelled style if needed
                    }
            ?>
                <div class="order-card" onclick="window.location.href='track_order.php?id=<?php echo $order['Order_Id']; ?>'">
                    <div class="order-info">
                        <h4><?php echo htmlspecialchars($order['items_summary']); ?></h4>
                        <p><?php echo htmlspecialchars($order['Merch_Name']); ?> • <?php echo date('M j, Y', strtotime($order['Order_Date'])); ?></p>
                    </div>
                    <div class="order-price-container">
                        <div class="order-price">₱<?php echo number_format($order['Order_Total'], 2); ?></div>
                        <div class="order-status <?php echo $status_class; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['Order_Status'])); ?>
                        </div>
                    </div>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <div class="order-card" style="cursor: default; justify-content: center;">
                    <p style="color: #64748b;">You haven't placed any orders yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div>
                <a href="dashboard.php" class="footer-brand">PickGo</a>
                <p style="color: #64748b; font-size: 15px; line-height: 1.6; margin: 0;">The best food and grocery delivery service. Fast, fresh, and reliable directly to your doorstep.</p>
            </div>
            <div>
                <h4 class="footer-heading">Company</h4>
                <div class="footer-links">
                    <a href="#">About Us</a>
                    <a href="#">Careers</a>
                    <a href="#">Blog</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">For Customers</h4>
                <div class="footer-links">
                    <a href="browse_items.php">Explore Foods</a>
                    <a href="browse_stores.php">Browse Stores</a>
                    <a href="my_orders.php">My Orders</a>
                    <a href="profile.php">Profile Settings</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Join Forces</h4>
                <div class="footer-links">
                    <a href="../seller_register.php">Become a Seller</a>
                    <a href="../rider_register.php">Ride with Us</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> PickGo Delivery. All rights reserved.</p>
            <div class="footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </footer>
</body>
</html>
