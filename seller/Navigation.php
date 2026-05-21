<?php
class SellerNavigation {
    public static function render($activePage = 'dashboard') {
        ?>
        <style>
            .sidebar { 
                background: #0f172a; 
                color: #fff; 
                padding: 40px 24px; 
                position: fixed; 
                top: 0; 
                left: 0; 
                height: 100vh; 
                width: 260px; 
                z-index: 1000;
                box-sizing: border-box;
                border-right: 1px solid rgba(255,255,255,0.05);
            }
            .sidebar-brand { font-size: 26px; font-weight: 800; color: #f97316; margin-bottom: 48px; padding-left: 12px; }
            .sidebar-nav { display: flex; flex-direction: column; gap: 8px; height: calc(100% - 100px); }
            .nav-item { padding: 14px 20px; border-radius: 12px; color: #94a3b8; text-decoration: none; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; gap: 12px; font-size: 15px; }
            .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(5px); }
            .nav-item.active { background: #f97316; color: #fff; box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.2); }
            
            .logout-item { margin-top: auto; color: #ef4444 !important; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 24px; border-radius: 0; }
            .logout-item:hover { background: transparent !important; color: #f87171 !important; transform: none; }

            /* Adjustment for pages using this navigation */
            .main-content { margin-left: 260px; padding: 40px; background: #f8fafc; min-height: 100vh; }
            .dashboard-wrapper { min-height: 100vh; }
        </style>
        <aside class="sidebar">
            <div class="sidebar-brand">PickGo</div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    Dashboard
                </a>
                <a href="manage_orders.php" class="nav-item <?php echo $activePage === 'orders' ? 'active' : ''; ?>">
                    Orders
                </a>
                <a href="manage_items.php" class="nav-item <?php echo $activePage === 'products' ? 'active' : ''; ?>">
                    Products
                </a>
                <a href="analytics.php" class="nav-item <?php echo $activePage === 'analytics' ? 'active' : ''; ?>">
                    Analytics
                </a>
                <a href="payouts.php" class="nav-item <?php echo $activePage === 'payouts' ? 'active' : ''; ?>">
                    Payouts
                </a>
                <a href="reviews.php" class="nav-item <?php echo $activePage === 'reviews' ? 'active' : ''; ?>">
                    Reviews
                </a>
                <a href="store_profile.php" class="nav-item <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                    Store Profile
                </a>
                <a href="../logout.php" class="nav-item logout-item">
                    Sign Out
                </a>
            </nav>
        </aside>
        <?php
    }
}
?>
