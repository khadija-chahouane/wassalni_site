<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chauffeur') {
    header("Location: auth.php");
    exit();
}

$host = 'localhost'; $db = 'transport_db'; $user = 'root'; $pass = '';
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);

// جلب طلبات الرحلات الجديدة الموجهة للسائقين أو العامة
$requests = $pdo->query("SELECT r.*, u.full_name as client_name FROM ride_requests r JOIN users u ON r.client_id = u.id WHERE r.statut = 'en_attente'")->fetchAll();

// حساب مكاسب اليوم للسائق
$driver_id = $_SESSION['user_id'];
$earnings = $pdo->prepare("SELECT SUM(prix_propose) as total FROM ride_requests WHERE driver_id = ? AND statut = 'terminee'");
$earnings->execute([$driver_id]);
$total_earned = $earnings->fetch()['total'] ?? 1450; // افتراضي للتجربة
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wasalni - Chauffeur Portal</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; flex-direction: column; height: 100vh; overflow: hidden; background: #f4f6f9; }
        
        /* الشريط العلوي */
        .navbar { background-color: #1e429f; color: white; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; z-index: 1000; }
        .navbar .brand { font-size: 20px; font-weight: bold; }
        .driver-status { background: #10b981; padding: 4px 10px; border-radius: 12px; font-size: 12px; color: white; display: inline-block; }

        .main-container { display: flex; flex: 1; height: calc(100vh - 60px); }
        
        /* القائمة الجانبية للشيفور (البانل اللي في الصورة) */
        .sidebar { width: 420px; background: white; padding: 20px; overflow-y: auto; box-shadow: -2px 0 15px rgba(0,0,0,0.08); z-index: 500; }
        .driver-profile { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; margin-bottom: 15px; }
        .driver-avatar { width: 50px; height: 50px; background: #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; color: #1e429f; }
        
        .section-title { font-size: 15px; font-weight: bold; color: #333; margin: 15px 0 10px 0; display: flex; justify-content: space-between; }
        
        /* بطاقات الطلبات الجديدة */
        .request-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
        .request-header { display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; margin-bottom: 5px; }
        .request-route { font-size: 13px; color: #64748b; margin-bottom: 8px; }
        .actions-btns { display: flex; gap: 8px; }
        .btn-accept { flex: 1; background: #2563eb; color: white; border: none; padding: 6px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-decline { flex: 1; background: #ef4444; color: white; border: none; padding: 6px; border-radius: 5px; cursor: pointer; font-weight: bold; }

        /* مكاسب اليوم */
        .earnings-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px; text-align: center; margin-top: 15px; }
        .earnings-box h4 { color: #1e429f; font-size: 13px; }
        .earnings-box .amount { font-size: 20px; font-weight: bold; color: #1e3a8a; margin-top: 5px; }

        #map { flex: 1; height: 100%; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">🚗 Wasalni Chauffeur Portal</div>
        <div>
            <span class="driver-status">● متصل (Online)</span>
            <a href="logout.php" style="color: white; margin-left: 15px; text-decoration: none; font-size: 13px;">خروج</a>
        </div>
    </div>

    <div class="main-container">
        <!-- القائمة الجانبية للشيفور -->
        <div class="sidebar">
            <div class="driver-profile">
                <div class="driver-avatar"><?= mb_substr($_SESSION['full_name'], 0, 1) ?></div>
                <div>
                    <h4 style="color: #1e429f;"><?= htmlspecialchars($_SESSION['full_name']) ?> ⭐ 4.9</h4>
                    <p style="font-size: 12px; color: #666;">سائق معتمد</p>
                </div>
            </div>

            <!-- الرحلة الحالية -->
            <div class="section-title"><span>الرحلة الحالية (Current Ride)</span></div>
            <div class="request-card" style="border-color: #3b82f6; background: #f0fdf4;">
                <p style="font-size: 13px;"><strong>الزبون:</strong> فاطمة الزهراء</p>
                <p style="font-size: 13px; margin: 4px 0;"><strong>الانطلاق:</strong> جامع الفنا (المرد)</p>
                <p style="font-size: 13px;"><strong>الوصول:</strong> حديقة ماجوريل (كليز)</p>
                <button class="btn-accept" style="margin-top: 8px; width: 100%; background: #10b981;">إنهاء الرحلة</button>
            </div>

            <!-- طلبات جديدة وسط المدينة (Intra-ville requests) -->
            <div class="section-title"><span>طلبات جديدة (New Ride Requests)</span></div>
            <?php if (count($requests) > 0): ?>
                <?php foreach($requests as $req): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <span><?= htmlspecialchars($req['client_name']) ?></span>
                            <span style="color: #059669;"><?= $req['prix_propose'] ?> MAD</span>
                        </div>
                        <div class="request-route">📍 من: <?= $req['lieu_depart'] ?> ➔ إلى: <?= $req['lieu_arrivee'] ?></div>
                        <div class="actions-btns">
                            <button class="btn-accept">قبول (Accept)</button>
                            <button class="btn-decline">رفض (Decline)</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="font-size: 13px; color: #888; text-align: center; padding: 10px;">لا توجد طلبات جديدة حالياً في هذه المنطقة.</p>
            <?php endif; ?>

            <!-- مكاسب اليوم -->
            <div class="earnings-box">
                <h4>مكاسب اليوم (Today's Earnings)</h4>
                <div class="amount"><?= $total_earned ?> MAD</div>
            </div>
        </div>

        <!-- الخريطة الواقعية (مثلاً مركّزة على مراكش وحومة جامع الفنا وكليز) -->
        <div id="map"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // الخريطة تبدأ من مراكش (جامع الفنا) وسط المدينة
        var map = L.map('map').setView([31.6295, -7.9811], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        // إضافة علامات تفاعلية وسط المدينة (مثلاً جامع الفنا وحديقة ماجوريل)
        L.marker([31.6258, -7.9891]).addTo(map).bindPopup("<b>نقطة الانطلاق:</b> جامع الفنا").openPopup();
        L.marker([31.6402, -8.0033]).addTo(map).bindPopup("<b>الوجهة:</b> حديقة ماجوريل (كليز)");
        
        var routeLine = L.polyline([
            [31.6258, -7.9891],
            [31.6402, -8.0033]
        ], {color: '#2563eb', weight: 5}).addTo(map);
    </script>
</body>
</html>