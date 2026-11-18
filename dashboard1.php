<?php
session_start();

// التحقق واش المستخدم مسجل الدخول (إلا بغيتي تجربيها نيشان بلا login حيدي هاد 4 سطورا مؤقتاً)
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// الاتصال بقاعدة البيانات transport_db
$host = 'localhost';
$db   = 'transport_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("خطأ في الاتصال: " . $e->getMessage());
}

$message_alerte = "";

// 1. معالجة طلب الحجز ملي المستخدم كيكليكي على "تأكيد الحجز"
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserver'])) {
    $user_id       = $_SESSION['user_id'];
    $vehicle_id    = $_POST['vehicle_id'];
    $date_voyage   = $_POST['date_voyage'];
    $nombre_places = $_POST['nombre_places'];

    $stmt = $pdo->prepare("INSERT INTO reservations (user_id, vehicle_id, date_voyage, nombre_places, statut) VALUES (?, ?, ?, ?, 'en_attente')");
    if ($stmt->execute([$user_id, $vehicle_id, $date_voyage, $nombre_places])) {
        $message_alerte = "✅ تم إرسال طلب الحجز بنجاح! سيتواصل معك السائق قريباً.";
    } else {
        $message_alerte = "❌ وقع خطأ أثناء الحجز.";
    }
}

// 2. جلب المدن المتوفرة من قاعدة البيانات باش نسهلو الاختيار
$villes_depart  = $pdo->query("SELECT DISTINCT ville_depart FROM vehicles WHERE disponible = 1")->fetchAll(PDO::FETCH_COLUMN);
$villes_arrivee = $pdo->query("SELECT DISTINCT ville_arrivee FROM vehicles WHERE disponible = 1")->fetchAll(PDO::FETCH_COLUMN);

