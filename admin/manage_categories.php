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

// Handle Create/Update/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create') {
            $name = trim($_POST['name']);
            $desc = trim($_POST['description']);
            
            if (empty($name)) {
                $error = "Category name is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (Categ_Name, Categ_Description) VALUES (?, ?)");
                    $stmt->execute([$name, $desc]);
                    $success = "Category created successfully!";
                } catch (PDOException $e) {
                    $error = "Error: " . ($e->getCode() == 23000 ? "Category name already exists." : $e->getMessage());
                }
            }
        } elseif ($action === 'update') {
            $id = $_POST['id'];
            $name = trim($_POST['name']);
            $desc = trim($_POST['description']);
            
            if (empty($name)) {
                $error = "Category name is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET Categ_Name = ?, Categ_Description = ? WHERE Categ_Id = ?");
                    $stmt->execute([$name, $desc, $id]);
                    $success = "Category updated successfully!";
                } catch (PDOException $e) {
                    $error = "Error updating category.";
                }
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE Categ_Id = ?");
                $stmt->execute([$id]);
                $success = "Category deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting category. It might be in use.";
            }
        }
    }
}

// Fetch all categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY Categ_Name ASC");
$categories = $stmt->fetchAll();

// Handle Edit Mode UI
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    foreach ($categories as $cat) {
        if ($cat['Categ_Id'] == $edit_id) {
            $edit_category = $cat;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - PickGo Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .dashboard-container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }


        .header-section { margin-top: 40px; margin-bottom: 32px; }
        .header-section h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        
        .management-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px; }

        .form-card { background: #fff; border-radius: 20px; padding: 32px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); height: fit-content; }
        .form-card h3 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 24px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 8px; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 15px; outline: none; transition: border-color 0.2s; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        .form-group input:focus, .form-group textarea:focus { border-color: #f97316; }
        
        .btn-submit { width: 100%; padding: 14px; background: #0f172a; color: #fff; border: none; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #1e293b; }
        .btn-cancel { display: block; text-align: center; margin-top: 12px; color: #64748b; text-decoration: none; font-size: 14px; font-weight: 600; }

        .table-card { background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px 24px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #334155; vertical-align: top; }
        
        .cat-name { font-weight: 700; color: #0f172a; display: block; margin-bottom: 4px; }
        .cat-desc { font-size: 13px; color: #64748b; line-height: 1.4; }

        .actions { display: flex; gap: 12px; }
        .btn-icon { color: #94a3b8; transition: color 0.2s; cursor: pointer; background: none; border: none; padding: 0; }
        .btn-icon:hover { color: #f97316; }
        .btn-icon.delete:hover { color: #ef4444; }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }

        @media (max-width: 850px) {
            .management-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php AdminNavigation::render(); ?>

        <div class="header-section">
            <h1>Category Management</h1>
            <p style="color: #64748b; margin-top: 4px;">Organize food and store types across the platform.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="management-grid">
            <!-- Form Section -->
            <div class="form-card">
                <h3><?php echo $edit_category ? 'Edit Category' : 'Create New Category'; ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_category ? 'update' : 'create'; ?>">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_category['Categ_Id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="name" placeholder="e.g. Italian, Burgers, Vegan" required value="<?php echo $edit_category ? htmlspecialchars($edit_category['Categ_Name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" rows="4" placeholder="Brief description of this category..."><?php echo $edit_category ? htmlspecialchars($edit_category['Categ_Description']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit"><?php echo $edit_category ? 'Update Category' : 'Create Category'; ?></button>
                    
                    <?php if ($edit_category): ?>
                        <a href="manage_categories.php" class="btn-cancel">Cancel Editing</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- List Section -->
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Category Info</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($categories) > 0): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <span class="cat-name"><?php echo htmlspecialchars($cat['Categ_Name']); ?></span>
                                        <span class="cat-desc"><?php echo htmlspecialchars($cat['Categ_Description']); ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="?edit=<?php echo $cat['Categ_Id']; ?>" class="btn-icon" title="Edit">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this category?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $cat['Categ_Id']; ?>">
                                                <button type="submit" class="btn-icon delete" title="Delete">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" style="text-align: center; padding: 40px; color: #94a3b8;">No categories created yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
