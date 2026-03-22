<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$shopFilter = $_GET['shop_id'] ?? '';
$typeFilter = $_GET['type']    ?? '';
$limit      = intval($_GET['limit'] ?? 50);

$where='1=1'; $params=[];
if ($shopFilter) { $where.=' AND shop_id=?'; $params[]=$shopFilter; }
if ($typeFilter) { $where.=' AND action_type LIKE ?'; $params[]='%'.$typeFilter.'%'; }

$logs = $pdo->prepare("SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC LIMIT $limit");
$logs->execute($params); $logData=$logs->fetchAll();
$shops = $pdo->query("SELECT DISTINCT shop_id FROM audit_logs ORDER BY shop_id")->fetchAll(PDO::FETCH_COLUMN);
$unreadCount=0;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Audit Logs — Admin</title>
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
    <a href="audit_logs.php" class="active"><span class="sidebar-icon">📋</span> Audit Logs</a>
    <a href="transactions.php"><span class="sidebar-icon">💳</span> Transactions</a>
    <div class="sidebar-divider"></div>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat</a>
    <a href="settings.php"><span class="sidebar-icon">⚙️</span> Settings</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content">
    <h2 style="margin-bottom:16px">📋 Audit Logs</h2>
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
      <select class="form-control" name="shop_id" style="width:180px" onchange="this.form.submit()">
        <option value="">All Shops</option>
        <?php foreach($shops as $sid): ?><option value="<?=$sid?>" <?=$sid===$shopFilter?'selected':''?>><?=$sid?></option><?php endforeach; ?>
      </select>
      <input class="form-control" type="text" name="type" placeholder="Filter by action..." value="<?=htmlspecialchars($typeFilter)?>" style="width:180px"/>
      <select class="form-control" name="limit" style="width:100px" onchange="this.form.submit()">
        <option value="50" <?=$limit==50?'selected':''?>>50</option>
        <option value="100" <?=$limit==100?'selected':''?>>100</option>
        <option value="200" <?=$limit==200?'selected':''?>>200</option>
      </select>
      <button type="submit" class="btn btn-gold">Filter</button>
      <?php if($shopFilter||$typeFilter): ?><a href="audit_logs.php" class="btn btn-outline">Clear</a><?php endif; ?>
    </form>
    <div class="card" style="padding:0">
      <?php if(empty($logData)): ?>
      <p class="text-muted" style="text-align:center;padding:30px">Koi logs nahi</p>
      <?php else: ?>
      <div style="max-height:calc(100vh - 220px);overflow-y:auto">
      <?php foreach($logData as $log):
        $icons=['pawn_created'=>'➕','pawn_deleted'=>'🗑','payment_received'=>'💰','shop_activated'=>'✅','shop_blocked'=>'🚫','shop_registered'=>'🆕','password_changed'=>'🔑','subscription_added'=>'🔁','bulk_subscription'=>'🔁','shop_edited'=>'✏️'];
        $icon=$icons[$log['action_type']]??'📌';
      ?>
      <div style="display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border2);align-items:flex-start">
        <div style="font-size:20px;flex-shrink:0;margin-top:2px"><?=$icon?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px"><?=htmlspecialchars($log['action_description'])?></div>
          <div class="text-small text-muted" style="margin-top:3px">
            <span style="background:var(--surface);border-radius:4px;padding:1px 6px;font-size:10px;margin-right:6px"><?=htmlspecialchars($log['action_type'])?></span>
            <?=htmlspecialchars($log['performed_by_name']??'System')?> · <?=htmlspecialchars($log['shop_id']??'—')?> · <?=date('d M Y H:i',strtotime($log['created_at']))?>
          </div>
        </div>
        <div class="text-small text-muted" style="white-space:nowrap;flex-shrink:0"><?=date('H:i',strtotime($log['created_at']))?></div>
      </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php include '../includes/admin_mobile_nav.php'; ?>
<script src="../js/app.js"></script>
</body>
</html>
