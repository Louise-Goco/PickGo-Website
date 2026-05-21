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

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_settings') {
            // Update simple settings
            foreach ($_POST['settings'] as $key => $value) {
                $stmt = $pdo->prepare("UPDATE settings SET Setting_Value = ? WHERE Setting_Key = ?");
                $stmt->execute([$value, $key]);
            }
            
            // Update payment methods (checkboxes)
            $payment_methods = ['payment_cod_enabled', 'payment_gcash_enabled', 'payment_card_enabled'];
            foreach ($payment_methods as $method) {
                $status = isset($_POST['payments'][$method]) ? '1' : '0';
                $stmt = $pdo->prepare("UPDATE settings SET Setting_Value = ? WHERE Setting_Key = ?");
                $stmt->execute([$status, $method]);
            }
            $success = "System configurations updated successfully!";
        } elseif ($action === 'add_promo') {
            $code = strtoupper(trim($_POST['promo_code']));
            $type = $_POST['promo_type'];
            $value = $_POST['promo_value'];
            $expiry = $_POST['promo_expiry'];
            $limit = $_POST['promo_limit'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO promo_codes (Code, Discount_Type, Discount_Value, Expiry_Date, Usage_Limit) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$code, $type, $value, $expiry, $limit]);
                $success = "Promo code added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding promo code. It might already exist.";
            }
        } elseif ($action === 'delete_promo') {
            $id = $_POST['promo_id'];
            $stmt = $pdo->prepare("DELETE FROM promo_codes WHERE Promo_Id = ?");
            $stmt->execute([$id]);
            $success = "Promo code deleted!";
        }
    }
}

// Fetch all settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings_raw = $stmt->fetchAll();
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['Setting_Key']] = $s['Setting_Value'];
}

