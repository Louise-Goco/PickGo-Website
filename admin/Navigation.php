<?php
class AdminNavigation {
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
            .nav-brand { font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
            .nav-links { display: flex; gap: 25px; }
            .nav-actions { display: flex; gap: 25px; align-items: center; }
            .nav-links a, .nav-actions a { color: #0f172a; text-decoration: none; font-weight: 600; font-size: 14px; transition: color 0.2s; }
            .nav-links a:hover, .nav-actions a:hover { color: #f97316; }
            
            /* Spacer for fixed navbar */
            .nav-spacer { height: 75px; }
        </style>
        <nav class="nav-bar">
            <div class="nav-brand">PickGo Admin</div>
            <div class="nav-links">
                <a href="dashboard.php">Overview</a>
                <a href="manage_customers.php">Manage Users</a>
                <a href="manage_sellers.php">Manage Sellers</a>
                <a href="manage_riders.php">Manage Riders</a>
                <a href="manage_categories.php">Manage Categories</a>
                <a href="manage_payouts.php">Manage Payouts</a>
                <a href="settings.php">System Settings</a>
                <a href="manage_orders.php">Manage Orders</a>
            </div>
            <div class="nav-actions">
                <a href="../logout.php">Logout</a>
            </div>
        </nav>
        <div class="nav-spacer"></div>
        <?php
    }
}
?>
