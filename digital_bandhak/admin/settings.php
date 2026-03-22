<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$success=''; $error='';

// Ensure site_settings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (`key` VARCHAR(100) PRIMARY KEY, `value` TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$pdo->exec("INSERT IGNORE INTO site_settings (`key`,`value`) VALUES ('site_name','Digital Bandhak'),('site_tagline','Aapki Girvee, Hamaari Zimmedaari'),('whatsapp_number',''),('support_email','')");

// Ensure uploads folder exists
if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);

$settingsRaw = $pdo->query("SELECT * FROM site_settings")->fetchAll();
$settings=[]; foreach($settingsRaw as $s) $settings[$s['key']]=$s['value'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';

    if ($action==='upload_logo') {
        if (empty($_FILES['site_logo']['name'])) { $error='Koi file select nahi ki'; }
        else {
            $file    = $_FILES['site_logo'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png','jpg','jpeg','gif','webp','svg'];
            $maxSize = 2*1024*1024;

            if (!in_array($ext,$allowed))      { $error='Only PNG/JPG/SVG/GIF/WEBP allowed'; }
            elseif ($file['size'] > $maxSize)  { $error='Max file size 2MB'; }
            elseif ($file['error'] !== UPLOAD_ERR_OK) { $error='Upload error: '.$file['error'].'. Check PHP upload_max_filesize setting.'; }
            else {
                // Delete old logo
                $old=$settings['site_logo']??'';
                if ($old && file_exists(UPLOAD_PATH.$old) && strpos($old,'logo_')===0) @unlink(UPLOAD_PATH.$old);

                $fname = 'logo_'.time().'_'.mt_rand(1000,9999).'.'.$ext;
                $dest  = UPLOAD_PATH . $fname;

                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $error = 'File save failed. Upload folder permission check karo: '.UPLOAD_PATH;
                } else {
                    $pdo->prepare("INSERT INTO site_settings (`key`,`value`) VALUES ('site_logo',?) ON DUPLICATE KEY UPDATE `value`=?")->execute([$fname,$fname]);
                    $settings['site_logo']=$fname;
                    $success='Logo successfully upload ho gaya!';
                }
            }
        }
    }

    if ($action==='update_settings') {
        $fields=['site_name','site_tagline','whatsapp_number','support_email'];
        foreach($fields as $f){
            $val=trim($_POST[$f]??'');
            $pdo->prepare("INSERT INTO site_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?")->execute([$f,$val,$val]);
            $settings[$f]=$val;
        }
        $success='Settings saved!';
    }

    if ($action==='remove_logo') {
        $old=$settings['site_logo']??'';
        if ($old && file_exists(UPLOAD_PATH.$old)) @unlink(UPLOAD_PATH.$old);
        $pdo->prepare("DELETE FROM site_settings WHERE `key`='site_logo'")->execute();
        $settings['site_logo']='';
        $success='Logo removed!';
    }
}

