<?php
require_once '../config.php';
require_once 'Navigation.php';

// Check if user is an admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_details') {
        $rider_id = $_POST['rider_id'];
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $vehicle = trim($_POST['vehicle_type']);
        $plate = trim($_POST['plate_number']);
        $license = trim($_POST['license_number']);

        try {
            $stmt = $pdo->prepare("UPDATE riders SET Rider_Fname = ?, Rider_Lname = ?, Rider_Email = ?, Rider_Phone = ?, Rider_VehicleType = ?, Rider_PlateNumber = ?, Rider_LicenseNumber = ? WHERE Rider_Id = ?");
            $stmt->execute([$fname, $lname, $email, $phone, $vehicle, $plate, $license, $rider_id]);
            $success = "Rider details updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating rider: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $rider_id = $_POST['rider_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM riders WHERE Rider_Id = ?");
            $stmt->execute([$rider_id]);
            $success = "Rider deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting rider. They might have delivery history.";
        }
    } elseif (in_array($action, ['approve', 'verify', 'suspend', 'activate', 'reject'])) {
        $rider_id = $_POST['rider_id'];
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE riders SET Rider_Status = 'active', Rider_Verified = 1 WHERE Rider_Id = ?");
            $stmt->execute([$rider_id]);
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE riders SET Rider_Status = 'rejected' WHERE Rider_Id = ?");
            $stmt->execute([$rider_id]);
        } elseif ($action === 'verify') {
            $stmt = $pdo->prepare("UPDATE riders SET Rider_Verified = 1 WHERE Rider_Id = ?");
            $stmt->execute([$rider_id]);
        } elseif ($action === 'suspend') {
            $stmt = $pdo->prepare("UPDATE riders SET Rider_Status = 'suspended' WHERE Rider_Id = ?");
            $stmt->execute([$rider_id]);
        } elseif ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE riders SET Rider_Status = 'active' WHERE Rider_Id = ?");
            $stmt->execute([$rider_id]);
        }
        $success = "Rider status updated successfully!";
    }
}

// Search logic
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM riders";
$params = [];

