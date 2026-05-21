<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: ../login.php');
    exit;
}

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: dashboard.php');
    exit;
}

// Fetch Order details
$stmt = $pdo->prepare("
    SELECT o.*, m.Merch_Name, m.Merch_Address
    FROM orders o
    JOIN sellers s ON o.Seller_Id = s.Seller_Id
    JOIN merchants m ON s.Merch_Id = m.Merch_Id
    WHERE o.Order_Id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation - Order #<?php echo $orderId; ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; overflow: hidden; }
        #map { height: 100vh; width: 100vw; }
        .overlay { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); width: 90%; max-width: 400px; z-index: 1000; }
        .nav-card { background: white; padding: 20px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .back-btn { position: fixed; top: 20px; left: 20px; z-index: 1000; background: white; width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-decoration: none; color: #0f172a; }
        .status-pill { background: #3b82f6; color: white; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; display: inline-block; margin-bottom: 12px; }
        .address-item { display: flex; gap: 12px; margin-bottom: 16px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; margin-top: 4px; flex-shrink: 0; }
        .dot-pickup { background: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }
        .dot-delivery { background: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2); }
        .address-text h4 { margin: 0 0 4px; font-size: 12px; color: #64748b; text-transform: uppercase; }
        .address-text p { margin: 0; font-size: 14px; font-weight: 600; color: #0f172a; }
        .btn-finish { width: 100%; background: #0f172a; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
    <a href="order_details.php?id=<?php echo $orderId; ?>" class="back-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
    </a>

    <div id="map"></div>

    <div class="overlay">
        <div class="nav-card">
            <span class="status-pill">Currently Navigating</span>
            
            <div class="address-item">
                <div class="dot dot-pickup"></div>
                <div class="address-text">
                    <h4>Pickup: <?php echo htmlspecialchars($order['Merch_Name']); ?></h4>
                    <p id="pickupText"><?php echo htmlspecialchars($order['Merch_Address']); ?></p>
                </div>
            </div>

            <div style="height: 20px; border-left: 2px dashed #e2e8f0; margin-left: 5px; margin-top: -16px; margin-bottom: 4px;"></div>

            <div class="address-item">
                <div class="dot dot-delivery"></div>
                <div class="address-text">
                    <h4>Delivery</h4>
                    <p id="deliveryText"><?php echo htmlspecialchars($order['Delivery_Address']); ?></p>
                </div>
            </div>

            <button onclick="window.location.href='order_details.php?id=<?php echo $orderId; ?>'" class="btn-finish">View Order Controls</button>
        </div>
    </div>

    <script>
        // Initialize Map
        const map = L.map('map').setView([14.5995, 120.9842], 13); // Default to Manila
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const pickupAddr = "<?php echo addslashes($order['Merch_Address']); ?>";
        const deliveryAddr = "<?php echo addslashes($order['Delivery_Address']); ?>";

        async function geocode(address) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
                const data = await response.json();
                if (data.length > 0) {
                    return [parseFloat(data[0].lat), parseFloat(data[0].lon)];
                }
            } catch (e) { console.error(e); }
            return null;
        }

        async function initNavigation() {
            const pickupCoords = await geocode(pickupAddr);
            const deliveryCoords = await geocode(deliveryAddr);

            const markers = [];

            if (pickupCoords) {
                const pickupMarker = L.marker(pickupCoords, {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: "<div style='background-color:#3b82f6; width:15px; height:15px; border-radius:50%; border:3px solid white; box-shadow:0 0 10px rgba(0,0,0,0.3)'></div>",
                        iconSize: [15, 15],
                        iconAnchor: [7, 7]
                    })
                }).addTo(map).bindPopup("<b>Pickup:</b> " + pickupAddr);
                markers.push(pickupCoords);
            }

            if (deliveryCoords) {
                const deliveryMarker = L.marker(deliveryCoords, {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: "<div style='background-color:#ef4444; width:15px; height:15px; border-radius:50%; border:3px solid white; box-shadow:0 0 10px rgba(0,0,0,0.3)'></div>",
                        iconSize: [15, 15],
                        iconAnchor: [7, 7]
                    })
                }).addTo(map).bindPopup("<b>Delivery:</b> " + deliveryAddr);
                markers.push(deliveryCoords);
            }

            if (markers.length === 2) {
                const polyline = L.polyline(markers, {color: '#3b82f6', weight: 4, opacity: 0.6, dashArray: '10, 10'}).addTo(map);
                map.fitBounds(polyline.getBounds(), {padding: [100, 100]});
            } else if (markers.length === 1) {
                map.setView(markers[0], 15);
            }
        }

        initNavigation();
    </script>
</body>
</html>
