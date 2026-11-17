<?php
session_start();
$host = 'localhost';
$db   = 'transport_db'; 
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("خطأ في الاتصال: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // ==========================================
    // 1. منطق التسجيل (SIGN UP)
    // ==========================================
    if ($action == 'signup') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $cin      = trim($_POST['cin'] ?? ''); 
        $phone    = trim($_POST['phone'] ?? '');
        $password = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT); 
        $gender   = $_POST['gender'] ?? ''; 
        $role     = $_POST['role'] ?? 'client'; // واش كليون ولا شيفور

        $verification_code = rand(100000, 999999);

        // التحقق واش الإيميل ديجا كاين
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            echo "<script>alert('هذا البريد الإلكتروني مسجل مسبقاً.'); window.location.href='auth.php';</script>";
        } else {
            try {
                $pdo->beginTransaction();

                // إدخال المستخدم في جدول users
                $sql = "INSERT INTO users (full_name, email, cin, phone, password, gender, role, verification_code, is_verified) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$fullname, $email, $cin, $phone, $password, $gender, $role, $verification_code]);
                
                $user_id = $pdo->lastInsertId();

                // إذا كان شيفور، كنضيفو ليه حتى الطوموبيل ديالو في جدول vehicles
                if ($role == 'chauffeur') {
                    $marque    = trim($_POST['marque'] ?? 'Dacia');
                    $modele    = trim($_POST['modele'] ?? 'Logan');
                    $matricule = trim($_POST['matricule'] ?? '00000-A-00');
                    $prix      = floatval($_POST['prix'] ?? 100);
                    $places    = intval($_POST['places'] ?? 4);
                    $ville_dep = trim($_POST['ville_depart'] ?? 'Casablanca');
                    $ville_arr = trim($_POST['ville_arrivee'] ?? 'Rabat');

                    $sqlCar = "INSERT INTO vehicles (marque, modele, ville_depart, ville_arrivee, prix, places, disponible, chauffeur_id) 
                               VALUES (?, ?, ?, ?, ?, ?, 1, ?)";
                    $stmtCar = $pdo->prepare($sqlCar);
                    $stmtCar->execute([$marque, $modele, $ville_dep, $ville_arr, $prix, $places, $user_id]);
                }

                $pdo->commit();
                echo "<script>alert('تم التسجيل بنجاح! كود التفعيل الخاص بك هو: $verification_code'); window.location.href='verify.php';</script>";

            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<script>alert('حدث خطأ أثناء التسجيل: " . $e->getMessage() . "');</script>";
            }
        }

    // ==========================================
    // 2. منطق الدخول (SIGN IN)
    // ==========================================
    } elseif ($action == 'signin') {
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['is_verified'] == 0) {
                echo "<script>alert('المرجو تفعيل حسابك عبر إدخال الكود أولاً.'); window.location.href='verify.php';</script>";
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // توجيه المستخدم حسبد الدور ديالو
                echo "<script>alert('مرحباً بك مجدداً " . $user['full_name'] . "'); window.location.href='dashboard.php';</script>";
            } else {
                echo "<script>alert('كلمة السر غير صحيحة.'); window.location.href='auth.php';</script>";
            }
        } else {
            echo "<script>alert('المستخدم غير موجود.'); window.location.href='auth.php';</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Login & Register - Wasalni</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* إضافات بسيطة لتنسيق حقول السائق الجديدة */
        .driver-fields { background: #f0f4ff; padding: 10px; border-radius: 8px; margin-top: 10px; border: 1px dashed #1e429f; display: none; }
        .driver-fields h4 { font-size: 13px; color: #1e429f; margin-bottom: 8px; }
    </style>
</head>

<body>
<div class="splash-screen" id="splashScreen">
    <div class="splash-logo">
        <div class="splash-mark">W</div>
        <h1>Welcome to Wasalni!</h1>
        <p>Your journey starts here. Enjoy a comfortable and safe ride wherever you go.</p>
        <br>
        <p>Travel smart. Travel with Wasalni.</p>
    </div>
</div>

<div class="container" id="container">

    <!-- SIGN UP FORM -->
    <div class="form-container sign-up">
        <form action="auth.php" method="POST">
            <h1>Create Account</h1>
            <input type="hidden" name="action" value="signup">

            <input type="text" name="fullname" placeholder="الاسم الكامل" required>
            <input type="email" name="email" placeholder="البريد الإلكتروني" required>
            <input type="text" name="cin" placeholder="رقم البطاقة الوطنية (CIN)" required>
            <input type="tel" name="phone" placeholder="رقم الهاتف" required>
            <input type="password" name="password" placeholder="كلمة السر" required>
            
            <select name="gender" required>
                <option value="" disabled selected>اختاري الجنس</option>
                <option value="ذكـر">ذكر</option>
                <option value="أنثى">أنثى</option>
            </select>

            <!-- اختيار الدور: كليون ولا شيفور -->
            <select name="role" id="roleSelect" onchange="toggleDriverFields()" required style="margin-top: 10px;">
                <option value="client">زبون (Client)</option>
                <option value="chauffeur">سائق (Chauffeur)</option>
            </select>

            <!-- حقول خاصة بالسائق تظهر فقط إذا اختار الشيفور -->
            <div id="driverFields" class="driver-fields">
                <h4>معلومات السيارة والرحلة الخاصة بالسائق:</h4>
                <input type="text" name="marque" placeholder="ماركة السيارة (مثلاً: Dacia)" style="margin-bottom: 8px; padding: 8px; width: 100%;">
                <input type="text" name="modele" placeholder="موديل السيارة (مثلاً: Logan)" style="margin-bottom: 8px; padding: 8px; width: 100%;">
                <input type="text" name="matricule" placeholder="رقم اللوحة (Matricule)" style="margin-bottom: 8px; padding: 8px; width: 100%;">
                <input type="text" name="ville_depart" placeholder="مدينة الانطلاق" style="margin-bottom: 8px; padding: 8px; width: 100%;">
                <input type="text" name="ville_arrivee" placeholder="مدينة الوصول" style="margin-bottom: 8px; padding: 8px; width: 100%;">
                <input type="number" name="prix" placeholder="ثمن المقعد (درهم)" style="margin-bottom: 8px; padding: 8px; width: 100%;">
                <input type="number" name="places" placeholder="عدد المقاعد المتوفرة" min="1" max="7" style="padding: 8px; width: 100%;">
            </div>

            <button type="submit" style="margin-top: 15px;">SIGN UP</button>
        </form>
    </div>

    <!-- SIGN IN FORM -->
    <div class="form-container sign-in">
        <form action="auth.php" method="POST">
            <h1>Sign In</h1>
            <input type="hidden" name="action" value="signin">
            
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>

            <a href="#">Forget Your Password?</a>
            <button type="submit">SIGN IN</button>
        </form>
    </div>

    <!-- TOGGLE CONTAINER -->
    <div class="toggle-container">
        <div class="toggle">
            <div class="toggle-panel toggle-left">
                <h1>Welcome Back!</h1>
                <p>Enter your personal details to use all of site features</p>
                <button class="hidden" id="login">SIGN IN</button>
            </div>
            <div class="toggle-panel toggle-right">
                <h1>Hello, Friend!</h1>
                <p>Register with your personal details to use all of site features</p>
                <button class="hidden" id="register">SIGN UP</button>
            </div>
        </div>
    </div>

</div>

<script src="script.js"></script>
<script>
    // دالة لإظهار أو إخفاء حقول الطوموبيل إذا اخترتي شيفور
function toggleDriverFields() {
var role = document.getElementById('roleSelect').value;
var driverBox = document.getElementById('driverFields');
if (role === 'chauffeur') {
        driverBox.style.display = 'block';
    } else {
        driverBox.style.display = 'none';
    }
}
</script>
</body>
</html>