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
        $seller_id = $_POST['seller_id'];
        $merch_id = $_POST['merch_id'];
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $merch_name = trim($_POST['merch_name']);
        $merch_type = trim($_POST['merch_type']);

        try {
            $pdo->beginTransaction();

            // Update Seller
            $stmt = $pdo->prepare("UPDATE sellers SET Sellr_Fname = ?, Sellr_Lname = ?, Sellr_Email = ?, Sellr_PhoneNumber = ? WHERE Seller_Id = ?");
            $stmt->execute([$fname, $lname, $email, $phone, $seller_id]);

            // Update Merchant
            if ($merch_id) {
                $stmt = $pdo->prepare("UPDATE merchants SET Merch_Name = ?, Merch_Type = ? WHERE Merch_Id = ?");
                $stmt->execute([$merch_name, $merch_type, $merch_id]);
            }

            $pdo->commit();
            $success = "Seller details updated successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error updating seller: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $seller_id = $_POST['seller_id'];
        $merch_id = $_POST['merch_id'];

        try {
            $pdo->beginTransaction();
            // Delete seller first
            $stmt = $pdo->prepare("DELETE FROM sellers WHERE Seller_Id = ?");
            $stmt->execute([$seller_id]);
            // Then merchant
            if ($merch_id) {
                $stmt = $pdo->prepare("DELETE FROM merchants WHERE Merch_Id = ?");
                $stmt->execute([$merch_id]);
            }
            $pdo->commit();
            $success = "Seller deleted successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error deleting seller. They might have active orders.";
        }
    } elseif (in_array($action, ['approve', 'suspend', 'deactivate', 'reject'])) {
        // Existing status update logic
        $seller_id = $_POST['seller_id'];
        $merch_id = $_POST['merch_id'];
        $new_status = '';

        if ($action === 'approve') $new_status = 'active';
        elseif ($action === 'suspend') $new_status = 'suspended';
        elseif ($action === 'deactivate') $new_status = 'closed';
        elseif ($action === 'reject') $new_status = 'rejected';

        if ($new_status) {
            $stmt = $pdo->prepare("UPDATE sellers SET Sellr_Status = ? WHERE Seller_Id = ?");
            // Mapping for merchant status if it's different
            $merch_db_status = ($new_status === 'rejected') ? 'closed' : $new_status;
            
            $seller_db_status = ($new_status === 'closed') ? 'suspended' : $new_status;
            $stmt->execute([$seller_db_status, $seller_id]);

            if ($merch_id) {
                $stmt = $pdo->prepare("UPDATE merchants SET Merch_Status = ? WHERE Merch_Id = ?");
                $stmt->execute([$merch_db_status, $merch_id]);
            }
            $success = "Status updated to $new_status";
        }
    }
}

// Search logic
$search = $_GET['search'] ?? '';
$query = "SELECT s.*, m.*
          FROM sellers s 
          LEFT JOIN merchants m ON s.Merch_Id = m.Merch_Id";
$params = [];

