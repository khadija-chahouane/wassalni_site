<?php
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
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_code = trim($_POST['verification_code']);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_code = ? AND is_verified = 0");
    $stmt->execute([$entered_code]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        $msg = "تم تفعيل الحساب بنجاح! يمكنك الآن تسجيل الدخول.";
    } else {
        $msg = "الكود غير صحيح أو أن الحساب مفعل مسبقاً.";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل الحساب - Wasalni</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #c9d6ff; font-family: Arial, sans-serif; }
        .verify-box { background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); text-align: center; width: 350px; }
        .verify-box input { width: 100%; padding: 12px; margin: 15px 0; border: 1px solid #ccc; border-radius: 8px; text-align: center; font-size: 18px; }
        .verify-box button { background-color: #512da8; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; width: 100%; font-size: 16px; }
        .verify-box button:hover { background-color: #3f1d91; }
        .msg { margin-top: 15px; color: #d9534f; font-weight: bold; }
    </style>
</head>
<body>

    <div class="verify-box">
        <h2>تفعيل الحساب</h2>
        <p>أدخل رمز التحقق الذي ظهر لك عند التسجيل:</p>
        
        <form action="verify.php" method="POST">
            <input type="text" name="verification_code" placeholder="أدخل الكود هنا" required autofocus>
            <button type="submit">تفعيل</button>
        </form>

        <?php if(!empty($msg)) echo "<p class='msg'>$msg</p>"; ?>
        
        <br>
        <a href="connecter.html" style="color: #512da8; text-decoration: none; font-size: 14px;">العودة لصفحة تسجيل الدخول</a>
    </div>

</body>
</html>