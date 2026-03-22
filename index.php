<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_type'])) {
    $r = [
        'super_admin' => 'admin/dashboard.php',
        'owner'       => 'shop/dashboard.php',
        'staff'       => 'shop/staff_panel.php',
        'customer'    => 'customer_dashboard.php',
    ];
    header('Location:'.($r[$_SESSION['user_type']] ?? 'index.php')); exit;
}

$logoUrl  = getSiteLogo($pdo);
$siteName = getSiteName($pdo);
$tagline  = getSiteTagline($pdo);
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $pw         = $_POST['password'] ?? '';

    // ---- SUPER ADMIN ----
    if (strpos($identifier,'@') !== false || strtolower($identifier) === 'superadmin') {
        $s = $pdo->prepare("SELECT * FROM super_admin WHERE email=? OR username=?");
        $s->execute([$identifier, $identifier]);
        $admin = $s->fetch();
        if ($admin && password_verify($pw, $admin['password'])) {
            $_SESSION['user_type'] = 'super_admin';
            $_SESSION['user_id']   = $admin['id'];
            $_SESSION['user_name'] = $admin['username'];
            if (!empty($admin['profile_pic'])) $_SESSION['profile_pic'] = $admin['profile_pic'];
            header('Location:admin/dashboard.php'); exit;
        }
        if (strpos($identifier,'@') !== false) {
            $error = 'Admin email ya password galat hai';
        }
    }

    // ---- SHOP OWNER (by shop_id OR username) ----
    if (!$error) {
        $shopIdUpper = strtoupper($identifier);
        $shopStmt = $pdo->prepare("SELECT * FROM shops WHERE shop_id=? OR username=?");
        $shopStmt->execute([$shopIdUpper, $identifier]);
        $shop = $shopStmt->fetch();

        if ($shop && password_verify($pw, $shop['password'])) {
            if (!empty($shop['blocked'])) {
                $error = 'Shop blocked hai. Admin se contact karein.';
            } elseif ($shop['status'] === 'inactive') {
                $error = 'Shop activate nahi hui. Admin se contact karein.';
            } else {
                $sub = $pdo->prepare("SELECT id FROM subscriptions WHERE shop_id=? AND status='active' AND end_date>=CURDATE()");
                $sub->execute([$shop['shop_id']]);
                if (!$sub->fetch()) {
                    $error = 'Subscription expired! Admin se renew karwao.';
                } else {
                    $_SESSION['user_type']  = 'owner';
                    $_SESSION['user_id']    = $shop['id'];
                    $_SESSION['shop_id']    = $shop['shop_id'];
                    $_SESSION['shop_name']  = $shop['shop_name'];
                    $_SESSION['user_name']  = $shop['owner_name'];
                    if (!empty($shop['logo'])) $_SESSION['profile_pic'] = $shop['logo'];
                    header('Location:shop/dashboard.php'); exit;
                }
            }
        } else {
            // ---- STAFF LOGIN ---- (always try, even if shop found but wrong password)
            // Staff uses their shop's ID as login + their own password
            $staffStmt = $pdo->prepare("
                SELECT s.*, sh.status as shop_status, sh.blocked as shop_blocked,
                       sh.shop_id as actual_shop_id, sh.shop_name
                FROM staff s
                JOIN shops sh ON s.shop_id = sh.shop_id
                WHERE (sh.shop_id = ? OR sh.username = ?) AND s.status = 'active'
            ");
            $staffStmt->execute([$shopIdUpper, $identifier]);
            $foundStaff = null;
            foreach ($staffStmt->fetchAll() as $sf) {
                if (password_verify($pw, $sf['password'])) { $foundStaff = $sf; break; }
            }

            if ($foundStaff) {
                if (!empty($foundStaff['shop_blocked'])) {
                    $error = 'Shop blocked. Admin se contact karein.';
                } elseif ($foundStaff['shop_status'] === 'inactive') {
                    $error = 'Shop inactive. Admin se contact karein.';
                } else {
                    $_SESSION['user_type'] = 'staff';
                    $_SESSION['user_id']   = $foundStaff['id'];
                    $_SESSION['shop_id']   = $foundStaff['actual_shop_id'];
                    $_SESSION['shop_name'] = $foundStaff['shop_name'];
                    $_SESSION['user_name'] = $foundStaff['staff_name'];
                    header('Location:shop/staff_panel.php'); exit;
                }
            } elseif (!$error) {
                $error = 'Invalid ID or Password';
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
  <title><?=htmlspecialchars($siteName)?> — Login</title>
  <link rel="stylesheet" href="css/style.css"/>
  <style>
    #loadingScreen{position:fixed;inset:0;z-index:9999;background:#1A0E05;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;transition:opacity .5s ease}
    #loadingScreen.hide{opacity:0;pointer-events:none}
    .loader-logo{width:96px;height:96px;border-radius:22px;display:flex;align-items:center;justify-content:center;overflow:hidden;animation:logoPulse 1.5s ease-in-out infinite}
    .loader-logo img{width:100%;height:100%;object-fit:contain}
    @keyframes logoPulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(184,118,10,.4)}50%{transform:scale(1.06);box-shadow:0 0 0 18px rgba(184,118,10,0)}}
    .loader-title{font-size:28px;font-weight:700;color:#F0C060;font-family:'Sora',sans-serif}
    .loader-sub{font-size:14px;color:#8A7050;font-family:'Sora',sans-serif}
    .loader-bar-wrap{width:200px;height:3px;background:rgba(184,118,10,.15);border-radius:2px;overflow:hidden}
    .loader-bar{height:3px;background:linear-gradient(90deg,#B8760A,#D4920F);border-radius:2px;animation:loadBar 1.6s ease-in-out forwards}
    @keyframes loadBar{from{width:0}to{width:100%}}
  </style>
</head>
<body>

<!-- LOADING SCREEN -->
<div id="loadingScreen">
  <div class="loader-logo">
    <?php if ($logoUrl): ?><img src="<?=htmlspecialchars($logoUrl)?>" alt="Logo"/><?php else: ?><span style="font-size:50px">🏛️</span><?php endif; ?>
  </div>
  <div class="loader-title"><?=htmlspecialchars($siteName)?></div>
  <div class="loader-sub"><?=htmlspecialchars($tagline)?></div>
  <div class="loader-bar-wrap"><div class="loader-bar"></div></div>
</div>

<div class="login-page" id="loginPage" style="opacity:0;transition:opacity .4s ease">
  <!-- LEFT -->
  <div class="login-left">
    <div class="login-brand">
      <?php if ($logoUrl): ?>
        <img src="<?=htmlspecialchars($logoUrl)?>" alt="Logo" style="width:80px;height:80px;object-fit:contain;border-radius:16px;box-shadow:0 4px 20px rgba(184,118,10,.4)"/>
      <?php else: ?>
        <div class="login-brand-icon">🏛️</div>
      <?php endif; ?>
      <h1><?=htmlspecialchars($siteName)?></h1>
      <p><?=htmlspecialchars($tagline)?></p>
    </div>
    <div class="login-feature"><div class="login-feature-icon">📋</div><div><div class="login-feature-title">Bandhak Management</div><div class="login-feature-desc">Customer entry, photos, documents</div></div></div>
    <div class="login-feature"><div class="login-feature-icon">💰</div><div><div class="login-feature-title">Payment Tracking</div><div class="login-feature-desc">Interest calculator, receipts, history</div></div></div>
    <div class="login-feature"><div class="login-feature-icon">📱</div><div><div class="login-feature-title">Mobile Friendly</div><div class="login-feature-desc">Desktop aur mobile dono pe kaam karta hai</div></div></div>
    <div class="login-feature"><div class="login-feature-icon">🔒</div><div><div class="login-feature-title">Secure &amp; Private</div><div class="login-feature-desc">Har shop ka data alag, encrypted</div></div></div>
    <?php $waNum = getSiteSettings($pdo)['whatsapp_number'] ?? ''; ?>
    <a href="<?=$waNum?'https://wa.me/'.$waNum:'#'?>" target="_blank" class="login-whatsapp">
      <div class="login-whatsapp-icon">💬</div>
      <div><div class="login-whatsapp-title">WhatsApp Support</div><div class="login-whatsapp-desc">Help chahiye? Click karein →</div></div>
    </a>
  </div>

  <!-- RIGHT -->
  <div class="login-right">
    <div class="login-box">
      <div class="login-box-title">
        <?php if ($logoUrl): ?>
          <img src="<?=htmlspecialchars($logoUrl)?>" alt="Logo" style="width:58px;height:58px;object-fit:contain;border-radius:14px;margin:0 auto 8px;display:block"/>
        <?php else: ?>
          <div class="icon">🏛️</div>
        <?php endif; ?>
        <h2><?=htmlspecialchars($siteName)?></h2>
        <p><?=htmlspecialchars($tagline)?></p>
      </div>

      <?php if ($error): ?><div class="login-error">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>

      <form method="POST" onsubmit="setLoginType()">
        <input type="hidden" name="login_type" id="loginTypeField" value="shop"/>
        <div class="login-form-group">
          <label class="login-form-label">Email / Shop ID / Username</label>
          <input class="login-form-control" type="text" name="identifier" id="identifierField"
            placeholder="Email, SH0001, ya username"
            required autocomplete="username"
            value="<?=htmlspecialchars($_POST['identifier']??'')?>"
            oninput="detectType(this.value)"/>
          <div id="typeHint" style="font-size:11px;margin-top:4px;color:#8A7050"></div>
        </div>
        <div class="login-form-group" style="position:relative">
          <label class="login-form-label">Password</label>
          <input class="login-form-control" type="password" name="password" id="pwField"
            placeholder="••••••••" required autocomplete="current-password"/>
          <span onclick="togglePw()" style="position:absolute;right:12px;top:30px;cursor:pointer;font-size:16px;color:#8A7050">👁</span>
        </div>
        <button type="submit" class="login-btn" id="loginBtn">🔐 Login</button>
        <div class="login-links">
          <a href="forgot_password.php" class="login-link">🔑 Password bhul gaye?</a>
          <a href="register_shop.php" class="login-link">➕ New Shop Register</a>
        </div>
      </form>

      <div class="login-divider"><span>Customer hain?</span></div>
      <button class="login-customer-btn" onclick="openCustomerLogin()">👤 Customer Login</button>
    </div>
  </div>
</div>

<!-- Customer Login Modal (Password + OTP option) -->
<div class="modal-backdrop" id="modal-customer" style="display:none">
  <div class="modal-box" style="background:#231A0E;border-color:rgba(184,118,10,.2);max-width:400px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="color:#F0C060;font-size:16px;font-weight:700">👤 Customer Login</div>
      <button onclick="closeModal('modal-customer')" style="background:none;border:none;font-size:20px;cursor:pointer;color:#8A7050">✕</button>
    </div>

    <!-- Tab Toggle: Password / OTP -->
    <div style="display:flex;background:rgba(255,255,255,.05);border-radius:8px;padding:3px;margin-bottom:14px">
      <button onclick="custTab('pw')" id="tabPw" style="flex:1;padding:7px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;background:#B8760A;color:#fff;font-family:inherit">🔑 Password</button>
      <button onclick="custTab('otp')" id="tabOtp" style="flex:1;padding:7px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;background:transparent;color:#8A7050;font-family:inherit">📱 OTP (Future)</button>
    </div>

    <!-- Password Login -->
    <div id="custPwPanel">
      <div style="background:rgba(184,118,10,.08);border:1px solid rgba(184,118,10,.15);border-radius:8px;padding:10px 14px;font-size:12px;color:#8A7050;margin-bottom:12px">
        💡 Apna Bandhak ID aur password daalo. Password shop owner se mil sakta hai.
      </div>
      <form method="POST" action="customer_login.php">
        <div class="login-form-group">
          <label class="login-form-label" style="color:#8A7050">Bandhak ID *</label>
          <input class="login-form-control" type="text" name="bandhak_id" placeholder="DBK-2025-1" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()" required/>
        </div>
        <div class="login-form-group">
          <label class="login-form-label" style="color:#8A7050">Password *</label>
          <input class="login-form-control" type="password" name="cust_password" placeholder="6-digit password" required/>
        </div>
        <button type="submit" class="login-btn">🔐 Login</button>
      </form>
    </div>

    <!-- OTP Login (Future) -->
    <div id="custOtpPanel" style="display:none">
      <div style="background:rgba(184,118,10,.08);border:1px solid rgba(184,118,10,.15);border-radius:8px;padding:10px 14px;font-size:12px;color:#8A7050;margin-bottom:12px">
        📱 OTP feature coming soon! Abhi password login use karein.
      </div>
      <div id="otp-s1">
        <div class="login-form-group">
          <label class="login-form-label" style="color:#8A7050">Bandhak ID *</label>
          <input class="login-form-control" type="text" id="c_bandhak_id" placeholder="DBK-2025-1" oninput="this.value=this.value.toUpperCase()"/>
        </div>
        <div class="login-form-group">
          <label class="login-form-label" style="color:#8A7050">Mobile (+91) *</label>
          <div style="display:flex">
            <span style="background:rgba(255,255,255,.05);border:1px solid rgba(184,118,10,.2);border-right:none;border-radius:var(--radius) 0 0 var(--radius);padding:11px 12px;font-size:13px;color:#8A7050">+91</span>
            <input class="login-form-control" type="tel" id="c_mobile" placeholder="9876543210" maxlength="10" style="border-radius:0 var(--radius) var(--radius) 0;border-left:none" oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)"/>
          </div>
        </div>
        <button class="login-btn" onclick="requestOtp()">📱 Send OTP</button>
      </div>
      <div id="otp-s2" style="display:none">
        <div style="background:rgba(26,107,58,.12);border:1px solid rgba(26,107,58,.25);border-radius:8px;padding:10px 14px;color:#5A9;font-size:13px;margin-bottom:12px">✔ OTP sent!</div>
        <div class="login-form-group">
          <label class="login-form-label" style="color:#8A7050">Enter OTP</label>
          <div class="otp-inputs">
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,0)" onkeydown="otpBack(this,0,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,1)" onkeydown="otpBack(this,1,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,2)" onkeydown="otpBack(this,2,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,3)" onkeydown="otpBack(this,3,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,4)" onkeydown="otpBack(this,4,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,5)" onkeydown="otpBack(this,5,event)"/>
          </div>
        </div>
        <button class="login-btn" onclick="verifyOtp()">✔ Verify & Login</button>
        <div style="text-align:center;margin-top:10px;display:flex;gap:14px;justify-content:center">
          <a href="#" onclick="requestOtp();return false" class="login-link">Resend</a>
          <a href="#" onclick="document.getElementById('otp-s1').style.display='block';document.getElementById('otp-s2').style.display='none'" class="login-link">← Back</a>
        </div>
      </div>
    </div>
  </div>
</div>

<a href="<?=$waNum?'https://wa.me/'.$waNum:'#'?>" target="_blank" class="support-fab">💬 Support</a>

<script src="js/app.js"></script>
<script>
// ---- LOADING SCREEN ----
window.addEventListener('load', () => {
  setTimeout(() => {
    const ls = document.getElementById('loadingScreen');
    const lp = document.getElementById('loginPage');
    ls.classList.add('hide');
    lp.style.opacity = '1';
    setTimeout(() => ls.style.display='none', 600);
  }, 1500);
});

// ---- LOGIN TYPE DETECT ----
function detectType(val) {
  const h = document.getElementById('typeHint');
  const f = document.getElementById('loginTypeField');
  if (!h) return;
  if (val.includes('@') || val.toLowerCase()==='superadmin') {
    h.textContent='→ Super Admin login'; h.style.color='#B8760A'; f.value='admin';
  } else if (val.toUpperCase().startsWith('SH') && val.length>=4) {
    h.textContent='→ Shop Owner / Staff login'; h.style.color='#5A9'; f.value='shop';
  } else if (val.length>=3 && !val.toUpperCase().startsWith('DBK')) {
    h.textContent='→ Shop Owner (username) login'; h.style.color='#5A9'; f.value='shop';
  } else { h.textContent=''; }
}

function setLoginType() {
  const val = document.getElementById('identifierField')?.value.trim();
  if (val && (val.includes('@') || val.toLowerCase()==='superadmin')) {
    document.getElementById('loginTypeField').value = 'admin';
  }
  // Show loader on login
  const btn = document.getElementById('loginBtn');
  if (btn) { btn.textContent='⏳ Logging in...'; btn.disabled=true; }
}

function togglePw() {
  const f = document.getElementById('pwField');
  f.type = f.type==='password'?'text':'password';
}

function openCustomerLogin() {
  openModal('modal-customer');
}

function custTab(tab) {
  document.getElementById('custPwPanel').style.display  = tab==='pw'?'block':'none';
  document.getElementById('custOtpPanel').style.display = tab==='otp'?'block':'none';
  document.getElementById('tabPw').style.background   = tab==='pw'?'#B8760A':'transparent';
  document.getElementById('tabPw').style.color         = tab==='pw'?'#fff':'#8A7050';
  document.getElementById('tabOtp').style.background  = tab==='otp'?'#B8760A':'transparent';
  document.getElementById('tabOtp').style.color        = tab==='otp'?'#fff':'#8A7050';
}

function otpBack(el, idx, e) {
  const boxes = document.querySelectorAll('.otp-input');
  if (e.key==='Backspace' && !el.value && idx>0) { boxes[idx-1].value=''; boxes[idx-1].focus(); }
}
</script>
</body>
</html>
