<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: ../login.php');
    exit;
}

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: dashboard.php');
    exit;
}

// Fetch rider data
$stmt = $pdo->prepare("SELECT * FROM riders WHERE Rider_Email = ?");
$stmt->execute([$_SESSION['user']]);
$riderData = $stmt->fetch();

// Fetch Order details
$stmt = $pdo->prepare("
    SELECT o.*, m.Merch_Name, m.Merch_Address, m.Merch_ContactNumber, u.first_name as Cust_Fname, u.last_name as Cust_Lname, u.phone_number as Cust_Phone
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    JOIN users u ON o.Customer_Id = u.id
    WHERE o.Order_Id = ? AND o.Rider_Id = ?
");
$stmt->execute([$orderId, $riderData['Rider_Id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

// Handle Status Updates
if (isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $proofPhoto = null;

    // Handle Proof Photo if delivered
    if ($newStatus === 'delivered' && isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['proof_photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_name = 'proof_' . $orderId . '_' . time() . '.' . $ext;
            $upload_dir = '../uploads/proofs/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($_FILES['proof_photo']['tmp_name'], $upload_dir . $new_name)) {
                $proofPhoto = 'uploads/proofs/' . $new_name;
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE orders SET Order_Status = ?, Order_ProofPhoto = COALESCE(?, Order_ProofPhoto) WHERE Order_Id = ?");
    if ($stmt->execute([$newStatus, $proofPhoto, $orderId])) {
        // If delivered, increment rider total deliveries and save earnings
        if ($newStatus === 'delivered') {
            // Fetch total to calculate commission
            $stmt = $pdo->prepare("SELECT Order_Total FROM orders WHERE Order_Id = ?");
            $stmt->execute([$orderId]);
            $orderTotal = $stmt->fetchColumn();
            $earnings = $orderTotal * 0.1; // 10% commission

            $stmt = $pdo->prepare("UPDATE orders SET Rider_Earnings = ? WHERE Order_Id = ?");
            $stmt->execute([$earnings, $orderId]);

            $stmt = $pdo->prepare("UPDATE riders SET Rider_TotalDeliveries = Rider_TotalDeliveries + 1 WHERE Rider_Id = ?");
            $stmt->execute([$riderData['Rider_Id']]);
        }
        header("Location: order_details.php?id=$orderId&success=1");
        exit;
    }
}

// Handle Cancellation
if (isset($_POST['cancel_delivery'])) {
    $stmt = $pdo->prepare("UPDATE orders SET Rider_Id = NULL, Order_Status = 'pending' WHERE Order_Id = ?");
    if ($stmt->execute([$orderId])) {
        header("Location: dashboard.php?msg=cancelled");
        exit;
    }
}

// Fetch items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE Order_Id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo $orderId; ?> - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        :root { --primary: #10b981; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .card { background: #fff; padding: 32px; border-radius: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .section-title { font-size: 14px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .info-label { color: #64748b; }
        .info-value { font-weight: 600; text-align: right; }
        .status-badge { padding: 6px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .status-on_the_way { background: #dcfce7; color: #166534; }
        .status-preparing { background: #e0f2fe; color: #0369a1; }
        .status-ready_for_pickup { background: #fef9c3; color: #854d0e; }
        .btn { width: 100%; padding: 16px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; font-size: 16px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: #059669; }
        .item-list { border-top: 1px solid #f1f5f9; margin-top: 16px; padding-top: 16px; }
        .item { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" style="display: inline-flex; align-items: center; color: #64748b; text-decoration: none; margin-bottom: 24px; font-weight: 500; gap: 8px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Dashboard
        </a>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h1 style="font-size: 24px; font-weight: 800;">Order #<?php echo $orderId; ?></h1>
                <span class="status-badge status-<?php echo $order['Order_Status']; ?>"><?php echo str_replace('_', ' ', $order['Order_Status']); ?></span>
            </div>

            <div class="section-title">Pickup Details</div>
            <div class="info-row">
                <span class="info-label">Merchant</span>
                <span class="info-value"><?php echo htmlspecialchars($order['Merch_Name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Address</span>
                <span class="info-value" style="max-width: 250px;"><?php echo htmlspecialchars($order['Merch_Address']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Contact</span>
                <span class="info-value"><?php echo htmlspecialchars($order['Merch_ContactNumber']); ?></span>
            </div>

            <div class="section-title" style="margin-top: 32px;">Delivery Details</div>
            <div class="info-row">
                <span class="info-label">Customer</span>
                <span class="info-value"><?php echo htmlspecialchars($order['Cust_Fname'] . ' ' . $order['Cust_Lname']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Address</span>
                <span class="info-value" style="max-width: 250px;"><?php echo htmlspecialchars($order['Delivery_Address']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?php echo htmlspecialchars($order['Cust_Phone']); ?></span>
            </div>

            <div class="item-list">
                <div class="section-title">Order Items</div>
                <?php foreach ($items as $item): ?>
                    <div class="item">
                        <span><?php echo $item['Quantity']; ?>x <?php echo htmlspecialchars($item['Food_Name']); ?></span>
                        <span style="font-weight: 600;">₱<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <div style="display: flex; justify-content: space-between; margin-top: 16px; padding-top: 16px; border-top: 2px solid #f8fafc;">
                    <span style="font-weight: 700; color: #0f172a;">Total Payment</span>
                    <span style="font-weight: 800; color: var(--primary); font-size: 18px;">₱<?php echo number_format($order['Order_Total'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Update Progress</div>
            <div style="display: flex; gap: 12px;">
                <a href="navigate.php?id=<?php echo $orderId; ?>" class="btn" style="background: #3b82f6; color: white; text-decoration: none; flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                    Navigate
                </a>
                    <?php if ($order['Order_Status'] === 'ready_for_pickup'): ?>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="status" value="on_the_way">
                            <button type="submit" name="update_status" class="btn" style="background: #f59e0b; color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                Confirm Pickup (Start Delivery)
                            </button>
                        </form>
                    <?php elseif ($order['Order_Status'] === 'on_the_way'): ?>
                        <form method="POST" enctype="multipart/form-data" style="flex: 1; display: flex; flex-direction: column; gap: 16px;">
                            <input type="hidden" name="status" value="delivered">
                            
                            <div style="background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 16px; padding: 20px; text-align: center;">
                                <label style="display: block; cursor: pointer;">
                                    <input type="file" name="proof_photo" accept="image/*" style="display: none;" onchange="previewProof(this)">
                                    <div id="proof-placeholder">
                                        <div style="font-size: 32px; margin-bottom: 8px;">📸</div>
                                        <p style="font-size: 13px; font-weight: 600; color: #64748b; margin: 0;">Add Photo Proof (Optional)</p>
                                        <p style="font-size: 11px; color: #94a3b8; margin-top: 4px;">JPG, PNG up to 5MB</p>
                                    </div>
                                    <img id="proof-preview" style="display: none; width: 100%; max-height: 200px; object-fit: contain; border-radius: 8px;">
                                </label>
                            </div>

                            <button type="submit" name="update_status" class="btn btn-primary" style="background: #10b981; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                Mark as Delivered
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if ($order['Order_Status'] === 'ready_for_pickup' || $order['Order_Status'] === 'on_the_way'): ?>
                    <form method="POST" style="margin-top: 16px;">
                        <button type="submit" name="cancel_delivery" class="btn" style="background: transparent; color: #ef4444; border: 1px solid #ef4444; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="return confirm('Are you sure you want to cancel this delivery? It will be reassigned to another rider.');">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            Cancel Delivery
                        </button>
                    </form>
                <?php endif; ?>
        </div>
    </div>

    <script>
        function previewProof(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('proof-placeholder').style.display = 'none';
                    const preview = document.getElementById('proof-preview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
