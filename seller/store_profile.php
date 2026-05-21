<?php
require_once '../db.php';
require_once 'Navigation.php';
session_start();

// Check if user is a seller
if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit;
}

// Fetch seller and merchant data
$stmt = $pdo->prepare("SELECT s.*, m.* FROM sellers s JOIN merchants m ON s.Merch_Id = m.Merch_Id WHERE m.Merch_Email = ?");
$stmt->execute([$_SESSION['user']]);
$sellerData = $stmt->fetch();

if (!$sellerData || $sellerData['Sellr_Status'] !== 'active') {
    header('Location: ../login.php');
    exit;
}

$merch_id = $sellerData['Merch_Id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_name = trim($_POST['merch_name']);
    $opening_time = $_POST['opening_time'];
    $closing_time = $_POST['closing_time'];
    $delivery_range = intval($_POST['delivery_range']);
    $description = trim($_POST['description']);

    // Handle File Uploads
    $logo = $sellerData['Merch_Logo'];
    $banner = $sellerData['Merch_Banner'];

    $upload_dir = '../uploads/merchants/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!empty($_FILES['logo']['name'])) {
        $logo_name = 'logo_' . $merch_id . '_' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_name)) {
            $logo = 'uploads/merchants/' . $logo_name;
        }
    }

    if (!empty($_FILES['banner']['name'])) {
        $banner_name = 'banner_' . $merch_id . '_' . time() . '.' . pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['banner']['tmp_name'], $upload_dir . $banner_name)) {
            $banner = 'uploads/merchants/' . $banner_name;
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE merchants SET 
            Merch_Name = ?, 
            Merch_OpeningTime = ?, 
            Merch_ClosingTime = ?, 
            Merch_DeliveryRange = ?, 
            Merch_Description = ?,
            Merch_Logo = ?,
            Merch_Banner = ?
            WHERE Merch_Id = ?");
        
        $stmt->execute([$store_name, $opening_time, $closing_time, $delivery_range, $description, $logo, $banner, $merch_id]);
        
        $success = "Store profile updated successfully!";
        // Refresh data
        $stmt = $pdo->prepare("SELECT s.*, m.* FROM sellers s JOIN merchants m ON s.Merch_Id = m.Merch_Id WHERE m.Merch_Email = ?");
        $stmt->execute([$_SESSION['user']]);
        $sellerData = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Profile - <?php echo htmlspecialchars($sellerData['Merch_Name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .settings-card { background: #fff; padding: 32px; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); max-width: 800px; }
        .preview-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 32px; }
        .logo-preview { width: 120px; height: 120px; border-radius: 20px; background: #f1f5f9; border: 2px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .banner-preview { width: 100%; height: 120px; border-radius: 20px; background: #f1f5f9; border: 2px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .preview-img { width: 100%; height: 100%; object-fit: cover; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 8px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #f97316; }
        .btn-save { background: #f97316; color: #fff; border: none; padding: 14px 28px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: transform 0.2s; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.3); }
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php SellerNavigation::render('profile'); ?>

        <main class="main-content">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Store Profile</h1>
                <p style="color: #64748b;">Customize how your store appears to customers.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="settings-card">
                <div class="preview-grid">
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 12px;">Store Logo</label>
                        <div class="logo-preview">
                            <?php if ($sellerData['Merch_Logo']): ?>
                                <img src="../<?php echo $sellerData['Merch_Logo']; ?>" class="preview-img">
                            <?php else: ?>
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo" style="margin-top: 12px; font-size: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 12px;">Store Banner</label>
                        <div class="banner-preview">
                            <?php if ($sellerData['Merch_Banner']): ?>
                                <img src="../<?php echo $sellerData['Merch_Banner']; ?>" class="preview-img">
                            <?php else: ?>
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="banner" style="margin-top: 12px; font-size: 12px;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Store Name</label>
                    <input type="text" name="merch_name" value="<?php echo htmlspecialchars($sellerData['Merch_Name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Store Description</label>
                    <textarea name="description" rows="3"><?php echo htmlspecialchars($sellerData['Merch_Description']); ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Opening Time</label>
                        <input type="time" name="opening_time" value="<?php echo $sellerData['Merch_OpeningTime'] ?: '08:00'; ?>">
                    </div>
                    <div class="form-group">
                        <label>Closing Time</label>
                        <input type="time" name="closing_time" value="<?php echo $sellerData['Merch_ClosingTime'] ?: '20:00'; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Delivery Coverage Range (Kilometers)</label>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="number" name="delivery_range" value="<?php echo $sellerData['Merch_DeliveryRange'] ?: 5; ?>" min="1" max="50">
                        <span style="color: #64748b; font-size: 14px;">km</span>
                    </div>
                </div>

                <div style="margin-top: 32px; padding-top: 32px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-save">Update Profile</button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
