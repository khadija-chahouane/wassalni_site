<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$host = 'localhost'; $db = 'transport_db'; $user = 'root'; $pass = '';
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);

$client_id = $_SESSION['user_id'];
$message_alerte = "";

// جلب جنس المستخدم الحالي للفلترة
$stmtUser = $pdo->prepare("SELECT gender FROM users WHERE id = ?");
$stmtUser->execute([$client_id]);
$current_client = $stmtUser->fetch();
$client_gender = $current_client['gender'] ?? 'أنثى';

// إرسال طلب رحلة جديد مع الثمن المقترح والكيلومترات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_ride'])) {
    $depart = $_POST['depart'];
    $arrivee = $_POST['arrivee'];
    $distance = $_POST['distance'];
    $prix_propose = $_POST['prix_propose'];

    $stmt = $pdo->prepare("INSERT INTO ride_requests (client_id, lieu_depart, lieu_arrivee, distance_km, prix_propose, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')");
    if ($stmt->execute([$client_id, $depart, $arrivee, $distance, $prix_propose])) {
        $message_alerte = "✅ تم ارسال طلبك بنجاح! في انتظار قبول السائق المناسب.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wasalni - حجز رحلة ذكية</title>
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
        .btn-search { width: 100%; background: #1e429f; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .fare-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 8px; margin: 15px 0; text-align: center; }
        #map { flex: 1; height: 100%; }
        .alert { padding: 10px; background: #d1fae5; color: #065f46; border-radius: 8px; margin-bottom: 15px; font-size: 14px; text-align: center; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">🚗 Wasalni - طلب رحلة (عداد الكيلومترات والتفاوض)</div>
        <div>مرحباً، <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong> (<span style="color:#fbbf24;"><?= $client_gender ?></span>) | <a href="logout.php" style="color:white;">خروج</a></div>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <?php if ($message_alerte): ?><div class="alert"><?= $message_alerte ?></div><?php endif; ?>

            <h3>📍 حدد مسارك (داخل أو بين المدن)</h3>
            <form method="POST" id="rideForm">
                <div class="form-group">
                    <label>نقطة الانطلاق (حي أو شارع):</label>
                    <input type="text" name="depart" id="departInput" value="جامع الفنا، مراكش" required oninput="calculateRoute()">
                </div>

                <div class="form-group">
                    <label>الوجهة:</label>
                    <input type="text" name="arrivee" id="arriveeInput" value="حديقة ماجوريل، كليز" required oninput="calculateRoute()">
                </div>

                <div class="fare-box">
                    <p style="font-size: 13px; color: #166534;">المسافة المحسوبة (العداد): <strong id="distanceText">3.5 كم</strong></p>
                    <input type="hidden" name="distance" id="distanceVal" value="3.5">
                    <p style="font-size: 14px; margin-top: 5px; color: #166534;">الثمن المقترح (الافتراضي): <strong id="suggestedFare">25 MAD</strong></p>
                </div>

                <div class="form-group">
                    <label>💰 اقترح ثمنك الخاص (التفاوض - InDrive Style):</label>
                    <input type="number" name="prix_propose" id="customPrice" value="25" min="5" required>
                    <small style="color: #666; font-size: 11px;">يمكنك زيادة أو تقليل الثمن للتفاوض مع السائقين.</small>
                </div>

                <!-- خاصية فلترة الجنس -->
                <div class="form-group" style="background: #fdf2f8; padding: 10px; border-radius: 8px; border: 1px dashed ec4899;">
                    <label style="color: #db2777;">🛡️ تفضيل نوع السائق (الأمان):</label>
                    <select name="gender_preference">
                        <option value="same">الرغبة في السفر مع نفس الجنس فقط (<?= $client_gender ?>)</option>
                        <option value="any">الكل (بدون تفضيل)</option>
                    </select>
                </div>

                <button type="submit" name="request_ride" class="btn-search">🚀 اطلب الرحلة وارسِل السعر</button>
            </form>
        </div>

        <div id="map"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([31.6295, -7.9811], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

        // إحداثيات تقريبية كمثال لتوضيح العداد
        var marker1 = L.marker([31.6258, -7.9891]).addTo(map).bindPopup("الانطلاق").openPopup();
        var marker2 = L.marker([31.6402, -8.0033]).addTo(map).bindPopup("الوصول");

        function calculateRoute() {
            // هنا محاكاة لحساب المسافة بالكلمتر (مثال: كل كلمتر بـ 6 درهم + 5 درهم انطلاق)
            var simulatedKm = (Math.random() * 5 + 2).toFixed(2); // كيلومتر عشوائي للتوضيح
            var calculatedPrice = Math.round(simulatedKm * 6 + 5);

            document.getElementById('distanceText').innerText = simulatedKm + ' كم';
            document.getElementById('distanceVal').value = simulatedKm;
            document.getElementById('suggestedFare').innerText = calculatedPrice + ' MAD';
            document.getElementById('customPrice').value = calculatedPrice;
        }
    </script>
</body>
</html>