<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$host = 'localhost'; $db = 'transport_db'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

$client_id = $_SESSION['user_id'];
$message_alerte = "";

// جلب جنس المستخدم الحالي للفلترة
$stmtUser = $pdo->prepare("SELECT gender FROM users WHERE id = ?");
$stmtUser->execute([$client_id]);
$current_client = $stmtUser->fetch();
$client_gender = $current_client['gender'] ?? 'أنثى';

// 1. إرسال طلب رحلة جديد (مخصّص للطلب الحر)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_ride'])) {
    $depart = $_POST['depart'];
    $arrivee = $_POST['arrivee'];
    $distance = $_POST['distance'];
    $prix_propose = $_POST['prix_propose'];

    $stmt = $pdo->prepare("INSERT INTO ride_requests (client_id, lieu_depart, lieu_arrivee, distance_km, prix_propose, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')");
    if ($stmt->execute([$client_id, $depart, $arrivee, $distance, $prix_propose])) {
        $message_alerte = "✅ تم ارسال طلبك بنجاح! في انتظار قبول السائق المناسب.";
    } else {
        $message_alerte = "❌ وقع خطأ أثناء الحجز.";
    }
}

// 2. معالجة حجز سيارة متوفرة مباشرة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserver'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $nombre_places = $_POST['nombre_places'];
    $date_voyage = $_POST['date_voyage'];

    // مثال لإضافة الحجز في جدول bookings (تأكد من توافق الأسماء مع قاعدة بياناتك)
    $stmtBook = $pdo->prepare("INSERT INTO bookings (client_id, vehicle_id, places_reserved, date_voyage, statut) VALUES (?, ?, ?, ?, 'confirmed')");
    if ($stmtBook->execute([$client_id, $vehicle_id, $nombre_places, $date_voyage])) {
        $message_alerte = "🎉 تم حجز السيارة بنجاح!";
    } else {
        $message_alerte = "❌ فشل حجز السيارة، حاول مرة أخرى.";
    }
}

// 3. البحث عن الطوموبيلات المتوفرة
$depart_choisi   = $_GET['depart'] ?? 'Casablanca';
$arrivee_choisie = $_GET['arrivee'] ?? 'Rabat';
$places_choisies = $_GET['places'] ?? 1;

