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

// Handle Item Actions (Create/Update/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $item_name = trim($_POST['item_name']);
            $item_price = floatval($_POST['item_price']);
            $item_category = intval($_POST['item_category']);
            $item_description = trim($_POST['item_description']);
            $item_status = $_POST['item_status'] ?? 'pending';
            
            $item_id = $_POST['item_id'] ?? null;
            $image_path = $_POST['existing_image'] ?? '';

            // Handle Image Upload
            if (!empty($_FILES['item_image']['name'])) {
                $upload_dir = '../uploads/items/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $img_name = 'item_' . time() . '_' . $_FILES['item_image']['name'];
                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_dir . $img_name)) {
                    $image_path = 'uploads/items/' . $img_name;
                }
            }

            try {
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO items (Seller_Id, Item_Name, Item_Description, Item_Price, Item_Category, Item_Image, Item_Status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$seller_id, $item_name, $item_description, $item_price, $item_category, $image_path, $item_status]);
                    $success = "Item added successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE items SET Item_Name = ?, Item_Description = ?, Item_Price = ?, Item_Category = ?, Item_Image = ?, Item_Status = ? WHERE Item_Id = ? AND Seller_Id = ?");
                    $stmt->execute([$item_name, $item_description, $item_price, $item_category, $image_path, $item_status, $item_id, $seller_id]);
                    $success = "Item updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Operation failed: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'delete') {
            $item_id = $_POST['item_id'];
            $stmt = $pdo->prepare("DELETE FROM items WHERE Item_Id = ? AND Seller_Id = ?");
            $stmt->execute([$item_id, $seller_id]);
            $success = "Item deleted successfully!";
        }
    }
}

// Fetch all items for this seller, joined with categories
$stmt = $pdo->prepare("SELECT i.*, c.Categ_Name FROM items i LEFT JOIN categories c ON i.Item_Category = c.Categ_Id WHERE i.Seller_Id = ? ORDER BY c.Categ_Name, i.Item_Name");
$stmt->execute([$seller_id]);
$items = $stmt->fetchAll();

// Group items by category
$grouped_items = [];
foreach ($items as $item) {
    $cat = $item['Categ_Name'] ?: 'Uncategorized';
    $grouped_items[$cat][] = $item;
}

