<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId  = $_SESSION['shop_id'];
$ownerId = $_SESSION['user_id'];
$success = ''; $error = '';

$shopStmt = $pdo->prepare("SELECT * FROM shops WHERE id=?"); $shopStmt->execute([$ownerId]); $shop=$shopStmt->fetch();
$unreadCount = $pdo->prepare("SELECT COUNT(*) FROM admin_chat_messages WHERE shop_id=? AND sender_type='admin' AND is_read=0")->execute([$shopId]) ? $pdo->prepare("SELECT COUNT(*) FROM admin_chat_messages WHERE shop_id=? AND sender_type='admin' AND is_read=0") : 0;
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_chat_messages WHERE shop_id=? AND sender_type='admin' AND is_read=0");
$unreadStmt->execute([$shopId]); $unreadCount = $unreadStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';

    if ($action==='update_info') {
        $name  = trim($_POST['owner_name']??'');
        $email = trim($_POST['owner_email']??'');
        $city  = trim($_POST['city']??'');
        $state = trim($_POST['state']??'');
        $addr  = trim($_POST['address']??'');
        $pdo->prepare("UPDATE shops SET owner_name=?,owner_email=?,city=?,state=?,address=? WHERE id=?")->execute([$name,$email,$city,$state,$addr,$ownerId]);
        $_SESSION['user_name']=$name;
        $success='Profile updated!';
        $shopStmt->execute([$ownerId]); $shop=$shopStmt->fetch();
    }

    if ($action==='update_photo' && !empty($_FILES['profile_pic']['name'])) {
        $f   = $_FILES['profile_pic'];
        $ext = strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp','gif'])) { $error='Only image files allowed'; }
        elseif ($f['size']>3*1024*1024) { $error='Max 3MB'; }
        elseif ($f['error']!==UPLOAD_ERR_OK) { $error='Upload error: '.$f['error'].'. Check PHP upload settings.'; }
        else {
            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH,0755,true);
            $old=$shop['logo']??'';
            if ($old && file_exists(UPLOAD_PATH.$old) && strpos($old,'profile_')===0) @unlink(UPLOAD_PATH.$old);
            $fname='profile_'.time().'.'.$ext;
            if (move_uploaded_file($f['tmp_name'],UPLOAD_PATH.$fname)) {
                $pdo->prepare("UPDATE shops SET logo=? WHERE id=?")->execute([$fname,$ownerId]);
                $_SESSION['profile_pic']=$fname;
                $success='Photo updated!';
                $shopStmt->execute([$ownerId]); $shop=$shopStmt->fetch();
            } else { $error='File save failed. Check uploads folder: '.UPLOAD_PATH; }
        }
    }

    if ($action==='change_password') {
        $old=$_POST['old_password']??''; $new1=$_POST['new_password']??''; $new2=$_POST['confirm_password']??'';
        if (!password_verify($old,$shop['password'])) { $error='Current password galat'; }
        elseif ($new1!==$new2) { $error='Passwords match nahi'; }
        elseif (strlen($new1)<8) { $error='8+ characters chahiye'; }
        else {
            $pdo->prepare("UPDATE shops SET password=? WHERE id=?")->execute([password_hash($new1,PASSWORD_DEFAULT),$ownerId]);
            auditLog($pdo,$shopId,'password_changed','Owner changed password','owner',$ownerId,$_SESSION['user_name']);
            $success='Password changed!';
        }
    }
}