// Fetch promo codes
$stmt = $pdo->query("SELECT * FROM promo_codes ORDER BY created_at DESC");
$promos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - PickGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }


        .header-section { margin-top: 40px; margin-bottom: 32px; }
        .header-section h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        
        .settings-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
        .settings-card { background: #fff; border-radius: 20px; padding: 32px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
        
        .settings-group { display: flex; align-items: center; justify-content: space-between; padding: 24px 0; border-bottom: 1px solid #f1f5f9; }
        .settings-group:last-child { border-bottom: none; }
        
        .settings-info { flex: 1; }
        .settings-info h3 { margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #0f172a; }
        .settings-info p { margin: 0; font-size: 14px; color: #64748b; }

        .settings-input { width: 150px; position: relative; }
        .settings-input input { width: 100%; padding: 12px 16px 12px 32px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 15px; font-weight: 600; color: #0f172a; outline: none; transition: border-color 0.2s; }
        .settings-input input:focus { border-color: #f97316; }
        .settings-input span.currency { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #94a3b8; font-size: 14px; }
        .settings-input span.percent { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #94a3b8; font-size: 14px; }

        .footer-actions { margin-top: 32px; display: flex; justify-content: flex-end; }
        .btn-save { padding: 14px 40px; background: #0f172a; color: #fff; border: none; border-radius: 12px; font-weight: 700; font-size: 16px; cursor: pointer; transition: transform 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-save:hover { background: #1e293b; transform: translateY(-2px); }

        /* Payment Section */
        .toggle-group { display: flex; align-items: center; justify-content: space-between; padding: 16px 0; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #e2e8f0; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #f97316; }
        input:checked + .slider:before { transform: translateX(24px); }

        /* Promo Table */
        .promo-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .promo-table th { text-align: left; padding: 12px; border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 12px; }
        .promo-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .promo-badge { padding: 4px 8px; background: #f1f5f9; border-radius: 6px; font-weight: 700; color: #0f172a; font-family: monospace; }
        .btn-delete { color: #ef4444; background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600; }

        .section-title { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 48px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #f1f5f9; }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; text-align: center; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php AdminNavigation::render(); ?>

        <div class="header-section">
            <h1>System Configuration</h1>
            <p style="color: #64748b; margin-top: 4px;">Adjust platform-wide fees and service parameters.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="section-title">Fee & Rate Configuration</div>
            <div class="settings-card">
                <!-- Delivery Fee -->
                <div class="settings-group">
                    <div class="settings-info">
                        <h3>Standard Delivery Fee</h3>
                        <p>The base cost charged to customers for each delivery.</p>
                    </div>
                    <div class="settings-input">
                        <span class="currency">₱</span>
                        <input type="number" step="0.01" name="settings[delivery_fee]" value="<?php echo htmlspecialchars($settings['delivery_fee'] ?? '0'); ?>">
                    </div>
                </div>

                <!-- Service Fee -->
                <div class="settings-group">
                    <div class="settings-info">
                        <h3>Service Charge</h3>
                        <p>A fixed processing fee applied to every order transaction.</p>
                    </div>
                    <div class="settings-input">
                        <span class="currency">₱</span>
                        <input type="number" step="0.01" name="settings[service_fee]" value="<?php echo htmlspecialchars($settings['service_fee'] ?? '0'); ?>">
                    </div>
                </div>

                <!-- Tax Rate -->
                <div class="settings-group">
                    <div class="settings-info">
                        <h3>Sales Tax Rate (VAT)</h3>
                        <p>Percentage of tax applied to the total order value.</p>
                    </div>
                    <div class="settings-input">
                        <input type="number" step="0.01" name="settings[tax_rate]" value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '0'); ?>" style="padding-left: 16px;">
                        <span class="percent">%</span>
                    </div>
                </div>
            </div>

            <div class="section-title">Payment Methods</div>
            <div class="settings-card">
                <div class="toggle-group">
                    <div class="settings-info">
                        <h3>Cash on Delivery (COD)</h3>
                        <p>Allow customers to pay with cash upon delivery.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="payments[payment_cod_enabled]" <?php echo ($settings['payment_cod_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="toggle-group" style="padding-top: 16px; border-top: 1px solid #f1f5f9;">
                    <div class="settings-info">
                        <h3>GCash / E-Wallet</h3>
                        <p>Enable digital wallet payments via GCash.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="payments[payment_gcash_enabled]" <?php echo ($settings['payment_gcash_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="toggle-group" style="padding-top: 16px; border-top: 1px solid #f1f5f9;">
                    <div class="settings-info">
                        <h3>Credit / Debit Card</h3>
                        <p>Accept payments via Visa, Mastercard, and JCB.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="payments[payment_card_enabled]" <?php echo ($settings['payment_card_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="footer-actions">
                <button type="submit" class="btn-save">Save Configurations</button>
            </div>
        </form>

        <div class="section-title">Promo Codes</div>
        <div class="settings-card">
            <h3>Add New Promo Code</h3>
            <form method="POST" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
                <input type="hidden" name="action" value="add_promo">
                <div class="form-group" style="margin:0;">
                    <label>Code</label>
                    <input type="text" name="promo_code" placeholder="e.g. SUMMER20" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Type</label>
                    <select name="promo_type" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₱)</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Value</label>
                    <input type="number" step="0.01" name="promo_value" placeholder="10.00" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Expiry Date</label>
                    <input type="date" name="promo_expiry" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Usage Limit</label>
                    <input type="number" name="promo_limit" value="100" required>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; margin: 0;">
                    <button type="submit" class="btn-save" style="width: 100%; padding: 12px;">Add Promo</button>
                </div>
            </form>

            <table class="promo-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Expiry</th>
                        <th>Usage</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($promos) > 0): ?>
                        <?php foreach($promos as $p): ?>
                            <tr>
                                <td><span class="promo-badge"><?php echo htmlspecialchars($p['Code']); ?></span></td>
                                <td><?php echo $p['Discount_Type'] === 'percentage' ? $p['Discount_Value'].'%' : '₱'.$p['Discount_Value']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($p['Expiry_Date'])); ?></td>
                                <td><?php echo $p['Current_Usage'].' / '.$p['Usage_Limit']; ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete this promo code?')">
                                        <input type="hidden" name="action" value="delete_promo">
                                        <input type="hidden" name="promo_id" value="<?php echo $p['Promo_Id']; ?>">
                                        <button type="submit" class="btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No promo codes active.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
