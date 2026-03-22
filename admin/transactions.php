<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$shopFilter = $_GET['shop_id'] ?? '';
$month      = $_GET['month']   ?? date('m');
$year       = $_GET['year']    ?? date('Y');

$where='1=1'; $params=[];
if ($shopFilter) { $where.=' AND p.shop_id=?'; $params[]=$shopFilter; }
$where.=' AND MONTH(p.payment_date)=? AND YEAR(p.payment_date)=?';
$params[]=$month; $params[]=$year;

$pays = $pdo->prepare("SELECT p.*,pe.bandhak_id,pe.item_type,c.full_name,s.shop_name FROM payments p JOIN pawn_entries pe ON p.pawn_id=pe.id JOIN customers c ON pe.customer_id=c.id JOIN shops s ON p.shop_id=s.shop_id WHERE $where ORDER BY p.payment_date DESC");
$pays->execute($params); $payments=$pays->fetchAll();
$totalAmt = array_sum(array_column($payments,'amount'));
$shops    = $pdo->query("SELECT shop_id,shop_name FROM shops ORDER BY shop_name")->fetchAll();
$unreadCount=0;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Transactions — Admin</title>
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
    <a href="transactions.php" class="active"><span class="sidebar-icon">💳</span> Transactions</a>
    <div class="sidebar-divider"></div>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat</a>
    <a href="settings.php"><span class="sidebar-icon">⚙️</span> Settings</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content">
    <h2 style="margin-bottom:16px">💳 Payment Transactions</h2>
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Shop</label>
        <select class="form-control" name="shop_id">
          <option value="">All Shops</option>
          <?php foreach($shops as $s): ?><option value="<?=$s['shop_id']?>" <?=$s['shop_id']===$shopFilter?'selected':''?>><?=$s['shop_id']?> — <?=htmlspecialchars($s['shop_name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Month</label>
        <select class="form-control" name="month">
          <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m==$month?'selected':''?>><?=date('M',mktime(0,0,0,$m,1))?></option><?php endfor; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Year</label>
        <select class="form-control" name="year">
          <?php for($y=date('Y');$y>=2024;$y--): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endfor; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-gold">Filter</button>
    </form>
    <div class="stat-grid" style="margin-bottom:16px">
      <div class="stat-card"><div class="stat-label">Total Transactions</div><div class="stat-value"><?=count($payments)?></div></div>
      <div class="stat-card"><div class="stat-label">Total Amount</div><div class="stat-value" style="font-size:18px;color:var(--success)">₹<?=number_format($totalAmt,0)?></div></div>
    </div>
    <div class="card" style="padding:0">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Shop</th><th>Customer</th><th>Bandhak ID</th><th>Item</th><th>Amount</th><th>Mode</th><th>Remaining</th></tr></thead>
          <tbody>
          <?php foreach($payments as $p): ?>
          <tr>
            <td><?=date('d M Y',strtotime($p['payment_date']))?></td>
            <td><strong><?=htmlspecialchars($p['shop_id'])?></strong><br/><small class="text-muted"><?=htmlspecialchars($p['shop_name'])?></small></td>
            <td><?=htmlspecialchars($p['full_name'])?></td>
            <td><?=htmlspecialchars($p['bandhak_id'])?></td>
            <td><?=htmlspecialchars($p['item_type'])?></td>
            <td style="color:var(--success);font-weight:600">₹<?=number_format($p['amount'],2)?></td>
            <td><?=strtoupper($p['payment_mode'])?></td>
            <td style="color:<?=$p['remaining_after']==0?'var(--success)':'var(--danger)'?>">₹<?=number_format($p['remaining_after'],2)?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($payments)): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3)">Koi transaction nahi</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php include '../includes/admin_mobile_nav.php'; ?>
<script src="../js/app.js"></script>
</body>
</html>
