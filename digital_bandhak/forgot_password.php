<?php
require_once 'includes/config.php';

$logoUrl  = getSiteLogo($pdo);
$siteName = getSiteName($pdo);
$step     = $_GET['step'] ?? '1'; // 1=enter email, 2=verify OTP, 3=new password
$success  = ''; $error = '';
$verifiedEmail = $_SESSION['forgot_email'] ?? '';

// STEP 3: Save new password
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_password'])) {
    $email = trim($_POST['email'] ?? '');
    $new1  = $_POST['new_password']     ?? '';
    $new2  = $_POST['confirm_password'] ?? '';
    if ($new1 !== $new2) { $error='Passwords match nahi kiye'; $step='3'; }
    elseif (strlen($new1) < 8) { $error='Password 8+ characters chahiye'; $step='3'; }
    else {
        $hash = password_hash($new1, PASSWORD_DEFAULT);
        // Update admin or shop
        $adminUpd = $pdo->prepare("UPDATE super_admin SET password=? WHERE email=?"); $adminUpd->execute([$hash,$email]);
        $shopUpd  = $pdo->prepare("UPDATE shops SET password=? WHERE owner_email=?");  $shopUpd->execute([$hash,$email]);
        if ($adminUpd->rowCount() > 0 || $shopUpd->rowCount() > 0) {
            unset($_SESSION['forgot_email']);
            $success = 'Password successfully change ho gaya!';
            $step = 'done';
        } else {
            $error = 'Password update failed. Email check karo.';
            $step = '3';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Forgot Password — <?=htmlspecialchars($siteName)?></title>
  <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<div class="login-page">
  <div class="login-left">
    <div class="login-brand">
      <?php if ($logoUrl): ?>
        <img src="<?=htmlspecialchars($logoUrl)?>" alt="Logo" style="width:80px;height:80px;object-fit:contain;border-radius:16px"/>
      <?php else: ?>
        <div class="login-brand-icon">🔑</div>
      <?php endif; ?>
      <h1><?=htmlspecialchars($siteName)?></h1>
      <p>Password Reset</p>
    </div>
    <div class="login-feature"><div class="login-feature-icon">📧</div>
      <div><div class="login-feature-title">Email OTP</div><div class="login-feature-desc">Registered email par OTP jaayega</div></div>
    </div>
    <div class="login-feature"><div class="login-feature-icon">🔒</div>
      <div><div class="login-feature-title">Secure Reset</div><div class="login-feature-desc">OTP 10 minute mein expire ho jaata hai</div></div>
    </div>
    <a href="index.php" class="login-whatsapp" style="text-decoration:none">
      <div class="login-whatsapp-icon">←</div>
      <div><div class="login-whatsapp-title" style="color:#B8760A">Login Page</div><div class="login-whatsapp-desc">Wapas jaao</div></div>
    </a>
  </div>

  <div class="login-right">
    <div class="login-box">
      <div class="login-box-title">
        <div class="icon">🔑</div>
        <h2>Password Reset</h2>
        <p>Email se OTP lekar password reset karo</p>
      </div>

      <?php if ($error): ?><div class="login-error">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>

      <?php if ($step === 'done'): ?>
      <!-- Done -->
      <div style="text-align:center;padding:20px">
        <div style="font-size:40px;margin-bottom:10px">✅</div>
        <div style="color:#5A9;font-size:16px;font-weight:700;margin-bottom:8px">Password reset ho gaya!</div>
        <a href="index.php" class="login-btn" style="display:inline-block;text-decoration:none;width:auto;padding:10px 30px;margin-top:10px">Login Karo →</a>
      </div>

      <?php elseif ($step === '1'): ?>
      <!-- STEP 1: Enter email -->
      <div id="email-step">
        <div class="login-form-group">
          <label class="login-form-label">Registered Email *</label>
          <input class="login-form-control" type="email" id="reset_email" placeholder="digitalbandhak@gmail.com" autofocus/>
        </div>
        <button class="login-btn" onclick="sendEmailOtp()">📧 OTP Bhejo</button>
        <div id="emailOtpStatus" style="margin-top:10px;font-size:12px;color:#8A7050"></div>
      </div>

      <!-- STEP 2: Verify OTP (shown after email sent) -->
      <div id="otp-step" style="display:none">
        <div style="background:rgba(26,107,58,0.12);border:1px solid rgba(26,107,58,0.25);border-radius:8px;padding:10px 14px;color:#5A9;font-size:13px;margin-bottom:14px">
          ✔ OTP bheja gaya hai! Check karo inbox/spam.
        </div>
        <div class="login-form-group">
          <label class="login-form-label">6-Digit OTP</label>
          <div class="otp-inputs">
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,0)" onkeydown="otpBack(this,0,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,1)" onkeydown="otpBack(this,1,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,2)" onkeydown="otpBack(this,2,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,3)" onkeydown="otpBack(this,3,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,4)" onkeydown="otpBack(this,4,event)"/>
            <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,5)" onkeydown="otpBack(this,5,event)"/>
          </div>
        </div>
        <button class="login-btn" onclick="verifyEmailOtp()">✔ Verify OTP</button>
        <div style="text-align:center;margin-top:10px">
          <a href="#" onclick="sendEmailOtp();return false" class="login-link">🔄 Resend OTP</a>
        </div>
      </div>

      <!-- STEP 3: New password (shown after OTP verified) -->
      <div id="newpw-step" style="display:none">
        <form method="POST">
          <input type="hidden" name="save_password" value="1"/>
          <input type="hidden" name="email" id="verifiedEmailField"/>
          <div class="login-form-group">
            <label class="login-form-label">New Password *</label>
            <input class="login-form-control" type="password" id="reg_password" name="new_password" required placeholder="Min 8 characters"/>
          </div>
          <div class="login-form-group">
            <label class="login-form-label">Confirm Password *</label>
            <input class="login-form-control" type="password" id="reg_confirm" name="confirm_password" required placeholder="Dobara daalo"/>
          </div>
          <button type="submit" class="login-btn" onclick="return validateShopForm()">🔑 Save New Password</button>
        </form>
      </div>

      <?php elseif ($step === '3'): ?>
      <!-- Direct step 3 if coming back -->
      <form method="POST">
        <input type="hidden" name="save_password" value="1"/>
        <input type="hidden" name="email" value="<?=htmlspecialchars($verifiedEmail)?>"/>
        <div class="login-form-group">
          <label class="login-form-label">New Password</label>
          <input class="login-form-control" type="password" id="reg_password" name="new_password" required/>
        </div>
        <div class="login-form-group">
          <label class="login-form-label">Confirm</label>
          <input class="login-form-control" type="password" id="reg_confirm" name="confirm_password" required/>
        </div>
        <button type="submit" class="login-btn" onclick="return validateShopForm()">Save Password</button>
      </form>
      <?php endif; ?>

      <div style="text-align:center;margin-top:14px">
        <a href="index.php" class="login-link">← Login page par jao</a>
      </div>
    </div>
  </div>
