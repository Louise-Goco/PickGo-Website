<?php
require_once '../config.php';
require_once 'Navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Stores - PickGo</title>
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

        .category-chips { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
        .chip { padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 20px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
        .chip:hover { background: #e2e8f0; color: #0f172a; }
        .chip.active { background: #fff7ed; color: #ea580c; border-color: #fed7aa; }

        /* Stores Grid */
        .stores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; margin-top: 40px; }
        .store-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; display: flex; flex-direction: column; cursor: pointer; text-decoration: none; color: inherit; }
        .store-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .store-cover { width: 100%; height: 160px; object-fit: cover; background: #e2e8f0; }
        .store-info { padding: 20px; position: relative; display: flex; flex-direction: column; flex-grow: 1; }
        .store-avatar { width: 64px; height: 64px; border-radius: 50%; border: 4px solid #fff; position: absolute; top: -32px; left: 20px; background: #fff; object-fit: cover; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .store-header { margin-top: 24px; display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .store-title { margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; }
        .store-rating { display: flex; align-items: center; gap: 4px; font-weight: 600; color: #0f172a; font-size: 14px; background: #f8fafc; padding: 4px 8px; border-radius: 8px; }
        .store-rating svg { color: #eab308; fill: #eab308; }
        .store-category { margin: 0 0 12px 0; color: #64748b; font-size: 14px; }
        
        .store-meta { display: flex; gap: 16px; margin-top: auto; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .meta-item { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; font-weight: 500; }

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
                <h1>Discover Local Stores</h1>
                <p>Find the best restaurants, cafes, and markets near you.</p>
            </div>
            
            <form action="" method="GET">
                <div class="search-controls">
                    <div class="search-input-group">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" name="q" class="search-input" placeholder="Search for stores (e.g., Burger Joint, Cafe)">
                    </div>
                    
                    <select name="type" class="filter-select">
                        <option value="">Any Type</option>
                        <option value="restaurant">Restaurant</option>
                        <option value="fastfood">Fast Food</option>
                        <option value="cafe">Cafe / Coffee Shop</option>
                        <option value="bakery">Bakery</option>
                        <option value="market">Market / Groceries</option>
                    </select>

                    <select name="sort" class="filter-select">
                        <option value="recommended">Recommended</option>
                        <option value="rating">Highest Rated</option>
                        <option value="delivery_time">Fastest Delivery</option>
                        <option value="distance">Nearest</option>
                    </select>
                    
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </form>

            <div class="category-chips">
                <a href="browse_stores.php" class="chip <?php echo !isset($_GET['type']) ? 'active' : ''; ?>" style="text-decoration: none;">All Stores</a>
                <?php
                $stmt = $pdo->query("SELECT DISTINCT Merch_Type FROM merchants WHERE Merch_Status = 'active' LIMIT 10");
                while ($type = $stmt->fetch()):
                    if (!$type['Merch_Type']) continue;
                ?>
                    <a href="browse_stores.php?type=<?php echo urlencode($type['Merch_Type']); ?>" 
                       class="chip <?php echo (isset($_GET['type']) && $_GET['type'] == $type['Merch_Type']) ? 'active' : ''; ?>"
                       style="text-decoration: none;">
                        <?php echo htmlspecialchars($type['Merch_Type']); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="stores-grid">
            <?php
            $query = "SELECT * FROM merchants WHERE Merch_Status = 'active'";
            $params = [];
            
            if (!empty($_GET['q'])) {
                $query .= " AND (Merch_Name LIKE ? OR Merch_Description LIKE ? OR Merch_Type LIKE ?)";
                $search = "%" . $_GET['q'] . "%";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
            
            if (!empty($_GET['type'])) {
                $query .= " AND Merch_Type = ?";
                $params[] = $_GET['type'];
            }
            
            if (isset($_GET['sort'])) {
                switch($_GET['sort']) {
                    case 'rating': $query .= " ORDER BY created_at DESC"; break; // Rating column not in merchants, using created_at for now
                    case 'recommended': $query .= " ORDER BY RAND()"; break;
                    default: $query .= " ORDER BY created_at DESC";
                }
            } else {
                $query .= " ORDER BY created_at DESC";
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $stores = $stmt->fetchAll();
            
            $stmt_fee = $pdo->prepare("SELECT Setting_Value FROM settings WHERE Setting_Key = 'delivery_fee'");
            $stmt_fee->execute();
            $setting_fee = $stmt_fee->fetch(PDO::FETCH_ASSOC);
            $delivery_fee = $setting_fee ? floatval($setting_fee['Setting_Value']) : 49.00;
            
            if (count($stores) > 0):
                foreach ($stores as $store):
            ?>
                <a href="view_store.php?id=<?php echo $store['Merch_Id']; ?>" class="store-card">
                    <img src="<?php echo $store['Merch_Banner'] ? '../' . $store['Merch_Banner'] : 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&q=80&w=800&h=400'; ?>" alt="<?php echo htmlspecialchars($store['Merch_Name']); ?> Cover" class="store-cover">
                    <div class="store-info">
                        <img src="<?php echo $store['Merch_Logo'] ? '../' . $store['Merch_Logo'] : 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&q=80&w=200&h=200'; ?>" alt="<?php echo htmlspecialchars($store['Merch_Name']); ?>" class="store-avatar">
                        <div class="store-header">
                            <h3 class="store-title"><?php echo htmlspecialchars($store['Merch_Name']); ?></h3>
                            <div class="store-rating">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                4.5
                            </div>
                        </div>
                        <p class="store-category"><?php echo htmlspecialchars($store['Merch_Type']); ?></p>
                        
                        <div class="store-meta">
                            <div class="meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                20-30 min
                            </div>
                            <div class="meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                ₱<?php echo number_format($delivery_fee, 2); ?> Delivery
                            </div>
                        </div>
                    </div>
                </a>
            <?php 
                endforeach;
            else:
            ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 80px; color: #64748b;">
                    <p>No active stores found matching your search.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