$logoFile = $settings['site_logo']??'';
$hasLogo  = $logoFile && file_exists(UPLOAD_PATH.$logoFile);
$logoPath = $hasLogo ? '../uploads/'.htmlspecialchars($logoFile) : null;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Site Settings — Digital Bandhak</title>
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
    <a href="chat.php"><span class="sidebar-icon">💬</span> Private Chat</a>
    <a href="settings.php" class="active"><span class="sidebar-icon">⚙️</span> Site Settings</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content">
    <h2 style="margin-bottom:20px">⚙️ Site Settings</h2>
    <?php if ($success): ?><div class="alert alert-success">✔ <?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">✖ <?=htmlspecialchars($error)?></div><?php endif; ?>

    <!-- Upload path debug info -->
    <div class="alert alert-info mb-16" style="font-size:12px">
      📁 Upload folder: <code><?=UPLOAD_PATH?></code> — 
      <?=is_dir(UPLOAD_PATH)?'<span style="color:var(--success)">✔ Exists</span>':'<span style="color:var(--danger)">✖ Not found! Create this folder.</span>'?> — 
      <?=is_writable(UPLOAD_PATH)?'<span style="color:var(--success)">✔ Writable</span>':'<span style="color:var(--danger)">✖ Not writable!</span>'?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <!-- Logo Upload -->
      <div class="card">
        <div class="card-title">🏅 Site Logo</div>
        <!-- Current logo display -->
        <div style="text-align:center;margin-bottom:16px">
          <div id="logoPreview" style="width:140px;height:140px;border-radius:16px;background:var(--surface);border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;margin:0 auto;overflow:hidden">
            <?php if ($hasLogo): ?>
              <img src="<?=$logoPath?>" style="width:100%;height:100%;object-fit:contain" alt="Logo"/>
            <?php else: ?>
              <span style="font-size:48px">🏅</span>
            <?php endif; ?>
          </div>
          <?php if ($hasLogo): ?>
          <form method="POST" style="margin-top:8px">
            <input type="hidden" name="action" value="remove_logo"/>
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Logo remove karein?')">✖ Remove Logo</button>
          </form>
          <?php endif; ?>
        </div>

        <!-- Logo upload form — MUST be multipart -->
        <form method="POST" enctype="multipart/form-data" id="logoForm">
          <input type="hidden" name="action" value="upload_logo"/>
          <div class="upload-zone" onclick="document.getElementById('logo_file_inp').click()" id="uploadZone">
            <span style="font-size:28px">📷</span><br/>
            <strong>Click to upload logo</strong><br/>
            <span class="text-small">PNG / JPG / SVG / WEBP — Max 2MB<br/>Recommended: 200×200px</span>
          </div>
          <input type="file" id="logo_file_inp" name="site_logo" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml" style="display:none" onchange="previewAndSubmit(this)"/>
          <button type="submit" id="logoUploadBtn" style="display:none" class="btn btn-gold w-full mt-8">Upload Logo</button>
        </form>

        <div class="alert alert-info mt-12" style="font-size:12px">
          Yeh logo <strong>login page</strong>, <strong>navbar</strong> aur <strong>receipts</strong> par sabko dikhega.
        </div>
      </div>

      <!-- Site Settings -->
      <div class="card">
        <div class="card-title">🔧 Site Information</div>
        <form method="POST">
          <input type="hidden" name="action" value="update_settings"/>
          <div class="form-group"><label class="form-label">Site Name</label><input class="form-control" type="text" name="site_name" value="<?=htmlspecialchars($settings['site_name']??'Digital Bandhak')?>"/></div>
          <div class="form-group"><label class="form-label">Tagline</label><input class="form-control" type="text" name="site_tagline" value="<?=htmlspecialchars($settings['site_tagline']??'Aapki Girvee, Hamaari Zimmedaari')?>"/></div>
          <div class="form-group"><label class="form-label">WhatsApp Number (with country code)</label><input class="form-control" type="text" name="whatsapp_number" value="<?=htmlspecialchars($settings['whatsapp_number']??'')?>" placeholder="919876543210"/></div>
          <div class="form-group"><label class="form-label">Support Email</label><input class="form-control" type="email" name="support_email" value="<?=htmlspecialchars($settings['support_email']??'')?>"/></div>
          <button type="submit" class="btn btn-gold w-full">💾 Save Settings</button>
        </form>
      </div>

      <!-- Credentials Reference -->
      <div class="card" style="grid-column:1/-1">
        <div class="card-title">🔑 Login Credentials Reference</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Role</th><th>Login ID</th><th>Password</th><th>Access</th></tr></thead>
            <tbody>
              <tr><td><span class="badge badge-active">Super Admin</span></td><td><code>digitalbandhak@gmail.com</code></td><td><code>Digitalbandhak@2026#</code></td><td>Full system access</td></tr>
              <tr><td><span class="badge badge-trial">Shop Owner</span></td><td>Shop ID (SHOP-XXXX-XXXXX)</td><td>Registration pe set</td><td>Apna shop data only</td></tr>
              <tr><td><span class="badge badge-closed">Staff</span></td><td>Same Shop ID</td><td>Owner ne set kiya</td><td>New entry + list only</td></tr>
              <tr><td><span class="badge badge-pending">Customer</span></td><td>Bandhak ID + Mobile OTP</td><td>SMS OTP</td><td>Apni items — view only</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/admin_mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
function previewAndSubmit(inp) {
  if (!inp.files[0]) return;
  const file = inp.files[0];
  // Show preview
  const reader = new FileReader();
  reader.onload = e => {
    const prev = document.getElementById('logoPreview');
    prev.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:contain"/>`;
  };
  reader.readAsDataURL(file);
  // Update zone text
  document.getElementById('uploadZone').innerHTML = `<span style="font-size:20px">✔</span><br/><strong>${file.name}</strong><br/><span class="text-small">${(file.size/1024).toFixed(1)} KB — Click Upload button below</span>`;
  document.getElementById('logoUploadBtn').style.display = 'block';
}
</script>
</body>
</html>
