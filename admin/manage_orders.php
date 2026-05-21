<?php
require_once '../config.php';
require_once 'Navigation.php';

// Check if user is an admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE orders SET Order_Status = ? WHERE Order_Id = ?");
    $stmt->execute([$new_status, $order_id]);
}

// Filters
$status_filter = $_GET['status'] ?? '';
$seller_filter = $_GET['seller'] ?? '';
$rider_filter = $_GET['rider'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT o.*, 
                 u.first_name as cust_fname, u.last_name as cust_lname, u.email as cust_email,
                 s.Sellr_Fname, s.Sellr_Lname,
                 r.Rider_Fname, r.Rider_Lname,
                 m.Merch_Name
          FROM orders o
          JOIN users u ON o.Customer_Id = u.id
          JOIN sellers s ON o.Seller_Id = s.Seller_Id
          LEFT JOIN merchants m ON s.Merch_Id = m.Merch_Id
          LEFT JOIN riders r ON o.Rider_Id = r.Rider_Id
          WHERE 1=1";

$params = [];

if ($status_filter) {
    $query .= " AND o.Order_Status = ?";
    $params[] = $status_filter;
}
if ($seller_filter) {
    $query .= " AND m.Merch_Name LIKE ?";
    $params[] = "%$seller_filter%";
}
if ($rider_filter) {
    $query .= " AND (r.Rider_Fname LIKE ? OR r.Rider_Lname LIKE ?)";
    $params[] = "%$rider_filter%";
    $params[] = "%$rider_filter%";
}
if ($search) {
    $query .= " AND (o.Order_Id LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY o.Order_Date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Fetch unique sellers and riders for filters (optional, but let's keep it simple with text inputs for now or quick chips)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Monitoring - PickGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 1440px; margin: 0 auto; padding: 40px 20px; }


        .header-section { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
        .header-section h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        
        .filters-container { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid rgba(0,0,0,0.05); margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .filter-group input, .filter-group select { padding: 10px 14px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 14px; outline: none; transition: border-color 0.2s; min-width: 180px; }
        .filter-group input:focus, .filter-group select:focus { border-color: #f97316; }
        
        .btn-filter { padding: 10px 24px; background: #0f172a; color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; height: 41px; }

        .table-container { background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 1000px; }
        th { background: #f8fafc; padding: 16px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 20px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        .order-id { font-weight: 700; color: #f97316; }
        
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-preparing { background: #dbeafe; color: #1e40af; }
        .status-on_the_way { background: #ffedd5; color: #9a3412; }
        .status-delivered { background: #dcfce7; color: #15803d; }
        .status-cancelled { background: #fee2e2; color: #b91c1c; }

        .price-tag { font-weight: 700; color: #0f172a; }

        .actions-select { padding: 6px 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 12px; outline: none; }

        .empty-state { padding: 80px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php AdminNavigation::render(); ?>

        <div class="header-section">
            <div>
                <h1>Order Monitoring</h1>
                <p style="color: #64748b; margin-top: 4px;">Monitor all active and past orders across the platform.</p>
            </div>
        </div>

        <form method="GET" class="filters-container">
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Order ID or Email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php if($status_filter=='pending') echo 'selected'; ?>>Pending</option>
                    <option value="preparing" <?php if($status_filter=='preparing') echo 'selected'; ?>>Preparing</option>
                    <option value="on_the_way" <?php if($status_filter=='on_the_way') echo 'selected'; ?>>On the Way</option>
                    <option value="delivered" <?php if($status_filter=='delivered') echo 'selected'; ?>>Delivered</option>
                    <option value="cancelled" <?php if($status_filter=='cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Seller / Store</label>
                <input type="text" name="seller" placeholder="Merchant name..." value="<?php echo htmlspecialchars($seller_filter); ?>">
            </div>
            <div class="filter-group">
                <label>Rider</label>
                <input type="text" name="rider" placeholder="Rider name..." value="<?php echo htmlspecialchars($rider_filter); ?>">
            </div>
            <button type="submit" class="btn-filter">Apply Filters</button>
            <a href="manage_orders.php" style="font-size: 13px; color: #64748b; text-decoration: none; margin-bottom: 12px;">Reset</a>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Store / Seller</th>
                        <th>Rider</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><span class="order-id">#<?php echo str_pad($order['Order_Id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($order['cust_fname'] . ' ' . $order['cust_lname']); ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($order['cust_email']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($order['Merch_Name'] ?? 'No Store'); ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($order['Sellr_Fname'] . ' ' . $order['Sellr_Lname']); ?></div>
                                </td>
                                <td>
                                    <?php if ($order['Rider_Fname']): ?>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($order['Rider_Fname'] . ' ' . $order['Rider_Lname']); ?></div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="price-tag">₱<?php echo number_format($order['Order_Total'], 2); ?></span></td>
                                <td>
                                    <span class="status-pill status-<?php echo $order['Order_Status']; ?>">
                                        <?php echo str_replace('_', ' ', $order['Order_Status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, H:i', strtotime($order['Order_Date'])); ?></td>
                                <td>
                                    <form method="POST" style="display: flex; gap: 4px;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['Order_Id']; ?>">
                                        <select name="new_status" class="actions-select" onchange="this.form.submit()">
                                            <option value="" disabled selected>Change Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="preparing">Preparing</option>
                                            <option value="on_the_way">On the Way</option>
                                            <option value="delivered">Delivered</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                        <input type="hidden" name="action" value="update_status">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <p>No orders found matching your filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
