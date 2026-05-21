<?php
require_once '../config.php';
require_once 'Navigation.php';
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $display_name = $_POST['display_name'] ?? '';
        $phone_number = $_POST['phone_number'] ?? '';
        
        $updateStmt = $pdo->prepare("UPDATE users SET display_name = ?, phone_number = ? WHERE id = ?");
        if ($updateStmt->execute([$display_name, $phone_number, $user['id']])) {
            $success = "Profile updated successfully.";
            $user['display_name'] = $display_name;
            $user['phone_number'] = $phone_number;
        } else {
            $error = "Failed to update profile.";
        }
    } elseif (isset($_POST['add_address'])) {
        $label = $_POST['label'] ?? '';
        $address_line = $_POST['address_line'] ?? '';
        $city = $_POST['city'] ?? '';
        
        if ($label && $address_line && $city) {
            $addAddr = $pdo->prepare("INSERT INTO addresses (user_id, label, address_line_1, city) VALUES (?, ?, ?, ?)");
            if ($addAddr->execute([$user['id'], $label, $address_line, $city])) {
                $success = "Address added successfully.";
            } else {
                $error = "Failed to add address.";
            }
        } else {
            $error = "All address fields are required.";
        }
    } elseif (isset($_POST['delete_address'])) {
        $address_id = $_POST['address_id'] ?? '';
        if ($address_id) {
            $delStmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
            if ($delStmt->execute([$address_id, $user['id']])) {
                $success = "Address deleted successfully.";
            } else {
                $error = "Failed to delete address.";
            }
        }
    }
}

// Fetch addresses
$addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ?");
$addrStmt->execute([$user['id']]);
$addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .profile-container { max-width: 1100px; margin: 40px auto; display: grid; grid-template-columns: 1fr 1fr; gap: 32px; padding: 0 20px;}
        @media (max-width: 992px) { .profile-container { grid-template-columns: 1fr; } }
        
        .profile-page-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 80px 0 40px 0; margin-top: 0; }
        .header-content { max-width: 1100px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; gap: 24px; }
        .profile-avatar-large { width: 80px; height: 80px; background: #f97316; color: #fff; border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.2); }
        .profile-main-meta h1 { font-size: 28px; font-weight: 800; color: #0f172a; margin: 0; }
        .profile-main-meta p { color: #64748b; font-size: 15px; margin-top: 4px; font-weight: 500; }

        .card { background: #ffffff; border: 1px solid rgba(0,0,0,0.05); border-radius: 24px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .card h3 { margin-top: 0; margin-bottom: 24px; font-size: 18px; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .card h3::after { content: ''; flex: 1; height: 1px; background: #f1f5f9; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #64748b; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
        .form-group input { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.2s ease; box-sizing: border-box; }
        .form-group input:focus { border-color: #f97316; outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1); }
        .form-group input:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        
        .btn { background: #f97316; color: white; border: none; padding: 14px 28px; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 15px; transition: all 0.2s ease; width: 100%; }
        .btn:hover { background: #ea580c; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.2); }
        
        .address-grid { display: flex; flex-direction: column; gap: 12px; }
        .address-item { background: #fff; padding: 20px; border-radius: 16px; border: 1px solid #f1f5f9; transition: all 0.2s; }
        .address-item:hover { border-color: #f97316; background: #fffaf5; }
        .address-item h4 { margin: 0 0 4px 0; color: #0f172a; font-size: 15px; font-weight: 700;}
        .address-item p { margin: 0; color: #64748b; font-size: 13px; line-height: 1.6; }
        
        .btn-delete { color: #ef4444; font-size: 12px; font-weight: 700; cursor: pointer; background: #fef2f2; border: none; padding: 6px 12px; border-radius: 8px; transition: all 0.2s; }
        .btn-delete:hover { background: #fee2e2; }
        
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600; border: 1px solid transparent; }
        .alert-success { background: #f0fdf4; color: #166534; border-color: #dcfce7; }
        .alert-error { background: #fef2f2; color: #991b1b; border-color: #fee2e2; }
    </style>
</head>
<body>
    <?php Navigation::render(); ?>

    <div class="profile-page-header">
        <div class="header-content">
            <div class="profile-avatar-large">
                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
            </div>
            <div class="profile-main-meta">
                <h1><?php echo htmlspecialchars($user['display_name'] ?: $user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <p>Member since <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?></p>
            </div>
        </div>
    </div>

    <div class="profile-container">
        <div class="card">
            <h3>Personal Information</h3>
            <?php if ($success) echo "<div class='alert alert-success'>" . htmlspecialchars($success) . "</div>"; ?>
            <?php if ($error) echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>"; ?>
            
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" placeholder="What should we call you?">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                </div>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>

        <div class="card">
            <h3>Saved Addresses</h3>
            <div class="address-grid">
                <?php if (empty($addresses)): ?>
                    <p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">No saved addresses yet.</p>
                <?php else: ?>
                    <?php foreach ($addresses as $addr): ?>
                        <div class="address-item">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <h4><?php echo htmlspecialchars($addr['label']); ?></h4>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this address?');">
                                    <input type="hidden" name="delete_address" value="1">
                                    <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($addr['id']); ?>">
                                    <button type="submit" class="btn-delete">Delete</button>
                                </form>
                            </div>
                            <p><?php echo htmlspecialchars($addr['address_line_1']); ?>, <?php echo htmlspecialchars($addr['city']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #f1f5f9;">
                <h4 style="margin-bottom: 16px; font-size: 16px; color: #0f172a; font-weight: 600;">Add New Address</h4>
                <form method="POST">
                    <input type="hidden" name="add_address" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Label</label>
                            <input type="text" name="label" placeholder="Home, Office" required>
                        </div>
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address Line</label>
                        <input type="text" name="address_line" required>
                    </div>
                    <button type="submit" class="btn">Add Address</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
