<?php
require_once '../config.php';
require_once 'Navigation.php';

// Check if user is an admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle Payout Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payout_id = $_POST['payout_id'];
    $action = $_POST['action'];
    $new_status = '';

    if ($action === 'approve') $new_status = 'approved';
    elseif ($action === 'reject') $new_status = 'rejected';
    elseif ($action === 'process') $new_status = 'processed';

    if ($new_status) {
        $stmt = $pdo->prepare("UPDATE payouts SET Payout_Status = ?, Processed_Date = IF(? = 'processed', CURRENT_TIMESTAMP, Processed_Date) WHERE Payout_Id = ?");
        $stmt->execute([$new_status, $new_status, $payout_id]);
    }
}

// Fetch Payouts with names
// Using UNION for cleaner results from different tables
$query = "
    (SELECT p.*, s.Sellr_Fname as fname, s.Sellr_Lname as lname, 'Seller' as role_label 
     FROM payouts p 
     JOIN sellers s ON p.User_Id = s.Seller_Id 
     WHERE p.User_Type = 'seller')
    UNION
    (SELECT p.*, r.Rider_Fname as fname, r.Rider_Lname as lname, 'Rider' as role_label 
     FROM payouts p 
     JOIN riders r ON p.User_Id = r.Rider_Id 
     WHERE p.User_Type = 'rider')
    ORDER BY Request_Date DESC";

$stmt = $pdo->query($query);
$payouts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout Management - PickGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 1400px; margin: 0 auto; padding: 40px 20px; }


        .header-section { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
        .header-section h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        
        .payout-layout { display: grid; grid-template-columns: 1fr 300px; gap: 32px; }

        .table-container { background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 24px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .role-pill { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 4px; display: inline-block; }
        .role-seller { background: #fff7ed; color: #c2410c; }
        .role-rider { background: #f0f9ff; color: #0369a1; }

        .amount { font-weight: 800; color: #0f172a; font-size: 16px; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-approved { background: #dbeafe; color: #1e40af; }
        .status-processed { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }

        .bank-info { font-size: 13px; line-height: 1.4; color: #64748b; }
        .bank-info b { color: #334155; }

        .actions { display: flex; gap: 8px; }
        .btn-payout { padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; transition: all 0.2s; }
        .btn-payout.approve { background: #0f172a; color: #fff; border: none; }
        .btn-payout.process { background: #10b981; color: #fff; border: none; }
        .btn-payout.reject { color: #ef4444; }

        /* Schedule Card */
        .schedule-card { background: #fff; border-radius: 20px; padding: 24px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); height: fit-content; }
        .schedule-card h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 20px; }
        .schedule-option { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; border: 1px solid #f1f5f9; margin-bottom: 12px; cursor: pointer; transition: all 0.2s; }
        .schedule-option:hover { border-color: #f97316; }
        .schedule-option.active { border-color: #f97316; background: #fff7ed; }
        .schedule-option input { display: none; }
        .schedule-info h4 { margin: 0; font-size: 14px; font-weight: 600; color: #0f172a; }
        .schedule-info p { margin: 0; font-size: 12px; color: #64748b; }

        @media (max-width: 1100px) {
            .payout-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php AdminNavigation::render(); ?>

        <div class="header-section">
            <div>
                <h1>Payout Management</h1>
                <p style="color: #64748b; margin-top: 4px;">Handle financial requests and schedule disbursements.</p>
            </div>
        </div>

        <div class="payout-layout">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Bank Information</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payouts) > 0): ?>
                            <?php foreach ($payouts as $p): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div>
                                                <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($p['fname'] . ' ' . $p['lname']); ?></div>
                                                <span class="role-pill role-<?php echo strtolower($p['role_label']); ?>">
                                                    <?php echo $p['role_label']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="bank-info">
                                            <b><?php echo htmlspecialchars($p['Bank_Name']); ?></b><br>
                                            # <?php echo htmlspecialchars($p['Account_Number']); ?><br>
                                            Name: <?php echo htmlspecialchars($p['Account_Name']); ?>
                                        </div>
                                    </td>
                                    <td><span class="amount">₱<?php echo number_format($p['Amount'], 2); ?></span></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $p['Payout_Status']; ?>">
                                            <?php echo $p['Payout_Status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($p['Request_Date'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <form method="POST">
                                                <input type="hidden" name="payout_id" value="<?php echo $p['Payout_Id']; ?>">
                                                <?php if ($p['Payout_Status'] === 'pending'): ?>
                                                    <button type="submit" name="action" value="approve" class="btn-payout approve">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn-payout reject">Reject</button>
                                                <?php elseif ($p['Payout_Status'] === 'approved'): ?>
                                                    <button type="submit" name="action" value="process" class="btn-payout process">Mark Processed</button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 60px; color: #94a3b8;">No payout requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Schedule Configuration -->
            <div class="schedule-card">
                <h3>Payout Schedule</h3>
                <div class="schedule-option active">
                    <div class="schedule-info">
                        <h4>Weekly Disbursements</h4>
                        <p>Every Friday at 12:00 AM</p>
                    </div>
                </div>
                <div class="schedule-option">
                    <div class="schedule-info">
                        <h4>Bi-Weekly</h4>
                        <p>1st and 15th of each month</p>
                    </div>
                </div>
                <div class="schedule-option">
                    <div class="schedule-info">
                        <h4>Manual Only</h4>
                        <p>Process requests individually</p>
                    </div>
                </div>
                <button class="btn-payout approve" style="width: 100%; margin-top: 20px;">Save Schedule</button>
                <p style="font-size: 11px; color: #94a3b8; text-align: center; margin-top: 16px;">Last updated: May 05, 2026</p>
            </div>
        </div>
    </div>
</body>
</html>
