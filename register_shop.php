<?php
require_once 'includes/config.php';

$logoUrl  = getSiteLogo($pdo);
$siteName = getSiteName($pdo);
$tagline  = getSiteTagline($pdo);

$success = ''; $error = ''; $generatedShopId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName  = trim($_POST['shop_name']  ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $mobile    = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
    $email     = trim($_POST['email']      ?? '');
    $address   = trim($_POST['address']    ?? '');
    $city      = trim($_POST['city']       ?? '');
    $state     = trim($_POST['state']      ?? '');
    $pass      = $_POST['password']        ?? '';
    $confirm   = $_POST['confirm_password']?? '';

    if (!$shopName || !$ownerName || !$mobile) { $error = 'Shop naam, owner naam aur mobile zaroori hain'; }
    elseif (strlen($mobile) < 10)              { $error = 'Valid 10-digit mobile number daalo'; }
    elseif ($pass !== $confirm)                { $error = 'Passwords match nahi kiye!'; }
    elseif (strlen($pass) < 8)                 { $error = 'Password 8+ characters hona chahiye'; }
    else {
        // Check duplicate mobile
        $dup = $pdo->prepare("SELECT id FROM shops WHERE owner_mobile=?");
        $dup->execute([$mobile]);
        if ($dup->fetch()) { $error = 'Yeh mobile number already registered hai'; }
        else {
            $shopId = generateShopId($pdo);
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            try {
                $ins = $pdo->prepare("INSERT INTO shops (shop_id, shop_name, owner_name, owner_email, owner_mobile, address, city, state, password, status) VALUES (?,?,?,?,?,?,?,?,'inactive',?)");
                // Corrected order: 9 values
                $ins = $pdo->prepare("INSERT INTO shops (shop_id, shop_name, owner_name, owner_email, owner_mobile, address, city, state, password, status) VALUES (?,?,?,?,?,?,?,?,?,'inactive')");
                $ins->execute([$shopId, $shopName, $ownerName, $email, $mobile, $address, $city, $state, $hashed]);

                auditLog($pdo, $shopId, 'shop_registered', "New registration: $shopName by $ownerName", 'owner', 0, $ownerName, $shopId);
                notifyAdminNewShop($pdo, $shopId, $shopName, $ownerName);

                $generatedShopId = $shopId;
                $success = "Registration successful!";
            } catch (PDOException $e) {
                $error = 'Registration failed. Please try again. (' . $e->getMessage() . ')';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>New Shop Register — <?= htmlspecialchars($siteName) ?></title>
  <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<div class="login-page">
  <div class="login-left">
    <div class="login-brand">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="width:80px;height:80px;object-fit:contain;border-radius:16px;"/>
      <?php else: ?>
        <div class="login-brand-icon">🏛️</div>
      <?php endif; ?>
      <h1><?= htmlspecialchars($siteName) ?></h1>
      <p><?= htmlspecialchars($tagline) ?></p>
    </div>
    <div class="login-feature"><div class="login-feature-icon">🆔</div>
      <div><div class="login-feature-title">Unique Shop ID</div><div class="login-feature-desc">Register hote hi auto-generated ID milegi</div></div>
    </div>
    <div class="login-feature"><div class="login-feature-icon">✅</div>
      <div><div class="login-feature-title">Admin Approval</div><div class="login-feature-desc">Register ke baad Admin activate karega</div></div>
    </div>
    <div class="login-feature"><div class="login-feature-icon">🔒</div>
      <div><div class="login-feature-title">Secure & Private</div><div class="login-feature-desc">Har shop ka data alag, encrypted</div></div>
    </div>
    <a href="index.php" class="login-whatsapp" style="text-decoration:none">
      <div class="login-whatsapp-icon">←</div>
      <div><div class="login-whatsapp-title" style="color:#B8760A">Login Page</div><div class="login-whatsapp-desc">Already registered? Login karo</div></div>
    </a>
  </div>

  <div class="login-right">
    <div class="login-box" style="max-width:480px">
      <div class="login-box-title">
        <div class="icon">🏪</div>
        <h2>New Shop Register</h2>
        <p>Apni pawnshop <?= htmlspecialchars($siteName) ?> par register karo</p>
      </div>

      <?php if ($success && $generatedShopId): ?>
      <div style="background:rgba(26,107,58,0.15);border:1px solid rgba(26,107,58,0.3);border-radius:var(--radius);padding:20px;text-align:center;margin-bottom:14px">
        <div style="font-size:32px;margin-bottom:8px">🎉</div>
        <div style="color:#5A9;font-weight:700;font-size:16px;margin-bottom:6px">Registration Successful!</div>
        <div style="color:#8A7050;font-size:13px;margin-bottom:14px">Aapka Shop ID hai:</div>
        <div style="background:rgba(184,118,10,0.15);border:1px solid rgba(184,118,10,0.3);border-radius:8px;padding:12px;font-size:22px;font-weight:700;color:#F0C060;letter-spacing:2px;margin-bottom:14px">
          <?= htmlspecialchars($generatedShopId) ?>
        </div>
        <div style="color:#8A7050;font-size:12px;margin-bottom:16px">
          ⚠ Yeh ID note kar lo — login ke liye zaroori hai!<br/>
          Admin approval ke baad aap login kar paoge.
        </div>
        <a href="index.php" class="login-btn" style="display:inline-block;width:auto;padding:10px 30px;text-decoration:none">Login Page →</a>
      </div>

      <?php else: ?>

      <?php if ($error): ?><div class="login-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST" onsubmit="return validateShopForm()">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="login-form-group" style="grid-column:1/-1">
            <label class="login-form-label">Shop / Dukan Ka Naam *</label>
            <input class="login-form-control" type="text" name="shop_name" required placeholder="Ravi Jewellers" value="<?= htmlspecialchars($_POST['shop_name'] ?? '') ?>"/>
          </div>
          <div class="login-form-group">
            <label class="login-form-label">Owner Ka Naam *</label>
            <input class="login-form-control" type="text" name="owner_name" required placeholder="Ravi Kumar" value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>"/>
          </div>
          <div class="login-form-group">
            <label class="login-form-label">Mobile Number *</label>
            <input class="login-form-control" type="tel" name="mobile" required placeholder="9876543210" maxlength="10" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>"/>
          </div>
          <div class="login-form-group" style="grid-column:1/-1">
            <label class="login-form-label">Email (optional)</label>
            <input class="login-form-control" type="email" name="email" placeholder="owner@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
          </div>
          <div class="login-form-group" style="grid-column:1/-1">
            <label class="login-form-label">Address</label>
            <input class="login-form-control" type="text" name="address" placeholder="Mohalla, Street" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"/>
          </div>
          <div class="login-form-group">
            <label class="login-form-label">City *</label>
            <input class="login-form-control" type="text" name="city" required placeholder="Patna" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"/>
          </div>
          <div class="login-form-group">
            <label class="login-form-label">State *</label>
            <select class="login-form-control" name="state" required style="color:#F0E6D0;background:#2A1F10">
              <option value="">-- State --</option>
              <?php foreach(['Bihar','Uttar Pradesh','Jharkhand','Delhi','Maharashtra','Rajasthan','Madhya Pradesh','Gujarat','West Bengal','Punjab','Haryana','Other'] as $st): ?>
              <option value="<?= $st ?>" <?= (($_POST['state']??'')===$st)?'selected':'' ?>><?= $st ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="login-form-group">
            <label class="login-form-label">Password *</label>
            <input class="login-form-control" type="password" id="reg_password" name="password" required placeholder="Min 8 characters"/>
          </div>
          <div class="login-form-group">
            <label class="login-form-label">Confirm Password *</label>
            <input class="login-form-control" type="password" id="reg_confirm" name="confirm_password" required placeholder="Dobara daalo"/>
          </div>
        </div>

        <div style="background:rgba(184,118,10,0.08);border:1px solid rgba(184,118,10,0.15);border-radius:8px;padding:10px 14px;font-size:12px;color:#8A7050;margin:8px 0 14px">
          ✅ Register karne ke baad ek <strong style="color:#D4920F">unique Shop ID</strong> generate hogi.<br/>
          Admin approval ke baad aap us ID se login kar paoge.
        </div>

        <button type="submit" class="login-btn">🏪 Register Shop</button>
        <div style="text-align:center;margin-top:12px">
          <a href="index.php" class="login-link">← Pehle se registered hain? Login karo</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="js/app.js"></script>
</body>
</html>
