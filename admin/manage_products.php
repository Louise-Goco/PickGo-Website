<?php
require_once '../config.php';
require_once 'Navigation.php';

// Check if user is an admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $item_id = $_POST['item_id'];
    $action = $_POST['action'];
    $status = ($action === 'approve') ? 'available' : 'rejected';

    try {
        $stmt = $pdo->prepare("UPDATE items SET Item_Status = ? WHERE Item_Id = ?");
        $stmt->execute([$status, $item_id]);
        $success = "Product " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch pending products
$stmt = $pdo->query("SELECT i.*, m.Merch_Name, c.Categ_Name 
                    FROM items i 
                    JOIN sellers s ON i.Seller_Id = s.Seller_Id 
                    JOIN merchants m ON s.Merch_Id = m.Merch_Id 
                    LEFT JOIN categories c ON i.Item_Category = c.Categ_Id 
                    ORDER BY i.created_at DESC");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .page-header { margin-bottom: 32px; }
        .data-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .data-table th { background: #f8fafc; padding: 16px; text-align: left; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        .data-table td { padding: 16px; border-top: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-available { background: #f0fdf4; color: #166534; }
        .badge-rejected { background: #fef2f2; color: #991b1b; }
        .action-btn { padding: 8px 16px; border-radius: 8px; border: none; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-approve { background: #10b981; color: #fff; }
        .btn-reject { background: #ef4444; color: #fff; }
        .product-img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #f1f5f9; }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php AdminNavigation::render(); ?>

        <div class="page-header">
            <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Product Management</h1>
            <p style="color: #64748b;">Review and moderate product submissions from merchants.</p>
        </div>

        <?php if ($success): ?>
            <div style="background: #f0fdf4; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #dcfce7;"><?php echo $success; ?></div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Merchant</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 12px;">
                            <img src="../<?php echo $p['Item_Image'] ?: 'placeholder.png'; ?>" class="product-img">
                            <div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($p['Item_Name']); ?></div>
                                <div style="font-size: 12px; color: #94a3b8;"><?php echo substr(htmlspecialchars($p['Item_Description']), 0, 40) . '...'; ?></div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($p['Merch_Name']); ?></td>
                        <td><?php echo htmlspecialchars($p['Categ_Name']); ?></td>
                        <td style="font-weight: 700;">₱<?php echo number_format($p['Item_Price'], 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $p['Item_Status']; ?>">
                                <?php echo str_replace('_', ' ', $p['Item_Status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        <td>
                            <?php if ($p['Item_Status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px;">
                                    <form method="POST">
                                        <input type="hidden" name="item_id" value="<?php echo $p['Item_Id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn btn-approve">Approve</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="item_id" value="<?php echo $p['Item_Id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn btn-reject">Reject</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
