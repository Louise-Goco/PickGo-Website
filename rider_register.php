<?php
session_start();
require_once 'db.php';

$loggedInUser = null;
if (isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['user']]);
    $loggedInUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if they already have a rider application
    $stmt = $pdo->prepare("SELECT Rider_Status FROM riders WHERE Rider_Email = ?");
    $stmt->execute([$_SESSION['user']]);
    $existingRider = $stmt->fetch();

    if ($existingRider) {
        if ($existingRider['Rider_Status'] === 'pending') {
            $pendingApplication = true;
        } elseif ($existingRider['Rider_Status'] === 'active') {
            header('Location: rider/dashboard.php');
            exit;
        }
    }
}
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $plate_number = trim($_POST['plate_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');

    if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($password) || empty($vehicle_type) || empty($plate_number) || empty($license_number)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT Rider_Id FROM riders WHERE Rider_Email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email already registered.';
            } else {
                // Handle file uploads
                $upload_dir = 'uploads/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $license_photo_path = null;
                $nbi_path = null;
                $or_path = null;
                $cr_path = null;

                if (isset($_FILES['license_photo']) && $_FILES['license_photo']['error'] == 0) {
                    $ext = pathinfo($_FILES['license_photo']['name'], PATHINFO_EXTENSION);
                    $license_photo_path = $upload_dir . 'license_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['license_photo']['tmp_name'], $license_photo_path);
                }
                if (isset($_FILES['nbi']) && $_FILES['nbi']['error'] == 0) {
                    $ext = pathinfo($_FILES['nbi']['name'], PATHINFO_EXTENSION);
                    $nbi_path = $upload_dir . 'nbi_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['nbi']['tmp_name'], $nbi_path);
                }
                if (isset($_FILES['or']) && $_FILES['or']['error'] == 0) {
                    $ext = pathinfo($_FILES['or']['name'], PATHINFO_EXTENSION);
                    $or_path = $upload_dir . 'or_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['or']['tmp_name'], $or_path);
                }
                if (isset($_FILES['cr']) && $_FILES['cr']['error'] == 0) {
                    $ext = pathinfo($_FILES['cr']['name'], PATHINFO_EXTENSION);
                    $cr_path = $upload_dir . 'cr_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['cr']['tmp_name'], $cr_path);
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO riders (Rider_Fname, Rider_Lname, Rider_Email, Rider_Password, Rider_Phone, Rider_VehicleType, Rider_PlateNumber, Rider_LicenseNumber, Rider_LicensePhoto, Rider_NBI, Rider_OR, Rider_CR, Rider_Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                if ($stmt->execute([$fname, $lname, $email, $hashed_password, $phone, $vehicle_type, $plate_number, $license_number, $license_photo_path, $nbi_path, $or_path, $cr_path])) {
                    $success = 'Application submitted! Please wait for admin approval.';
                    $_POST = array();
                } else {
                    $error = 'Registration failed.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive with us - Pickaroo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
    <style>
        .login-container.register-rider {
            max-width: 600px;
        }
        .form-section h3 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .form-section h3 svg { color: #10b981; }
        .input-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 480px) {
            .input-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="background-elements">
        <div class="blob blob-1"></div>
        <div class="blob blob-2" style="background: #10b981;"></div>
    </div>

    <div class="login-container register-rider">
        <div class="glass-panel">
            <a href="index.php" class="back-home-btn" title="Back to Homepage">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <div class="login-header">
                <h2>Drive with PickGo</h2>
                <p>Deliver food and earn on your own schedule.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($pendingApplication) && $pendingApplication): ?>
                <div style="text-align: center; padding: 40px 0;">
                    <div style="width: 80px; height: 80px; background: #f0fdf4; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: #10b981;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 12px;">Application Pending</h3>
                    <p style="color: #64748b; margin-bottom: 32px;">Your application to join our delivery fleet is being reviewed. We'll notify you as soon as you're approved!</p>
                    <a href="customer/dashboard.php" class="submit-btn" style="text-decoration: none; display: inline-flex; justify-content: center; align-items: center; background: #10b981;">Go to Customer Dashboard</a>
                </div>
            <?php elseif ($success): ?>
                <div class="success-message" style="background: #f0fdf4; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #dcfce7;">
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <div style="text-align: center;">
                    <a href="login.php" class="submit-btn" style="text-decoration: none; display: inline-flex; justify-content: center; align-items: center;">Return to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="login-form" enctype="multipart/form-data">
                    <div class="form-section">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Personal Information
                        </h3>
                        <div class="input-grid">
                            <div class="input-group">
                                <label>First Name</label>
                                <div class="input-wrapper"><input type="text" name="fname" required value="<?php echo htmlspecialchars($_POST['fname'] ?? ($loggedInUser['first_name'] ?? '')); ?>"></div>
                            </div>
                            <div class="input-group">
                                <label>Last Name</label>
                                <div class="input-wrapper"><input type="text" name="lname" required value="<?php echo htmlspecialchars($_POST['lname'] ?? ($loggedInUser['last_name'] ?? '')); ?>"></div>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Email Address</label>
                            <div class="input-wrapper"><input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ($loggedInUser['email'] ?? '')); ?>"></div>
                        </div>
                        <div class="input-group">
                            <label>Phone Number</label>
                            <div class="input-wrapper"><input type="tel" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ($loggedInUser['phone_number'] ?? '')); ?>"></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            Vehicle & License
                        </h3>
                        <div class="input-group">
                            <label>Vehicle Type</label>
                            <div class="input-wrapper">
                                <select name="vehicle_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Bicycle">Bicycle</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                    <option value="Car">Car</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-grid">
                            <div class="input-group">
                                <label>Plate Number</label>
                                <div class="input-wrapper"><input type="text" name="plate_number" required value="<?php echo htmlspecialchars($_POST['plate_number'] ?? ''); ?>"></div>
                            </div>
                            <div class="input-group">
                                <label>License Number</label>
                                <div class="input-wrapper"><input type="text" name="license_number" required value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>"></div>
                            </div>
                        </div>
                    </div>

                    <div class="input-grid">
                        <div class="input-group">
                            <label>Password</label>
                            <div class="input-wrapper"><input type="password" name="password" required></div>
                        </div>
                        <div class="input-group">
                            <label>Confirm Password</label>
                            <div class="input-wrapper"><input type="password" name="confirm_password" required></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            Required Documents
                        </h3>
                        <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">Please upload clear images (JPEG, PNG, PDF) of the following required documents.</p>
                        <div class="input-grid">
                            <div class="input-group full-width" style="grid-column: 1 / -1;">
                                <label>Driver's License</label>
                                <div class="input-wrapper"><input type="file" name="license_photo" accept=".jpg,.jpeg,.png,.pdf" required style="padding: 10px; cursor: pointer; padding-left: 16px;"></div>
                            </div>
                            <div class="input-group full-width" style="grid-column: 1 / -1;">
                                <label>NBI Clearance</label>
                                <div class="input-wrapper"><input type="file" name="nbi" accept=".jpg,.jpeg,.png,.pdf" required style="padding: 10px; cursor: pointer; padding-left: 16px;"></div>
                            </div>
                            <div class="input-group full-width" style="grid-column: 1 / -1;">
                                <label>Official Receipt (OR)</label>
                                <div class="input-wrapper"><input type="file" name="or" accept=".jpg,.jpeg,.png,.pdf" required style="padding: 10px; cursor: pointer; padding-left: 16px;"></div>
                            </div>
                            <div class="input-group full-width" style="grid-column: 1 / -1;">
                                <label>Certificate of Registration (CR)</label>
                                <div class="input-wrapper"><input type="file" name="cr" accept=".jpg,.jpeg,.png,.pdf" required style="padding: 10px; cursor: pointer; padding-left: 16px;"></div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" style="background: #10b981;">
                        <span>Join the Fleet</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
