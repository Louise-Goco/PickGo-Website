<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $item_id = $_POST['item_id'] ?? 0;

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($action === 'add') {
        $quantity = $_POST['quantity'] ?? 1;
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['item_id'] == $item_id) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $_SESSION['cart'][] = [
                'item_id' => $item_id,
                'quantity' => (int)$quantity
            ];
        }

        // Sync with DB
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, item_id, quantity) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
        $stmt->execute([$user['id'], $item_id, $quantity]);
    } elseif ($action === 'update') {
        $change = $_POST['change'] ?? 0;
        foreach ($_SESSION['cart'] as $key => &$item) {
            if ($item['item_id'] == $item_id) {
                $item['quantity'] += $change;
                if ($item['quantity'] <= 0) {
                    unset($_SESSION['cart'][$key]);
                }
                break;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array

        // Sync with DB
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$change, $user['id'], $item_id]);
        
        // Clean up zero or negative quantities
        $pdo->prepare("DELETE FROM cart WHERE quantity <= 0")->execute();
    } elseif ($action === 'remove') {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['item_id'] == $item_id) {
                unset($_SESSION['cart'][$key]);
                break;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array

        // Sync with DB
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$user['id'], $item_id]);
    } elseif ($action === 'apply_promo') {
        $promo_code = strtoupper(trim($_POST['promo_code'] ?? ''));
        $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE Code = ? AND Is_Active = 1 AND Expiry_Date >= CURRENT_DATE()");
        $stmt->execute([$promo_code]);
        $promo = $stmt->fetch();
        
        if ($promo && ($promo['Usage_Limit'] <= 0 || $promo['Current_Usage'] < $promo['Usage_Limit'])) {
            $_SESSION['applied_promo'] = [
                'id' => $promo['Promo_Id'],
                'code' => $promo['Code'],
                'type' => $promo['Discount_Type'],
                'value' => (float)$promo['Discount_Value']
            ];
            $_SESSION['promo_msg'] = "Promo code applied successfully!";
        } else {
            $_SESSION['promo_err'] = "Invalid or expired promo code.";
        }
    } elseif ($action === 'remove_promo') {
        unset($_SESSION['applied_promo']);
        $_SESSION['promo_msg'] = "Promo code removed.";
    }
}

// Redirect back to the previous page or cart
$referer = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
header("Location: $referer");
exit;