if ($search) {
    $query .= " WHERE s.Sellr_Fname LIKE ? OR s.Sellr_Lname LIKE ? OR s.Sellr_Email LIKE ? OR m.Merch_Name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$query .= " ORDER BY s.Sellr_DateCreated DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sellers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sellers - PickGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 1400px; margin: 0 auto; padding: 40px 20px; }

        .header-section { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
        .header-section h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        
        .header-actions { display: flex; gap: 16px; align-items: center; }
        
        .btn-add { background: #f97316; color: #fff; padding: 12px 24px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-add:hover { background: #ea580c; transform: translateY(-1px); }

        .search-box { position: relative; width: 350px; }
        .search-box input { width: 100%; padding: 12px 16px 12px 44px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .search-box input:focus { border-color: #f97316; }
        .search-box svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        .table-container { background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 24px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        .seller-info { display: flex; align-items: center; gap: 12px; }
        .seller-avatar { width: 40px; height: 40px; border-radius: 10px; background: #f97316; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 14px; }
        .seller-details h4 { margin: 0; font-size: 14px; font-weight: 600; color: #0f172a; }
        .seller-details p { margin: 0; font-size: 13px; color: #64748b; }

        .merchant-pill { display: inline-block; padding: 4px 12px; background: #f1f5f9; border-radius: 6px; font-size: 12px; font-weight: 600; color: #475569; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-badge.active { background: #dcfce7; color: #15803d; }
        .status-badge.pending { background: #fef9c3; color: #854d0e; }
        .status-badge.suspended { background: #fee2e2; color: #b91c1c; }
        .status-badge.rejected { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .status-badge.closed { background: #f1f5f9; color: #64748b; }

        .rating-star { color: #eab308; display: flex; align-items: center; gap: 4px; font-weight: 600; font-size: 13px; }

        .actions { display: flex; gap: 8px; align-items: center; }
        .btn-action { padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; transition: all 0.2s; }
        .btn-action:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-approve { background: #10b981; color: #fff; border: none; }
        .btn-approve:hover { background: #059669; }
        
        .btn-edit { color: #0ea5e9; }
        .btn-delete { color: #ef4444; }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); overflow-y: auto; padding: 40px 0; }
        .modal-content { background: #fff; margin: 0 auto; padding: 32px; width: 650px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); position: relative; }
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
                <h1>Seller Management</h1>
                <p style="color: #64748b; margin-top: 4px;">Approve applications and manage merchant partnerships.</p>
            </div>
            <div class="header-actions">
                <form method="GET" class="search-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Search sellers or stores..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <th>Seller Name</th>
                        <th>Merchant / Store</th>
                        <th>Status</th>
                        <th>Compliance</th>
                        <th>Documents</th>
                        <th>Date Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sellers) > 0): ?>
                        <?php foreach ($sellers as $seller): ?>
                            <tr>
                                <td>
                                    <div class="seller-info">
                                        <div class="seller-avatar">
                                            <?php echo strtoupper($seller['Sellr_Fname'][0] . $seller['Sellr_Lname'][0]); ?>
                                        </div>
                                        <div class="seller-details">
                                            <h4><?php echo htmlspecialchars($seller['Sellr_Fname'] . ' ' . $seller['Sellr_Lname']); ?></h4>
                                            <p><?php echo htmlspecialchars($seller['Sellr_Email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($seller['Merch_Name']): ?>
                                        <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($seller['Merch_Name']); ?></div>
                                        <div class="merchant-pill"><?php echo htmlspecialchars($seller['Merch_Type']); ?></div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">No Store Linked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $seller['Sellr_Status']; ?>">
                                        <?php echo $seller['Sellr_Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="rating-star">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        <?php echo number_format($seller['Sellr_Rating'], 1); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($seller['Merch_GovID'])): ?>
                                        <a href="../<?php echo htmlspecialchars($seller['Merch_GovID']); ?>" target="_blank" style="display: flex; align-items: center; gap: 4px; font-size: 13px; color: #0ea5e9; text-decoration: none; margin-bottom: 6px; font-weight: 500;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                            Gov ID
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($seller['Merch_BIRCert'])): ?>
                                        <a href="../<?php echo htmlspecialchars($seller['Merch_BIRCert']); ?>" target="_blank" style="display: flex; align-items: center; gap: 4px; font-size: 13px; color: #0ea5e9; text-decoration: none; font-weight: 500;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                            BIR Cert
                                        </a>
                                    <?php endif; ?>
                                    <?php if (empty($seller['Merch_GovID']) && empty($seller['Merch_BIRCert'])): ?>
                                        <span style="color: #94a3b8; font-size: 12px; font-style: italic;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($seller['Sellr_DateCreated'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($seller); ?>)'>Edit</button>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['Seller_Id']; ?>">
                                            <input type="hidden" name="merch_id" value="<?php echo $seller['Merch_Id']; ?>">
                                            
                                            <?php if ($seller['Sellr_Status'] === 'pending'): ?>
                                                <button type="submit" name="action" value="approve" class="btn-action btn-approve">Approve</button>
                                                <button type="submit" name="action" value="reject" class="btn-action btn-delete" onclick="return confirm('Reject this application?')">Reject</button>
                                            <?php endif; ?>

                                            <?php if ($seller['Sellr_Status'] === 'active'): ?>
                                                <button type="submit" name="action" value="suspend" class="btn-action btn-suspend">Suspend</button>
                                            <?php else: ?>
                                                <?php if ($seller['Sellr_Status'] !== 'pending'): ?>
                                                    <button type="submit" name="action" value="approve" class="btn-action">Activate</button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <button type="submit" name="action" value="delete" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this seller and their store?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <p>No seller applications found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="sellerModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2 id="modalTitle">Add New Seller</h2>
            </div>
            <form id="sellerForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="seller_id" id="formSellerId">
                <input type="hidden" name="merch_id" id="formMerchId">

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
                <h3 style="font-size: 16px; margin-bottom: 16px;">Store Information</h3>

                <div class="form-group">
                    <label>Store Name</label>
                    <input type="text" name="merch_name" id="merch_name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Store Type</label>
                        <select name="merch_type" id="merch_type">
                            <option value="Restaurant">Restaurant</option>
                            <option value="Fast Food">Fast Food</option>
                            <option value="Cafe">Cafe</option>
                            <option value="Grocery">Grocery</option>
                            <option value="Pharmacy">Pharmacy</option>
                        </select>
                    </div>
                </div>

                <div id="addressGroup">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Unit/Floor Number</label>
                            <input type="text" name="unit_floor" id="unit_floor" placeholder="e.g. Unit 123">
                        </div>
                        <div class="form-group">
                            <label>Building Name</label>
                            <input type="text" name="building" id="building" placeholder="e.g. Ayala Mall">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Street Number</label>
                            <input type="text" name="street_no" id="street_no" placeholder="e.g. 45">
                        </div>
                        <div class="form-group">
                            <label>Street Name</label>
                            <input type="text" name="street_name" id="street_name">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Barangay</label>
                            <input type="text" name="barangay" id="barangay">
                        </div>
                        <div class="form-group">
                            <label>City/Municipality</label>
                            <input type="text" name="city" id="city">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" name="province" id="province">
                        </div>
                        <div class="form-group">
                            <label>ZIP/Postal Code</label>
                            <input type="text" name="zip" id="zip">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Landmark</label>
                        <input type="text" name="landmark" id="landmark" placeholder="e.g. Near San Jose Church">
                    </div>

                    <h3 style="font-size: 16px; margin-top: 24px; margin-bottom: 16px;">Store Contact Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Store Phone (Optional)</label>
                            <input type="text" name="merch_phone" id="merch_phone">
                        </div>
                        <div class="form-group">
                            <label>Store Email (Optional)</label>
                            <input type="email" name="merch_email" id="merch_email">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">Create Seller</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('sellerModal');
        const form = document.getElementById('sellerForm');
        
        function openEditModal(seller) {
            document.getElementById('modalTitle').innerText = 'Edit Seller Details';
            document.getElementById('formAction').value = 'update_details';
            document.getElementById('submitBtn').innerText = 'Save Changes';
            document.getElementById('passwordGroup').style.display = 'none';
            document.getElementById('addressGroup').style.display = 'none';
            document.getElementById('password').required = false;
            // Remove required fields for edit
            document.getElementById('street_name').required = false;
            document.getElementById('barangay').required = false;
            document.getElementById('city').required = false;
            document.getElementById('province').required = false;
            document.getElementById('zip').required = false;

            document.getElementById('formSellerId').value = seller.Seller_Id;
            document.getElementById('formMerchId').value = seller.Merch_Id;
            document.getElementById('fname').value = seller.Sellr_Fname;
            document.getElementById('lname').value = seller.Sellr_Lname;
            document.getElementById('email').value = seller.Sellr_Email;
            document.getElementById('phone').value = seller.Sellr_PhoneNumber;
            document.getElementById('merch_name').value = seller.Merch_Name || '';
            document.getElementById('merch_type').value = seller.Merch_Type || 'Restaurant';
            
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

