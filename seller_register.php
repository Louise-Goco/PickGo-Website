<?php
session_start();
require_once 'db.php';

$loggedInUser = null;
if (isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['user']]);
    $loggedInUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if they already have a seller application
    $stmt = $pdo->prepare("SELECT Sellr_Status FROM sellers WHERE Sellr_Email = ?");
    $stmt->execute([$_SESSION['user']]);
    $existingSeller = $stmt->fetch();

    if ($existingSeller) {
        if ($existingSeller['Sellr_Status'] === 'pending') {
            $pendingApplication = true;
        } elseif ($existingSeller['Sellr_Status'] === 'active') {
            header('Location: seller/dashboard.php');
            exit;
        }
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Seller Info
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Business Info
    $merch_name = trim($_POST['merch_name'] ?? '');
    $merch_type = trim($_POST['merch_type'] ?? '');
    $unit_floor = trim($_POST['unit_floor'] ?? '');
    $building = trim($_POST['building'] ?? '');
    $street_no = trim($_POST['street_no'] ?? '');
    $street_name = trim($_POST['street_name'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $merch_phone = trim($_POST['merch_phone'] ?? '');
    $merch_email = trim($_POST['merch_email'] ?? '');

    // Validation
    if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($password) || empty($merch_name) || empty($city)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();

            // Check if email already exists in users, sellers, or riders
            $stmt = $pdo->prepare("SELECT Seller_Id FROM sellers WHERE Sellr_Email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = 'This email is already registered as a seller.';
                $pdo->rollBack();
            } else {
                // Handle File Uploads
                $upload_dir = 'uploads/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $gov_id_path = null;
                $bir_cert_path = null;

                if (isset($_FILES['gov_id']) && $_FILES['gov_id']['error'] == 0) {
                    $ext = pathinfo($_FILES['gov_id']['name'], PATHINFO_EXTENSION);
                    $gov_id_path = $upload_dir . 'gov_id_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['gov_id']['tmp_name'], $gov_id_path);
                }
                if (isset($_FILES['bir_cert']) && $_FILES['bir_cert']['error'] == 0) {
                    $ext = pathinfo($_FILES['bir_cert']['name'], PATHINFO_EXTENSION);
                    $bir_cert_path = $upload_dir . 'bir_cert_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['bir_cert']['tmp_name'], $bir_cert_path);
                }

                // 1. Create Merchant Entry (Pending)
                $address_parts = array_filter([$unit_floor, $building, $street_no, $street_name, $barangay, $city, $province, $zip]);
                $full_address = implode(', ', $address_parts);
                if ($landmark) $full_address .= " (Landmark: $landmark)";

                $stmt = $pdo->prepare("INSERT INTO merchants (
                    Merch_Name, Merch_Type, Merch_Address, Merch_UnitFloor, Merch_Building, 
                    Merch_StreetNo, Merch_StreetName, Merch_Barangay, Merch_City, 
                    Merch_Province, Merch_ZIP, Merch_Landmark, 
                    Merch_ContactNumber, Merch_Email, Merch_GovID, Merch_BIRCert, Merch_Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                
                $stmt->execute([
                    $merch_name, $merch_type, $full_address, $unit_floor, $building,
                    $street_no, $street_name, $barangay, $city,
                    $province, $zip, $landmark,
                    !empty($merch_phone) ? $merch_phone : $phone, 
                    !empty($merch_email) ? $merch_email : $email,
                    $gov_id_path, $bir_cert_path
                ]);
                
                $merch_id = $pdo->lastInsertId();

                // 2. Create Seller Entry (Pending)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO sellers (
                    Sellr_Fname, Sellr_Lname, Sellr_Email, Sellr_Password, 
                    Sellr_PhoneNumber, Merch_Id, Sellr_Status
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                
                $stmt->execute([
                    $fname, $lname, $email, $hashed_password, 
                    $phone, $merch_id
                ]);

                $pdo->commit();
                $success = 'Application submitted successfully! Please wait for admin approval.';
                // Clear post data
                $_POST = array();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Seller - Pickaroo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
    <style>
        .login-container.register-seller {
            max-width: 800px;
            width: 95%;
        }
        .form-section {
            margin-bottom: 32px;
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
        .form-section h3 svg {
            color: #f97316;
        }
        .input-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .input-group.full-width {
            grid-column: span 2;
        }
        @media (max-width: 640px) {
            .input-grid {
                grid-template-columns: 1fr;
            }
            .input-group.full-width {
                grid-column: span 1;
            }
        }
        .success-message {
            background: #f0fdf4;
            color: #166534;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #dcfce7;
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
</head>
<body>
    <div class="background-elements">
        <div class="blob blob-1"></div>
        <div class="blob blob-2" style="background: #f97316;"></div>
    </div>

    <div class="login-container register-seller">
        <div class="glass-panel">
            <a href="index.php" class="back-home-btn" title="Back to Homepage">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <div class="login-header">
                <h2>Register Your Store</h2>
                <p>Fill out the form below to apply as a merchant partner.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($pendingApplication) && $pendingApplication): ?>
                <div style="text-align: center; padding: 40px 0;">
                    <div style="width: 80px; height: 80px; background: #fffbeb; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: #f59e0b;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 12px;">Application Pending</h3>
                    <p style="color: #64748b; margin-bottom: 32px;">We've received your application for <strong><?php echo htmlspecialchars($merch_name ?? 'your store'); ?></strong>. Our admin team is currently reviewing your business details.</p>
                    <a href="customer/dashboard.php" class="submit-btn" style="text-decoration: none; display: inline-flex; justify-content: center; align-items: center;">Go to Customer Dashboard</a>
                </div>
            <?php elseif ($success): ?>
                <div class="success-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="submit-btn" style="text-decoration: none; display: inline-flex; justify-content: center; align-items: center;">Return to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="login-form" enctype="multipart/form-data">
                    <!-- Seller Personal Info -->
                    <div class="form-section">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Owner Information
                        </h3>
                        <div class="input-grid">
                            <div class="input-group">
                                <label for="fname">First Name</label>
                                <div class="input-wrapper">
                                    <input type="text" id="fname" name="fname" placeholder="Enter first name" required value="<?php echo htmlspecialchars($_POST['fname'] ?? ($loggedInUser['first_name'] ?? '')); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="lname">Last Name</label>
                                <div class="input-wrapper">
                                    <input type="text" id="lname" name="lname" placeholder="Enter last name" required value="<?php echo htmlspecialchars($_POST['lname'] ?? ($loggedInUser['last_name'] ?? '')); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="email">Email Address</label>
                                <div class="input-wrapper">
                                    <input type="email" id="email" name="email" placeholder="owner@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ($loggedInUser['email'] ?? '')); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="phone">Phone Number</label>
                                <div class="input-wrapper">
                                    <input type="tel" id="phone" name="phone" placeholder="09XXXXXXXXX" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ($loggedInUser['phone_number'] ?? '')); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="password">Password</label>
                                <div class="input-wrapper">
                                    <input type="password" id="password" name="password" placeholder="Create password" required>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="confirm_password">Confirm Password</label>
                                <div class="input-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Store Info -->
                    <div class="form-section">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            Store Information
                        </h3>
                        <div class="input-grid">
                            <div class="input-group">
                                <label for="merch_name">Store Name</label>
                                <div class="input-wrapper">
                                    <input type="text" id="merch_name" name="merch_name" placeholder="e.g. Burger King" required value="<?php echo htmlspecialchars($_POST['merch_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="merch_type">Store Type</label>
                                <div class="input-wrapper">
                                    <select id="merch_type" name="merch_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Restaurant">Restaurant</option>
                                        <option value="Fast Food">Fast Food</option>
                                        <option value="Cafe">Cafe</option>
                                        <option value="Grocery">Grocery</option>
                                        <option value="Pharmacy">Pharmacy</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="merch_phone">Store Phone (Optional)</label>
                                <div class="input-wrapper">
                                    <input type="text" id="merch_phone" name="merch_phone" placeholder="Store contact number" value="<?php echo htmlspecialchars($_POST['merch_phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="merch_email">Store Email (Optional)</label>
                                <div class="input-wrapper">
                                    <input type="email" id="merch_email" name="merch_email" placeholder="store@example.com" value="<?php echo htmlspecialchars($_POST['merch_email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Store Location -->
                    <div class="form-section">
                        <h3 style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <span style="display: flex; align-items: center; gap: 10px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                Store Location
                            </span>
                            <button type="button" onclick="clearStoreLocation()" style="background: none; border: none; color: #f97316; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='rgba(249,115,22,0.08)'" onmouseout="this.style.background='none'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                Clear Location
                            </button>
                        </h3>
                        <div class="input-grid">
                            <div class="input-group">
                                <label for="building">Building / Mall (Optional)</label>
                                <div class="input-wrapper">
                                    <select id="building" name="building" onchange="autoFillFromBuilding(this.value)">
                                        <option value="">-- Optional: Select Building/Mall --</option>
                                        <option value="SM Seaside City Cebu" <?php echo (($_POST['building'] ?? '') === 'SM Seaside City Cebu') ? 'selected' : ''; ?>>SM Seaside City Cebu</option>
                                        <option value="SM City Cebu" <?php echo (($_POST['building'] ?? '') === 'SM City Cebu') ? 'selected' : ''; ?>>SM City Cebu</option>
                                        <option value="Ayala Center Cebu" <?php echo (($_POST['building'] ?? '') === 'Ayala Center Cebu') ? 'selected' : ''; ?>>Ayala Center Cebu</option>
                                        <option value="Ayala Malls Central Bloc" <?php echo (($_POST['building'] ?? '') === 'Ayala Malls Central Bloc') ? 'selected' : ''; ?>>Ayala Malls Central Bloc</option>
                                        <option value="Robinsons Galleria Cebu" <?php echo (($_POST['building'] ?? '') === 'Robinsons Galleria Cebu') ? 'selected' : ''; ?>>Robinsons Galleria Cebu</option>
                                        <option value="IT Park - The Walk" <?php echo (($_POST['building'] ?? '') === 'IT Park - The Walk') ? 'selected' : ''; ?>>IT Park - The Walk</option>
                                        <option value="J Centre Mall" <?php echo (($_POST['building'] ?? '') === 'J Centre Mall') ? 'selected' : ''; ?>>J Centre Mall</option>
                                        <option value="Oakridge Business Park" <?php echo (($_POST['building'] ?? '') === 'Oakridge Business Park') ? 'selected' : ''; ?>>Oakridge Business Park</option>
                                        <option value="Gaisano Grand Mall Mactan" <?php echo (($_POST['building'] ?? '') === 'Gaisano Grand Mall Mactan') ? 'selected' : ''; ?>>Gaisano Grand Mall Mactan</option>
                                        <option value="Mactan Marina Mall" <?php echo (($_POST['building'] ?? '') === 'Mactan Marina Mall') ? 'selected' : ''; ?>>Mactan Marina Mall</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="unit_floor">Unit / Floor</label>
                                <div class="input-wrapper">
                                    <select id="unit_floor" name="unit_floor">
                                        <option value="">-- Select Unit/Floor --</option>
                                        <option value="Ground Floor" <?php echo (($_POST['unit_floor'] ?? '') === 'Ground Floor') ? 'selected' : ''; ?>>Ground Floor</option>
                                        <option value="Upper Ground Floor" <?php echo (($_POST['unit_floor'] ?? '') === 'Upper Ground Floor') ? 'selected' : ''; ?>>Upper Ground Floor</option>
                                        <option value="Lower Ground Floor" <?php echo (($_POST['unit_floor'] ?? '') === 'Lower Ground Floor') ? 'selected' : ''; ?>>Lower Ground Floor</option>
                                        <option value="Level 1" <?php echo (($_POST['unit_floor'] ?? '') === 'Level 1') ? 'selected' : ''; ?>>Level 1</option>
                                        <option value="Level 2" <?php echo (($_POST['unit_floor'] ?? '') === 'Level 2') ? 'selected' : ''; ?>>Level 2</option>
                                        <option value="Level 3" <?php echo (($_POST['unit_floor'] ?? '') === 'Level 3') ? 'selected' : ''; ?>>Level 3</option>
                                        <option value="2nd Floor" <?php echo (($_POST['unit_floor'] ?? '') === '2nd Floor') ? 'selected' : ''; ?>>2nd Floor</option>
                                        <option value="3rd Floor" <?php echo (($_POST['unit_floor'] ?? '') === '3rd Floor') ? 'selected' : ''; ?>>3rd Floor</option>
                                        <option value="4th Floor" <?php echo (($_POST['unit_floor'] ?? '') === '4th Floor') ? 'selected' : ''; ?>>4th Floor</option>
                                        <option value="5th Floor" <?php echo (($_POST['unit_floor'] ?? '') === '5th Floor') ? 'selected' : ''; ?>>5th Floor</option>
                                        <option value="Basement" <?php echo (($_POST['unit_floor'] ?? '') === 'Basement') ? 'selected' : ''; ?>>Basement</option>
                                        <option value="Unit 101" <?php echo (($_POST['unit_floor'] ?? '') === 'Unit 101') ? 'selected' : ''; ?>>Unit 101</option>
                                        <option value="Unit 102" <?php echo (($_POST['unit_floor'] ?? '') === 'Unit 102') ? 'selected' : ''; ?>>Unit 102</option>
                                        <option value="Unit 201" <?php echo (($_POST['unit_floor'] ?? '') === 'Unit 201') ? 'selected' : ''; ?>>Unit 201</option>
                                        <option value="Unit 202" <?php echo (($_POST['unit_floor'] ?? '') === 'Unit 202') ? 'selected' : ''; ?>>Unit 202</option>
                                        <option value="Suite 100" <?php echo (($_POST['unit_floor'] ?? '') === 'Suite 100') ? 'selected' : ''; ?>>Suite 100</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="street_no">Street No.</label>
                                <div class="input-wrapper">
                                    <select id="street_no" name="street_no">
                                        <option value="">-- Select Street No. --</option>
                                        <option value="" <?php echo (($_POST['street_no'] ?? '') === '') ? 'selected' : ''; ?>>None</option>
                                        <option value="1" <?php echo (($_POST['street_no'] ?? '') === '1') ? 'selected' : ''; ?>>1</option>
                                        <option value="2" <?php echo (($_POST['street_no'] ?? '') === '2') ? 'selected' : ''; ?>>2</option>
                                        <option value="3" <?php echo (($_POST['street_no'] ?? '') === '3') ? 'selected' : ''; ?>>3</option>
                                        <option value="5" <?php echo (($_POST['street_no'] ?? '') === '5') ? 'selected' : ''; ?>>5</option>
                                        <option value="8" <?php echo (($_POST['street_no'] ?? '') === '8') ? 'selected' : ''; ?>>8</option>
                                        <option value="10" <?php echo (($_POST['street_no'] ?? '') === '10') ? 'selected' : ''; ?>>10</option>
                                        <option value="12" <?php echo (($_POST['street_no'] ?? '') === '12') ? 'selected' : ''; ?>>12</option>
                                        <option value="24" <?php echo (($_POST['street_no'] ?? '') === '24') ? 'selected' : ''; ?>>24</option>
                                        <option value="45" <?php echo (($_POST['street_no'] ?? '') === '45') ? 'selected' : ''; ?>>45</option>
                                        <option value="56" <?php echo (($_POST['street_no'] ?? '') === '56') ? 'selected' : ''; ?>>56</option>
                                        <option value="88" <?php echo (($_POST['street_no'] ?? '') === '88') ? 'selected' : ''; ?>>88</option>
                                        <option value="99" <?php echo (($_POST['street_no'] ?? '') === '99') ? 'selected' : ''; ?>>99</option>
                                        <option value="100" <?php echo (($_POST['street_no'] ?? '') === '100') ? 'selected' : ''; ?>>100</option>
                                        <option value="123" <?php echo (($_POST['street_no'] ?? '') === '123') ? 'selected' : ''; ?>>123</option>
                                        <option value="165" <?php echo (($_POST['street_no'] ?? '') === '165') ? 'selected' : ''; ?>>165</option>
                                        <option value="880" <?php echo (($_POST['street_no'] ?? '') === '880') ? 'selected' : ''; ?>>880</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="street_name">Street Name</label>
                                <div class="input-wrapper">
                                    <select id="street_name" name="street_name">
                                        <option value="">-- Select Street Name --</option>
                                        <option value="South Road Properties" <?php echo (($_POST['street_name'] ?? '') === 'South Road Properties') ? 'selected' : ''; ?>>South Road Properties</option>
                                        <option value="Juan Luna Avenue" <?php echo (($_POST['street_name'] ?? '') === 'Juan Luna Avenue') ? 'selected' : ''; ?>>Juan Luna Avenue</option>
                                        <option value="Cardinal Rosales Avenue" <?php echo (($_POST['street_name'] ?? '') === 'Cardinal Rosales Avenue') ? 'selected' : ''; ?>>Cardinal Rosales Avenue</option>
                                        <option value="V. Padriga Street" <?php echo (($_POST['street_name'] ?? '') === 'V. Padriga Street') ? 'selected' : ''; ?>>V. Padriga Street</option>
                                        <option value="General Maxilom Avenue" <?php echo (($_POST['street_name'] ?? '') === 'General Maxilom Avenue') ? 'selected' : ''; ?>>General Maxilom Avenue</option>
                                        <option value="Abad Santos Street" <?php echo (($_POST['street_name'] ?? '') === 'Abad Santos Street') ? 'selected' : ''; ?>>Abad Santos Street</option>
                                        <option value="A.S. Fortuna Street" <?php echo (($_POST['street_name'] ?? '') === 'A.S. Fortuna Street') ? 'selected' : ''; ?>>A.S. Fortuna Street</option>
                                        <option value="Basak-Marigondon Road" <?php echo (($_POST['street_name'] ?? '') === 'Basak-Marigondon Road') ? 'selected' : ''; ?>>Basak-Marigondon Road</option>
                                        <option value="M.L. Quezon National Highway" <?php echo (($_POST['street_name'] ?? '') === 'M.L. Quezon National Highway') ? 'selected' : ''; ?>>M.L. Quezon National Highway</option>
                                        <option value="Salinas Drive" <?php echo (($_POST['street_name'] ?? '') === 'Salinas Drive') ? 'selected' : ''; ?>>Salinas Drive</option>
                                        <option value="Gorordo Avenue" <?php echo (($_POST['street_name'] ?? '') === 'Gorordo Avenue') ? 'selected' : ''; ?>>Gorordo Avenue</option>
                                        <option value="Escario Street" <?php echo (($_POST['street_name'] ?? '') === 'Escario Street') ? 'selected' : ''; ?>>Escario Street</option>
                                        <option value="Jones Avenue" <?php echo (($_POST['street_name'] ?? '') === 'Jones Avenue') ? 'selected' : ''; ?>>Jones Avenue</option>
                                        <option value="Banilad Road" <?php echo (($_POST['street_name'] ?? '') === 'Banilad Road') ? 'selected' : ''; ?>>Banilad Road</option>
                                        <option value="Hernan Cortes Street" <?php echo (($_POST['street_name'] ?? '') === 'Hernan Cortes Street') ? 'selected' : ''; ?>>Hernan Cortes Street</option>
                                        <option value="Mactan Airport Road" <?php echo (($_POST['street_name'] ?? '') === 'Mactan Airport Road') ? 'selected' : ''; ?>>Mactan Airport Road</option>
                                        <option value="Ouano Avenue" <?php echo (($_POST['street_name'] ?? '') === 'Ouano Avenue') ? 'selected' : ''; ?>>Ouano Avenue</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="city">City / Municipality</label>
                                <div class="input-wrapper">
                                    <select id="city" name="city" required onchange="onCityChange(this.value)">
                                        <option value="">-- Select City/Municipality --</option>
                                        <option value="Cebu City" <?php echo (($_POST['city'] ?? '') === 'Cebu City') ? 'selected' : ''; ?>>Cebu City</option>
                                        <option value="Mandaue City" <?php echo (($_POST['city'] ?? '') === 'Mandaue City') ? 'selected' : ''; ?>>Mandaue City</option>
                                        <option value="Lapu-Lapu City" <?php echo (($_POST['city'] ?? '') === 'Lapu-Lapu City') ? 'selected' : ''; ?>>Lapu-Lapu City</option>
                                        <option value="Talisay City" <?php echo (($_POST['city'] ?? '') === 'Talisay City') ? 'selected' : ''; ?>>Talisay City</option>
                                        <option value="Consolacion" <?php echo (($_POST['city'] ?? '') === 'Consolacion') ? 'selected' : ''; ?>>Consolacion</option>
                                        <option value="Liloan" <?php echo (($_POST['city'] ?? '') === 'Liloan') ? 'selected' : ''; ?>>Liloan</option>
                                        <option value="Cordova" <?php echo (($_POST['city'] ?? '') === 'Cordova') ? 'selected' : ''; ?>>Cordova</option>
                                        <option value="Minglanilla" <?php echo (($_POST['city'] ?? '') === 'Minglanilla') ? 'selected' : ''; ?>>Minglanilla</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="barangay">Barangay</label>
                                <div class="input-wrapper">
                                    <select id="barangay" name="barangay" required>
                                        <option value="">-- Select Barangay --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="province">Province</label>
                                <div class="input-wrapper">
                                    <select id="province" name="province" required>
                                        <option value="Cebu" selected>Cebu</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="zip">ZIP Code</label>
                                <div class="input-wrapper">
                                    <select id="zip" name="zip" required>
                                        <option value="">-- Select ZIP Code --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="input-group full-width">
                                <label for="landmark">Landmark (Optional)</label>
                                <div class="input-wrapper">
                                    <input type="text" id="landmark" name="landmark" placeholder="e.g. Near City Hall" value="<?php echo htmlspecialchars($_POST['landmark'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Business Documents -->
                    <div class="form-section">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            Business Documents
                        </h3>
                        <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">Please upload clear images of the following required documents in JPEG, PNG, or PDF format.</p>
                        <div class="input-grid">
                            <div class="input-group full-width">
                                <label for="gov_id">1. Picture of Valid Government ID</label>
                                <div class="input-wrapper">
                                    <input type="file" id="gov_id" name="gov_id" accept=".jpg,.jpeg,.png,.pdf" required style="padding: 10px; cursor: pointer; padding-left: 16px;">
                                </div>
                            </div>
                            <div class="input-group full-width">
                                <label for="bir_cert">2. BIR Certificate of Registration</label>
                                <div class="input-wrapper">
                                    <input type="file" id="bir_cert" name="bir_cert" accept=".jpg,.jpeg,.png,.pdf" required style="padding: 10px; cursor: pointer; padding-left: 16px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <span>Submit Application</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="login-footer">
                <p>Already have a seller account? <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>

    <script>
    const locationData = {
        cities: {
            "Cebu City": {
                zip: "6000",
                barangays: ["Mambaling", "Mabolo", "Apas", "Tejero", "Lahug", "Kasambagan", "Guadalupe", "Talamban", "Banilad", "Capitol Site", "Kamputhaw"]
            },
            "Mandaue City": {
                zip: "6014",
                barangays: ["Bakilid", "Banilad", "Cabancalan", "Casuntingan", "Centro", "Guizo", "Subangdaku", "Tipolo"]
            },
            "Lapu-Lapu City": {
                zip: "6015",
                barangays: ["Basak", "Gun-ob", "Maribago", "Mactan", "Pajo", "Pusok"]
            },
            "Talisay City": {
                zip: "6045",
                barangays: ["Bulacao", "Cansojong", "Dumlog", "Jaclupan", "Lawaan", "Linao", "Poblacion", "Tabunok"]
            },
            "Consolacion": {
                zip: "6001",
                barangays: ["Casili", "Danglag", "Garing", "Nangka", "Poblacion Occidental", "Poblacion Oriental", "Tayud", "Tugbongan"]
            },
            "Liloan": {
                zip: "6002",
                barangays: ["Catarman", "Jubay", "Poblacion", "San Vicente", "Santa Cruz", "Yati"]
            },
            "Cordova": {
                zip: "6017",
                barangays: ["Catarman", "Day-as", "Gabi", "Poblacion", "San Miguel"]
            },
            "Minglanilla": {
                zip: "6046",
                barangays: ["Calajo-an", "Lipaata", "Poblacion Ward I", "Poblacion Ward II", "Tungkop", "Tungha-an"]
            }
        },
        buildings: {
            "SM Seaside City Cebu": {
                unit_floor: "Upper Ground Floor",
                street_no: "",
                street_name: "South Road Properties",
                barangay: "Mambaling",
                city: "Cebu City",
                province: "Cebu",
                zip: "6000"
            },
            "SM City Cebu": {
                unit_floor: "Lower Ground Floor",
                street_no: "",
                street_name: "Juan Luna Avenue",
                barangay: "Mabolo",
                city: "Cebu City",
                province: "Cebu",
                zip: "6000"
            },
            "Ayala Center Cebu": {
                unit_floor: "Level 1",
                street_no: "",
                street_name: "Cardinal Rosales Avenue",
                barangay: "Mabolo",
                city: "Cebu City",
                province: "Cebu",
                zip: "6000"
            },
            "Ayala Malls Central Bloc": {
                unit_floor: "Ground Floor",
                street_no: "",
                street_name: "V. Padriga Street",
                barangay: "Apas",
                city: "Cebu City",
                province: "Cebu",
                zip: "6000"
            },
            "Robinsons Galleria Cebu": {
                unit_floor: "Ground Level",
                street_no: "",
                street_name: "General Maxilom Avenue",
                barangay: "Tejero",
                city: "Cebu City",
                province: "Cebu",
                zip: "6000"
            },
            "IT Park - The Walk": {
                unit_floor: "Ground Floor",
                street_no: "",
                street_name: "Abad Santos Street",
                barangay: "Apas",
                city: "Cebu City",
                province: "Cebu",
                zip: "6000"
            },
            "J Centre Mall": {
                unit_floor: "Ground Floor",
                street_no: "165",
                street_name: "A.S. Fortuna Street",
                barangay: "Bakilid",
                city: "Mandaue City",
                province: "Cebu",
                zip: "6014"
            },
            "Oakridge Business Park": {
                unit_floor: "Block 88",
                street_no: "880",
                street_name: "A.S. Fortuna Street",
                barangay: "Banilad",
                city: "Mandaue City",
                province: "Cebu",
                zip: "6014"
            },
            "Gaisano Grand Mall Mactan": {
                unit_floor: "Ground Floor",
                street_no: "",
                street_name: "Basak-Marigondon Road",
                barangay: "Basak",
                city: "Lapu-Lapu City",
                province: "Cebu",
                zip: "6015"
            },
            "Mactan Marina Mall": {
                unit_floor: "Ground Floor",
                street_no: "",
                street_name: "M.L. Quezon National Highway",
                barangay: "Pusok",
                city: "Lapu-Lapu City",
                province: "Cebu",
                zip: "6015"
            }
        }
    };

    function onCityChange(cityVal, selectedBarangay = "", selectedZip = "") {
        const barangaySelect = document.getElementById('barangay');
        const zipSelect = document.getElementById('zip');
        
        // Clear previous options
        barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
        zipSelect.innerHTML = '<option value="">-- Select ZIP Code --</option>';
        
        if (!cityVal || !locationData.cities[cityVal]) return;
        
        const cityDetails = locationData.cities[cityVal];
        
        // Populate Barangays
        cityDetails.barangays.forEach(bg => {
            const opt = document.createElement('option');
            opt.value = bg;
            opt.textContent = bg;
            if (bg === selectedBarangay) opt.selected = true;
            barangaySelect.appendChild(opt);
        });
        
        // Populate and select ZIP
        const zipOpt = document.createElement('option');
        zipOpt.value = cityDetails.zip;
        zipOpt.textContent = cityDetails.zip;
        zipOpt.selected = true;
        zipSelect.appendChild(zipOpt);
    }

    function autoFillFromBuilding(buildingVal) {
        if (!buildingVal || !locationData.buildings[buildingVal]) return;
        
        const details = locationData.buildings[buildingVal];
        
        // Set text/select fields
        document.getElementById('unit_floor').value = details.unit_floor;
        document.getElementById('street_no').value = details.street_no;
        document.getElementById('street_name').value = details.street_name;
        document.getElementById('city').value = details.city;
        document.getElementById('province').value = details.province;
        
        // Trigger city change to populate barangay and zip correctly, then select them
        onCityChange(details.city, details.barangay, details.zip);
    }

    function clearStoreLocation() {
        document.getElementById('building').value = "";
        document.getElementById('unit_floor').value = "";
        document.getElementById('street_no').value = "";
        document.getElementById('street_name').value = "";
        document.getElementById('city').value = "";
        document.getElementById('province').value = "Cebu";
        
        // Reset barangay and zip dropdowns
        document.getElementById('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
        document.getElementById('zip').innerHTML = '<option value="">-- Select ZIP Code --</option>';
    }

    // Initial trigger if values are already posted or saved
    window.addEventListener('DOMContentLoaded', () => {
        const initialCity = document.getElementById('city').value;
        const initialBuilding = document.getElementById('building').value;
        
        if (initialBuilding) {
            // If building was selected, populate
            autoFillFromBuilding(initialBuilding);
        } else if (initialCity) {
            // If city was selected, populate matching barangays and zip
            const postedBarangay = "<?php echo htmlspecialchars($_POST['barangay'] ?? ''); ?>";
            const postedZip = "<?php echo htmlspecialchars($_POST['zip'] ?? ''); ?>";
            onCityChange(initialCity, postedBarangay, postedZip);
        }
    });
    </script>
</body>
</html>
