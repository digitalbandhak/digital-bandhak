<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$adminId = $_SESSION['user_id'];
$success = ''; $error = '';

// Safe column add — check first
try {
    $cols = $pdo->query("SHOW COLUMNS FROM super_admin")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('profile_pic', $cols)) {
        $pdo->exec("ALTER TABLE super_admin ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
    }
} catch(Exception $e) { /* ignore */ }

$stmt = $pdo->prepare("SELECT * FROM super_admin WHERE id=?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $user  = trim($_POST['username'] ?? '');
        $email = trim($_POST['email']    ?? '');
        if (!$user || !$email) {
            $error = 'Username aur email required';
        } else {
            $pdo->prepare("UPDATE super_admin SET username=?, email=? WHERE id=?")
                ->execute([$user, $email, $adminId]);
            $_SESSION['user_name'] = $user;
            $success = 'Profile updated!';
            $stmt->execute([$adminId]); $admin = $stmt->fetch();
        }
    }

    if ($action === 'update_photo' && !empty($_FILES['profile_pic']['name'])) {
        $f   = $_FILES['profile_pic'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $error = 'Only image files (JPG/PNG/WEBP) allowed';
        } elseif ($f['size'] > 3*1024*1024) {
            $error = 'Max 3MB allowed';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error code: '.$f['error'].'. Check PHP upload settings in Laragon.';
        } else {
            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
            // Remove old pic
            $oldPic = $admin['profile_pic'] ?? '';
            if ($oldPic && file_exists(UPLOAD_PATH.$oldPic)) @unlink(UPLOAD_PATH.$oldPic);
            $fname = 'admin_'.time().'.'.$ext;
            if (move_uploaded_file($f['tmp_name'], UPLOAD_PATH.$fname)) {
                $pdo->prepare("UPDATE super_admin SET profile_pic=? WHERE id=?")
                    ->execute([$fname, $adminId]);
                $_SESSION['profile_pic'] = $fname;
                $success = 'Photo updated!';
                $stmt->execute([$adminId]); $admin = $stmt->fetch();
            } else {
                $error = 'File save failed. Path: '.UPLOAD_PATH.' | Writable: '.(is_writable(UPLOAD_PATH)?'Yes':'NO - Fix permissions!');
            }
        }
    }

    if ($action === 'change_password') {
        $old  = $_POST['old_password']     ?? '';
        $new1 = $_POST['new_password']     ?? '';
        $new2 = $_POST['confirm_password'] ?? '';
        if (!password_verify($old, $admin['password'])) {
            $error = 'Current password galat hai';
        } elseif ($new1 !== $new2) {
            $error = 'New passwords match nahi kiye';
        } elseif (strlen($new1) < 8) {
            $error = '8+ characters chahiye';
        } else {
            $pdo->prepare("UPDATE super_admin SET password=? WHERE id=?")
                ->execute([password_hash($new1, PASSWORD_DEFAULT), $adminId]);
            $success = 'Password successfully changed!';
        }
    }
}