if ($search) {
    $query .= " WHERE Rider_Fname LIKE ? OR Rider_Lname LIKE ? OR Rider_Email LIKE ? OR Rider_PlateNumber LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$riders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Riders - PickGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 1400px; margin: 0 auto; padding: 40px 20px; height: auto}

        .header-section { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
        .header-section h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        
        .header-actions { display: flex; gap: 16px; align-items: center; }
        
        .btn-add { background: #0f172a; color: #fff; padding: 12px 24px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-add:hover { background: #1e293b; transform: translateY(-1px); }

        .search-box { position: relative; width: 350px; }
        .search-box input { width: 100%; padding: 12px 16px 12px 44px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .search-box input:focus { border-color: #f97316; }
        .search-box svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        .table-container { background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 24px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        .rider-info { display: flex; align-items: center; gap: 12px; }
        .rider-avatar { width: 40px; height: 40px; border-radius: 50%; background: #0f172a; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 14px; }
        .rider-details h4 { margin: 0; font-size: 14px; font-weight: 600; color: #0f172a; }
        .rider-details p { margin: 0; font-size: 13px; color: #64748b; }

        .vehicle-tag { display: inline-block; padding: 4px 10px; background: #f1f5f9; border-radius: 6px; font-size: 12px; font-weight: 600; color: #475569; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-badge.active { background: #dcfce7; color: #15803d; }
        .status-badge.pending { background: #fef9c3; color: #854d0e; }
        .status-badge.suspended { background: #fee2e2; color: #b91c1c; }
        .status-badge.rejected { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .status-badge.offline { background: #f1f5f9; color: #64748b; }

        .verified-badge { display: inline-flex; align-items: center; gap: 4px; color: #0ea5e9; font-weight: 700; font-size: 11px; text-transform: uppercase; }

        .performance-metrics { display: flex; flex-direction: column; gap: 4px; }
        .metric-row { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }
        .rating-star { color: #eab308; }

        .actions { display: flex; gap: 8px; align-items: center; }
        .btn-action { padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; transition: all 0.2s; }
        .btn-action:hover { background: #f8fafc; border-color: #cbd5e1; }
        
        .btn-edit { color: #0ea5e9; }
        .btn-delete { color: #ef4444; }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); overflow-y: auto; padding: 40px 0; }
        .modal-content { background: #fff; margin: 0 auto; padding: 32px; width: 500px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); position: relative; }
        .modal-header { margin-bottom: 24px; }
        .modal-header h2 { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; }
        .close-modal { position: absolute; right: 24px; top: 24px; cursor: pointer; color: #94a3b8; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #f97316; }

        .btn-submit { width: 100%; padding: 14px; background: #0f172a; color: #fff; border: none; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background: #1e293b; }

        .empty-state { padding: 80px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php AdminNavigation::render(); ?>

        <div class="header-section">
            <div>
                <h1>Rider Management</h1>
                <p style="color: #64748b; margin-top: 4px;">Approve riders and monitor delivery fleet performance.</p>
            </div>
            <div class="header-actions">
                <form method="GET" class="search-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Search by name, email, or plate..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Rider Details</th>
                        <th>Vehicle / Documents</th>
                        <th>Status</th>
                        <th>Performance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($riders) > 0): ?>
                        <?php foreach ($riders as $rider): ?>
                            <tr>
                                <td>
                                    <div class="rider-info">
                                        <div class="rider-avatar">
                                            <?php echo strtoupper($rider['Rider_Fname'][0] . $rider['Rider_Lname'][0]); ?>
                                        </div>
                                        <div class="rider-details">
                                            <h4><?php echo htmlspecialchars($rider['Rider_Fname'] . ' ' . $rider['Rider_Lname']); ?></h4>
                                            <p><?php echo htmlspecialchars($rider['Rider_Email']); ?></p>
                                            <p style="font-size: 12px;"><?php echo htmlspecialchars($rider['Rider_Phone']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="vehicle-tag"><?php echo htmlspecialchars($rider['Rider_VehicleType']); ?></div>
                                    <div style="font-size: 13px; font-weight: 700; margin-top: 4px;"><?php echo htmlspecialchars($rider['Rider_PlateNumber']); ?></div>
                                    
                                    <div style="margin-top: 8px;">
                                        <?php if (!empty($rider['Rider_LicensePhoto'])): ?>
                                            <a href="../<?php echo htmlspecialchars($rider['Rider_LicensePhoto']); ?>" target="_blank" style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: #0ea5e9; text-decoration: none; margin-bottom: 4px; font-weight: 500;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                                License
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($rider['Rider_NBI'])): ?>
                                            <a href="../<?php echo htmlspecialchars($rider['Rider_NBI']); ?>" target="_blank" style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: #0ea5e9; text-decoration: none; margin-bottom: 4px; font-weight: 500;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                                NBI Clearance
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($rider['Rider_OR'])): ?>
                                            <a href="../<?php echo htmlspecialchars($rider['Rider_OR']); ?>" target="_blank" style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: #0ea5e9; text-decoration: none; margin-bottom: 4px; font-weight: 500;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                                OR
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($rider['Rider_CR'])): ?>
                                            <a href="../<?php echo htmlspecialchars($rider['Rider_CR']); ?>" target="_blank" style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: #0ea5e9; text-decoration: none; margin-bottom: 4px; font-weight: 500;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                                CR
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($rider['Rider_Verified']): ?>
                                        <div class="verified-badge" style="margin-top: 6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
                                            Documents Verified
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 11px; color: #ef4444; font-weight: 700; text-transform: uppercase; margin-top: 6px;">Pending Verification</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $rider['Rider_Status']; ?>">
                                        <?php echo $rider['Rider_Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="performance-metrics">
                                        <div class="metric-row">
                                            <span class="rating-star">★</span>
                                            <span><?php echo number_format($rider['Rider_Rating'], 1); ?></span>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo $rider['Rider_TotalDeliveries']; ?> Deliveries
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($rider); ?>)'>Edit</button>

                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="rider_id" value="<?php echo $rider['Rider_Id']; ?>">
                                            
                                            <?php if (!$rider['Rider_Verified']): ?>
                                                <button type="submit" name="action" value="verify" class="btn-action">Verify</button>
                                            <?php endif; ?>

                                            <?php if ($rider['Rider_Status'] === 'pending'): ?>
                                                <button type="submit" name="action" value="approve" class="btn-action" style="background: #0f172a; color: #fff; border: none;">Approve</button>
                                                <button type="submit" name="action" value="reject" class="btn-action btn-delete" onclick="return confirm('Reject this application?')">Reject</button>
                                            <?php elseif ($rider['Rider_Status'] === 'active'): ?>
                                                <button type="submit" name="action" value="suspend" class="btn-action btn-delete">Suspend</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="activate" class="btn-action">Activate</button>
                                            <?php endif; ?>

                                            <button type="submit" name="action" value="delete" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this rider?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <p>No rider applications found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="riderModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2 id="modalTitle">Add New Rider</h2>
            </div>
            <form id="riderForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="rider_id" id="formRiderId">

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="fname" id="fname" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lname" id="lname" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="email" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="phone" required>
                </div>

                <div class="form-group" id="passwordGroup">
                    <label>Password</label>
                    <input type="password" name="password" id="password">
                </div>

                <hr style="border: 0; border-top: 1px solid #f1f5f9; margin: 24px 0;">
                <h3 style="font-size: 16px; margin-bottom: 16px;">Vehicle Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <select name="vehicle_type" id="vehicle_type">
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Bicycle">Bicycle</option>
                            <option value="Car">Car</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" id="plate_number" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="license_number" id="license_number" required>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">Create Rider</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('riderModal');
        const form = document.getElementById('riderForm');
        
        function openEditModal(rider) {
            document.getElementById('modalTitle').innerText = 'Edit Rider Details';
            document.getElementById('formAction').value = 'update_details';
            document.getElementById('submitBtn').innerText = 'Save Changes';
            document.getElementById('passwordGroup').style.display = 'none';
            document.getElementById('password').required = false;

            document.getElementById('formRiderId').value = rider.Rider_Id;
            document.getElementById('fname').value = rider.Rider_Fname;
            document.getElementById('lname').value = rider.Rider_Lname;
            document.getElementById('email').value = rider.Rider_Email;
            document.getElementById('phone').value = rider.Rider_Phone;
            document.getElementById('vehicle_type').value = rider.Rider_VehicleType;
            document.getElementById('plate_number').value = rider.Rider_PlateNumber;
            document.getElementById('license_number').value = rider.Rider_LicenseNumber;
            
            modal.style.display = 'block';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