</div>

<script src="js/app.js"></script>
<script>
let verifiedEmail = '';

async function sendEmailOtp() {
  const email = document.getElementById('reset_email')?.value.trim();
  if (!email) { showAlert('Email daalo','warning'); return; }
  const status = document.getElementById('emailOtpStatus');
  status.textContent = '⏳ Sending OTP...';

  const r = await apiPost('php/email_otp_send.php', { email, purpose:'forgot_password' });
  if (r.success) {
    document.getElementById('email-step').style.display = 'none';
    document.getElementById('otp-step').style.display   = 'block';
    verifiedEmail = email;
    // DEV: auto-fill OTP
    if (r.dev_otp) {
      showAlert('DEV OTP: ' + r.dev_otp, 'warning', 15000);
      const boxes = document.querySelectorAll('#otp-step .otp-input');
      r.dev_otp.toString().split('').forEach((d,i)=>{ if(boxes[i]) boxes[i].value=d; });
    }
  } else {
    status.textContent = '✖ ' + (r.msg||'Error');
    status.style.color = '#E55';
  }
}

async function verifyEmailOtp() {
  const boxes = document.querySelectorAll('#otp-step .otp-input');
  const otp   = Array.from(boxes).map(b=>b.value).join('');
  if (otp.length < 6) { showAlert('6-digit OTP daalo','warning'); return; }

  const r = await apiPost('php/email_otp_verify.php', { email:verifiedEmail, otp, purpose:'forgot_password' });
  if (r.success) {
    document.getElementById('otp-step').style.display    = 'none';
    document.getElementById('newpw-step').style.display  = 'block';
    document.getElementById('verifiedEmailField').value  = verifiedEmail;
    showAlert('OTP verified!', 'success');
  } else {
    showAlert(r.msg||'Invalid OTP','danger');
  }
}

function otpBack(el, idx, e) {
  const boxes = document.querySelectorAll('.otp-input');
  if (e.key === 'Backspace' && !el.value && idx > 0) boxes[idx-1].focus();
}
</script>
</body>
</html>