$pic    = $shop['logo'] ?? ($_SESSION['profile_pic']??'');
$hasPic = $pic && file_exists(UPLOAD_PATH.$pic);
$allStates=['Bihar','Uttar Pradesh','Jharkhand','Delhi','Maharashtra','Rajasthan','Madhya Pradesh','Gujarat','West Bengal','Punjab','Haryana','Tamil Nadu','Karnataka','Andhra Pradesh','Telangana','Other'];
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>My Profile — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="dashboard-layout">
  <!-- CORRECT SIDEBAR — all links point to correct pages -->
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="pawn_add.php"><span class="sidebar-icon">➕</span> New Entry</a>
    <a href="pawn_list.php"><span class="sidebar-icon">📋</span> Pawn List</a>
    <a href="payments.php"><span class="sidebar-icon">💰</span> Payments</a>
    <div class="sidebar-divider"></div>
    <a href="reports.php"><span class="sidebar-icon">📄</span> Reports</a>
    <a href="interest_calc.php"><span class="sidebar-icon">🧮</span> Interest Calc</a>
    <a href="subscription.php"><span class="sidebar-icon">🔁</span> Subscription</a>
    <a href="staff.php"><span class="sidebar-icon">👷</span> Staff</a>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat Admin<?php if($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?></a>
    <a href="profile.php" class="active"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../terms.php"><span class="sidebar-icon">📜</span> Terms</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content">
    <h2 style="margin-bottom:20px">👤 My Profile</h2>
    <?php if ($success): ?><div class="alert alert-success">✔ <?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">✖ <?=htmlspecialchars($error)?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:260px 1fr;gap:16px" class="profile-grid">
    <style>.profile-grid{@media(max-width:768px){grid-template-columns:1fr!important;}}</style>
      <!-- Photo + Shop Info -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card" style="text-align:center">
          <div class="card-title" style="justify-content:center">📸 Photo</div>
          <div style="width:90px;height:90px;border-radius:50%;border:3px solid var(--gold);background:var(--gold-light);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--gold-dark);overflow:hidden">
            <?php if ($hasPic): ?><img src="../uploads/<?=htmlspecialchars($pic)?>" style="width:100%;height:100%;object-fit:cover" alt=""/><?php else: ?><?=strtoupper(substr($shop['owner_name'],0,2))?><?php endif; ?>
          </div>
          <p style="font-weight:600;font-size:14px;margin-bottom:2px"><?=htmlspecialchars($shop['owner_name'])?></p>
          <p class="text-muted text-small mb-12"><?=htmlspecialchars($shop['shop_name'])?></p>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_photo"/>
            <div class="upload-zone" onclick="document.getElementById('ppic').click()" style="padding:10px">
              📷 Upload Photo<br/><span class="text-small">JPG/PNG max 3MB</span>
            </div>
            <input type="file" id="ppic" name="profile_pic" accept="image/*" style="display:none" onchange="document.getElementById('ppicSubmit').click()"/>
            <button type="submit" id="ppicSubmit" style="display:none"></button>
          </form>
        </div>
        <!-- Shop readonly info -->
        <div class="card">
          <div class="card-title">🏪 Shop Info</div>
          <div style="font-size:13px">
            <div class="receipt-row"><span class="receipt-key">Shop ID</span><span class="receipt-val" style="font-size:11px"><?=htmlspecialchars($shop['shop_id'])?></span></div>
            <div class="receipt-row"><span class="receipt-key">Mobile</span><span class="receipt-val"><?=htmlspecialchars($shop['owner_mobile'])?></span></div>
            <div class="receipt-row"><span class="receipt-key">GST</span><span class="receipt-val text-small"><?=htmlspecialchars($shop['gst_number']??'—')?></span></div>
            <div class="receipt-row"><span class="receipt-key">Joined</span><span class="receipt-val text-small"><?=date('d M Y',strtotime($shop['created_at']))?></span></div>
          </div>
          <p class="text-small text-muted mt-8">Mobile change ke liye Admin se contact karo.</p>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px">
        <!-- Edit Info -->
        <div class="card">
          <div class="card-title">✏️ Profile Info</div>
          <form method="POST">
            <input type="hidden" name="action" value="update_info"/>
            <div class="form-grid">
              <div class="form-group"><label class="form-label">Owner Name</label><input class="form-control" type="text" name="owner_name" value="<?=htmlspecialchars($shop['owner_name'])?>" required/></div>
              <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="owner_email" value="<?=htmlspecialchars($shop['owner_email']??'')?>"/></div>
              <div class="form-group"><label class="form-label">City</label><input class="form-control" type="text" name="city" value="<?=htmlspecialchars($shop['city']??'')?>"/></div>
              <div class="form-group"><label class="form-label">State</label>
                <select class="form-control" name="state">
                  <?php foreach($allStates as $st): ?><option <?=($shop['state']??'')===$st?'selected':''?>><?=$st?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-span-2"><label class="form-label">Address</label><input class="form-control" type="text" name="address" value="<?=htmlspecialchars($shop['address']??'')?>"/></div>
            </div>
            <button type="submit" class="btn btn-gold">💾 Save Changes</button>
          </form>
        </div>
        <!-- Password -->
        <div class="card">
          <div class="card-title">🔑 Change Password</div>
          <form method="POST">
            <input type="hidden" name="action" value="change_password"/>
            <div class="form-group"><label class="form-label">Current Password</label><input class="form-control" type="password" name="old_password" required/></div>
            <div class="form-grid">
              <div class="form-group"><label class="form-label">New Password</label><input class="form-control" type="password" id="reg_password" name="new_password" required placeholder="Min 8 chars"/></div>
              <div class="form-group"><label class="form-label">Confirm</label><input class="form-control" type="password" id="reg_confirm" name="confirm_password" required/></div>
            </div>
            <button type="submit" class="btn btn-gold" onclick="return validateShopForm()">🔑 Change Password</button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
</body>
</html>