$pic    = $admin['profile_pic'] ?? ($_SESSION['profile_pic'] ?? '');
$hasPic = $pic && file_exists(UPLOAD_PATH.$pic);
$unreadCount = 0;
try {
    $ur = $pdo->query("SELECT COUNT(*) FROM admin_chat_messages WHERE sender_type='owner' AND is_read=0");
    $unreadCount = $ur->fetchColumn();
} catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin Profile — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="dashboard-layout">
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="shop_add.php"><span class="sidebar-icon">🏪</span> Add Shop</a>
    <a href="subscription_add.php"><span class="sidebar-icon">🔁</span> Subscriptions</a>
    <div class="sidebar-divider"></div>
    <a href="audit_logs.php"><span class="sidebar-icon">📋</span> Audit Logs</a>
    <a href="transactions.php"><span class="sidebar-icon">💳</span> Transactions</a>
    <div class="sidebar-divider"></div>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Private Chat<?php if($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?></a>
    <a href="settings.php"><span class="sidebar-icon">⚙️</span> Site Settings</a>
    <a href="profile.php" class="active"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>

  <main class="main-content">
    <h2 style="margin-bottom:20px">👤 Admin Profile</h2>
    <?php if ($success): ?><div class="alert alert-success">✔ <?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">✖ <?=htmlspecialchars($error)?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px">
      <!-- Photo Card -->
      <div class="card" style="text-align:center">
        <div class="card-title" style="justify-content:center">📸 Profile Photo</div>
        <div style="width:90px;height:90px;border-radius:50%;border:3px solid var(--gold);background:var(--gold-light);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:var(--gold-dark);overflow:hidden" id="picBox">
          <?php if ($hasPic): ?>
            <img src="../uploads/<?=htmlspecialchars($pic)?>" style="width:100%;height:100%;object-fit:cover" alt="Photo" id="picImg"/>
          <?php else: ?>
            <span id="picInitials">SA</span>
          <?php endif; ?>
        </div>
        <p style="font-weight:600;margin-bottom:2px"><?=htmlspecialchars($admin['username'])?></p>
        <p class="text-muted text-small mb-16">Super Admin</p>

        <form method="POST" enctype="multipart/form-data" id="photoForm">
          <input type="hidden" name="action" value="update_photo"/>
          <div class="upload-zone" onclick="document.getElementById('adminPicInp').click()" style="padding:12px;cursor:pointer">
            📷 Click to upload<br/><span class="text-small">JPG/PNG/WEBP max 3MB</span>
          </div>
          <input type="file" id="adminPicInp" name="profile_pic" accept="image/jpeg,image/png,image/webp,image/gif"
            style="display:none"
            onchange="previewAndUpload(this)"/>
          <button type="submit" id="picSubmitBtn" class="btn btn-gold w-full mt-8" style="display:none">📤 Upload Photo</button>
        </form>

        <div class="alert alert-info mt-12" style="font-size:11px;text-align:left">
          Upload path: <code style="font-size:10px"><?=basename(UPLOAD_PATH)?>/</code><br/>
          <?=is_dir(UPLOAD_PATH)?'<span style="color:var(--success)">✔ Folder exists</span>':'<span style="color:var(--danger)">✖ Folder missing!</span>'?>
          · <?=is_writable(UPLOAD_PATH)?'<span style="color:var(--success)">✔ Writable</span>':'<span style="color:var(--danger)">✖ Not writable</span>'?>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px">
        <!-- Admin Info -->
        <div class="card">
          <div class="card-title">✏️ Admin Info</div>
          <form method="POST">
            <input type="hidden" name="action" value="update_info"/>
            <div class="form-group">
              <label class="form-label">Username</label>
              <input class="form-control" type="text" name="username" value="<?=htmlspecialchars($admin['username'])?>" required/>
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" value="<?=htmlspecialchars($admin['email'])?>" required/>
            </div>
            <button type="submit" class="btn btn-gold">💾 Save Changes</button>
          </form>
        </div>

        <!-- Change Password -->
        <div class="card">
          <div class="card-title">🔑 Change Password</div>
          <form method="POST">
            <input type="hidden" name="action" value="change_password"/>
            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input class="form-control" type="password" name="old_password" required placeholder="Current password"/>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input class="form-control" type="password" id="reg_password" name="new_password" required placeholder="Min 8 chars"/>
              </div>
              <div class="form-group">
                <label class="form-label">Confirm New</label>
                <input class="form-control" type="password" id="reg_confirm" name="confirm_password" required placeholder="Dobara daalo"/>
              </div>
            </div>
            <button type="submit" class="btn btn-gold" onclick="return validateShopForm()">🔑 Change Password</button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/admin_mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
function previewAndUpload(inp) {
  if (!inp.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const box = document.getElementById('picBox');
    let img = document.getElementById('picImg');
    if (!img) {
      box.innerHTML = '';
      img = document.createElement('img');
      img.id = 'picImg';
      img.style.cssText = 'width:100%;height:100%;object-fit:cover';
      box.appendChild(img);
    }
    img.src = e.target.result;
  };
  reader.readAsDataURL(inp.files[0]);
  document.getElementById('picSubmitBtn').style.display = 'block';
}
</script>
</body>
</html>
