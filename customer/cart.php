<?php
require_once '../config.php';
require_once 'Navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <style>
        .page-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        .cart-header { margin-top: 40px; margin-bottom: 30px; }
        .cart-header h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; }
        .cart-header p { color: #64748b; margin: 0; font-size: 16px; }

        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 32px; }
        
        /* Cart Items */
        .cart-items { display: flex; flex-direction: column; gap: 20px; }
        .cart-item { display: flex; align-items: center; gap: 20px; background: #fff; padding: 20px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .item-image { width: 100px; height: 100px; border-radius: 12px; object-fit: cover; background: #e2e8f0; border: 1px solid rgba(0,0,0,0.05); }
        .item-details { flex: 1; }
        .item-store { color: #f97316; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px; }
        .item-name { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; }
        .item-price { font-size: 15px; font-weight: 500; color: #64748b; margin: 0 0 12px 0; }
        
        .quantity-controls { display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 6px; border-radius: 8px; width: fit-content; border: 1px solid #e2e8f0; }
        .qty-btn { width: 32px; height: 32px; border-radius: 6px; border: none; background: #fff; font-size: 18px; font-weight: 600; cursor: pointer; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s; display: flex; align-items: center; justify-content: center; }
        .qty-btn:hover { background: #f1f5f9; }
        .qty-value { font-size: 16px; font-weight: 600; color: #0f172a; min-width: 24px; text-align: center; }
        
        .item-total-section { text-align: right; }
        .item-total { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0; }
        .remove-btn { color: #ef4444; font-size: 14px; font-weight: 600; cursor: pointer; border: none; background: none; padding: 0; display: inline-flex; align-items: center; gap: 4px; }
        .remove-btn:hover { text-decoration: underline; }

        /* Order Summary */
        .summary-card { background: #fff; padding: 32px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); height: fit-content; position: sticky; top: 20px; }
        .summary-card h2 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 24px 0; }
        
        .discount-section { display: flex; gap: 12px; margin-bottom: 24px; }
        .discount-input { flex: 1; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 14px; font-family: 'Inter', sans-serif; transition: border-color 0.2s; }
        .discount-input:focus { outline: none; border-color: #f97316; }
        .apply-btn { padding: 12px 20px; background: #0f172a; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.2s; }
        .apply-btn:hover { background: #1e293b; }
        
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 16px; color: #64748b; font-size: 15px; }
        .summary-row.discount { color: #10b981; }
        .summary-divider { height: 1px; background: #e2e8f0; margin: 20px 0; }
        .summary-total { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .summary-total-label { font-size: 18px; font-weight: 600; color: #0f172a; }
        .summary-total-price { font-size: 28px; font-weight: 800; color: #f97316; }
        
        .checkout-btn { width: 100%; padding: 16px; background: #f97316; color: #fff; border: none; border-radius: 12px; font-size: 18px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.2s, transform 0.2s; box-shadow: 0 4px 6px rgba(249, 115, 22, 0.2); }
        .checkout-btn:hover { background: #ea580c; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(249, 115, 22, 0.3); }

        /* Responsive */
        @media (max-width: 900px) {
            .cart-layout { grid-template-columns: 1fr; }
            .summary-card { position: static; }
        }
        @media (max-width: 600px) {
            .cart-item { flex-direction: column; align-items: flex-start; }
            .item-image { width: 100%; height: 200px; }
            .item-total-section { text-align: left; margin-top: 16px; width: 100%; display: flex; justify-content: space-between; align-items: center; }
            .item-total { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <?php Navigation::render(); ?>

        <div class="cart-header">
            <h1>Your Cart</h1>
            <p>Review your items before proceeding to checkout.</p>
        </div>

        <div class="cart-layout">
            <!-- Cart Items -->
            <div class="cart-items">
                <?php
                $subtotal = 0;
                $item_count = 0;
                
                if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])):
                    foreach ($_SESSION['cart'] as $cart_item):
                        $stmt = $pdo->prepare("
                            SELECT i.*, m.Merch_Name 
                            FROM items i 
                            JOIN sellers s ON i.Seller_Id = s.Seller_Id 
                            JOIN merchants m ON s.Merch_Id = m.Merch_Id 
                            WHERE i.Item_Id = ?
                        ");
                        $stmt->execute([$cart_item['item_id']]);
                        $item = $stmt->fetch();
                        
                        if ($item):
                            $line_total = $item['Item_Price'] * $cart_item['quantity'];
                            $subtotal += $line_total;
                            $item_count += $cart_item['quantity'];
                ?>
                    <div class="cart-item">
                        <img src="<?php echo $item['Item_Image'] ? '../' . $item['Item_Image'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80&w=200&h=200'; ?>" alt="<?php echo htmlspecialchars($item['Item_Name']); ?>" class="item-image">
                        <div class="item-details">
                            <div class="item-store"><?php echo htmlspecialchars($item['Merch_Name']); ?></div>
                            <h3 class="item-name"><?php echo htmlspecialchars($item['Item_Name']); ?></h3>
                            <p class="item-price">₱<?php echo number_format($item['Item_Price'], 2); ?></p>
                            
                            <div class="quantity-controls">
                                <form action="cart_action.php" method="POST" style="display: flex; align-items: center; gap: 12px;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="item_id" value="<?php echo $item['Item_Id']; ?>">
                                    <button type="submit" name="change" value="-1" class="qty-btn">-</button>
                                    <span class="qty-value"><?php echo $cart_item['quantity']; ?></span>
                                    <button type="submit" name="change" value="1" class="qty-btn">+</button>
                                </form>
                            </div>
                        </div>
                        <div class="item-total-section">
                            <div class="item-total">₱<?php echo number_format($line_total, 2); ?></div>
                            <form action="cart_action.php" method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="item_id" value="<?php echo $item['Item_Id']; ?>">
                                <button type="submit" class="remove-btn">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php 
                        endif;
                    endforeach;
                else:
                ?>
                    <div style="text-align: center; padding: 40px; background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05);">
                        <p style="color: #64748b; margin-bottom: 20px;">Your cart is empty.</p>
                        <a href="browse_items.php" class="apply-btn" style="text-decoration: none;">Browse Menu</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Summary -->
            <div>
                <div class="summary-card">
                    <h2>Order Summary</h2>
                    
                    <?php if (isset($_SESSION['promo_msg'])): ?>
                        <div style="color: #10b981; font-size: 13px; font-weight: 600; margin-bottom: 12px;"><?php echo $_SESSION['promo_msg']; unset($_SESSION['promo_msg']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['promo_err'])): ?>
                        <div style="color: #ef4444; font-size: 13px; font-weight: 600; margin-bottom: 12px;"><?php echo $_SESSION['promo_err']; unset($_SESSION['promo_err']); ?></div>
                    <?php endif; ?>

                    <?php 
                    $applied_promo = $_SESSION['applied_promo'] ?? null;
                    if ($applied_promo): 
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; background: #f0fdf4; border: 1px solid #86efac; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                <span style="font-weight: 700; color: #166534; font-family: monospace; font-size: 14px;"><?php echo htmlspecialchars($applied_promo['code']); ?></span>
                            </div>
                            <form action="cart_action.php" method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="remove_promo">
                                <button type="submit" style="background: none; border: none; color: #ef4444; font-weight: 700; font-size: 13px; cursor: pointer;">Remove</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form action="cart_action.php" method="POST" class="discount-section">
                            <input type="hidden" name="action" value="apply_promo">
                            <input type="text" name="promo_code" class="discount-input" placeholder="Promo Code" required>
                            <button type="submit" class="apply-btn">Apply</button>
                        </form>
                    <?php endif; ?>

                    <?php
                    $stmt_fee = $pdo->prepare("SELECT Setting_Value FROM settings WHERE Setting_Key = 'delivery_fee'");
                    $stmt_fee->execute();
                    $setting_fee = $stmt_fee->fetch(PDO::FETCH_ASSOC);
                    $base_delivery_fee = $setting_fee ? floatval($setting_fee['Setting_Value']) : 49.00;

                    $delivery_fee = $item_count > 0 ? $base_delivery_fee : 0;
                    
                    $discount = 0;
                    if ($applied_promo && $subtotal > 0) {
                        if ($applied_promo['type'] === 'percentage') {
                            $discount = round(($subtotal * $applied_promo['value']) / 100, 2);
                        } else {
                            $discount = min($subtotal, $applied_promo['value']);
                        }
                    }
                    $total = max(0, $subtotal + $delivery_fee - $discount);
                    ?>

                    <div class="summary-row">
                        <span>Subtotal (<?php echo $item_count; ?> items)</span>
                        <span>₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <span>₱<?php echo number_format($delivery_fee, 2); ?></span>
                    </div>
                    <?php if ($discount > 0): ?>
                    <div class="summary-row discount">
                        <span>Discount (<?php echo htmlspecialchars($applied_promo['code']); ?>)</span>
                        <span>-₱<?php echo number_format($discount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-total">
                        <span class="summary-total-label">Total</span>
                        <span class="summary-total-price">₱<?php echo number_format($total, 2); ?></span>
                    </div>

                    <?php if ($item_count > 0): ?>
                        <a href="checkout.php" class="checkout-btn" style="display: block; text-align: center; text-decoration: none;">Proceed to Checkout</a>
                    <?php else: ?>
                        <button class="checkout-btn" style="opacity: 0.5; cursor: not-allowed;">Proceed to Checkout</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateQty(btn, change) {
            const qtySpan = btn.parentElement.querySelector('.qty-value');
            let currentQty = parseInt(qtySpan.innerText);
            let newQty = currentQty + change;
            
            if (newQty >= 1) {
                qtySpan.innerText = newQty;
            }
        }
    </script>
</body>
</html>
