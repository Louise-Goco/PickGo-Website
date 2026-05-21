<?php
session_start();

if (isset($_SESSION['user'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: customer/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        require_once 'db.php';
        $authenticated = false;
        
        try {
            // 1. Check Users table (Admin/Customer)
            $stmt = $pdo->prepare("SELECT id, password, user_type, account_status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $authenticated = true;
                $user_type = $user['user_type'];
                $account_status = $user['account_status'];

                if ($account_status === 'suspended') {
                    $error = 'Your account has been suspended.';
                    $authenticated = false;
                }
            } 
            
            // 2. Check Sellers table using Store Email (Merch_Email)
            if (!$authenticated) {
                $stmt = $pdo->prepare("SELECT s.Sellr_Password as password, s.Sellr_Status as status, s.Sellr_Email as personal_email 
                                     FROM sellers s 
                                     JOIN merchants m ON s.Merch_Id = m.Merch_Id 
                                     WHERE m.Merch_Email = ?");
                $stmt->execute([$email]);
                $seller = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($seller && password_verify($password, $seller['password'])) {
                    $authenticated = true;
                    $user_type = 'seller';
                    $account_status = $seller['status'];
                    
                    if ($account_status === 'pending') {
                        header('Location: seller_register.php'); // Shows the pending screen
                        exit;
                    } elseif ($account_status === 'rejected') {
                        $error = 'Your seller application was rejected.';
                        $authenticated = false;
                    } elseif ($account_status === 'suspended') {
                        $error = 'Your seller account is suspended.';
                        $authenticated = false;
                    }
                }
            }

            // 3. Check Riders table if still not authenticated
            if (!$authenticated) {
                $stmt = $pdo->prepare("SELECT Rider_Password as password, Rider_Status as status FROM riders WHERE Rider_Email = ?");
                $stmt->execute([$email]);
                $rider = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($rider && password_verify($password, $rider['password'])) {
                    $authenticated = true;
                    $user_type = 'rider';
                    $account_status = $rider['status'];

                    if ($account_status === 'pending') {
                        header('Location: rider_register.php'); // Shows the pending screen
                        exit;
                    } elseif ($account_status === 'rejected') {
                        $error = 'Your rider application was rejected.';
                        $authenticated = false;
                    } elseif ($account_status === 'suspended') {
                        $error = 'Your rider account is suspended.';
                        $authenticated = false;
                    }
                }
            }

            if (!$authenticated && !$error) {
                $error = 'Invalid email or password.';
            }

        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }

        if ($authenticated) {
            $_SESSION['user'] = $email;
            $_SESSION['user_type'] = $user_type;
            
            if ($user_type === 'admin') {
                header('Location: admin/dashboard.php');
            } elseif ($user_type === 'seller') {
                header('Location: seller/dashboard.php');
            } elseif ($user_type === 'rider') {
                // Force offline status on login
                $stmt = $pdo->prepare("UPDATE riders SET Rider_Status = 'offline' WHERE Rider_Email = ?");
                $stmt->execute([$email]);
                header('Location: rider/dashboard.php');
            } else {
                // Load cart from database for customers
                $stmt = $pdo->prepare("SELECT item_id, quantity FROM cart WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $_SESSION['cart'] = [];
                foreach ($cart_items as $item) {
                    $_SESSION['cart'][] = [
                        'item_id' => $item['item_id'],
                        'quantity' => (int)$item['quantity']
                    ];
                }

                header('Location: customer/dashboard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <meta name="description" content="Securely log in to access your dashboard.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="background-elements">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div class="login-container">
        <div class="glass-panel">
            <a href="index.php" class="back-home-btn" title="Back to Homepage">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <div class="login-header">
                <h2>Welcome to PickGo</h2>
                <p>Sign in to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required autocomplete="email">
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                </div>

                <div class="form-actions">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <span>Sign In</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </form>
            
            <div class="login-footer">
                <p>Don't have an account? <a href="register.php">Create one</a></p>
        </div>
    </div>
</body>
</html>