// Fetch all categories for the dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY Categ_Name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - <?php echo htmlspecialchars($sellerData['Merch_Name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .btn-add { background: #f97316; color: #fff; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-add:hover { background: #ea580c; }
        .btn-outline { background: #fff; color: #64748b; border: 1px solid #cbd5e1; padding: 12px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-outline:hover { background: #f1f5f9; color: #0f172a; }
        
        .manage-container { display: flex; gap: 32px; align-items: flex-start; }
        
        /* Sticky Form Panel */
        .product-form-card {
            background: #fff;
            border-radius: 24px;
            padding: 32px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            width: 400px;
            position: sticky;
            top: 40px;
            flex-shrink: 0;
        }
        .form-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; }
        .form-header h2 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; }
        
        /* Product Catalog Panel */
        .product-catalog { flex: 1; min-width: 0; }
        
        .category-section { margin-bottom: 40px; }
        .category-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .category-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        
        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
        .item-card { background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; position: relative; display: flex; flex-direction: column; }
        .item-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.1); }
        
        .item-image { width: 100%; height: 180px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .item-image img { width: 100%; height: 100%; object-fit: cover; }
        
        .item-info { padding: 20px; display: flex; flex-direction: column; flex: 1; }
        .item-name { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .item-price { font-size: 18px; font-weight: 800; color: #f97316; margin-bottom: 8px; }
        .item-desc { font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 16px; flex: 1; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        
        .item-status { position: absolute; top: 12px; right: 12px; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; z-index: 10; }
        .status-available { background: #f0fdf4; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-rejected { background: #fef2f2; color: #991b1b; }
        .status-out_of_stock { background: #f1f5f9; color: #475569; }
        
        .item-actions { display: flex; gap: 10px; padding-top: 16px; border-top: 1px solid #f1f5f9; margin-top: auto; }
        .action-btn { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .action-btn:hover { background: #f8fafc; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 8px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; transition: border-color 0.2s; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #f97316; }

        @media (max-width: 1024px) {
            .manage-container { flex-direction: column; }
            .product-form-card { width: 100%; position: static; margin-bottom: 32px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php SellerNavigation::render('products'); ?>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="font-size: 28px; font-weight: 700; color: #0f172a;">Manage Products</h1>
                    <p style="color: #64748b;">Add, edit or remove items from your store menu.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div style="background: #f0fdf4; color: #166534; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #dcfce7; font-weight: 600;"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #fecaca; font-weight: 600;"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="manage-container">
                <!-- Sticky Product Form Card -->
                <div class="product-form-card" id="formContainer">
                    <div class="form-header">
                        <h2 id="formTitle">Add New Product</h2>
                        <button type="button" id="cancelEditBtn" onclick="resetForm()" class="btn-outline" style="display: none; padding: 6px 12px; font-size: 13px;">Cancel Edit</button>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="item_id" id="itemId">
                        <input type="hidden" name="existing_image" id="existingImage">
                        
                        <div class="form-group">
                            <label>Product Name</label>
                            <input type="text" name="item_name" id="itemName" required placeholder="e.g. Special Beef Burger">
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="item_category" id="itemCategory" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['Categ_Id']; ?>"><?php echo htmlspecialchars($cat['Categ_Name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Price (₱)</label>
                            <input type="number" name="item_price" id="itemPrice" step="0.01" required placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="item_description" id="itemDescription" rows="3" placeholder="Tell customers about this item..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="item_status" id="itemStatus">
                                <option value="available">Available</option>
                                <option value="pending">Pending Approval</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Product Image</label>
                            <input type="file" name="item_image" id="itemImage" accept="image/*">
                        </div>

                        <button type="submit" id="saveBtn" class="btn-add" style="width: 100%; justify-content: center; margin-top: 24px; padding: 14px;">
                            Save Product
                        </button>
                    </form>
                </div>

                <!-- Product Catalog List -->
                <div class="product-catalog">
                    <?php if (empty($grouped_items)): ?>
                        <div style="background: #fff; padding: 80px 40px; border-radius: 24px; text-align: center; border: 1px solid rgba(0,0,0,0.05);">
                            <div style="width: 80px; height: 80px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                            </div>
                            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">No products created yet</h2>
                            <p style="color: #64748b; margin: 0;">Use the form to add your first product to your store.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped_items as $category => $items): ?>
                            <section class="category-section">
                                <h2 class="category-title"><?php echo htmlspecialchars($category); ?> <span style="font-size: 14px; font-weight: 500; color: #94a3b8; margin-left: 8px;">(<?php echo count($items); ?>)</span></h2>
                                <div class="items-grid">
                                    <?php foreach ($items as $item): ?>
                                        <div class="item-card">
                                            <div class="item-status status-<?php echo $item['Item_Status']; ?>">
                                                <?php echo str_replace('_', ' ', $item['Item_Status']); ?>
                                            </div>
                                            <div class="item-image">
                                                <?php if ($item['Item_Image']): ?>
                                                    <img src="../<?php echo $item['Item_Image']; ?>" alt="">
                                                <?php else: ?>
                                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-info">
                                                <div class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></div>
                                                <div class="item-price">₱<?php echo number_format($item['Item_Price'], 2); ?></div>
                                                <div class="item-desc"><?php echo htmlspecialchars($item['Item_Description']); ?></div>
                                                <div class="item-actions">
                                                    <button class="action-btn" onclick='editProduct(<?php echo json_encode($item); ?>)'>Edit</button>
                                                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this item?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['Item_Id']; ?>">
                                                        <button type="submit" class="action-btn" style="width: 100%; color: #ef4444;">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function editProduct(item) {
            document.getElementById('formTitle').innerText = 'Edit Product';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('itemId').value = item.Item_Id;
            document.getElementById('itemName').value = item.Item_Name;
            document.getElementById('itemCategory').value = item.Item_Category;
            document.getElementById('itemPrice').value = item.Item_Price;
            document.getElementById('itemDescription').value = item.Item_Description;
            document.getElementById('itemStatus').value = item.Item_Status;
            document.getElementById('existingImage').value = item.Item_Image;
            document.getElementById('saveBtn').innerText = 'Update Product';
            document.getElementById('cancelEditBtn').style.display = 'inline-flex';
            
            // Highlight and smooth scroll to form
            const formContainer = document.getElementById('formContainer');
            formContainer.style.outline = '3px solid #f97316';
            formContainer.style.outlineOffset = '4px';
            setTimeout(() => { formContainer.style.outline = 'none'; }, 2000);
            formContainer.scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('formTitle').innerText = 'Add New Product';
            document.getElementById('formAction').value = 'add';
            document.getElementById('itemId').value = '';
            document.getElementById('itemName').value = '';
            document.getElementById('itemCategory').value = '';
            document.getElementById('itemPrice').value = '';
            document.getElementById('itemDescription').value = '';
            document.getElementById('itemStatus').value = 'available';
            document.getElementById('existingImage').value = '';
            document.getElementById('itemImage').value = '';
            document.getElementById('saveBtn').innerText = 'Save Product';
            document.getElementById('cancelEditBtn').style.display = 'none';
        }
    </script>
</body>
</html>
