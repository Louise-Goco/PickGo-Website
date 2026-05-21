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

$seller_id = $sellerData['Seller_Id'];
$success = '';
$error = '';

// Calculate Balance
// 1. Total Earned (Delivered Orders)
$stmt = $pdo->prepare("SELECT SUM(Order_Total) FROM orders WHERE Seller_Id = ? AND Order_Status = 'delivered'");
$stmt->execute([$seller_id]);
$total_earned = $stmt->fetchColumn() ?: 0;

// 2. Total Paid Out/Pending (Payout Requests)
$stmt = $pdo->prepare("SELECT SUM(Amount) FROM payouts WHERE User_Type = 'seller' AND User_Id = ? AND Payout_Status != 'rejected'");
$stmt->execute([$seller_id]);
$total_requested = $stmt->fetchColumn() ?: 0;

$available_balance = $total_earned - $total_requested;

// Handle Payout Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_payout') {
    $amount = floatval($_POST['amount']);
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $account_name = trim($_POST['account_name']);

    if ($amount <= 0) {
        $error = "Invalid amount.";
    } elseif ($amount > $available_balance) {
        $error = "Insufficient balance.";
    } elseif (empty($bank_name) || empty($account_number) || empty($account_name)) {
        $error = "All bank details are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO payouts (User_Type, User_Id, Amount, Bank_Name, Account_Number, Account_Name, Payout_Status) VALUES ('seller', ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$seller_id, $amount, $bank_name, $account_number, $account_name]);
            $success = "Payout request submitted successfully!";
            // Recalculate balance
            $available_balance -= $amount;
        } catch (PDOException $e) {
            $error = "Request failed: " . $e->getMessage();
        }
    }
}

// Fetch Payout History
$stmt = $pdo->prepare("SELECT * FROM payouts WHERE User_Type = 'seller' AND User_Id = ? ORDER BY Request_Date DESC");
$stmt->execute([$seller_id]);
$payout_history = $stmt->fetchAll();

// Fetch Recent Transactions (Delivered Orders)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE Seller_Id = ? AND Order_Status = 'delivered' ORDER BY Order_Date DESC LIMIT 10");
$stmt->execute([$seller_id]);
$recent_transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Earnings - <?php echo htmlspecialchars($sellerData['Merch_Name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .earnings-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px; }
        
        .card { background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 32px; }
        .card h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 24px; }
        
        .balance-card { background: #0f172a; color: #fff; border: none; }
        .balance-card h3 { color: #94a3b8; }
        .balance-value { font-size: 40px; font-weight: 800; color: #f97316; margin-bottom: 8px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; box-sizing: border-box; }
        
        .btn-request { width: 100%; padding: 14px; background: #f97316; color: #fff; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; transition: transform 0.2s; }
        .btn-request:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.3); }
        
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { text-align: left; padding: 12px; font-size: 12px; color: #94a3b8; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; }
        .history-table td { padding: 16px 12px; font-size: 14px; border-bottom: 1px solid #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-approved { background: #e0f2fe; color: #0369a1; }
        .badge-processed { background: #f0fdf4; color: #166534; }
        .badge-rejected { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php SellerNavigation::render('payouts'); ?>

        <main class="main-content">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Earnings & Payouts</h1>
                <p style="color: #64748b;">Manage your wallet, view transactions, and request bank transfers.</p>
            </div>

            <?php if ($success): ?>
                <div style="background: #f0fdf4; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #dcfce7;"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #fee2e2;"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="earnings-grid">
                <div>
                    <div class="card balance-card">
                        <h3>Available Balance</h3>
                        <div class="balance-value">₱<?php echo number_format($available_balance, 2); ?></div>
                        <p style="font-size: 14px; color: #94a3b8;">Total Earned: ₱<?php echo number_format($total_earned, 2); ?></p>
                    </div>

                    <div class="card">
                        <h3>Request Payout</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="request_payout">
                            <div class="form-group">
                                <label>Amount to Withdraw (₱)</label>
                                <input type="number" name="amount" step="0.01" max="<?php echo $available_balance; ?>" required placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" name="bank_name" placeholder="e.g. BDO, BPI, GCash" required>
                            </div>
                            <div class="form-group">
                                <label>Account Name</label>
                                <input type="text" name="account_name" required>
                            </div>
                            <div class="form-group">
                                <label>Account Number</label>
                                <input type="text" name="account_number" required>
                            </div>
                            <button type="submit" class="btn-request">Withdraw Funds</button>
                        </form>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <h3>Payout History</h3>
                        <?php if (empty($payout_history)): ?>
                            <p style="color: #94a3b8; text-align: center; padding: 20px;">No payout requests yet.</p>
                        <?php else: ?>
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Bank</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payout_history as $p): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($p['Request_Date'])); ?></td>
                                            <td style="font-weight: 700;">₱<?php echo number_format($p['Amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($p['Bank_Name']); ?></td>
                                            <td><span class="badge badge-<?php echo $p['Payout_Status']; ?>"><?php echo $p['Payout_Status']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h3>Recent Earnings</h3>
                        <?php if (empty($recent_transactions)): ?>
                            <p style="color: #94a3b8; text-align: center; padding: 20px;">No earnings recorded yet.</p>
                        <?php else: ?>
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_transactions as $t): ?>
                                        <tr>
                                            <td>#<?php echo $t['Order_Id']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($t['Order_Date'])); ?></td>
                                            <td style="color: #10b981; font-weight: 700;">+₱<?php echo number_format($t['Order_Total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
