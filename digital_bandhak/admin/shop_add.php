<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$success=''; $error=''; $createdId=''; $createdUser=''; $createdPass='';

// Check username availability (AJAX)
if (isset($_GET['check_username'])) {
    $un = trim($_GET['check_username']);
    $exists = $pdo->prepare("SELECT id FROM shops WHERE username=?"); $exists->execute([$un]); $r=$exists->fetch();
    header('Content-Type: application/json');
    echo json_encode(['available' => !$r, 'username' => $un]);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $shopName  = trim($_POST['shop_name']  ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $mobile    = preg_replace('/\D/','',trim($_POST['mobile']??''));
    $email     = trim($_POST['email']      ?? '');
    $city      = trim($_POST['city']       ?? '');
    $district  = trim($_POST['district']   ?? '');
    $state     = trim($_POST['state']      ?? '');
    $pincode   = trim($_POST['pincode']    ?? '');
    $address   = trim($_POST['address']    ?? '');
    $pass      = $_POST['password']        ?? '';
    $username  = strtolower(trim($_POST['username'] ?? ''));
    $plan      = $_POST['plan']            ?? 'trial';
    $gst       = trim($_POST['gst']        ?? '');
    $license   = trim($_POST['license']    ?? '');

    if (!$shopName||!$ownerName||!$mobile||!$pass) { $error='Required fields bharein'; }
    elseif (strlen($pass)<6) { $error='Password 6+ characters chahiye'; }
    elseif ($username && strlen($username)<3) { $error='Username 3+ characters chahiye'; }
    else {
        // Check username uniqueness
        if ($username) {
            $unCheck = $pdo->prepare("SELECT id FROM shops WHERE username=?"); $unCheck->execute([$username]); if ($unCheck->fetch()) { $error='Yeh username already le liya gaya hai. Dusra choose karo.'; }
        }
    }

    if (!$error) {
        $shopId = generateShopId($pdo); // Short: SH0001
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        try {
            // Build full address
            $fullAddress = implode(', ', array_filter([$address, $city, $district, $state, $pincode]));

            $pdo->prepare("INSERT INTO shops (shop_id,shop_name,owner_name,owner_email,owner_mobile,address,city,district,state,pincode,password,username,gst_number,license_number,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active')")
                ->execute([$shopId,$shopName,$ownerName,$email,$mobile,$fullAddress,$city,$district,$state,$pincode,$hashed,$username?:null,$gst,$license]);

            $durations=['trial'=>30,'monthly'=>30,'halfyearly'=>180,'annual'=>365];
            $prices=['trial'=>0,'monthly'=>299,'halfyearly'=>1499,'annual'=>2999];
            $end=date('Y-m-d',strtotime('+'.($durations[$plan]??30).' days'));
            $pdo->prepare("INSERT INTO subscriptions (shop_id,plan_type,start_date,end_date,amount,payment_mode,status) VALUES (?,?,CURDATE(),?,?,?,'active')")
                ->execute([$shopId,$plan,$end,$prices[$plan]??0,$plan==='trial'?'free':'cash']);

            auditLog($pdo,$shopId,'shop_created_admin',"Created: $shopName",'super_admin',$_SESSION['user_id'],$_SESSION['user_name'],$shopId);
            $createdId=$shopId; $createdUser=$username; $createdPass=$pass; $success='Shop created!';
        } catch(PDOException $e) {
            $error='Error: '.$e->getMessage();
        }
    }
}

// Check which extra columns exist
try {
    $allCols = $pdo->query("SHOW COLUMNS FROM shops")->fetchAll(PDO::FETCH_COLUMN);
    $hasDistrict = in_array('district', $allCols);
    $hasPincode  = in_array('pincode',  $allCols);
    $hasUsername = in_array('username', $allCols);
} catch(Exception $e) { $hasDistrict=$hasPincode=$hasUsername=false; }

$allStates=['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Delhi','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Other'];
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Add Shop — Admin</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php $navTitle='Add Shop'; $backLink='dashboard.php'; include '../includes/navbar.php'; ?>
<div class="main-content container" style="max-width:700px;padding-top:24px">
  <h2 style="margin-bottom:20px">🏪 New Shop Add Karo</h2>
  <?php if ($success && $createdId): ?>
  <div class="card" style="text-align:center;padding:30px">
    <div style="font-size:40px;margin-bottom:10px">🎉</div>
    <h3 style="color:var(--success);margin-bottom:14px">Shop Created!</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:400px;margin:0 auto 16px">
      <div style="background:var(--gold-light);border-radius:var(--radius);padding:12px;text-align:center">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">SHOP ID (Login ke liye)</div>
        <div style="font-size:22px;font-weight:700;color:var(--gold-dark);letter-spacing:2px"><?=$createdId?></div>
      </div>
      <?php if ($createdUser): ?>
      <div style="background:var(--info-bg);border-radius:var(--radius);padding:12px;text-align:center">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">USERNAME</div>
        <div style="font-size:18px;font-weight:700;color:var(--info)"><?=$createdUser?></div>
      </div>
      <?php endif; ?>
    </div>
    <div style="background:var(--surface);border-radius:var(--radius);padding:12px;max-width:400px;margin:0 auto 16px;font-size:13px">
      <div><strong>Password:</strong> <code><?=htmlspecialchars($createdPass)?></code></div>
      <div class="text-small text-muted mt-8">Yeh details owner ko de do. Shop ID ya Username se login kar sakte hain.</div>
    </div>
    <div style="display:flex;gap:10px;justify-content:center">
      <a href="subscription_add.php?shop_id=<?=$createdId?>" class="btn btn-gold">+ Add Subscription</a>
      <a href="dashboard.php" class="btn btn-outline">← Dashboard</a>
      <a href="shop_add.php" class="btn btn-outline">+ Another Shop</a>
    </div>
  </div>
  <?php else: ?>
  <?php if ($error): ?><div class="alert alert-danger">✖ <?=htmlspecialchars($error)?></div><?php endif; ?>

  <div class="card">
    <form method="POST" id="shopForm">
      <!-- Shop Details -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--border)">🏪 Shop Details</div>
      <div class="form-grid">
        <div class="form-group col-span-2">
          <label class="form-label">Shop Name *</label>
          <input class="form-control" type="text" name="shop_name" required placeholder="Ravi Jewellers" value="<?=htmlspecialchars($_POST['shop_name']??'')?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Owner Name *</label>
          <input class="form-control" type="text" name="owner_name" required placeholder="Ravi Kumar" value="<?=htmlspecialchars($_POST['owner_name']??'')?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Mobile *</label>
          <div style="display:flex">
            <span style="background:var(--surface);border:1px solid var(--border2);border-right:none;border-radius:var(--radius) 0 0 var(--radius);padding:9px 11px;font-size:13px;color:var(--text3)">+91</span>
            <input class="form-control" type="tel" name="mobile" required placeholder="9876543210" maxlength="10"
              style="border-radius:0 var(--radius) var(--radius) 0;border-left:none"
              oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)"
              value="<?=htmlspecialchars($_POST['mobile']??'')?>"/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" placeholder="owner@email.com" value="<?=htmlspecialchars($_POST['email']??'')?>"/>
        </div>
        <?php if ($hasUsername): ?>
        <div class="form-group">
          <label class="form-label">Username (optional)</label>
          <input class="form-control" type="text" name="username" id="usernameInp"
            placeholder="ravijewellers (login ke liye)"
            oninput="checkUsername(this.value)"
            value="<?=htmlspecialchars($_POST['username']??'')?>"/>
          <div id="unStatus" style="font-size:11px;margin-top:4px"></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Address -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin:14px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border)">📍 Address</div>
      <div class="form-grid">
        <div class="form-group col-span-2">
          <label class="form-label">Street / Mohalla</label>
          <input class="form-control" type="text" name="address" placeholder="Gali no. 5, Near Masjid" value="<?=htmlspecialchars($_POST['address']??'')?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">City *</label>
          <input class="form-control" type="text" name="city" required placeholder="Patna" value="<?=htmlspecialchars($_POST['city']??'')?>"/>
        </div>
        <?php if ($hasDistrict): ?>
        <div class="form-group">
          <label class="form-label">District</label>
          <input class="form-control" type="text" name="district" placeholder="Patna" value="<?=htmlspecialchars($_POST['district']??'')?>"/>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">State *</label>
          <select class="form-control" name="state" required>
            <option value="">-- State --</option>
            <?php foreach($allStates as $st): ?><option value="<?=$st?>" <?=(($_POST['state']??'')===$st)?'selected':''?>><?=$st?></option><?php endforeach; ?>
          </select>
        </div>
        <?php if ($hasPincode): ?>
        <div class="form-group">
          <label class="form-label">Pincode</label>
          <input class="form-control" type="text" name="pincode" placeholder="800001" maxlength="6" oninput="this.value=this.value.replace(/\D/g,'')" value="<?=htmlspecialchars($_POST['pincode']??'')?>"/>
        </div>
        <?php endif; ?>
      </div>

      <!-- Optional -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin:14px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border)">📄 Optional Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">GST Number</label>
          <input class="form-control" type="text" name="gst" placeholder="29ABCDE1234F1Z5" maxlength="15" style="text-transform:uppercase"/>
        </div>
        <div class="form-group">
          <label class="form-label">License Number</label>
          <input class="form-control" type="text" name="license" placeholder="Pawnbroker License No."/>
        </div>
      </div>

      <!-- Login & Plan -->
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin:14px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border)">🔑 Login & Plan</div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input class="form-control" type="text" name="password" required placeholder="Owner ka password" autocomplete="new-password"/>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Plan</label>
          <select class="form-control" name="plan">
            <option value="trial">Free Trial (30 days)</option>
            <option value="monthly">Monthly — ₹299</option>
            <option value="halfyearly">6 Month — ₹1,499</option>
            <option value="annual">Annual — ₹2,999</option>
          </select>
        </div>
      </div>

      <div class="form-group mt-8">
        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;color:var(--text2)">
          <input type="checkbox" name="terms" required style="margin-top:2px;width:16px;height:16px;flex-shrink:0"/>
          <span>Shop owner ne <a href="../terms.php" target="_blank" style="color:var(--gold)">Terms & Conditions</a> accept ki hain.</span>
        </label>
      </div>

      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="submit" class="btn btn-gold btn-lg">✔ Create Shop</button>
        <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php include '../includes/admin_mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
let unTimer = null;
function checkUsername(val) {
  const el = document.getElementById('unStatus');
  val = val.trim().toLowerCase();
  if (!val || val.length < 3) { el.textContent=''; return; }
  el.textContent = '⏳ Checking...'; el.style.color = 'var(--text3)';
  clearTimeout(unTimer);
  unTimer = setTimeout(async () => {
    const r = await fetch('shop_add.php?check_username=' + encodeURIComponent(val));
    const d = await r.json();
    if (d.available) {
      el.textContent = '✔ "' + val + '" available!';
      el.style.color = 'var(--success)';
    } else {
      el.textContent = '✖ "' + val + '" already taken. Dusra try karo.';
      el.style.color = 'var(--danger)';
    }
  }, 500);
}
</script>
</body>
</html>
