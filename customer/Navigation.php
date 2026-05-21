<?php
class Navigation {
    public static function render() {
        ?>
        <style>
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
            .nav-brand { font-size: 24px; font-weight: 800; color: #f97316; letter-spacing: -0.5px; }
            .nav-links { display: flex; gap: 40px; }
            .nav-actions { display: flex; gap: 30px; align-items: center; }
            .nav-links a, .nav-actions a { color: #0f172a; text-decoration: none; font-weight: 600; font-size: 15px; transition: color 0.2s; }
            .nav-links a:hover, .nav-actions a:hover { color: #f97316; }
            
            /* Spacer for fixed navbar */
            .nav-spacer { height: 75px; }
        </style>
        <?php
        global $pdo;
        $isSeller = false;
        $isRider = false;
        
        if (isset($_SESSION['user'])) {
            // Check if seller
            $stmt = $pdo->prepare("SELECT 1 FROM sellers WHERE Sellr_Email = ?");
            $stmt->execute([$_SESSION['user']]);
            $isSeller = $stmt->fetchColumn();
            
            // Check if rider
            $stmt = $pdo->prepare("SELECT 1 FROM riders WHERE Rider_Email = ?");
            $stmt->execute([$_SESSION['user']]);
            $isRider = $stmt->fetchColumn();
        }
        ?>
        <nav class="nav-bar">
            <div class="nav-brand">PickGo</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="browse_items.php">Foods</a>
                <a href="browse_stores.php">Stores</a>
                <?php if (!$isSeller): ?>
                    <a href="../seller_register.php" style="color: #f97316;">Start Selling</a>
                <?php endif; ?>
                <?php if (!$isRider): ?>
                    <a href="../rider_register.php" style="color: #10b981;">Drive with us</a>
                <?php endif; ?>
            </div>
            <div class="nav-actions">
                <a href="cart.php" style="position: relative; display: flex; align-items: center; gap: 6px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    Cart
                    <?php 
                    $cart_count = 0;
                    if (isset($_SESSION['cart'])) {
                        foreach ($_SESSION['cart'] as $item) {
                            $cart_count += $item['quantity'];
                        }
                    }
                    if ($cart_count > 0): 
                    ?>
                        <span style="position: absolute; top: -8px; right: -12px; background: #ef4444; color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px;"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php">Profile</a>
                <a href="../logout.php">Logout</a>
            </div>
        </nav>
        <div class="nav-spacer"></div>
        <?php
    }
}
