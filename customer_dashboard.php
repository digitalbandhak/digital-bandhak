<?php
require_once 'includes/config.php';
requireLogin('customer', 'index.php');

$customerId   = $_SESSION['user_id'];
$bandhakId    = $_SESSION['bandhak_id'];
$customerName = $_SESSION['customer_name'];
$customerShop = $_SESSION['customer_shopid'] ?? '';

$custStmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$custStmt->execute([$customerId]); $cust = $custStmt->fetch();

$pawnStmt = $pdo->prepare("
    SELECT pe.*, s.shop_name, s.city, s.owner_mobile
    FROM pawn_entries pe
    JOIN shops s ON pe.shop_id=s.shop_id
    WHERE pe.customer_id=? AND pe.status!='deleted'
    ORDER BY pe.created_at DESC
");
$pawnStmt->execute([$customerId]); $pawns=$pawnStmt->fetchAll();

$payStmt = $pdo->prepare("
    SELECT p.*, pe.bandhak_id, pe.item_type
    FROM payments p
    JOIN pawn_entries pe ON p.pawn_id=pe.id
    WHERE pe.customer_id=?
    ORDER BY p.payment_date DESC LIMIT 20
");
$payStmt->execute([$customerId]); $payments=$payStmt->fetchAll();

$totalLoan    = array_sum(array_column($pawns,'loan_amount'));
$totalPaid    = array_sum(array_column($pawns,'total_paid'));
$totalPending = array_sum(array_column($pawns,'remaining_amount'));
$activePawns  = count(array_filter($pawns, function($p){ return $p['status']==='active'; }));

$logoUrl  = getSiteLogo($pdo);
$siteName = getSiteName($pdo);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>My Bandhak — <?=htmlspecialchars($siteName)?></title>
  <link rel="stylesheet" href="css/style.css"/>
  <style>
    body{background:var(--bg);}
    /* Customer fixed header */
    .cust-header{
      background:var(--nav-bg);border-bottom:1px solid var(--border);
      padding:0 16px;height:54px;display:flex;align-items:center;justify-content:space-between;
      position:fixed;top:0;left:0;right:0;z-index:200;box-shadow:var(--shadow);
    }
    .cust-brand{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:var(--gold-dark);text-decoration:none;}
    [data-theme="dark"] .cust-brand{color:var(--gold);}
    .cust-brand img{height:30px;width:30px;object-fit:contain;border-radius:6px;}
    .cust-avatar{width:32px;height:32px;border-radius:50%;background:var(--gold);color:#fff;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .cust-body{max-width:680px;margin:0 auto;padding:70px 14px 24px;}
    /* Item card */
    .item-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px;margin-bottom:12px;}
    .item-photo{width:60px;height:60px;border-radius:8px;border:1px solid var(--border);background:var(--surface);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:24px;overflow:hidden;}
    .item-photo img{width:100%;height:100%;object-fit:cover;}
    .amounts-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px;}
    .amt-box{background:var(--surface);border-radius:8px;padding:8px;text-align:center;}
    .amt-label{font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.04em;}
    .amt-val{font-size:14px;font-weight:700;margin-top:2px;}
    /* Payment history cards */
    .pay-card{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border2);}
    .pay-icon{width:36px;height:36px;border-radius:50%;background:rgba(26,107,58,.12);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
    /* Tab nav */
    .cust-tabs{display:flex;gap:4px;background:var(--surface);border-radius:10px;padding:3px;margin-bottom:16px;}
    .cust-tab{flex:1;padding:8px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;background:transparent;color:var(--text3);font-family:inherit;transition:all .15s;}
    .cust-tab.active{background:var(--gold);color:#fff;}
    .tab-content{display:none;}
    .tab-content.active{display:block;}
  </style>
</head>
<body>
<!-- Header -->
<div class="cust-header">
  <a href="#" class="cust-brand">
    <?php if ($logoUrl): ?><img src="<?=htmlspecialchars($logoUrl)?>" alt=""/><?php else: ?><span style="font-size:20px">🏛️</span><?php endif; ?>
    <?=htmlspecialchars($siteName)?>
  </a>
  <div style="display:flex;align-items:center;gap:10px">
    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" style="width:32px;height:32px;font-size:15px">🌙</button>
    <div class="cust-avatar"><?=strtoupper(substr($customerName,0,2))?></div>
    <a href="php/logout.php" class="btn btn-outline btn-sm" style="font-size:11px;padding:5px 10px">Logout</a>
  </div>
</div>

<div class="cust-body">
  <!-- Welcome -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px;">
    <div class="cust-avatar" style="width:48px;height:48px;font-size:18px"><?=strtoupper(substr($customerName,0,2))?></div>
    <div style="flex:1;min-width:0">
      <div style="font-size:16px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($customerName)?></div>
      <div class="text-muted text-small">ID: <strong><?=htmlspecialchars($bandhakId)?></strong></div>
      <?php if (!empty($cust['mobile'])): ?>
      <div class="text-muted text-small">+91 <?=htmlspecialchars($cust['mobile'])?></div>
      <?php endif; ?>
    </div>
    <span class="badge badge-active">View Only</span>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px">
    <div class="stat-card"><div class="stat-label">Active Items</div><div class="stat-value" style="color:var(--success)"><?=$activePawns?></div></div>
    <div class="stat-card"><div class="stat-label">Total Items</div><div class="stat-value"><?=count($pawns)?></div></div>
    <div class="stat-card"><div class="stat-label">Total Loan</div><div class="stat-value" style="font-size:18px">₹<?=number_format($totalLoan,0)?></div></div>
    <div class="stat-card" style="<?=$totalPending>0?'border-color:var(--danger)':''?>"><div class="stat-label">Remaining</div><div class="stat-value" style="font-size:18px;color:<?=$totalPending>0?'var(--danger)':'var(--success)'?>">₹<?=number_format($totalPending,0)?></div></div>
  </div>

  <!-- Tabs -->
  <div class="cust-tabs">
    <button class="cust-tab active" onclick="custSwitchTab(0)">💍 My Items (<?=count($pawns)?>)</button>
    <button class="cust-tab" onclick="custSwitchTab(1)">💰 Payments (<?=count($payments)?>)</button>
  </div>

  <!-- Items Tab -->
  <div class="tab-content active" id="tab-items">
    <?php if (empty($pawns)): ?>
    <div class="item-card" style="text-align:center;padding:30px;color:var(--text3)">Koi pawned item nahi hai</div>
    <?php endif; ?>
    <?php foreach ($pawns as $p):
      $remaining = floatval($p['remaining_amount']);
      $loan      = floatval($p['loan_amount']);
      $paid      = floatval($p['total_paid']);
    ?>
    <div class="item-card">
      <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:10px">
        <div class="item-photo">
          <?php if (!empty($p['item_photo']) && file_exists('uploads/'.$p['item_photo'])): ?>
            <img src="uploads/<?=htmlspecialchars($p['item_photo'])?>" alt="Item"/>
          <?php else: ?>
            💍
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap">
            <div>
              <div style="font-weight:700;font-size:13px"><?=htmlspecialchars($p['bandhak_id'])?></div>
              <div class="text-muted text-small"><?=htmlspecialchars($p['item_type'])?></div>
              <div class="text-muted text-small" style="font-size:11px"><?=htmlspecialchars(substr($p['item_description'],0,35))?></div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
              <span class="badge badge-<?=$p['status']?>"><?=ucfirst($p['status'])?></span>
              <button class="btn btn-outline btn-sm" onclick="printReceipt(<?=$p['id']?>)" style="font-size:11px;padding:3px 8px">🖨</button>
            </div>
          </div>
          <div class="text-small text-muted" style="margin-top:4px;font-size:11px">
            🏪 <?=htmlspecialchars($p['shop_name'])?><?php if($p['city']): ?>, <?=htmlspecialchars($p['city'])?><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Amounts -->
      <div class="amounts-row">
        <div class="amt-box">
          <div class="amt-label">Loan</div>
          <div class="amt-val" style="color:var(--gold)">₹<?=number_format($loan,0)?></div>
        </div>
        <div class="amt-box">
          <div class="amt-label">Paid</div>
          <div class="amt-val" style="color:var(--success)">₹<?=number_format($paid,0)?></div>
        </div>
        <div class="amt-box" style="<?=$remaining>0?'border:1px solid var(--danger)':''?>">
          <div class="amt-label">Remaining</div>
          <div class="amt-val" style="color:<?=$remaining>0?'var(--danger)':'var(--success)'?>">₹<?=number_format($remaining,0)?></div>
        </div>
      </div>

      <!-- Dates -->
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-top:8px;flex-wrap:wrap;gap:4px">
        <span>📅 Pawned: <?=date('d M Y',strtotime($p['pawn_date']))?></span>
        <span>⏰ Due: <?=date('d M Y',strtotime($p['due_date']))?></span>
        <span>📈 <?=$p['interest_rate']?>%/mo</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Payments Tab -->
  <div class="tab-content" id="tab-payments">
    <?php if (empty($payments)): ?>
    <div class="item-card" style="text-align:center;padding:24px;color:var(--text3)">Koi payment record nahi</div>
    <?php else: ?>
    <div class="item-card" style="padding:14px">
      <?php foreach ($payments as $pay): ?>
      <div class="pay-card">
        <div class="pay-icon">💰</div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px"><?=htmlspecialchars($pay['bandhak_id'])?></div>
          <div class="text-muted text-small"><?=date('d M Y',strtotime($pay['payment_date']))?> · <?=strtoupper($pay['payment_mode']??'Cash')?></div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div style="font-weight:700;color:var(--success);font-size:14px">₹<?=number_format($pay['amount'],0)?></div>
          <div style="font-size:11px;color:<?=floatval($pay['remaining_after'])<=0?'var(--success)':'var(--text3)'?>">Rem: ₹<?=number_format($pay['remaining_after'],0)?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <p class="text-small text-muted" style="text-align:center;margin-top:16px;font-size:11px">
    🔒 View only — aapka data secure hai
  </p>
</div>

<script src="js/app.js"></script>
<script>
window.RECEIPT_BASE = '';
function printReceipt(id) {
  window.open('php/receipt_print.php?id=' + id, '_blank', 'width=440,height=700');
}
function custSwitchTab(idx) {
  document.querySelectorAll('.cust-tab').forEach((t,i)=>t.classList.toggle('active',i===idx));
  document.querySelectorAll('.tab-content').forEach((c,i)=>c.classList.toggle('active',i===idx));
}
</script>
</body>
</html>
