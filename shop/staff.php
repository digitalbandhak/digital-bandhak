<?php
// shop/staff.php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId  = $_SESSION['shop_id'];
$ownerId = $_SESSION['user_id'];
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    if ($action==='add_staff') {
        $name = trim($_POST['staff_name']??'');
        $mob  = trim($_POST['mobile']??'');
        $pw   = $_POST['password']??'';
        if (!$name||!$pw) { $error='Name aur password zaroori'; }
        elseif (strlen($pw)<6) { $error='Password 6+ chars'; }
        else {
            $pdo->prepare("INSERT INTO staff (shop_id,staff_name,mobile,password) VALUES (?,?,?,?)")->execute([$shopId,$name,$mob,password_hash($pw,PASSWORD_DEFAULT)]);
            $success='Staff added!';
        }
    }
    if ($action==='toggle_staff') {
        $sid=$_POST['staff_id']??0; $s=$_POST['status']??'active';
        $pdo->prepare("UPDATE staff SET status=? WHERE id=? AND shop_id=?")->execute([$s,$sid,$shopId]);
        $success='Staff status updated!';
    }
    if ($action==='delete_staff') {
        $sid=$_POST['staff_id']??0;
        $pdo->prepare("DELETE FROM staff WHERE id=? AND shop_id=?")->execute([$sid,$shopId]);
        $success='Staff removed!';
    }
}

$staffList = $pdo->prepare("SELECT * FROM staff WHERE shop_id=? ORDER BY created_at DESC"); $staffList->execute([$shopId]); $staff=$staffList->fetchAll();
$unreadCount=0;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Staff Management</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="dashboard-layout">
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="pawn_add.php"><span class="sidebar-icon">➕</span> New Entry</a>
    <a href="pawn_list.php"><span class="sidebar-icon">📋</span> Pawn List</a>
    <a href="payments.php"><span class="sidebar-icon">💰</span> Payments</a>
    <div class="sidebar-divider"></div>
    <a href="reports.php"><span class="sidebar-icon">📄</span> Reports</a>
    <a href="interest_calc.php"><span class="sidebar-icon">🧮</span> Interest Calc</a>
    <a href="subscription.php"><span class="sidebar-icon">🔁</span> Subscription</a>
    <a href="staff.php" class="active"><span class="sidebar-icon">👷</span> Staff</a>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat Admin</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content">
    <h2 style="margin-bottom:20px">👷 Staff Management</h2>
    <?php if ($success): ?><div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">✖ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div class="card">
        <div class="card-title">➕ Add New Staff</div>
        <div class="alert alert-info">Staff sirf New Pawn Entry add aur Bandhak List dekh sakta hai.</div>
        <form method="POST">
          <input type="hidden" name="action" value="add_staff"/>
          <div class="form-group"><label class="form-label">Staff Name *</label><input class="form-control" type="text" name="staff_name" required placeholder="Staff ka naam"/></div>
          <div class="form-group"><label class="form-label">Mobile</label><input class="form-control" type="tel" name="mobile" placeholder="Optional"/></div>
          <div class="form-group"><label class="form-label">Password *</label><input class="form-control" type="text" name="password" required placeholder="Login password (min 6 chars)" autocomplete="new-password"/></div>
          <p class="text-small text-muted mb-8">Login ke liye: Shop ID = <strong><?= $shopId ?></strong> + yeh password</p>
          <button type="submit" class="btn btn-gold">➕ Add Staff</button>
        </form>
      </div>
      <div class="card">
        <div class="card-title">👷 Current Staff (<?= count($staff) ?>)</div>
        <?php if (empty($staff)): ?>
        <p class="text-muted" style="text-align:center;padding:20px">Koi staff nahi</p>
        <?php else: ?>
        <?php foreach ($staff as $s): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border2)">
          <div class="nav-avatar" style="background:var(--info)"><?= strtoupper(substr($s['staff_name'],0,2)) ?></div>
          <div style="flex:1">
            <div style="font-weight:600"><?= htmlspecialchars($s['staff_name']) ?></div>
            <div class="text-small text-muted"><?= htmlspecialchars($s['mobile']?:'No mobile') ?> · <span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></div>
          </div>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle_staff"/>
            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>"/>
            <input type="hidden" name="status" value="<?= $s['status']==='active'?'inactive':'active' ?>"/>
            <button type="submit" class="btn btn-outline btn-sm"><?= $s['status']==='active'?'Disable':'Enable' ?></button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Staff remove karein?')">
            <input type="hidden" name="action" value="delete_staff"/>
            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>"/>
            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
</body>
</html>
