<?php
// ═══════════════════════════════════════════════════════════
//  Digital Bandhak — Admin Setup
//  Pehli baar chalao to admin set karo
//  ⚠️ DONE hone ke baad YEH FILE DELETE KAR DENA!
// ═══════════════════════════════════════════════════════════
require_once __DIR__.'/php/config.php';

if (!$pdo) {
    die('<div style="font-family:sans-serif;padding:40px;color:red"><h2>❌ Database connect nahi hua!</h2><p>config.php mein DB credentials check karo.</p></div>');
}

$msg = '';
$msgColor = 'green';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name']   ?? '');
    $email  = trim($_POST['email']  ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $pass   = trim($_POST['pass']   ?? '');
    $cpass  = trim($_POST['cpass']  ?? '');

    if (!$name || !$email || !$pass) {
        $msg = '❌ Naam, Email aur Password zaruri hai!';
        $msgColor = 'red';
    } elseif ($pass !== $cpass) {
        $msg = '❌ Password match nahi kiya!';
        $msgColor = 'red';
    } elseif (strlen($pass) < 6) {
        $msg = '❌ Password kam se kam 6 characters ka hona chahiye!';
        $msgColor = 'red';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("SELECT id FROM admin LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE admin SET name=?, email=?, mobile=?, password=? WHERE id=1")->execute([$name, $email, $mobile, $hash]);
        } else {
            $pdo->prepare("INSERT INTO admin (name,email,mobile,password) VALUES (?,?,?,?)")->execute([$name, $email, $mobile, $hash]);
        }
        $msg = '✅ Admin account set ho gaya! Ab login karo. Is file ko delete karo!';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Digital Bandhak — Admin Setup</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #0f0f0f; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .card { background: #1a1a1a; border: 1px solid rgba(255,107,0,.3); border-radius: 20px; padding: 32px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(255,107,0,.1); }
  .logo { text-align: center; margin-bottom: 24px; }
  .logo div:first-child { font-size: 40px; }
  .logo div:nth-child(2) { font-size: 20px; font-weight: 800; color: #ff6b00; margin-top: 6px; }
  .logo div:nth-child(3) { font-size: 12px; color: #888; margin-top: 2px; }
  h2 { font-size: 16px; font-weight: 700; margin-bottom: 20px; color: #ff6b00; text-align: center; }
  label { display: block; font-size: 12px; color: #aaa; margin-bottom: 5px; margin-top: 14px; font-weight: 600; }
  input { width: 100%; padding: 11px 14px; background: #222; border: 1px solid #333; border-radius: 10px; color: #fff; font-size: 14px; outline: none; }
  input:focus { border-color: #ff6b00; }
  button { width: 100%; margin-top: 22px; padding: 13px; background: linear-gradient(135deg,#ff6b00,#ffb300); border: none; border-radius: 12px; color: #fff; font-size: 15px; font-weight: 800; cursor: pointer; }
  .msg { margin-top: 16px; padding: 12px; border-radius: 10px; font-size: 13px; text-align: center; }
  .warn { background: rgba(231,76,60,.15); border: 1px solid rgba(231,76,60,.3); color: #e74c3c; font-size: 12px; text-align: center; margin-top: 16px; padding: 10px; border-radius: 8px; }
  a { color: #ff6b00; text-decoration: none; font-weight: 700; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div>🏦</div>
    <div>Digital Bandhak</div>
    <div>Admin Setup</div>
  </div>
  <h2>⚙️ Super Admin Account Set Karo</h2>
  <?php if ($msg): ?>
    <div class="msg" style="background:rgba(<?= $msgColor==='green'?'46,204,113':'231,76,60' ?>,.15);border:1px solid rgba(<?= $msgColor==='green'?'46,204,113':'231,76,60' ?>,.3);color:<?= $msgColor==='green'?'#2ecc71':'#e74c3c' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php if ($msgColor === 'green'): ?>
      <div style="text-align:center;margin-top:16px"><a href="/">← Login Page Par Jao</a></div>
    <?php endif; ?>
  <?php endif; ?>
  <form method="POST">
    <label>Aapka Naam *</label>
    <input type="text" name="name" value="Digital Bandhak" required>
    <label>Email Address *</label>
    <input type="email" name="email" value="digitalbandhak@gmail.com" required>
    <label>Mobile Number</label>
    <input type="tel" name="mobile" value="" placeholder="9900000001">
    <label>Password *</label>
    <input type="password" name="pass" placeholder="Kam se kam 6 characters" required>
    <label>Password Confirm Karo *</label>
    <input type="password" name="cpass" placeholder="Wahi password dobara" required>
    <button type="submit">🚀 Admin Setup Complete Karo</button>
  </form>
  <div class="warn">⚠️ Setup complete hone ke baad is file ko <b>zaroor delete karo!</b><br>setup_admin.php → GitHub se bhi remove karo</div>
</div>
</body>
</html>
