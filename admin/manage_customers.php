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

    if ($action === 'create') {
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone_number']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone_number, password, user_type, account_status) VALUES (?, ?, ?, ?, ?, 'customer', 'active')");
            $stmt->execute([$fname, $lname, $email, $phone, $password]);
            $success = "Customer created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating customer: " . $e->getMessage();
        }
    } elseif ($action === 'update_details') {
        $user_id = $_POST['user_id'];
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone_number']);

        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ? WHERE id = ? AND user_type = 'customer'");
            $stmt->execute([$fname, $lname, $email, $phone, $user_id]);
            $success = "Customer details updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating customer: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $user_id = $_POST['user_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'customer'");
            $stmt->execute([$user_id]);
            $success = "Customer deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting customer. They might have order history.";
        }
    } elseif (in_array($action, ['suspend', 'deactivate', 'activate'])) {
        $user_id = $_POST['user_id'];
        $new_status = '';
        if ($action === 'suspend') $new_status = 'suspended';
        elseif ($action === 'deactivate') $new_status = 'deactivated';
        elseif ($action === 'activate') $new_status = 'active';

        if ($new_status) {
            $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE id = ? AND user_type = 'customer'");
            $stmt->execute([$new_status, $user_id]);
            $success = "Account status updated to $new_status";
        }
    }
}

// Search logic
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM users WHERE user_type = 'customer'";
$params = [];

if ($search) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - PickGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }

        .header-section { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
        .header-section h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        
        .header-actions { display: flex; gap: 16px; align-items: center; }
        
        .btn-add { background: #f97316; color: #fff; padding: 12px 24px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-add:hover { background: #ea580c; transform: translateY(-1px); }

        .search-box { position: relative; width: 300px; }
        .search-box input { width: 100%; padding: 12px 16px 12px 44px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .search-box input:focus { border-color: #f97316; }
        .search-box svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        .table-container { background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 24px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #64748b; font-size: 12px; }
        .user-details h4 { margin: 0; font-size: 14px; font-weight: 600; color: #0f172a; }
        .user-details p { margin: 0; font-size: 13px; color: #64748b; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .status-badge.active { background: #dcfce7; color: #15803d; }
        .status-badge.suspended { background: #fef9c3; color: #854d0e; }
        .status-badge.deactivated { background: #fee2e2; color: #b91c1c; }

        .actions { display: flex; gap: 8px; align-items: center; }
        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; transition: all 0.2s; }
        .btn-action:hover { background: #f8fafc; }
        
        .btn-edit { color: #0ea5e9; }
        .btn-delete { color: #ef4444; }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); overflow-y: auto; padding: 40px 0; }
        .modal-content { background: #fff; margin: 0 auto; padding: 32px; width: 450px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); position: relative; }
        .modal-header { margin-bottom: 24px; }
        .modal-header h2 { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; }
        .close-modal { position: absolute; right: 24px; top: 24px; cursor: pointer; color: #94a3b8; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #f97316; }

        .btn-submit { width: 100%; padding: 14px; background: #0f172a; color: #fff; border: none; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background: #1e293b; }

        .empty-state { padding: 60px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php AdminNavigation::render(); ?>

        <div class="header-section">
            <div>
                <h1>Customer Management</h1>
                <p style="color: #64748b; margin-top: 4px;">Monitor and manage customer account statuses.</p>
            </div>
            <div class="header-actions">
                <form method="GET" class="search-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
                <button class="btn-add" onclick="openAddModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Add Customer
                </button>
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
                        <th>Customer</th>
                        <th>Phone Number</th>
                        <th>Joined Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper($customer['first_name'][0] . $customer['last_name'][0]); ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($customer['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($customer['phone_number']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $customer['account_status']; ?>">
                                        <?php echo $customer['account_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($customer); ?>)'>Edit</button>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                            <?php if ($customer['account_status'] !== 'active'): ?>
                                                <button type="submit" name="action" value="activate" class="btn-action">Activate</button>
                                            <?php endif; ?>
                                            <?php if ($customer['account_status'] === 'active'): ?>
                                                <button type="submit" name="action" value="suspend" class="btn-action">Suspend</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="delete" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this customer?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <p>No customers found matching your search.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2 id="modalTitle">Add New Customer</h2>
            </div>
            <form id="customerForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="formUserId">

                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" id="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" id="last_name" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" id="phone_number" required>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label>Password</label>
                    <input type="password" name="password" id="password">
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">Create Customer</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('customerModal');
        const form = document.getElementById('customerForm');
        
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add New Customer';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitBtn').innerText = 'Create Customer';
            document.getElementById('passwordGroup').style.display = 'block';
            document.getElementById('password').required = true;
            form.reset();
            modal.style.display = 'block';
        }

        function openEditModal(user) {
            document.getElementById('modalTitle').innerText = 'Edit Customer';
            document.getElementById('formAction').value = 'update_details';
            document.getElementById('submitBtn').innerText = 'Save Changes';
            document.getElementById('passwordGroup').style.display = 'none';
            document.getElementById('password').required = false;

            document.getElementById('formUserId').value = user.id;
            document.getElementById('first_name').value = user.first_name;
            document.getElementById('last_name').value = user.last_name;
            document.getElementById('email').value = user.email;
            document.getElementById('phone_number').value = user.phone_number;
            
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
