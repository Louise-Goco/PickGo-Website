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
$rider_id = $riderData['Rider_Id'];

// 1. Calculate Total Balance (Delivered earnings - Processed payouts)
// Total Earnings from delivered orders
$stmt = $pdo->prepare("SELECT SUM(Rider_Earnings) FROM orders WHERE Rider_Id = ? AND Order_Status = 'delivered'");
$stmt->execute([$rider_id]);
$total_earned = $stmt->fetchColumn() ?: 0;

// Total Payouts (Approved or Processed)
$stmt = $pdo->prepare("SELECT SUM(Amount) FROM payouts WHERE User_Type = 'rider' AND User_Id = ? AND Payout_Status IN ('approved', 'processed')");
$stmt->execute([$rider_id]);
$total_paid = $stmt->fetchColumn() ?: 0;

$balance = $total_earned - $total_paid;

// 2. Fetch Earnings History (Trips)
$stmt = $pdo->prepare("
    SELECT o.Order_Id, o.Order_Total, o.Rider_Earnings, o.Order_Date, m.Merch_Name
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    WHERE o.Rider_Id = ? AND o.Order_Status = 'delivered'
    ORDER BY o.Order_Date DESC
");
$stmt->execute([$rider_id]);
$trips = $stmt->fetchAll();

// 3. Fetch Payout History
$stmt = $pdo->prepare("SELECT * FROM payouts WHERE User_Type = 'rider' AND User_Id = ? ORDER BY Request_Date DESC");
$stmt->execute([$rider_id]);
$payouts = $stmt->fetchAll();

// Handle Payout Request
$msg = '';
if (isset($_POST['request_payout'])) {
    if ($balance >= 100) { // Minimum payout 100
        $bank_name = $riderData['Rider_BankName'];
        $bank_acc = $riderData['Rider_BankAccNo'];
        $bank_name_acc = $riderData['Rider_BankAccName'];
        
        if ($bank_name && $bank_acc && $bank_name_acc) {
            $stmt = $pdo->prepare("INSERT INTO payouts (User_Type, User_Id, Amount, Bank_Name, Account_Number, Account_Name, Payout_Status) VALUES ('rider', ?, ?, ?, ?, ?, 'pending')");
            if ($stmt->execute([$rider_id, $balance, $bank_name, $bank_acc, $bank_name_acc])) {
                header("Location: earnings.php?success=1");
                exit;
            }
        } else {
            $msg = "Please complete bank info in profile first.";
        }
    } else {
        $msg = "Minimum payout balance is ₱100.00";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #10b981; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; margin: 0; color: #0f172a; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
        .back-link { text-decoration: none; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        
        .balance-card { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px; border-radius: 32px; box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.2); margin-bottom: 32px; position: relative; overflow: hidden; }
        .balance-label { font-size: 14px; opacity: 0.9; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .balance-value { font-size: 48px; font-weight: 800; margin: 12px 0 24px; }
        .btn-payout { background: white; color: var(--primary); border: none; padding: 14px 28px; border-radius: 14px; font-weight: 700; cursor: pointer; transition: transform 0.2s; }
        .btn-payout:hover { transform: scale(1.05); }

        .section { margin-bottom: 40px; }
        .section-title { font-size: 18px; font-weight: 800; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        .card { background: white; border-radius: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 16px 24px; background: #f1f5f9; font-size: 12px; color: #64748b; text-transform: uppercase; }
        .table td { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .status { padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-processed { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        .bonus-pill { background: #dcfce7; color: #10b981; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; margin-left: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Dashboard
            </a>
            <h1 style="font-size: 24px; font-weight: 800; margin: 0;">Earnings Dashboard</h1>
        </div>

        <?php if ($msg): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #fecaca; font-weight: 600;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="balance-card">
            <div class="balance-label">Withdrawable Balance</div>
            <div class="balance-value">₱<?php echo number_format($balance, 2); ?></div>
            <form method="POST">
                <button type="submit" name="request_payout" class="btn-payout">Withdraw Earnings</button>
            </form>
        </div>

        <div class="section">
            <div class="section-title">Trip History</div>
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Trip / Store</th>
                            <th>Order Total</th>
                            <th>Your Earnings</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700;">#<?php echo $trip['Order_Id']; ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($trip['Merch_Name']); ?></div>
                                </td>
                                <td>₱<?php echo number_format($trip['Order_Total'], 2); ?></td>
                                <td style="font-weight: 700; color: var(--primary);">
                                    ₱<?php echo number_format($trip['Rider_Earnings'], 2); ?>
                                    <span class="bonus-pill">+ ₱0.00 Bonus</span>
                                </td>
                                <td style="color: #64748b;"><?php echo date('M d, Y', strtotime($trip['Order_Date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($trips)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">No trips completed yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Payout Requests</div>
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Request Date</th>
                            <th>Amount</th>
                            <th>Bank Account</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payouts as $payout): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payout['Request_Date'])); ?></td>
                                <td style="font-weight: 700;">₱<?php echo number_format($payout['Amount'], 2); ?></td>
                                <td style="font-size: 12px;">
                                    <?php echo htmlspecialchars($payout['Bank_Name']); ?><br>
                                    <?php echo htmlspecialchars($payout['Account_Number']); ?>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $payout['Payout_Status']; ?>">
                                        <?php echo $payout['Payout_Status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payouts)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">No payout requests yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
