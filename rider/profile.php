<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: ../login.php');
    exit;
}

// Fetch rider data
$stmt = $pdo->prepare("SELECT * FROM riders WHERE Rider_Email = ?");
$stmt->execute([$_SESSION['user']]);
$riderData = $stmt->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_type = $_POST['vehicle_type'] ?? '';
    $plate_number = $_POST['plate_number'] ?? '';
    $bank_name = $_POST['bank_name'] ?? '';
    $bank_acc_no = $_POST['bank_acc_no'] ?? '';
    $bank_acc_name = $_POST['bank_acc_name'] ?? '';

    // Handle Photo Upload
    $photo_path = $riderData['Rider_Photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_name = 'rider_' . $riderData['Rider_Id'] . '_' . time() . '.' . $ext;
            $upload_dir = '../uploads/riders/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_path = 'uploads/riders/' . $new_name;
            }
        } else {
            $error = "Invalid file type for photo.";
        }
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("UPDATE riders SET 
                Rider_VehicleType = ?, 
                Rider_PlateNumber = ?, 
                Rider_BankName = ?, 
                Rider_BankAccNo = ?, 
                Rider_BankAccName = ?,
                Rider_Photo = ?
                WHERE Rider_Id = ?");
            
            if ($stmt->execute([$vehicle_type, $plate_number, $bank_name, $bank_acc_no, $bank_acc_name, $photo_path, $riderData['Rider_Id']])) {
                $success = "Profile updated successfully!";
                // Refresh data
                $stmt = $pdo->prepare("SELECT * FROM riders WHERE Rider_Id = ?");
                $stmt->execute([$riderData['Rider_Id']]);
                $riderData = $stmt->fetch();
            } else {
                $error = "Failed to update profile.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Profile - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        :root { --primary: #10b981; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .card { background: #fff; padding: 32px; border-radius: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .profile-header { display: flex; align-items: center; gap: 24px; margin-bottom: 32px; }
        .profile-photo { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; background: #e2e8f0; border: 4px solid #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 32px; }
        .form-section h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 8px; }
        .input-wrapper input, .input-wrapper select { width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 14px; transition: border-color 0.2s; }
        .input-wrapper input:focus { border-color: var(--primary); outline: none; }
        .btn-save { background: var(--primary); color: #fff; border: none; padding: 16px 32px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s; width: 100%; }
        .btn-save:hover { background: #059669; }
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" style="display: inline-flex; align-items: center; color: #64748b; text-decoration: none; margin-bottom: 24px; font-weight: 500; gap: 8px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Dashboard
        </a>

        <div class="card">
            <h1 style="font-size: 24px; font-weight: 800; margin-bottom: 32px;">Profile Settings</h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="profile-header">
                    <img src="../<?php echo $riderData['Rider_Photo'] ?? 'uploads/default_avatar.png'; ?>" alt="Profile" class="profile-photo" id="photo-preview">
                    <div>
                        <h2 style="font-size: 18px; font-weight: 700;"><?php echo htmlspecialchars($riderData['Rider_Fname'] . ' ' . $riderData['Rider_Lname']); ?></h2>
                        <p style="color: #64748b; font-size: 14px; margin-bottom: 12px;"><?php echo htmlspecialchars($riderData['Rider_Email']); ?></p>
                        <input type="file" name="photo" id="photo-input" style="display: none;" onchange="previewImage(this)">
                        <button type="button" onclick="document.getElementById('photo-input').click()" style="background: #f1f5f9; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Change Photo</button>
                    </div>
                </div>

                <div class="form-section">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                        Vehicle Information
                    </h3>
                    <div class="grid">
                        <div class="input-group">
                            <label>Vehicle Type</label>
                            <div class="input-wrapper">
                                <select name="vehicle_type" required>
                                    <option value="Bicycle" <?php echo $riderData['Rider_VehicleType'] === 'Bicycle' ? 'selected' : ''; ?>>Bicycle</option>
                                    <option value="Motorcycle" <?php echo $riderData['Rider_VehicleType'] === 'Motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                    <option value="Car" <?php echo $riderData['Rider_VehicleType'] === 'Car' ? 'selected' : ''; ?>>Car</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Plate Number</label>
                            <div class="input-wrapper">
                                <input type="text" name="plate_number" value="<?php echo htmlspecialchars($riderData['Rider_PlateNumber']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                        Bank Account Info
                    </h3>
                    <div class="input-group">
                        <label>Bank Name</label>
                        <div class="input-wrapper">
                            <?php $selectedBank = $riderData['Rider_BankName'] ?? ''; ?>
                            <select name="bank_name" id="bankNameSelect" required onchange="handleBankChange(this.value)">
                                <option value="" disabled <?php echo empty($selectedBank) ? 'selected' : ''; ?>>Select Bank / E-Wallet</option>
                                <option value="GCash" <?php echo $selectedBank === 'GCash' ? 'selected' : ''; ?>>GCash</option>
                                <option value="Maya" <?php echo $selectedBank === 'Maya' ? 'selected' : ''; ?>>Maya</option>
                                <option value="BDO Unibank" <?php echo $selectedBank === 'BDO Unibank' ? 'selected' : ''; ?>>BDO Unibank</option>
                                <option value="BPI" <?php echo $selectedBank === 'BPI' ? 'selected' : ''; ?>>Bank of the Philippine Islands (BPI)</option>
                                <option value="UnionBank" <?php echo $selectedBank === 'UnionBank' ? 'selected' : ''; ?>>UnionBank of the Philippines</option>
                                <option value="Metrobank" <?php echo $selectedBank === 'Metrobank' ? 'selected' : ''; ?>>Metrobank</option>
                                <option value="LandBank" <?php echo $selectedBank === 'LandBank' ? 'selected' : ''; ?>>LandBank of the Philippines</option>
                                <option value="Security Bank" <?php echo $selectedBank === 'Security Bank' ? 'selected' : ''; ?>>Security Bank</option>
                                <option value="RCBC" <?php echo $selectedBank === 'RCBC' ? 'selected' : ''; ?>>RCBC</option>
                                <option value="PNB" <?php echo $selectedBank === 'PNB' ? 'selected' : ''; ?>>Philippine National Bank (PNB)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid">
                        <div class="input-group">
                            <label>Account Number</label>
                            <div class="input-wrapper">
                                <input type="text" name="bank_acc_no" id="bankAccNoInput" value="<?php echo htmlspecialchars($riderData['Rider_BankAccNo'] ?? ''); ?>" placeholder="Enter account number">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Account Name</label>
                            <div class="input-wrapper">
                                <input type="text" name="bank_acc_name" value="<?php echo htmlspecialchars($riderData['Rider_BankAccName'] ?? ''); ?>" placeholder="Enter account name">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        const riderPhone = <?php echo json_encode($riderData['Rider_Phone'] ?? ''); ?>;
        function handleBankChange(bankName) {
            if (bankName === 'GCash') {
                document.getElementById('bankAccNoInput').value = riderPhone;
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photo-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
