<?php
require_once '../config.php';
require_once 'Navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .page-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        /* Search & Filter Section */
        .search-section { background: #ffffff; padding: 32px; border-radius: 16px; margin-top: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid rgba(0,0,0,0.05); }
        .search-header { margin-bottom: 24px; }
        .search-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; }
        .search-header p { color: #64748b; margin: 0; font-size: 16px; }
        
        .search-controls { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 16px; align-items: center; }
        .search-input-group { position: relative; }
        .search-input { width: 100%; padding: 14px 20px; padding-left: 48px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 16px; font-family: 'Inter', sans-serif; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; }
        .search-input:focus { outline: none; border-color: #f97316; box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        
        .filter-select { width: 100%; padding: 14px 20px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 16px; font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #0f172a; cursor: pointer; transition: border-color 0.2s; box-sizing: border-box; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; background-size: 16px; }
        .filter-select:focus { outline: none; border-color: #f97316; }
        
        .search-btn { padding: 14px 32px; background: #f97316; color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.2s; }
        .search-btn:hover { background: #ea580c; }

        .category-chips { 
            display: flex; 
            gap: 12px; 
            margin-top: 32px; 
            overflow-x: auto; 
            padding: 10px 4px 20px 4px; 
            cursor: grab;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .category-chips::-webkit-scrollbar { height: 6px; }
        .category-chips::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .category-chips::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; transition: background 0.2s; }
        .category-chips::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .chip { 
            padding: 12px 28px; 
            background: #fff; 
            border: 1px solid #e2e8f0; 
            border-radius: 100px; 
            font-size: 14px; 
            font-weight: 600; 
            color: #64748b; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            white-space: nowrap;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            display: inline-block;
        }
        .chip:hover { 
            background: #f8fafc; 
            border-color: #f97316; 
            color: #f97316; 
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        }
        .chip.active { 
            background: #f97316; 
            color: #fff; 
            border-color: #f97316; 
            box-shadow: 0 8px 16px rgba(249, 115, 22, 0.25); 
        }

        /* Items Grid */
        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; margin-top: 40px; }
        .item-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; display: flex; flex-direction: column; }
        .item-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .item-image { width: 100%; height: 200px; object-fit: cover; background: #e2e8f0; }
        .item-info { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
        .item-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .item-title { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
        .item-price { font-size: 18px; font-weight: 700; color: #f97316; }
        .item-seller { margin: 0 0 12px 0; color: #64748b; font-size: 14px; display: flex; align-items: center; gap: 6px; }
        .item-tags { display: flex; gap: 8px; margin-bottom: 20px; }
        .tag { font-size: 12px; padding: 4px 8px; border-radius: 4px; background: #f1f5f9; color: #475569; font-weight: 500; }
        .add-to-cart-btn { margin-top: auto; width: 100%; padding: 12px; background: #fff; color: #f97316; border: 1px solid #f97316; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; }
        .add-to-cart-btn:hover { background: #f97316; color: #fff; }

        /* Responsive */
        @media (max-width: 900px) {
            .search-controls { grid-template-columns: 1fr 1fr; }
            .search-input-group { grid-column: 1 / -1; }
            .search-btn { grid-column: 1 / -1; }
        }
        @media (max-width: 600px) {
            .search-controls { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <?php Navigation::render(); ?>

        <div class="search-section">
            <div class="search-header">
                <h1>Find Your Next Meal</h1>
                <p>Search by keyword, filter by category or explore different cuisines.</p>
            </div>
            
            <form action="" method="GET">
                <div class="search-controls">
                    <div class="search-input-group">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" name="q" class="search-input" placeholder="What are you craving? (e.g., burger, salad, pizza)">
                    </div>
                    
                    <select name="category" class="filter-select">
                        <option value="">Any Category</option>
                        <option value="main">Main Course</option>
                        <option value="appetizer">Appetizers</option>
                        <option value="dessert">Desserts</option>
                        <option value="beverage">Beverages</option>
                        <option value="snack">Snacks</option>
                    </select>
 
                    <select name="cuisine" class="filter-select">
                        <option value="">Any Cuisine</option>
                        <option value="filipino">Filipino</option>
                        <option value="american">American</option>
                        <option value="italian">Italian</option>
                        <option value="japanese">Japanese</option>
                        <option value="chinese">Chinese</option>
                        <option value="mexican">Mexican</option>
                    </select>
                    
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </form>
 
            <div class="category-chips">
                <a href="browse_items.php" class="chip <?php echo !isset($_GET['category']) ? 'active' : ''; ?>" style="text-decoration: none;">Foods</a>
                <?php
                $stmt = $pdo->query("SELECT * FROM categories ORDER BY Categ_Name ASC");
                while ($cat = $stmt->fetch()):
                ?>
                    <a href="browse_items.php?category=<?php echo urlencode($cat['Categ_Name']); ?>" 
                       class="chip <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['Categ_Name']) ? 'active' : ''; ?>"
                       style="text-decoration: none;">
                        <?php echo htmlspecialchars($cat['Categ_Name']); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="items-grid">
            <?php
            $query = "SELECT i.*, m.Merch_Id, m.Merch_Name, c.Categ_Name 
                      FROM items i 
                      JOIN sellers s ON i.Seller_Id = s.Seller_Id 
                      JOIN merchants m ON s.Merch_Id = m.Merch_Id 
                      LEFT JOIN categories c ON i.Item_Category = c.Categ_Id 
                      WHERE i.Item_Status = 'available' 
                      AND s.Sellr_Status = 'active' 
                      AND m.Merch_Status = 'active'";
            
            $params = [];
            if (!empty($_GET['q'])) {
                $query .= " AND (i.Item_Name LIKE ? OR i.Item_Description LIKE ?)";
                $search = "%" . $_GET['q'] . "%";
                $params[] = $search;
                $params[] = $search;
            }
            
            if (!empty($_GET['category'])) {
                $query .= " AND c.Categ_Name = ?";
                $params[] = $_GET['category'];
            }

            $query .= " ORDER BY RAND()";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $items = $stmt->fetchAll();

            if (count($items) > 0):
                foreach ($items as $item):
            ?>
                <div class="item-card">
                    <img src="<?php echo $item['Item_Image'] ? '../' . $item['Item_Image'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80&w=400&h=300'; ?>" alt="<?php echo htmlspecialchars($item['Item_Name']); ?>" class="item-image">
                    <div class="item-info">
                        <div class="item-header">
                            <h3 class="item-title"><?php echo htmlspecialchars($item['Item_Name']); ?></h3>
                            <span class="item-price">₱<?php echo number_format($item['Item_Price'], 2); ?></span>
                        </div>
                        <p class="item-seller" onclick="window.location.href='view_store.php?id=<?php echo $item['Merch_Id']; ?>'" style="cursor: pointer;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            <span style="hover: text-decoration: underline;"><?php echo htmlspecialchars($item['Merch_Name']); ?></span>
                        </p>
                        <div class="item-tags">
                            <span class="tag"><?php echo htmlspecialchars($item['Categ_Name'] ?: 'General'); ?></span>
                        </div>
                        <form action="cart_action.php" method="POST">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="item_id" value="<?php echo $item['Item_Id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="add-to-cart-btn">Add to Cart</button>
                        </form>
                    </div>
                </div>
            <?php 
                endforeach;
            else:
            ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 80px; color: #64748b;">
                    <p>No approved products found matching your search.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
