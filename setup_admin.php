<?php
// ⚠️ DELETE THIS FILE AFTER USE!
$host = getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$port = (int)(getenv('MYSQLPORT') ?: 3306);
$dbname = getenv('MYSQL_DATABASE') ?: 'railway';

$msg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? 'Super Admin');

    if ($email && $password) {
        $conn = new mysqli($host, $user, $pass, $dbname, $port);
        if (!$conn->connect_error) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // Delete old admin and insert new
            $conn->query("DELETE FROM admin");
            $stmt = $conn->prepare("INSERT INTO admin (name, email, mobile, password) VALUES (?, ?, '9900000001', ?)");
            $stmt->bind_param('sss', $name, $email, $hashed);
            if ($stmt->execute()) {
                $msg = "✅ Admin set ho gaya! Ab login karo.";
                $success = true;
            } else {
                $msg = "❌ Error: " . $conn->error;
            }
            $conn->close();
        } else {
            $msg = "❌ DB Connection failed: " . $conn->connect_error;
        }
    } else {
        $msg = "⚠️ Email aur Password dono bharo!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Digital Bandhak - Admin Setup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial; background: #1a1a2e; color: white; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .box { background: #16213e; padding: 40px; border-radius: 15px; width: 350px; box-shadow: 0 0 30px rgba(0,0,0,0.5); }
        h2 { color: #e67e22; text-align: center; margin-bottom: 30px; }
        input { width: 100%; padding: 12px; margin: 8px 0; border-radius: 8px; border: 1px solid #444; background: #0f3460; color: white; box-sizing: border-box; font-size: 15px; }
        button { width: 100%; padding: 14px; background: #e67e22; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; margin-top: 10px; }
        button:hover { background: #d35400; }
        .msg { padding: 12px; border-radius: 8px; margin: 15px 0; text-align: center; font-weight: bold; }
        .success { background: #27ae60; }
        .error { background: #c0392b; }
        a { color: #e67e22; }
        .warning { background: #c0392b; padding: 10px; border-radius: 8px; font-size: 12px; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
<div class="box">
    <h2>🏦 Admin Setup</h2>

    <?php if ($msg): ?>
        <div class="msg <?= $success ? 'success' : 'error' ?>"><?= $msg ?></div>
        <?php if ($success): ?>
            <p style="text-align:center"><a href="/">👉 Login Page Pe Jaao</a></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <input type="text" name="name" placeholder="Admin Name (e.g. Super Admin)" value="Super Admin">
        <input type="email" name="email" placeholder="Admin Email" required>
        <input type="password" name="password" placeholder="Password (strong rakho!)" required>
        <button type="submit">✅ Admin Set Karo</button>
    </form>
    <?php endif; ?>

    <div class="warning">⚠️ Ye file use ke baad GitHub se DELETE karo!</div>
</div>
</body>
</html>
