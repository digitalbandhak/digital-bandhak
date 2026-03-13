<?php
require_once __DIR__.'/php/config.php';
$role = $_SESSION['role'] ?? null;
$shop_id = $_SESSION['shop_id'] ?? 'SH001';
$user_name = $_SESSION['name'] ?? '';
$shop_name = $_SESSION['shop_name'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_mobile = $_SESSION['mobile'] ?? '';
// Fetch real email/mobile/photo from DB if not in session
if ($role && $pdo) {
    try {
        if ($role === 'admin') {
            $stmt = $pdo->prepare("SELECT name,email,mobile,photo FROM admin LIMIT 1");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT owner_name,email,mobile,photo,logo FROM shops WHERE id=? LIMIT 1");
            $stmt->execute([$shop_id]);
        }
        $row = $stmt->fetch();
        if ($row) {
            if (!empty($row['email']))  { $user_email  = $row['email'];  $_SESSION['email']  = $row['email']; }
            if (!empty($row['mobile'])) { $user_mobile = $row['mobile']; $_SESSION['mobile'] = $row['mobile']; }
            // Photo: prefer 'photo' column, fallback to 'logo'
            $db_photo = $row['photo'] ?? $row['logo'] ?? '';
            if (!empty($db_photo)) { $_SESSION['photo'] = $db_photo; }
        }
    } catch(Exception $e) { /* photo column may not exist yet */ }
}
?><!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Digital Bandhak — <?= $role ? ucfirst($role).' Dashboard' : 'Login' ?></title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="splashLoader" style="position:fixed;inset:0;background:#0d0800;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;transition:opacity .5s">
  <div style="width:78px;height:78px;background:linear-gradient(135deg,#FF6B00,#FFB300);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:34px;margin-bottom:20px;animation:splPulse 1.2s ease-in-out infinite;box-shadow:0 8px 32px rgba(255,107,0,.4)">🏦</div>
  <div style="font-size:26px;font-weight:800;color:#FF6B00;letter-spacing:1px">Digital Bandhak</div>
  <div style="font-size:12px;color:#7a6040;margin-top:6px">Aapki Girvee, Hamaari Zimmedaari</div>
  <div style="width:180px;height:3px;background:#1a1005;border-radius:2px;margin-top:24px;overflow:hidden">
    <div style="height:100%;background:linear-gradient(to right,#FF6B00,#FFB300);animation:splBar 1.8s ease-in-out infinite"></div>
  </div>
</div>
<style>
@keyframes splPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
@keyframes splBar{0%{width:0;margin-left:0}60%{width:100%;margin-left:0}100%{width:0;margin-left:100%}}
.spin{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin360 .7s linear infinite;vertical-align:middle}
@keyframes spin360{to{transform:rotate(360deg)}}
</style>

<?php if (!$role): // ═══ LOGIN SCREEN ═══════════════════════════════════════ ?>

<div class="login-bg" id="loginArea">
  <!-- INFO PANEL (left side on desktop, top on mobile) -->
  <div class="login-info-panel" id="infoPanel">
    <div class="lip-logo">
      <div style="font-size:52px;margin-bottom:12px">🏦</div>
      <div style="font-size:26px;font-weight:900;color:var(--saffron);letter-spacing:-0.5px">Digital Bandhak</div>
      <div style="font-size:13px;color:rgba(255,220,160,.7);margin-top:4px">Aapki Girvee, Hamaari Zimmedaari</div>
    </div>

    <div class="lip-features">
      <div class="lip-feat">
        <span style="font-size:22px">📦</span>
        <div>
          <div style="font-weight:700;font-size:13px">Bandhak Management</div>
          <div style="font-size:11px;color:rgba(255,220,160,.6)">Customer entry, photos, documents</div>
        </div>
      </div>
      <div class="lip-feat">
        <span style="font-size:22px">💰</span>
        <div>
          <div style="font-weight:700;font-size:13px">Payment Tracking</div>
          <div style="font-size:11px;color:rgba(255,220,160,.6)">Interest calculator, receipts, history</div>
        </div>
      </div>
      <div class="lip-feat">
        <span style="font-size:22px">📱</span>
        <div>
          <div style="font-weight:700;font-size:13px">Mobile Friendly</div>
          <div style="font-size:11px;color:rgba(255,220,160,.6)">Desktop aur mobile dono pe kaam karta hai</div>
        </div>
      </div>
      <div class="lip-feat">
        <span style="font-size:22px">🔒</span>
        <div>
          <div style="font-weight:700;font-size:13px">Secure &amp; Private</div>
          <div style="font-size:11px;color:rgba(255,220,160,.6)">Har shop ka data alag, encrypted</div>
        </div>
      </div>
      <div class="lip-feat">
        <span style="font-size:22px">📊</span>
        <div>
          <div style="font-weight:700;font-size:13px">Reports &amp; Analytics</div>
          <div style="font-size:11px;color:rgba(255,220,160,.6)">Monthly reports, export CSV/PDF</div>
        </div>
      </div>
    </div>

    <!-- WhatsApp Support - only on login page -->
    <div class="lip-support" onclick="openSupport()">
      <span style="font-size:18px">💬</span>
      <div>
        <div style="font-weight:700;font-size:12px">WhatsApp Support</div>
        <div style="font-size:11px;color:rgba(255,220,160,.6)">Help chahiye? Click karein</div>
      </div>
      <span style="margin-left:auto;font-size:11px;color:rgba(255,220,160,.5)">→</span>
    </div>
  </div>

  <!-- RIGHT: LOGIN CARD -->
  <div class="login-right-panel">
  <!-- LOGIN CARD - UNIFIED -->
  <div class="login-card" id="loginCard">
    <div style="text-align:center;margin-bottom:20px">
      <div class="brand-icon">🏦</div>
      <div style="font-size:22px;font-weight:800;color:var(--saffron)">Digital Bandhak</div>
      <div style="font-size:11px;color:var(--muted)">Aapki Girvee, Hamaari Zimmedaari</div>
    </div>

    <!-- Unified Login Form -->
    <div id="panel-unified">
      <div class="fg">
        <label class="fl">Email / Shop ID / Mobile</label>
        <input class="fi" id="uni-id" placeholder="Email, SH001, ya 9876543210" autocomplete="username">
      </div>
      <div class="fg">
        <label class="fl">Password</label>
        <div style="position:relative">
          <input class="fi" id="uni-pass" type="password" placeholder="••••••••" autocomplete="current-password"
            onkeydown="if(event.key==='Enter')doUnifiedLogin()">
          <button onclick="togglePwd('uni-pass')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px">👁️</button>
        </div>
      </div>
      <div id="uni-err" style="color:var(--red);font-size:13px;margin-bottom:10px;display:none;padding:8px;background:rgba(231,76,60,.1);border-radius:8px;border:1px solid rgba(231,76,60,.3)"></div>
      <button class="btnP" onclick="doUnifiedLogin()" id="uniLoginBtn">🔓 Login</button>
      <div style="display:flex;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:6px">
        <button class="lnk" onclick="showForgot()">🔁 Password bhul gaye?</button>
        <button class="lnk" onclick="showRegister()">➕ New Shop Register</button>
      </div>
      
      <!-- Customer login button -->
      <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:14px">
        <div style="font-size:11px;color:var(--muted);text-align:center;margin-bottom:8px">Customer hain?</div>
        <button class="btnG" style="width:100%;font-size:13px;padding:11px 0;font-weight:700" onclick="showCustomerLogin()">
          👤 Customer OTP Login
        </button>
      </div>
    </div>

    <!-- Customer Login Panel (hidden by default) -->
    <div id="panel-customer" style="display:none">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
        <button class="btnG" onclick="hideCustomerLogin()">← Back</button>
        <div style="font-size:15px;font-weight:800;color:var(--saffron)">👤 Customer Login</div>
      </div>
      <div class="fg"><label class="fl">Bandhak ID</label>
        <input class="fi" id="cust-bandhak" placeholder="BDK-2025-001">
      </div>
      <div class="fg"><label class="fl">Registered Mobile</label>
        <div style="display:flex;gap:8px">
          <input class="fi" id="cust-mobile" placeholder="9876543210" style="flex:1">
          <button class="btnG" onclick="sendOTP()" id="otpBtn">OTP भेजें</button>
        </div>
      </div>
      <div id="otp-panel" style="display:none">
        <div class="fg"><label class="fl">OTP Enter करें</label>
          <div class="otp-row">
            <input class="otp-i" maxlength="1" id="o1" oninput="otpMove(this,'o2')">
            <input class="otp-i" maxlength="1" id="o2" oninput="otpMove(this,'o3')">
            <input class="otp-i" maxlength="1" id="o3" oninput="otpMove(this,'o4')">
            <input class="otp-i" maxlength="1" id="o4" oninput="otpMove(this,'o5')">
            <input class="otp-i" maxlength="1" id="o5" oninput="otpMove(this,'o6')">
            <input class="otp-i" maxlength="1" id="o6">
          </div>
          <div style="text-align:center"><button class="lnk" onclick="sendOTP()">Resend OTP</button></div>
          <div id="otp-hint" style="text-align:center;font-size:11px;color:var(--gold);margin-top:6px"></div>
        </div>
      </div>
      <div id="cust-err" style="color:var(--red);font-size:13px;margin-bottom:10px;display:none"></div>
      <button class="btnP" onclick="verifyOTP()">✅ Verify & Login</button>
    </div>

    <!-- Customer Register Panel -->
    <div id="panel-cust-register" style="display:none">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
        <button class="btnG" onclick="hideCustomerRegister()">← Back</button>
        <div style="font-size:15px;font-weight:800;color:var(--saffron)">📝 Customer Register</div>
      </div>
      <div class="fg"><label class="fl">Poora Naam *</label>
        <input class="fi" id="cr-name" placeholder="Ramesh Kumar">
      </div>
      <div class="fg"><label class="fl">Mobile Number *</label>
        <input class="fi" id="cr-mobile" placeholder="9876543210" maxlength="10" type="tel">
      </div>
      <div class="fg"><label class="fl">Address</label>
        <input class="fi" id="cr-address" placeholder="Ghar ka pata, mohalla, city">
      </div>
      <div id="cr-err" style="color:var(--red);font-size:12px;margin-bottom:10px;display:none;padding:8px;background:rgba(231,76,60,.08);border-radius:8px;border:1px solid rgba(231,76,60,.2)"></div>
      <button class="btnP" id="crBtn" onclick="doCustomerRegister()">✅ Register Karein</button>
      <div style="font-size:11px;color:var(--muted);margin-top:10px;text-align:center;line-height:1.5">
        Register ke baad admin approval ka wait karein.<br>Approval hone ke baad OTP se login kar sakenge.
      </div>
    </div>

    <!-- OLD ADMIN/SHOP hidden panels for backward compatibility -->
    <div id="panel-admin" style="display:none">
      <input class="fi" id="admin-email" value="">
      <input class="fi" id="admin-pass" type="password" value="">
      <div id="admin-err" style="display:none"></div>
    </div>
    <div id="panel-shop" style="display:none">
      <!-- Legacy panel - kept for JS compatibility, hidden by design -->
    </div>
  </div><!-- /login-card -->

    <!-- REGISTER SCREEN -->
  <div id="registerCard" style="display:none" class="reg-card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <button class="btnG" onclick="hideRegister()">← Back</button>
      <div style="font-size:16px;font-weight:800;color:var(--saffron)">🏪 New Shop Register</div>
    </div>
    <div class="fg2">
      <div class="fg"><label class="fl">Shop Name *</label><input class="fi" id="r-shop" placeholder="Sharma Bandhak Ghar"></div>
      <div class="fg"><label class="fl">Owner Name *</label><input class="fi" id="r-owner" placeholder="Ramesh Sharma"></div>
      <div class="fg"><label class="fl">Phone *</label><input class="fi" id="r-mobile" placeholder="9876543210"></div>
      <div class="fg"><label class="fl">Email *</label><input class="fi" id="r-email" placeholder="shop@email.com"></div>
      <div class="fg"><label class="fl">Pin Code *</label><input class="fi" id="r-pin" placeholder="803221"></div>
      <div class="fg"><label class="fl">State *</label>
        <select class="si" id="r-state">
          <option value="">-- State chunein --</option>
          <option>Andhra Pradesh</option><option>Arunachal Pradesh</option>
          <option>Assam</option><option>Bihar</option><option>Chhattisgarh</option>
          <option>Delhi</option><option>Goa</option><option>Gujarat</option>
          <option>Haryana</option><option>Himachal Pradesh</option>
          <option>Jammu & Kashmir</option><option>Jharkhand</option>
          <option>Karnataka</option><option>Kerala</option>
          <option>Madhya Pradesh</option><option>Maharashtra</option>
          <option>Manipur</option><option>Meghalaya</option><option>Mizoram</option>
          <option>Nagaland</option><option>Odisha</option><option>Punjab</option>
          <option>Rajasthan</option><option>Sikkim</option>
          <option>Tamil Nadu</option><option>Telangana</option><option>Tripura</option>
          <option>Uttar Pradesh</option><option>Uttarakhand</option>
          <option>West Bengal</option>
        </select>
      </div>
    </div>
    <div class="fg"><label class="fl">Address *</label><input class="fi" id="r-address" placeholder="Shop address, mohalla, city"></div>
    <div class="fg2">
      <div class="fg"><label class="fl">New Password *</label><input class="fi" type="password" id="r-pass" placeholder="••••••••"></div>
      <div class="fg"><label class="fl">Confirm Password *</label><input class="fi" type="password" id="r-conf" placeholder="••••••••"></div>
      <div class="fg"><label class="fl">GST (optional)</label><input class="fi" id="r-gst" placeholder="22AAAAA0000A1Z5"></div>
      <div class="fg"><label class="fl">Licence (optional)</label><input class="fi" id="r-lic" placeholder="Licence Number"></div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin:11px 0">
      <input type="checkbox" id="reg-tc" style="accent-color:#FF6B00;width:15px;height:15px" onchange="document.getElementById('regBtn').disabled=!this.checked;document.getElementById('regBtn').style.opacity=this.checked?1:.5">
      <label for="reg-tc" style="font-size:12px">Main <button class="lnk" onclick="showRegTC()">Terms & Conditions</button> se agree karta/karti hoon</label>
    </div>
    <div id="reg-err" style="color:var(--red);font-size:12px;margin-bottom:10px;display:none"></div>
    <button class="btnP" id="regBtn" onclick="doRegister()" disabled style="opacity:.5">✅ Shop Register Karein</button>
  </div>

  <!-- FORGOT PASSWORD SCREEN -->
  <div id="forgotCard" style="display:none" class="login-card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <button class="btnG" onclick="hideForgot()">← Back</button>
      <div style="font-size:16px;font-weight:800;color:var(--saffron)">🔁 Password Reset</div>
    </div>
    <!-- Step 1 -->
    <div id="fp-step1">
      <div class="fg"><label class="fl">Mobile Number</label><input class="fi" id="fp-mobile" placeholder="9876543210"></div>
      <div class="fg"><label class="fl">Shop ID</label><input class="fi" id="fp-shop" placeholder="SH001"></div>
      <button class="btnP" onclick="fpStep1()">📱 OTP Bhejein</button>
    </div>
    <!-- Step 2 -->
    <div id="fp-step2" style="display:none">
      <div class="fg"><label class="fl">OTP Enter Karein</label>
        <div class="otp-row">
          <input class="otp-i" maxlength="1" id="fp1" oninput="otpMove(this,'fp2')">
          <input class="otp-i" maxlength="1" id="fp2" oninput="otpMove(this,'fp3')">
          <input class="otp-i" maxlength="1" id="fp3" oninput="otpMove(this,'fp4')">
          <input class="otp-i" maxlength="1" id="fp4" oninput="otpMove(this,'fp5')">
          <input class="otp-i" maxlength="1" id="fp5" oninput="otpMove(this,'fp6')">
          <input class="otp-i" maxlength="1" id="fp6">
        </div>
        <div id="fp-hint" style="text-align:center;font-size:11px;color:var(--gold);margin-top:5px"></div>
      </div>
      <button class="btnP" onclick="fpStep2()">✅ Verify OTP</button>
    </div>
    <!-- Step 3 -->
    <div id="fp-step3" style="display:none">
      <div class="fg"><label class="fl">Naya Password</label><input class="fi" type="password" id="fp-new" placeholder="••••••••"></div>
      <div class="fg"><label class="fl">Confirm Password</label><input class="fi" type="password" id="fp-conf" placeholder="••••••••"></div>
      <button class="btnP" onclick="fpStep3()">💾 Update Password</button>
    </div>
    <div id="fp-err" style="color:var(--red);font-size:12px;margin-top:8px;display:none"></div>
  </div>
  </div><!-- /login-right-panel -->
</div><!-- /login-bg -->

<?php else: // ═══ DASHBOARD ═══════════════════════════════════════════════════ ?>

<div class="dash">
  <!-- Overlay (mobile) -->
  <div class="ov" id="overlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR -->
  <div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="sidebar">
    <div style="padding:16px 15px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,var(--saffron),var(--gold));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px">🏦</div>
      <div>
        <div style="font-size:14px;font-weight:800;color:var(--saffron)">Bandhak</div>
        <div style="font-size:10px;color:var(--muted)">Digital Platform</div>
      </div>
    </div>
    <div class="sb-nav">
      <div class="nav-lbl">Navigation</div>
      <?php if ($role === 'admin'): ?>
        <button class="nv active" onclick="loadPage('dashboard')" id="nav-dashboard">📊 <span data-lang="nav_dashboard">Dashboard</span></button>
        <button class="nv" onclick="loadPage('shops')" id="nav-shops">🏪 <span data-lang="nav_shops">Shops List</span></button>
        <button class="nv" onclick="loadPage('subscriptions')" id="nav-subscriptions">💳 <span data-lang="nav_subscriptions">Subscriptions</span></button>
        <button class="nv" onclick="loadPage('search')" id="nav-search">🔍 <span data-lang="nav_search">Customer Search</span></button>
        <button class="nv" onclick="loadPage('reports')" id="nav-reports">📋 <span data-lang="nav_reports">Reports</span></button>
        <button class="nv" onclick="loadPage('audit')" id="nav-audit">🔍 <span data-lang="nav_audit">Audit Logs</span></button>
        <button class="nv" onclick="loadPage('calculator')" id="nav-calculator">🧮 <span data-lang="nav_calculator">Interest Calculator</span></button>
        <button class="nv" onclick="loadPage('chat')" id="nav-chat">💬 <span data-lang="nav_chat">Private Chat</span> <span class="nbadge" id="chatBadge" style="display:none">3</span></button>
      <?php elseif ($role === 'shop'): ?>
        <button class="nv active" onclick="loadPage('dashboard')" id="nav-dashboard">📊 <span data-lang="nav_dashboard">Dashboard</span></button>
        <button class="nv" onclick="loadPage('add-pawn')" id="nav-add-pawn">➕ <span data-lang="nav_add_bandhak">New Bandhak</span></button>
        <button class="nv" onclick="loadPage('pawns')" id="nav-pawns">📦 <span data-lang="nav_all_bandhak">All Bandhak</span></button>
        <button class="nv" onclick="loadPage('search')" id="nav-search">🔍 <span data-lang="nav_search">Customer Search</span></button>
        <button class="nv" onclick="loadPage('payments')" id="nav-payments">💵 <span data-lang="nav_payments">Payments</span></button>
        <button class="nv" onclick="loadPage('subscription')" id="nav-subscription">💳 <span data-lang="nav_subscription">Subscription</span></button>
        <button class="nv" onclick="loadPage('reports')" id="nav-reports">📋 <span data-lang="nav_reports">Reports</span></button>
        <button class="nv" onclick="loadPage('terms')" id="nav-terms">📜 <span>Terms & Conditions</span></button>
        <button class="nv" onclick="loadPage('calculator')" id="nav-calculator">🧮 <span data-lang="nav_calculator">Interest Calculator</span></button>
        <button class="nv" onclick="loadPage('chat')" id="nav-chat">💬 <span data-lang="nav_chat_admin">Chat with Admin</span> <span class="nbadge" id="chatBadge" style="display:none">1</span></button>
      <?php else: ?>
        <button class="nv active" onclick="loadPage('my-items')" id="nav-my-items">📦 <span>Meri Items</span></button>
        <button class="nv" onclick="loadPage('payment-history')" id="nav-payment-history">💵 <span>Payment History</span></button>
      <?php endif; ?>
    </div>
    <div class="sb-footer">
      <div style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;cursor:pointer" onclick="openModal('modalProfile')">
        <div class="av" style="width:32px;height:32px;font-size:12px"><?= $role==='admin'?'SA':($role==='shop'?strtoupper(substr($user_name,0,2)):'AK') ?></div>
        <div>
          <div style="font-size:12px;font-weight:700"><?= htmlspecialchars($user_name ?: ($role==='admin'?'Super Admin':'Amit Kumar')) ?></div>
          <div style="font-size:10px;color:var(--saffron)" data-lang="edit_profile">Edit Profile →</div>
        </div>
      </div>
      <button class="nv" style="color:var(--red);justify-content:center;margin-top:4px;width:100%" onclick="doLogout()" data-lang="logout">🚪 Logout</button>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main" id="mainArea">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:10px">
        <button class="ham" onclick="toggleSidebar()">☰</button>
        <div style="font-size:16px;font-weight:800" id="pageTitle" data-lang="nav_dashboard">Dashboard</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <?php if ($role !== 'customer'): ?>
        <button class="ib" onclick="loadPage('search')" title="Customer Search">🔍</button>
        <?php endif; ?>
        <button class="ib" onclick="loadNotifications()" title="Notifications" id="notifBtn">🔔<div class="dot" id="notifDot" style="display:none"></div></button>
        <?php if ($role !== 'customer'): ?>
        <button class="ib" onclick="openModal('modalSettings')" title="Settings">⚙️</button>
        <?php endif; ?>
        <div class="av" style="width:34px;height:34px;font-size:12px;cursor:pointer" onclick="openModal('modalProfile')">
          <?= $role==='admin'?'SA':($role==='shop'?strtoupper(substr($user_name,0,2)):(strtoupper(substr($user_name,0,2))||'CU')) ?>
        </div>
      </div>
    </div>
    <!-- Page Content renders here -->
    <div id="pageContent"></div>
  </div>
</div>

<!-- ═══════════ MODALS ═══════════════════════════════════════════════════════ -->

<!-- PAYMENT HISTORY MODAL -->
<div class="mo" id="modalPayHist" style="display:none">
  <div class="mb" style="max-width:580px" id="payHistContent"></div>
</div>

<!-- DELETE MODAL -->
<div class="mo" id="modalDelete" style="display:none">
  <div class="mb" style="max-width:400px;text-align:center">
    <div style="font-size:44px;margin-bottom:10px">⚠️</div>
    <div style="font-size:16px;font-weight:800;margin-bottom:5px">Bandhak Delete Karein?</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:14px" id="delPawnInfo"></div>
    <div style="background:rgba(231,76,60,.07);border:1px solid rgba(231,76,60,.2);border-radius:10px;padding:11px;font-size:12px;color:var(--red);margin-bottom:14px">
      ⚠️ Permanent action hai. Record recover nahi hoga. Audit log mein record rahega.
    </div>
    <div class="fg" style="text-align:left">
      <label class="fl">Owner Password *</label>
      <input class="fi" type="password" id="delPwd" placeholder="Password confirm karein">
    </div>
    <div class="brow" style="justify-content:center;margin-top:8px">
      <button class="bs bsr" onclick="confirmDelete()">🗑️ Delete Karein</button>
      <button class="bs bsg" onclick="closeModal('modalDelete')">Cancel</button>
    </div>
  </div>
</div>

<!-- TERMS MODAL -->
<div class="mo" id="modalTC" style="display:none">
  <div class="mb" style="max-width:600px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div>
        <div style="font-size:17px;font-weight:800">📜 Terms & Conditions</div>
        <div style="font-size:11px;color:var(--muted)">Digital Bandhak Platform — Shop Members ke liye</div>
      </div>
      <button class="bs bsg" onclick="closeModal('modalTC')">✕</button>
    </div>
    <div style="max-height:520px;overflow-y:auto;font-size:13px;line-height:1.9">
      <div style="background:linear-gradient(135deg,rgba(255,107,0,.1),rgba(255,179,0,.05));border:1px solid rgba(255,107,0,.2);border-radius:12px;padding:14px;margin-bottom:18px;text-align:center">
        <div style="font-size:22px;margin-bottom:6px">🏦</div>
        <div style="font-weight:800;font-size:15px;color:var(--saffron)">Digital Bandhak Platform</div>
        <div style="font-size:11px;color:var(--muted)">Yeh terms padhna zaroori hai. Platform use karne se pehle samajh lein.</div>
      </div>
      <div id="tcContent"></div>
      <div style="background:rgba(46,204,113,.07);border:1px solid rgba(46,204,113,.2);border-radius:10px;padding:13px;margin-top:8px;font-size:12px">
        <div style="font-weight:800;color:var(--green);margin-bottom:5px">✅ Agreement</div>
        <div style="color:var(--muted)">Platform use karne se aap in saari terms se agree karte hain.</div>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button class="bs bsp" onclick="tcAgree()">✅ Samajh Gaya, Close Karein</button>
    </div>
  </div>
</div>

<!-- PROFILE MODAL -->
<div class="mo" id="modalProfile" style="display:none">
  <div class="mb" style="max-width:500px" id="profileContent"></div>
</div>

<!-- NOTIFICATIONS MODAL -->
<div class="mo" id="modalNotif" style="display:none">
  <div class="mb" style="max-width:450px" id="notifContent"></div>
</div>

<!-- SETTINGS MODAL -->
<div class="mo" id="modalSettings" style="display:none">
  <div class="mb" style="max-width:480px" id="settingsContent"></div>
</div>

<!-- EXTEND SUB MODAL (Admin) -->
<div class="mo" id="modalExtend" style="display:none">
  <div class="mb" style="max-width:400px">
    <div style="font-size:16px;font-weight:800;margin-bottom:5px" id="extTitle">🔄 Extend Subscription</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:14px" id="extSub"></div>
    <div class="fg"><label class="fl">Duration</label>
      <select class="si" id="extDur" onchange="calcExtendAmount()">
        <option>1 Month</option><option>3 Months</option><option>6 Months</option><option>1 Year</option><option>2 Years</option>
      </select>
    </div>
    <div class="fg">
      <label class="fl">Amount (₹)</label>
      <input class="fi" id="extAmt" placeholder="Auto calculated">
      <div id="extDiscInfo" style="font-size:11px;margin-top:4px"></div>
    </div>
    <div class="fg"><label class="fl">Mode</label>
      <select class="si" id="extMode"><option>Cash</option><option>UPI</option><option>Bank Transfer</option></select>
    </div>
    <div class="brow" style="justify-content:flex-end;margin-top:8px">
      <button class="bs bsg" onclick="closeModal('modalExtend')">Cancel</button>
      <button class="bs bsp" onclick="confirmExtend()">✅ Confirm</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast" style="display:none">
  <span id="toastMsg">✅ Saved!</span>
</div>

<!-- PRINT AREA - sirf yahi print hoga -->
<div id="printArea" style="display:none">
  <div class="receipt" id="printReceipt" style="max-width:100%;border-radius:0;box-shadow:none"></div>
</div>

<!-- jsPDF for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>

<?php endif; // end dashboard ?>

<script src="js/app.js"></script>
<?php if ($role): ?>
<script>
const ROLE = '<?= $role ?>';
const SHOP_ID = '<?= $shop_id ?>';
const USER_NAME = '<?= htmlspecialchars($user_name) ?>';
const SHOP_NAME = '<?= htmlspecialchars($shop_name) ?>';
const USER_EMAIL = '<?= htmlspecialchars($user_email ?? "") ?>';
const USER_MOBILE = '<?= htmlspecialchars($user_mobile ?? "") ?>';
const USER_PHOTO_INIT = '<?= htmlspecialchars($_SESSION["photo"] ?? "") ?>';
<?php if ($role === 'customer'): ?>
const CUST_NAME    = '<?= htmlspecialchars($_SESSION['name'] ?? '') ?>';
const CUST_MOBILE  = '<?= htmlspecialchars($_SESSION['mobile'] ?? '') ?>';
const CUST_AADHAAR = '<?= htmlspecialchars($_SESSION['aadhaar'] ?? '') ?>';
<?php else: ?>
const CUST_NAME = '';
const CUST_MOBILE = '';
const CUST_AADHAAR = '';
<?php endif; ?>
</script>
<script src="js/dashboard.js"></script>
<?php endif; ?>
<!-- Dynamic Modal for Renew/Upgrade/ShopDetail -->
<div class="mo" id="dynModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="mb" style="max-width:520px;max-height:85vh;overflow-y:auto;-webkit-overflow-scrolling:touch" id="dynModalContent"></div>
</div>

<script>
window.addEventListener('load', function() {
  setTimeout(function() {
    var s = document.getElementById('splashLoader');
    if (s) { s.style.opacity = '0'; setTimeout(function(){ s.remove(); }, 500); }
  }, 800);
});
</script>



  <!-- BOTTOM NAVIGATION (Mobile) -->
  <div class="bottom-nav" id="bottomNav">
    <button class="bn-item active" id="bn-dash" onclick="navTo('dashboard',this)">
      <span class="bn-icon">🏠</span><span class="bn-label">Dashboard</span>
    </button>
    <button class="bn-item" id="bn-bandhak" onclick="navTo('bandhak',this)">
      <span class="bn-icon">📦</span><span class="bn-label">Bandhak</span>
    </button>
    <button class="bn-item" onclick="openAddPawnModal()" style="position:relative">
      <span class="bn-fab">➕</span>
      <span class="bn-label" style="margin-top:20px">Add</span>
    </button>
    <button class="bn-item" id="bn-payments" onclick="navTo('payments',this)">
      <span class="bn-icon">💵</span><span class="bn-label">Payments</span>
    </button>
    <button class="bn-item" id="bn-profile" onclick="navTo('profile',this)">
      <span class="bn-icon">👤</span><span class="bn-label">Profile</span>
    </button>
  </div>

<!-- Floating support button - login page only -->
<div id="floatSupport" style="position:fixed;bottom:20px;right:16px;z-index:9999">
  <a href="https://wa.me/916206869543?text=Help+chahiye+Digital+Bandhak+ke+liye" 
     target="_blank"
     style="display:flex;align-items:center;gap:6px;background:#25d366;color:#fff;border-radius:50px;padding:10px 16px;font-size:13px;font-weight:700;text-decoration:none;box-shadow:0 4px 16px rgba(37,211,102,.4)">
    💬 <span>Support</span>
  </a>
</div>
<script>
// Float support: only on login page - hide when dashboard loads
function hideFloatSupport() {
  var s = document.getElementById('floatSupport');
  if (s) s.style.display = 'none';
}
</script>

</body>
</html>
