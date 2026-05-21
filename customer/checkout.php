<?php
require_once '../config.php';
require_once 'Navigation.php';

// Fetch all user addresses
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC");
$stmt->execute([$user['id']]);
$user_addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$default_address = !empty($user_addresses) ? $user_addresses[0] : null;

// Fetch cart items with details
$cart_items_detailed = [];
$subtotal = 0;
$item_count = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_item) {
        $stmt = $pdo->prepare("SELECT i.*, s.Seller_Id, m.Merch_Name 
                              FROM items i 
                              JOIN sellers s ON i.Seller_Id = s.Seller_Id
                              JOIN merchants m ON s.Merch_Id = m.Merch_Id
                              WHERE i.Item_Id = ?");
        $stmt->execute([$cart_item['item_id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $line_total = $item['Item_Price'] * $cart_item['quantity'];
            $subtotal += $line_total;
            $item_count += $cart_item['quantity'];
            
            $cart_items_detailed[] = array_merge($item, ['quantity' => $cart_item['quantity'], 'line_total' => $line_total]);
        }
    }
}

// Redirect if cart is empty
if (empty($cart_items_detailed)) {
    header('Location: cart.php');
    exit;
}

// Fetch delivery fee from settings table
$stmt_fee = $pdo->prepare("SELECT Setting_Value FROM settings WHERE Setting_Key = 'delivery_fee'");
$stmt_fee->execute();
$setting_fee = $stmt_fee->fetch(PDO::FETCH_ASSOC);
$delivery_fee = $setting_fee ? floatval($setting_fee['Setting_Value']) : 49.00;

$applied_promo = $_SESSION['applied_promo'] ?? null;
$discount = 0.00;
if ($applied_promo && $subtotal > 0) {
    if ($applied_promo['type'] === 'percentage') {
        $discount = round(($subtotal * $applied_promo['value']) / 100, 2);
    } else {
        $discount = min($subtotal, $applied_promo['value']);
    }
}
$total = max(0, $subtotal + $delivery_fee - $discount);

// Handle Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $pdo->beginTransaction();
        
        $delivery_address = $_POST['full_address'] . " " . ($_POST['unit'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'COD';
        
        $batch_id = 'BATCH-' . strtoupper(uniqid());

        // Group cart items by Seller_Id
        $items_by_seller = [];
        foreach ($cart_items_detailed as $item) {
            $seller_id = $item['Seller_Id'];
            $items_by_seller[$seller_id][] = $item;
        }

        $seller_count = count($items_by_seller);
        $base_split_fee = round($delivery_fee / $seller_count, 2);
        $base_split_discount = round($discount / $seller_count, 2);
        $total_fee_allocated = 0;
        $total_discount_allocated = 0;
        $idx = 0;

        foreach ($items_by_seller as $seller_id => $items) {
            $idx++;
            $seller_subtotal = 0;
            foreach ($items as $item) {
                $seller_subtotal += $item['line_total'];
            }
            
            // Adjust last item's allocation to account for any rounding difference
            if ($idx === $seller_count) {
                $allocated_fee = $delivery_fee - $total_fee_allocated;
                $allocated_discount = $discount - $total_discount_allocated;
            } else {
                $allocated_fee = $base_split_fee;
                $total_fee_allocated += $allocated_fee;
                $allocated_discount = $base_split_discount;
                $total_discount_allocated += $allocated_discount;
            }
            
            $seller_total = max(0, $seller_subtotal + $allocated_fee - $allocated_discount);

            // Create Order for this specific store
            $stmt = $pdo->prepare("INSERT INTO orders (Customer_Id, Seller_Id, Order_Total, Order_Status, Delivery_Address, Payment_Method, Batch_Id) VALUES (?, ?, ?, 'pending', ?, ?, ?)");
            $stmt->execute([$user['id'], $seller_id, $seller_total, $delivery_address, $payment_method, $batch_id]);
            $order_id = $pdo->lastInsertId();
            
            // Create Order Items for this store
            $stmt_item = $pdo->prepare("INSERT INTO order_items (Order_Id, Food_Name, Quantity, Price) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt_item->execute([$order_id, $item['Item_Name'], $item['quantity'], $item['Item_Price']]);
            }
        }
        
        // Update promo usage if applied
        if ($applied_promo) {
            $pdo->prepare("UPDATE promo_codes SET Current_Usage = Current_Usage + 1 WHERE Promo_Id = ?")->execute([$applied_promo['id']]);
            unset($_SESSION['applied_promo']);
        }
        
        // Clear Cart
        unset($_SESSION['cart']);
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user['id']]);
        
        $pdo->commit();
        header('Location: dashboard.php?order_success=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to place order: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - PickGo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../login.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .page-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        


        .checkout-header { margin-top: 40px; margin-bottom: 30px; display: flex; align-items: center; gap: 16px; }
        .checkout-header h1 { font-size: 32px; font-weight: 700; color: #0f172a; margin: 0; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 16px; transition: color 0.2s; }
        .back-link:hover { color: #f97316; }

        .checkout-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 32px; }
        
        .checkout-section { background: #fff; padding: 32px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; }
        .section-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 24px 0; display: flex; align-items: center; gap: 12px; }
        
        /* Form Inputs */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 8px; }
        .form-input { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 15px; font-family: 'Inter', sans-serif; transition: border-color 0.2s; box-sizing: border-box; background: #f8fafc; }
        .form-input:focus { outline: none; border-color: #f97316; background: #fff; box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* Map Mock */
        .map-container { width: 100%; height: 200px; background: #e2e8f0; border-radius: 12px; margin-bottom: 20px; position: relative; overflow: hidden; border: 1px solid #cbd5e1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #64748b; }
        .map-container img { width: 100%; height: 100%; object-fit: cover; opacity: 0.6; position: absolute; top: 0; left: 0; z-index: 1; }
        .map-overlay { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; }
        .map-pin { color: #f97316; margin-bottom: 8px; }
        .pick-map-btn { padding: 10px 20px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-family: 'Inter', sans-serif; }
        .pick-map-btn:hover { background: #f8fafc; }

        /* Payment Methods */
        .payment-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
        .payment-option { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 20px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .payment-option:hover { border-color: #cbd5e1; background: #f8fafc; }
        .payment-option.active { border-color: #f97316; background: #fff7ed; }
        .payment-option svg { color: #475569; }
        .payment-option.active svg { color: #f97316; }
        .payment-name { font-weight: 600; font-size: 15px; color: #0f172a; }

        /* Summary */
        .summary-card { background: #fff; padding: 32px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); position: sticky; top: 20px; }
        .summary-card h2 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 24px 0; }
        
        .order-items-preview { margin-bottom: 24px; display: flex; flex-direction: column; gap: 12px; }
        .preview-item { display: flex; justify-content: space-between; font-size: 15px; color: #475569; }
        .preview-qty { font-weight: 600; color: #0f172a; margin-right: 8px; }
        
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 16px; color: #64748b; font-size: 15px; }
        .summary-row.discount { color: #10b981; }
        .summary-divider { height: 1px; background: #e2e8f0; margin: 20px 0; }
        .summary-total { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .summary-total-label { font-size: 18px; font-weight: 600; color: #0f172a; }
        .summary-total-price { font-size: 28px; font-weight: 800; color: #f97316; }
        
        .confirm-btn { width: 100%; padding: 16px; background: #f97316; color: #fff; border: none; border-radius: 12px; font-size: 18px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.2s, transform 0.2s; box-shadow: 0 4px 6px rgba(249, 115, 22, 0.2); }
        .confirm-btn:hover { background: #ea580c; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(249, 115, 22, 0.3); }

        /* Map Picker Modal */
        .map-modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .map-modal { background: #fff; width: 100%; max-width: 700px; border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; animation: modalScaleIn 0.2s ease-out; }
        .map-modal-header { padding: 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .map-modal-header h3 { margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; }
        .modal-close-btn { background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; font-size: 18px; font-weight: 600; transition: all 0.2s; }
        .modal-close-btn:hover { background: #e2e8f0; color: #0f172a; }
        .map-modal-body { padding: 24px; }
        .quick-pins { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .quick-pin-chip { padding: 8px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .quick-pin-chip:hover, .quick-pin-chip.active { background: #fff7ed; color: #ea580c; border-color: #f97316; }
        .map-modal-footer { padding: 20px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; }
        @keyframes modalScaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        @media (max-width: 900px) {
            .checkout-layout { grid-template-columns: 1fr; }
            .summary-card { position: static; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <?php Navigation::render(); ?>

        <div class="checkout-header">
            <a href="cart.php" class="back-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Cart
            </a>
            <h1>Checkout</h1>
        </div>

        <form action="checkout.php" method="POST">
            <div class="checkout-layout">
                
                <div class="checkout-details">
                    <!-- Delivery Address Section -->
                    <div class="checkout-section">
                        <h2 class="section-title">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Delivery Address
                        </h2>
                        
                        <div class="map-container">
                            <img src="https://images.unsplash.com/photo-1524661135-423995f22d0b?auto=format&fit=crop&q=80&w=800&h=400" alt="Map">
                            <div class="map-overlay">
                                <svg class="map-pin" width="32" height="32" viewBox="0 0 24 24" fill="#f97316" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <button type="button" class="pick-map-btn" onclick="pickOnMap()">Select Location on Map</button>
                            </div>
                        </div>

                        <?php if (!empty($user_addresses)): ?>
                        <div class="form-group">
                            <label class="form-label">Select Preferred Delivery Location</label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                <?php foreach ($user_addresses as $index => $addr): 
                                    $is_sel = ($default_address && $default_address['id'] == $addr['id']);
                                ?>
                                    <div class="address-option <?php echo $is_sel ? 'active' : ''; ?>" onclick="selectSavedAddress(this, '<?php echo addslashes($addr['address_line_1'] . ', ' . $addr['city']); ?>')" style="padding: 16px; border: 2px solid <?php echo $is_sel ? '#f97316' : '#e2e8f0'; ?>; border-radius: 12px; cursor: pointer; transition: all 0.2s; background: <?php echo $is_sel ? '#fff7ed' : '#fff'; ?>;">
                                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
                                            <span><?php echo htmlspecialchars($addr['label']); ?></span>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $is_sel ? '#f97316' : 'none'; ?>" stroke="<?php echo $is_sel ? '#f97316' : '#cbd5e1'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>
                                        </div>
                                        <div style="font-size: 13px; color: #64748b; line-height: 1.4;">
                                            <?php echo htmlspecialchars($addr['address_line_1']); ?>, <br><?php echo htmlspecialchars($addr['city']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Full Address</label>
                            <input type="text" name="full_address" id="fullAddress" class="form-input" placeholder="e.g. 123 Main Street, Block 4" value="<?php echo htmlspecialchars($default_address ? ($default_address['address_line_1'] . ', ' . $default_address['city']) : ''); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Floor / Unit / Room (Optional)</label>
                                <input type="text" name="unit" class="form-input" placeholder="e.g. Apt 4B">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Number</label>
                                <input type="tel" name="phone" class="form-input" placeholder="e.g. 0912 345 6789" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Delivery Instructions (Optional)</label>
                            <input type="text" class="form-input" placeholder="e.g. Leave at the lobby">
                        </div>
                    </div>

                    <!-- Payment Method Section -->
                    <div class="checkout-section">
                        <h2 class="section-title">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                            Payment Method
                        </h2>
                        
                        <div class="payment-methods">
                            <input type="hidden" name="payment_method" id="paymentMethod" value="COD">
                            <!-- Option 1: COD -->
                            <div class="payment-option active" onclick="selectPayment(this, 'COD')">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><path d="M12 12h.01"></path><path d="M17 12h.01"></path><path d="M7 12h.01"></path></svg>
                                <span class="payment-name">Cash on Delivery</span>
                            </div>
                            
                            <!-- Option 2: Card -->
                            <div class="payment-option" onclick="selectPayment(this, 'Card')">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                <span class="payment-name">Credit/Debit Card</span>
                            </div>
                            
                            <!-- Option 3: GCash -->
                            <div class="payment-option" onclick="selectPayment(this, 'GCash')">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                <span class="payment-name">GCash / E-Wallet</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div>
                    <div class="summary-card">
                        <h2>Your Order</h2>
                        
                        <div class="order-items-preview">
                            <?php foreach ($cart_items_detailed as $item): ?>
                            <div class="preview-item">
                                <div><span class="preview-qty"><?php echo $item['quantity']; ?>x</span> <?php echo htmlspecialchars($item['Item_Name']); ?></div>
                                <span>₱<?php echo number_format($item['line_total'], 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-divider"></div>

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

                        <button type="submit" name="place_order" class="confirm-btn">Confirm Order</button>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- Map Modal -->
    <div id="mapModal" class="map-modal-backdrop">
        <div class="map-modal">
            <div class="map-modal-header">
                <h3>Select Delivery Location on Map</h3>
                <button type="button" class="modal-close-btn" onclick="closeMapModal()">&times;</button>
            </div>
            <div class="map-modal-body">
                <p style="color: #64748b; font-size: 14px; margin-top: 0; margin-bottom: 16px;">Choose from prominent Cebu locations or pinpoint your precise location below.</p>
                <div class="quick-pins">
                    <div class="quick-pin-chip" onclick="selectQuickPin(this, 'Cebu IT Park, Lahug, Cebu City')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                        Cebu IT Park
                    </div>
                    <div class="quick-pin-chip" onclick="selectQuickPin(this, 'Ayala Center Cebu, Cebu Business Park, Cebu City')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                        Ayala Center Cebu
                    </div>
                    <div class="quick-pin-chip" onclick="selectQuickPin(this, 'SM Seaside City Cebu, South Road Properties, Cebu City')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                        SM Seaside Cebu
                    </div>
                    <div class="quick-pin-chip" onclick="selectQuickPin(this, 'Mactan-Cebu International Airport, Lapu-Lapu City')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                        Mactan Airport
                    </div>
                    <div class="quick-pin-chip" onclick="selectQuickPin(this, 'Fuente Osmeña Circle, Cebu City')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                        Fuente Osmeña
                    </div>
                </div>

                <div style="position: relative; margin-bottom: 16px;">
                    <div id="modalLeafletMap" style="width: 100%; height: 320px; border-radius: 16px; border: 1px solid #cbd5e1; z-index: 1;"></div>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -100%); z-index: 1000; pointer-events: none; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="#f97316" stroke="#fff" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3" fill="#fff"></circle></svg>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Pin Address Details</label>
                    <input type="text" id="modalMapInput" class="form-input" placeholder="Drag map or type street / building name" value="Cebu IT Park, Lahug, Cebu City">
                </div>
            </div>
            <div class="map-modal-footer">
                <button type="button" class="btn-outline" style="padding: 12px 24px; border-radius: 12px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; font-weight: 600;" onclick="closeMapModal()">Cancel</button>
                <button type="button" class="confirm-btn" style="width: auto; padding: 12px 28px;" onclick="confirmMapLocation()">Confirm Pin Address Details</button>
            </div>
        </div>
    </div>

    <script>
        function selectSavedAddress(element, addressStr) {
            document.querySelectorAll('.address-option').forEach(el => {
                el.classList.remove('active');
                el.style.borderColor = '#e2e8f0';
                el.style.background = '#fff';
                const svg = el.querySelector('svg');
                if(svg) { svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', '#cbd5e1'); }
            });
            element.classList.add('active');
            element.style.borderColor = '#f97316';
            element.style.background = '#fff7ed';
            const svg = element.querySelector('svg');
            if(svg) { svg.setAttribute('fill', '#f97316'); svg.setAttribute('stroke', '#f97316'); }

            document.getElementById('fullAddress').value = addressStr;
        }

        function selectPayment(element, method) {
            // Remove active class from all options
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('active'));
            // Add active class to clicked option
            element.classList.add('active');
            // Update hidden input
            document.getElementById('paymentMethod').value = method;
        }

        let leafletMap = null;

        function pickOnMap() {
            document.getElementById('mapModal').style.display = 'flex';
            if (!leafletMap) {
                leafletMap = L.map('modalLeafletMap').setView([10.3298, 123.9060], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(leafletMap);

                leafletMap.on('moveend', function() {
                    const center = leafletMap.getCenter();
                    document.getElementById('modalMapInput').placeholder = "Fetching address details...";
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${center.lat}&lon=${center.lng}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.display_name) {
                                document.getElementById('modalMapInput').value = data.display_name;
                            }
                        })
                        .catch(err => console.error("Reverse geocoding error:", err));
                });
            } else {
                leafletMap.invalidateSize();
            }
        }

        function closeMapModal() {
            document.getElementById('mapModal').style.display = 'none';
        }

        const quickCoordinates = {
            'Cebu IT Park, Lahug, Cebu City': [10.3298, 123.9060],
            'Ayala Center Cebu, Cebu Business Park, Cebu City': [10.3182, 123.9052],
            'SM Seaside City Cebu, South Road Properties, Cebu City': [10.2821, 123.8808],
            'Mactan-Cebu International Airport, Lapu-Lapu City': [10.3255, 123.9792],
            'Fuente Osmeña Circle, Cebu City': [10.3121, 123.8924]
        };

        function selectQuickPin(element, locationStr) {
            document.querySelectorAll('.quick-pin-chip').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('modalMapInput').value = locationStr;
            if (leafletMap && quickCoordinates[locationStr]) {
                leafletMap.flyTo(quickCoordinates[locationStr], 16);
            }
        }

        function confirmMapLocation() {
            const loc = document.getElementById('modalMapInput').value;
            if(loc.trim()) {
                document.getElementById('fullAddress').value = loc;
                document.querySelectorAll('.address-option').forEach(el => {
                    el.classList.remove('active');
                    el.style.borderColor = '#e2e8f0';
                    el.style.background = '#fff';
                    const svg = el.querySelector('svg');
                    if(svg) { svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', '#cbd5e1'); }
                });
                closeMapModal();
            } else {
                alert('Please enter or select a valid location');
            }
        }
    </script>
</body>
</html>