// 3. البحث عن الطوموبيلات المتوفرة على حساب اختيار المستخدم
$depart_choisi  = $_GET['depart'] ?? 'Casablanca';
$arrivee_choisie = $_GET['arrivee'] ?? 'Rabat';
$places_choisies = $_GET['places'] ?? 1;

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE ville_depart = ? AND ville_arrivee = ? AND places >= ? AND disponible = 1");
$stmt->execute([$depart_choisi, $arrivee_choisie, $places_choisies]);
$vehicles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wasalni - حجز رحلة</title>
    
    <!-- مكتبة الخريطة الحقيقية Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; flex-direction: column; height: 100vh; overflow: hidden; background: #f4f6f9; }

        /* الشريط العلوي الأزرق بحال الصورة */
        .navbar {
            background-color: #1e429f;
            color: white;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        .navbar .brand { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .navbar .user-info { display: flex; align-items: center; gap: 15px; }
        .navbar .avatar { width: 35px; height: 35px; background: #fff; color: #1e429f; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .btn-logout { background: #e02424; color: white; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; }

        /* تقسيم الشاشة: الخريطة + القائمة الجانبية */
        .main-container { display: flex; flex: 1; height: calc(100vh - 60px); }

        /* القائمة الجانبية */
        .sidebar {
            width: 400px;
            background: white;
            padding: 20px;
            overflow-y: auto;
            box-shadow: -2px 0 15px rgba(0,0,0,0.08);
            z-index: 500;
        }
        .sidebar h3 { color: #1e429f; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; }

        /* فورم البحث */
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; font-weight: 600; }
        .form-group select, .form-group input {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none;
        }
        .form-group select:focus, .form-group input:focus { border-color: #1e429f; }
        .btn-search {
            width: 100%; background: #1e429f; color: white; border: none; padding: 12px;
            border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.3s;
        }
        .btn-search:hover { background: #16327a; }

        /* بطاقات السيارات (Select Ride) */
        .rides-list { margin-top: 20px; }
        .ride-card {
            border: 2px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 12px;
            transition: 0.2s; background: #fafafa;
        }
        .ride-card:hover { border-color: #1e429f; background: #f0f5ff; }
        .ride-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; color: #111; }
        .ride-price { color: #059669; font-size: 16px; }
        .ride-details { font-size: 13px; color: #666; margin: 6px 0; }
        .btn-book {
            width: 100%; background: #059669; color: white; border: none; padding: 8px;
            border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 5px;
        }
        .btn-book:hover { background: #047857; }

        /* الخريطة */
        #map { flex: 1; height: 100%; z-index: 1; }

        .alert { padding: 10px; background: #d1fae5; color: #065f46; border-radius: 8px; margin-bottom: 15px; font-size: 14px; text-align: center; }
    </style>
</head>
<body>

    <!-- الشريط العلوي -->
    <div class="navbar">
        <div class="brand">🚗 Wasalni Maroc</div>
        <div class="user-info">
            <span>مرحباً، <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong></span>
            <div class="avatar"><?= mb_substr($_SESSION['full_name'], 0, 1) ?></div>
            <a href="logout.php" class="btn-logout">خروج</a>
        </div>
    </div>

    <!-- المحتوى الرئيسي -->
    <div class="main-container">
        
        <!-- القائمة الجانبية للحجز -->
        <div class="sidebar">
            <?php if ($message_alerte): ?>
                <div class="alert"><?= $message_alerte ?></div>
            <?php endif; ?>

            <h3>📍 إلى أين أنت ذاهب؟</h3>
            <form method="GET" action="dashboard.php">
                <div class="form-group">
                    <label>مدينة الانطلاق:</label>
                    <select name="depart" id="departSelect" onchange="updateMap()">
                        <?php foreach ($villes_depart as $ville): ?>
                            <option value="<?= $ville ?>" <?= ($depart_choisi == $ville) ? 'selected' : '' ?>><?= $ville ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>مدينة الوصول (الوجهة):</label>
                    <select name="arrivee" id="arriveeSelect" onchange="updateMap()">
                        <?php foreach ($villes_arrivee as $ville): ?>
                            <option value="<?= $ville ?>" <?= ($arrivee_choisie == $ville) ? 'selected' : '' ?>><?= $ville ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>عدد الركاب (المقاعد):</label>
                    <input type="number" name="places" min="1" max="7" value="<?= htmlspecialchars($places_choisies) ?>" required>
                </div>

                <button type="submit" class="btn-search">🔍 بحث عن رحلة</button>
            </form>

            <!-- عرض السيارات المتوفرة من Database -->
            <div class="rides-list">
                <h3>🚘 السيارات المتوفرة (Select Ride)</h3>
                
                <?php if (count($vehicles) > 0): ?>
                <?php foreach ($vehicles as $car): 
                    // حساب الثمن الإجمالي بناءً على عدد الركاب اللي اختار الكليون
                    $prix_total = $car['prix'] * $places_choisies;
                ?>
                        <div class="ride-card">
                            <div class="ride-header">
                                <span><?= htmlspecialchars($car['marque'] . ' ' . $car['modele']) ?></span>
                                <span class="ride-price"><?= $prix_total ?> MAD <small style="font-size:11px; color:#555;">(لـ <?= $places_choisies ?> ركاب)</small></span>
                            </div>
                            <div class="ride-details">
                                     💺 ثمن المقعد الواحد: <?= $car['prix'] ?> MAD | <BR></BR>
                                💺 المقاعد المتوفرة: <strong><?= $car['places'] ?></strong> BR| 
                                🛣️ <?= $car['ville_depart'] ?> ⬅️ <?= $car['ville_arrivee'] ?>
                            </div>

                            <!-- فورم تأكيد الحجز -->
                            <form method="POST" action="dashboard.php">
                                <input type="hidden" name="vehicle_id" value="<?= $car['id'] ?>">
                                <input type="hidden" name="nombre_places" value="<?= $places_choisies ?>">
                                <div class="form-group" style="margin-top: 8px;">
                                    <label style="font-size: 11px;">تاريخ ووقت الرحلة:</label>
                                    <input type="datetime-local" name="date_voyage" required style="padding: 5px; font-size: 12px;">
                                </div>
                                <button type="submit" name="reserver" class="btn-book">احجز الآن (Réserver)</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #888; font-size: 14px; text-align: center; margin-top: 20px;">
                        لا توجد سيارات متوفرة حالياً لهذا المسار أو لعدد الركاب المطلوب. جربي مساراً آخر (مثلاً Casablanca إلى Rabat).
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- الخريطة الحقيقية -->
        <div id="map"></div>
    </div>

    <!-- مكتبة الخريطة الحقيقية Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // إحداثيات المدن المغربية الموجودة في قاعدة البيانات ديالك
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

        // تهيئة الخريطة على المغرب
        var map = L.map('map').setView([33.5731, -7.5898], 7);

        // تحميل شكل الخريطة (OpenStreetMap)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var markerDepart, markerArrivee, routeLine;

        function updateMap() {
            var dep = document.getElementById('departSelect').value;
            var arr = document.getElementById('arriveeSelect').value;

            // مسح العلامات القديمة
            if (markerDepart) map.removeLayer(markerDepart);
            if (markerArrivee) map.removeLayer(markerArrivee);
            if (routeLine) map.removeLayer(routeLine);

            if (cityCoords[dep] && cityCoords[arr]) {
                var coord1 = cityCoords[dep];
                var coord2 = cityCoords[arr];

                // وضع دبوس الانطلاق والوصول
                markerDepart = L.marker(coord1).addTo(map).bindPopup("<b>الانطلاق:</b> " + dep).openPopup();
                markerArrivee = L.marker(coord2).addTo(map).bindPopup("<b>الوصول:</b> " + arr);

                // رسم خط المسار بين المدينتين
                routeLine = L.polyline([coord1, coord2], {color: '#1e429f', weight: 4, dashArray: '8, 8'}).addTo(map);

                // ضبط الزوم باش يبانو المدينتين بجوج
                map.fitBounds(routeLine.getBounds(), {padding: [50, 50]});
            }
        }

        // تشغيل الدالة عند فتح الصفحة
        updateMap();
    </script>
</body>
</html>
