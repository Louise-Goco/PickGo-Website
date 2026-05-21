<?php
require_once '../config.php';
require_once 'Navigation.php';

// Check if user is an admin (Generic login check is handled by config.php)
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$admin_name = $user['first_name'] ?? $_SESSION['user'];

// Fetch stats
$stmt = $pdo->query("SELECT (SELECT COUNT(*) FROM users) + (SELECT COUNT(*) FROM sellers) + (SELECT COUNT(*) FROM riders)");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE is_verified = 0) + 
    (SELECT COUNT(*) FROM sellers WHERE Sellr_Status = 'pending') + 
    (SELECT COUNT(*) FROM riders WHERE Rider_Status = 'pending') +
    (SELECT COUNT(*) FROM items WHERE Item_Status = 'pending')
");
$pending_approvals = $stmt->fetchColumn();

// Fetch latest pending registration for alert
$stmt = $pdo->query("
    (SELECT Sellr_Fname as fname, Sellr_Lname as lname, Sellr_DateCreated as date, 'seller' as type FROM sellers WHERE Sellr_Status = 'pending')
    UNION
    (SELECT Rider_Fname as fname, Rider_Lname as lname, created_at as date, 'rider' as type FROM riders WHERE Rider_Status = 'pending')
    UNION
    (SELECT Item_Name as fname, '' as lname, created_at as date, 'product' as type FROM items WHERE Item_Status = 'pending')
    ORDER BY date DESC LIMIT 1
");
$latest_pending = $stmt->fetch();

$stmt = $pdo->query("SELECT COUNT(*) FROM sellers WHERE Sellr_Status = 'active'");
$active_sellers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM riders WHERE Rider_Status = 'active'");
$active_riders = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 100%; margin: 0 auto; padding: 40px 20px; }


        .welcome-section { margin-top: 40px; color: #0f172a; }
        .welcome-section h1 { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .welcome-section p { color: #64748b; font-size: 16px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-top: 30px; }
        .stat-card { background: #ffffff; border: 1px solid rgba(0,0,0,0.05); border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .stat-card h3 { color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; }
        .stat-card .value { font-size: 28px; font-weight: 800; color: #0f172a; }
        .stat-card .trend { font-size: 13px; margin-top: 8px; display: flex; align-items: center; gap: 4px; }
        .trend.up { color: #10b981; }
        
        .admin-sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-top: 40px; }
        .admin-card { background: #ffffff; border: 1px solid rgba(0,0,0,0.05); border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
        .admin-card:hover { transform: translateY(-4px); }
        .admin-card h3 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
        .admin-card p { color: #64748b; font-size: 14px; line-height: 1.5; margin-bottom: 20px; }
        .admin-link { color: #f97316; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .admin-link:hover { text-decoration: underline; }

        .alerts-section { margin-top: 40px; background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .alerts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .alerts-header h2 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; }
        .alert-item { display: flex; align-items: flex-start; gap: 16px; padding: 16px; border-radius: 12px; background: #f8fafc; margin-bottom: 12px; border: 1px solid transparent; }
        .alert-item.warning { border-color: #fef9c3; background: #fffbeb; }
        .alert-item.error { border-color: #fee2e2; background: #fef2f2; }
        .alert-icon { margin-top: 2px; }
        .alert-content h4 { margin: 0 0 4px 0; font-size: 15px; font-weight: 600; color: #0f172a; }
        .alert-content p { margin: 0; font-size: 14px; color: #64748b; }
        .alert-time { font-size: 12px; color: #94a3b8; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php AdminNavigation::render(); ?>

        <div class="welcome-section">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?>. Here's what's happening today.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo number_format($total_users); ?></div>
                <div class="trend up">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    8% increase
                </div>
            </div>
            <div class="stat-card">
                <h3>Pending Approvals</h3>
                <div class="value"><?php echo number_format($pending_approvals); ?></div>
                <div class="trend" style="color: #f97316;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    Requires attention
                </div>
            </div>
            <div class="stat-card">
                <h3>Active Sellers</h3>
                <div class="value"><?php echo number_format($active_sellers); ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Riders</h3>
                <div class="value"><?php echo number_format($active_riders); ?></div>
            </div>
            <div class="stat-card">
                <h3>Today's Revenue</h3>
                <div class="value">₱12,450.00</div>
                <div class="trend up">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    15% vs yesterday
                </div>
            </div>
        </div>

        <div class="admin-sections">
            <div class="admin-card">
                <h3>User Management</h3>
                <p>View, edit, and manage all customer and admin accounts. Control permissions and account status.</p>
                <a href="manage_customers.php" class="admin-link">Manage Users &rarr;</a>
            </div>
            <div class="admin-card">
                <h3>Seller Management</h3>
                <p>Approve new seller applications, manage merchant profiles, suspend accounts, and monitor compliance.</p>
                <a href="manage_sellers.php" class="admin-link">Manage Sellers &rarr;</a>
            </div>
            <div class="admin-card">
                <h3>Rider Management</h3>
                <p>Verify rider documents, approve applications, monitor delivery performance, and manage status.</p>
                <a href="manage_riders.php" class="admin-link">Manage Riders &rarr;</a>
            </div>
            <div class="admin-card">
                <h3>Product Management</h3>
                <p>Review new product submissions from sellers. Approve or reject items for the public marketplace.</p>
                <a href="manage_products.php" class="admin-link">Manage Products &rarr;</a>
            </div>
            <div class="admin-card">
                <h3>System Settings</h3>
                <p>Configure platform-wide settings, payment methods, delivery fees, and promo codes.</p>
                <a href="settings.php" class="admin-link">Global Settings &rarr;</a>
            </div>
        </div>

        <div class="alerts-section">
            <div class="alerts-header">
                <h2>System Alerts</h2>
                <a href="#" class="admin-link">View All Alerts</a>
            </div>
            
            <div class="alert-item error">
                <div class="alert-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
                <div class="alert-content">
                    <h4>High Server Latency</h4>
                    <p>System response times are higher than usual in the North region.</p>
                    <div class="alert-time">2 minutes ago</div>
                </div>
            </div>

            <?php if ($latest_pending): ?>
            <div class="alert-item warning">
                <div class="alert-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <div class="alert-content">
                    <h4>New <?php echo ucfirst($latest_pending['type']); ?> Application</h4>
                    <p><?php echo htmlspecialchars($latest_pending['fname'] . ' ' . $latest_pending['lname']); ?> has submitted a new application for approval.</p>
                    <div class="alert-time"><?php echo date('M d, H:i', strtotime($latest_pending['date'])); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