$stmtVehicles = $pdo->prepare("SELECT * FROM vehicles WHERE ville_depart = ? AND ville_arrivee = ? AND places >= ? AND disponible = 1");
$stmtVehicles->execute([$depart_choisi, $arrivee_choisie, $places_choisies]);
$vehicles = $stmtVehicles->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wasalni - حجز رحلة ذكية</title>
    <!-- مكتبة الخريطة الحقيقية Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; flex-direction: column; height: 100vh; overflow: hidden; background: #f4f6f9; }
        .navbar { background-color: #1e429f; color: white; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; z-index: 1000; }
        .main-container { display: flex; flex: 1; height: calc(100vh - 60px); }
        .sidebar { width: 420px; background: white; padding: 20px; overflow-y: auto; box-shadow: -2px 0 15px rgba(0,0,0,0.08); z-index: 500; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-search { width: 100%; background: #1e429f; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .fare-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 8px; margin: 15px 0; text-align: center; }
        #map { flex: 1; height: 100%; }
        .rides-list { margin-top: 20px; }
        .ride-card { border: 2px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 12px; background: #fafafa; }
        .ride-header { display: flex; justify-content: space-between; font-weight: bold; color: #1e429f; margin-bottom: 6px; }
        .ride-price { color: #059669; }
        .ride-details { font-size: 12px; color: #555; margin-bottom: 8px; }
        .btn-book { width: 100%; background: #059669; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 5px; }
        .btn-book:hover { background: #047857; }
        .alert { padding: 10px; background: #d1fae5; color: #065f46; border-radius: 8px; margin-bottom: 15px; font-size: 14px; text-align: center; }
        .btn-logout { background: #e02424; color: white; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; }
        .navbar .avatar { width: 35px; height: 35px; background: #fff; color: #da0e74; border-radius: 50%; align-items: center; justify-content: center; font-weight: bold; }

        h3 { margin-top: 15px; margin-bottom: 10px; color: #333; font-size: 16px; }
    </style>
</head>
<body>

    <!-- الشريط العلوي --> 
    <div class="navbar">
        <div class="brand">🚗 Wasalni - طلب رحلة وتتبع المسار</div>
        <strong class="avatar"><?= $client_gender ?></strong>
        <div>مرحباً، <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong> 
         <a href="logout.php" class="btn-logout" >خروج</a>
        </div>
    </div>

    <!-- المحتوى الرئيسي -->
    <div class="main-container">
        <!-- القائمة الجانبية للحجز -->
        <div class="sidebar">
            <?php if ($message_alerte): ?>
                <div class="alert"><?= $message_alerte ?></div>
            <?php endif; ?>

            <h3>📍 حدد مسارك لإرسال طلب رحلة خاص</h3>
            <form method="POST">
                <div class="form-group">
                    <label>نقطة الانطلاق :</label>
                    <input type="text" name="depart" id="departInput" value="<?= htmlspecialchars($depart_choisi) ?>" placeholder="Casablanca" required oninput="updateMap(); calculateRoute();">
                </div>

                <div class="form-group">
                    <label>الوجهة:</label>
                    <input type="text" name="arrivee" id="arriveeInput" value="<?= htmlspecialchars($arrivee_choisie) ?>" placeholder="Rabat" required oninput="updateMap(); calculateRoute();">
                </div>

                <div class="form-group">
                    <label>عدد الركاب (المقاعد المطلوبة):</label>
                    <input type="number" name="places" min="1" max="5" value="<?= htmlspecialchars($places_choisies) ?>" required>
                </div>

                <div class="fare-box">
                    <p style="font-size: 13px; color: #166534;">المسافة المحسوبة (العداد): <strong id="distanceText">3.5 كم</strong></p>
                    <input type="hidden" name="distance" id="distanceVal" value="3.5">
                    <p style="font-size: 14px; margin-top: 5px; color: #166534;">الثمن المقترح (الافتراضي): <strong id="suggestedFare">25 MAD</strong></p>
                </div>

                <div class="form-group">
                    <label>💰 اقترح ثمنك الخاص :</label>
                    <input type="number" name="prix_propose" id="customPrice" value="25" min="5" required>
                    <small style="color: #666; font-size: 11px;">يمكنك زيادة أو تقليل الثمن للتفاوض مع السائقين.</small>
                </div>

                <!-- خاصية فلترة الجنس -->
                <div class="form-group" style="background: #fdf2f8; padding: 10px; border-radius: 8px; border: 1px dashed #db2777;">
                    <label style="color: #db2777;">🛡️ تفضيل نوع السائق (الأمان):</label>
                    <select name="gender_preference">
                        <option value="same">الرغبة في السفر مع نفس الجنس فقط (<?= $client_gender ?>)</option>
                        <option value="any">الكل (بدون تفضيل)</option>
                    </select>
                </div>

                <button type="submit" name="request_ride" class="btn-search">إرسال طلب الرحلة للسائقين</button>
            </form>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

            <!-- عرض السيارات المتوفرة من Database -->
            <div class="rides-list">
                <h3>🚘 السيارات المتوفرة حالياً</h3>
                <?php if (count($vehicles) > 0): ?>
                    <?php foreach ($vehicles as $car): 
                        $prix_total = $car['prix'] * $places_choisies;
                    ?>
                        <div class="ride-card">
                            <div class="ride-header">
                                <span><?= htmlspecialchars($car['marque'] . ' ' . $car['modele']) ?></span>
                                <span class="ride-price"><?= $prix_total ?> MAD</span>
                            </div>
                            <div class="ride-details">
                                💺 ثمن المقعد: <?= $car['prix'] ?> MAD | المقاعد المتاحة: <strong><?= $car['places'] ?></strong><br>
                                🛣️ من: <?= htmlspecialchars($car['ville_depart']) ?> ⬅️ إلى: <?= htmlspecialchars($car['ville_arrivee']) ?>
                            </div>

                            <!-- فورم تأكيد حجز السيارة -->
                            <form method="POST">
                                <input type="hidden" name="vehicle_id" value="<?= $car['id'] ?>">
                                <input type="hidden" name="nombre_places" value="<?= $places_choisies ?>">
                                <div class="form-group" style="margin-top: 8px;">
                                    <label style="font-size: 11px;">تاريخ ووقت الرحلة:</label>
                                    <input type="datetime-local" name="date_voyage" required style="padding: 5px; font-size: 12px;">
                                </div>
                                <button type="submit" name="reserver" class="btn-book">احجز هذه السيارة</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #888; font-size: 13px; text-align: center; margin-top: 10px;">
                        لا توجد سيارات متوفرة حالياً لهذا المسار.
                    </p>
                <?php endif; ?>
            </div>

        </div>

        <!-- الخريطة الحقيقية -->
        <div id="map"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const cityCoords = {
            "Casablanca": [33.5731, -7.5898],
            "Rabat": [34.0209, -6.8416],
            "Marrakech": [31.6295, -7.9811],
            "Fes": [34.0181, -5.0078],
            "Meknes": [33.8935, -5.5473],
            "Agadir": [30.4278, -9.5981],
            "Tanger": [35.7595, -5.8340],
            "El Jadida": [33.2316, -8.5007],
            "Tetouan": [35.5889, -5.3626],
            "Oujda": [34.6814, -1.9086],
            "Essaouira": [31.5085, -9.7595]
        };

        var map = L.map('map').setView([31.6295, -7.9811], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
            attribution: '&copy; OpenStreetMap' 
        }).addTo(map);

        var markerDepart, markerArrivee, routeLine;

        function updateMap() {
            var dep = document.getElementById('departInput').value.trim();
            var arr = document.getElementById('arriveeInput').value.trim();

            if (markerDepart) map.removeLayer(markerDepart);
            if (markerArrivee) map.removeLayer(markerArrivee);
            if (routeLine) map.removeLayer(routeLine);

            if (cityCoords[dep] && cityCoords[arr]) {
                var coord1 = cityCoords[dep];
                var coord2 = cityCoords[arr];

                markerDepart = L.marker(coord1).addTo(map).bindPopup("<b>الانطلاق:</b> " + dep);
                markerArrivee = L.marker(coord2).addTo(map).bindPopup("<b>الوصول:</b> " + arr);

                routeLine = L.polyline([coord1, coord2], {color: '#1e429f', weight: 4, dashArray: '8, 8'}).addTo(map);
                map.fitBounds(routeLine.getBounds(), {padding: [50, 50]});
            }
        }

        // تشغيل الدالة عند تحميل الصفحة لأول مرة
        updateMap();

        function calculateRoute() {
            var simulatedKm = (Math.random() * 20 + 5).toFixed(2); 
            var calculatedPrice = Math.round(simulatedKm * 4 + 10);

            document.getElementById('distanceText').innerText = simulatedKm + ' كم';
            document.getElementById('distanceVal').value = simulatedKm;
            document.getElementById('suggestedFare').innerText = calculatedPrice + ' MAD';
            document.getElementById('customPrice').value = calculatedPrice;
        }
    </script>
</body>
</html>