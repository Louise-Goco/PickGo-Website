<?php
require_once '../config.php';
require_once 'Navigation.php';

$merch_id = $_GET['id'] ?? 0;

// Fetch merchant details
$stmt = $pdo->prepare("SELECT * FROM merchants WHERE Merch_Id = ? AND Merch_Status = 'active'");
$stmt->execute([$merch_id]);
$merchant = $stmt->fetch();

if (!$merchant) {
    header('Location: browse_stores.php');
    exit;
}

// Fetch merchant items
$stmt = $pdo->prepare("
    SELECT i.*, c.Categ_Name 
    FROM items i 
    LEFT JOIN categories c ON i.Item_Category = c.Categ_Id 
    JOIN sellers s ON i.Seller_Id = s.Seller_Id
    WHERE s.Merch_Id = ? AND i.Item_Status = 'available'
    ORDER BY c.Categ_Name, i.Item_Name
");
$stmt->execute([$merch_id]);
$items = $stmt->fetchAll();

// Group items by category
$categories = [];
foreach ($items as $item) {
    $cat = $item['Categ_Name'] ?: 'General';
    $categories[$cat][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($merchant['Merch_Name']); ?> - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        body { background: #f8fafc; }
        .content-container { max-width: 1100px; margin: 0 auto 60px; padding: 0 40px; }
        
        .store-banner-container { width: 100%; height: 280px; overflow: hidden; background: #e2e8f0; border-radius: 32px; margin-top: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .store-banner { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .store-banner:hover { transform: scale(1.02); }
        
        .store-profile-header { display: flex; align-items: flex-end; gap: 32px; padding: 32px 0; border-bottom: 1px solid rgba(0,0,0,0.05); margin-bottom: 40px; }
        .store-logo-main { width: 130px; height: 130px; border-radius: 32px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 10px 20px rgba(0,0,0,0.05); object-fit: cover; }
        
        .store-main-info { flex-grow: 1; }
        .store-title-text { font-size: 34px; font-weight: 850; color: #0f172a; margin: 0 0 12px 0; letter-spacing: -1px; }
        .store-meta-tags { display: flex; gap: 12px; align-items: center; }
        .badge-tag { padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; background: #fff; color: #475569; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .badge-primary { background: #f97316; color: #fff; border-color: #f97316; }
        
        .store-description { background: #fff; padding: 32px; border-radius: 24px; border: 1px solid rgba(0,0,0,0.03); margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .store-description h3 { font-size: 12px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px; }
        .store-description p { color: #475569; line-height: 1.7; font-size: 16px; }

        .category-scroll-wrapper { position: sticky; top: 75px; background: #f8fafc; z-index: 100; margin: 0 -40px 32px; padding: 12px 40px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .category-nav { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; }
        .category-nav::-webkit-scrollbar { display: none; }
        .category-link { padding: 10px 18px; border-radius: 12px; color: #64748b; text-decoration: none; font-weight: 600; transition: all 0.2s; font-size: 14px; white-space: nowrap; background: #fff; border: 1px solid #e2e8f0; }
        .category-link:hover { color: #f97316; border-color: #f97316; background: #fff7ed; }
        .category-link.active { background: #0f172a; color: #fff; border-color: #0f172a; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        
        .menu-section { margin-bottom: 60px; scroll-margin-top: 150px; }
        .section-title { font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 30px; display: flex; align-items: center; gap: 16px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
        .product-card { background: #fff; border-radius: 24px; border: 1px solid rgba(0,0,0,0.03); overflow: hidden; transition: all 0.3s ease; display: flex; flex-direction: column; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); }
        .product-image-container { position: relative; height: 200px; }
        .product-image { width: 100%; height: 100%; object-fit: cover; }
        .add-quick { position: absolute; bottom: 12px; right: 12px; width: 44px; height: 44px; border-radius: 14px; background: #f97316; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px rgba(249, 115, 22, 0.2); transition: all 0.2s; }
        .add-quick:hover { transform: scale(1.1); background: #ea580c; }
        
        .product-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .product-name { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .product-desc { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; }
        .product-price { font-size: 20px; font-weight: 800; color: #0f172a; }
        
        @media (max-width: 768px) {
            .content-container { padding: 0 20px; }
            .store-profile-header { flex-direction: column; text-align: center; gap: 20px; padding: 30px 0; }
            .store-logo-main { width: 120px; height: 120px; border-radius: 24px; }
            .store-title-text { font-size: 28px; }
            .store-meta-tags { justify-content: center; }
            .category-scroll-wrapper { margin: 0 -20px 24px; padding: 10px 20px; }
        }
    </style>
</head>
<body>
    <?php Navigation::render(); ?>

    <div class="content-container">
        <div class="store-banner-container">
            <img src="<?php echo $merchant['Merch_Banner'] ? '../' . $merchant['Merch_Banner'] : 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&q=80&w=1200&h=400'; ?>" class="store-banner" alt="Store Banner">
        </div>
        <div class="store-profile-header">
            <img src="<?php echo $merchant['Merch_Logo'] ? '../' . $merchant['Merch_Logo'] : 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&q=80&w=200&h=200'; ?>" class="store-logo-main" alt="Store Logo">
            <div class="store-main-info">
                <h1 class="store-title-text"><?php echo htmlspecialchars($merchant['Merch_Name']); ?></h1>
                <div class="store-meta-tags">
                    <span class="badge-tag badge-primary"><?php echo htmlspecialchars($merchant['Merch_Type']); ?></span>
                    <span class="badge-tag">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 6px; vertical-align: middle;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?php echo date('h:i A', strtotime($merchant['Merch_OpeningTime'] ?: '08:00:00')); ?> - <?php echo date('h:i A', strtotime($merchant['Merch_ClosingTime'] ?: '20:00:00')); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($merchant['Merch_Description']): ?>
            <div class="store-description">
                <h3>About the Store</h3>
                <p><?php echo nl2br(htmlspecialchars($merchant['Merch_Description'])); ?></p>
            </div>
        <?php endif; ?>

        <div class="category-scroll-wrapper">
            <div class="category-nav">
                <?php foreach ($categories as $catName => $catItems): ?>
                    <a href="#<?php echo md5($catName); ?>" class="category-link"><?php echo htmlspecialchars($catName); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <main>
            <?php foreach ($categories as $catName => $catItems): ?>
                <section class="menu-section" id="<?php echo md5($catName); ?>">
                    <h2 class="section-title"><?php echo htmlspecialchars($catName); ?></h2>
                    <div class="products-grid">
                        <?php foreach ($catItems as $item): ?>
                            <div class="product-card">
                                <div class="product-image-container">
                                    <img src="<?php echo $item['Item_Image'] ? '../' . $item['Item_Image'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80&w=400&h=300'; ?>" class="product-image" alt="<?php echo htmlspecialchars($item['Item_Name']); ?>">
                                    <form action="cart_action.php" method="POST">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="item_id" value="<?php echo $item['Item_Id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="add-quick">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                        </button>
                                    </form>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name"><?php echo htmlspecialchars($item['Item_Name']); ?></h3>
                                    <p class="product-desc"><?php echo htmlspecialchars($item['Item_Description']); ?></p>
                                    <div class="product-footer">
                                        <span class="product-price">₱<?php echo number_format($item['Item_Price'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php if (empty($categories)): ?>
                <div style="text-align: center; padding: 100px 0; color: #94a3b8;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <h3>No products available in this store yet.</h3>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Category navigation highlighting
        const sections = document.querySelectorAll('.menu-section');
        const navLinks = document.querySelectorAll('.category-link');
        const scrollContainer = document.querySelector('.category-nav');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (window.scrollY >= sectionTop - 180) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active');
                    // Optional: auto-scroll the horizontal nav to keep active category in view
                    const offsetLeft = link.offsetLeft - scrollContainer.offsetLeft;
                    scrollContainer.scrollTo({
                        left: offsetLeft - 20,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
