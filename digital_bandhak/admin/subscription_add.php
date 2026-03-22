<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$success = ''; $error = '';
$preselect   = $_GET['shop_id'] ?? '';
$preplanGET  = $_GET['plan']    ?? 'monthly';
$preReqId    = intval($_GET['req_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode     = $_POST['mode']         ?? 'single';
    $plan     = $_POST['plan_type']    ?? 'monthly';
    $start    = $_POST['start_date']   ?? date('Y-m-d');
    $amount   = floatval($_POST['amount']   ?? 0);
    $payMode  = $_POST['payment_mode'] ?? 'cash';
    $notes    = trim($_POST['notes']   ?? '');
    $discount = floatval($_POST['discount_pct'] ?? 0);
    $extDays  = intval($_POST['extend_days']  ?? 0);
    $reqId    = intval($_POST['req_id']       ?? $preReqId);

    $durations = ['trial'=>30,'monthly'=>30,'halfyearly'=>180,'annual'=>365];
    $days = $durations[$plan] ?? 30;

    if ($mode === 'bulk') {
        $shops = $pdo->query("SELECT shop_id FROM shops WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($shops as $sid) {
            if ($extDays > 0) {
                $es=$pdo->prepare("SELECT id,end_date FROM subscriptions WHERE shop_id=? AND status='active' AND end_date>=CURDATE() ORDER BY end_date DESC LIMIT 1");
                $es->execute([$sid]); $row=$es->fetch();
                if ($row) {
                    $newEnd=date('Y-m-d',strtotime($row['end_date']." +$extDays days"));
                    $pdo->prepare("UPDATE subscriptions SET end_date=?,notes=? WHERE id=?")->execute([$newEnd,$notes,$row['id']]);
                    $count++; continue;
                }
            }
            $pdo->prepare("UPDATE subscriptions SET status='expired' WHERE shop_id=? AND status='active'")->execute([$sid]);
            $finalAmt = $discount>0 ? round($amount*(1-$discount/100),2) : $amount;
            $end = date('Y-m-d',strtotime("$start +$days days"));
            $pdo->prepare("INSERT INTO subscriptions (shop_id,plan_type,start_date,end_date,amount,payment_mode,status,notes) VALUES (?,?,?,?,?,?,'active',?)")
                ->execute([$sid,$plan,$start,$end,$finalAmt,$payMode,$notes]);
            // Notify shop
            try { $pdo->prepare("INSERT INTO admin_chat_messages (shop_id,sender_type,sender_id,message) VALUES (?,'admin',?,?)")
                ->execute([$sid,$_SESSION['user_id'],"🎉 ".($notes?$notes:'Festival Offer')."! ".ucfirst($plan)." plan activated".($discount>0?" ($discount% discount)":"").". Valid till: ".date('d M Y',strtotime($end))]); } catch(Exception $e){}
            $count++;
        }
        auditLog($pdo,'ALL','bulk_subscription',"Bulk $plan applied to $count shops",'super_admin',$_SESSION['user_id'],$_SESSION['user_name']);
        $success = "✔ Bulk done! $count shops updated.";

    } else {
        $sid = trim($_POST['shop_id'] ?? $preselect ?? '');
        if (!$sid) { $error = 'Shop select karo'; }
        else {
            if ($extDays > 0) {
                $es=$pdo->prepare("SELECT id,end_date FROM subscriptions WHERE shop_id=? AND status='active' AND end_date>=CURDATE() ORDER BY end_date DESC LIMIT 1");
                $es->execute([$sid]); $row=$es->fetch();
                if ($row) {
                    $newEnd=date('Y-m-d',strtotime($row['end_date']." +$extDays days"));
                    $pdo->prepare("UPDATE subscriptions SET end_date=?,notes=CONCAT(IFNULL(notes,''),' | Extended $extDays days') WHERE id=?")->execute([$newEnd,$row['id']]);
                    auditLog($pdo,$sid,'sub_extended',"Extended $extDays days",'super_admin',$_SESSION['user_id'],$_SESSION['user_name'],$sid);
                    $success = "Extended by $extDays days!";
                } else { $error = 'No active sub to extend. Add new plan.'; }
            } else {
                $pdo->prepare("UPDATE subscriptions SET status='expired' WHERE shop_id=? AND status='active'")->execute([$sid]);
                $finalAmt = $discount>0 ? round($amount*(1-$discount/100),2) : $amount;
                $end = date('Y-m-d',strtotime("$start +$days days"));
                $pdo->prepare("INSERT INTO subscriptions (shop_id,plan_type,start_date,end_date,amount,payment_mode,status,extended_by,notes) VALUES (?,?,?,?,?,?,'active',?,?)")
                    ->execute([$sid,$plan,$start,$end,$finalAmt,$payMode,$_SESSION['user_id'],$notes]);
                $pdo->prepare("UPDATE shops SET status='active' WHERE shop_id=?")->execute([$sid]);
                // Notify shop
                try { $pdo->prepare("INSERT INTO admin_chat_messages (shop_id,sender_type,sender_id,message) VALUES (?,'admin',?,?)")
                    ->execute([$sid,$_SESSION['user_id'],"✅ Subscription activated! ".ucfirst($plan)." plan".($discount>0?" ($discount% discount)":"").". Valid till ".date('d M Y',strtotime($end))]); } catch(Exception $e){}
                // Mark request approved
                if ($reqId) {
                    try { $pdo->prepare("UPDATE subscription_requests SET status='approved' WHERE id=?")->execute([$reqId]); } catch(Exception $e){}
                } else {
                    try { $pdo->prepare("UPDATE subscription_requests SET status='approved' WHERE shop_id=? AND status='pending'")->execute([$sid]); } catch(Exception $e){}
                }
                auditLog($pdo,$sid,'subscription_added',"$plan plan, ₹$finalAmt",'super_admin',$_SESSION['user_id'],$_SESSION['user_name'],$sid);
                $success = "✔ Subscription added!";
            }
        }
    }
}

$shops = $pdo->query("SELECT shop_id, shop_name, owner_name FROM shops WHERE status='active' ORDER BY shop_name")->fetchAll();
// Pending requests
try {
    $subReqs = $pdo->query("SELECT sr.*, s.shop_name, s.owner_name FROM subscription_requests sr JOIN shops s ON sr.shop_id=s.shop_id WHERE sr.status='pending' ORDER BY sr.created_at DESC")->fetchAll();
} catch(Exception $e){ $subReqs=[]; }

$plans = [
    'trial'      => ['name'=>'Free Trial',  'days'=>30,  'price'=>0],
    'monthly'    => ['name'=>'Monthly',     'days'=>30,  'price'=>299],
    'halfyearly' => ['name'=>'6 Months',    'days'=>180, 'price'=>1499],
    'annual'     => ['name'=>'Annual ⭐',   'days'=>365, 'price'=>2999],
];
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Subscriptions — Admin</title>
  <link rel="stylesheet" href="../css/style.css"/>
  <style>
    /* Plan card grid — 2x2 on mobile */
    .plan-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
    @media(max-width:400px){.plan-grid{grid-template-columns:1fr;}}
    .plan-card{
      border:2px solid var(--border);border-radius:var(--radius-lg);
      padding:14px;cursor:pointer;transition:all .15s;
      background:var(--card-bg);
    }
    .plan-card:hover,.plan-card.selected{border-color:var(--gold);background:var(--gold-light);}
    .plan-card-name{font-weight:700;font-size:14px;margin-bottom:4px;}
    .plan-card-price{font-size:20px;font-weight:700;color:var(--gold-dark);}
    [data-theme="dark"] .plan-card-price{color:var(--gold);}
    .plan-card-days{font-size:11px;color:var(--text3);margin-top:3px;}
    .plan-card.selected .plan-card-name{color:var(--gold-dark);}
    /* Request cards */
    .req-card{background:var(--card-bg);border:1px solid var(--gold);border-radius:var(--radius);padding:12px;margin-bottom:10px;}
  </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="dashboard-layout">
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="shop_add.php"><span class="sidebar-icon">🏪</span> Add Shop</a>
    <a href="subscription_add.php" class="active"><span class="sidebar-icon">🔁</span> Subscriptions</a>
    <div class="sidebar-divider"></div>
    <a href="audit_logs.php"><span class="sidebar-icon">📋</span> Audit Logs</a>
    <a href="transactions.php"><span class="sidebar-icon">💳</span> Transactions</a>
    <div class="sidebar-divider"></div>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat</a>
    <a href="settings.php"><span class="sidebar-icon">⚙️</span> Settings</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>

  <main class="main-content">
    <h2 style="margin-bottom:16px">🔁 Subscription Management</h2>

    <?php if ($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">✖ <?=htmlspecialchars($error)?></div><?php endif; ?>

    <!-- Pending Requests -->
    <?php if (!empty($subReqs)): ?>
    <div class="card mb-16" style="border-color:var(--gold)">
      <div class="card-title" style="color:var(--gold)">📨 Pending Requests (<?=count($subReqs)?>)</div>
      <?php foreach ($subReqs as $sr): ?>
      <div class="req-card">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
          <div>
            <strong><?=htmlspecialchars($sr['shop_id'])?></strong> — <?=htmlspecialchars($sr['shop_name'])?>
            <div class="text-small text-muted"><?=ucfirst($sr['plan_type'])?> · <?=date('d M H:i',strtotime($sr['created_at']))?>
            <?php if ($sr['message']): ?> · "<?=htmlspecialchars(substr($sr['message'],0,40))?>"<?php endif; ?>
            </div>
          </div>
          <div style="display:flex;gap:6px">
            <a href="subscription_add.php?shop_id=<?=htmlspecialchars($sr['shop_id'])?>&plan=<?=$sr['plan_type']?>&req_id=<?=$sr['id']?>" class="btn btn-gold btn-sm">✔ Approve</a>
            <button class="btn btn-danger btn-sm" onclick="rejectReq(<?=$sr['id']?>)">✖</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Mode Toggle -->
    <div style="display:flex;gap:10px;margin-bottom:16px">
      <button class="btn btn-gold" id="btnSingle" onclick="setMode('single')">👤 Single Shop</button>
      <button class="btn btn-outline" id="btnBulk" onclick="setMode('bulk')">👥 All Shops (Festival)</button>
    </div>

    <form method="POST" id="subForm">
      <input type="hidden" name="mode" id="modeField" value="single"/>
      <input type="hidden" name="plan_type" id="planField" value="<?=htmlspecialchars($preplanGET)?>"/>
      <input type="hidden" name="req_id" value="<?=$preReqId?>"/>

      <!-- Single shop select -->
      <div id="singlePanel" class="card mb-16">
        <div class="card-title">🏪 Shop Select</div>
        <select class="form-control" name="shop_id" id="shopSelect">
          <option value="">-- Shop choose karo --</option>
          <?php foreach ($shops as $s): ?>
          <option value="<?=htmlspecialchars($s['shop_id'])?>" <?=$s['shop_id']===$preselect?'selected':''?>>
            <?=htmlspecialchars($s['shop_id'])?> — <?=htmlspecialchars($s['shop_name'])?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Bulk notice -->
      <div id="bulkPanel" class="alert alert-warning mb-16" style="display:none">
        🎉 <strong>Festival/Bulk Mode:</strong> Yeh SAARE <?=count($shops)?> active shops pe apply hoga. Discount sab ko milega.
      </div>

      <!-- Plan Cards — 2x2 grid -->
      <div class="card mb-16">
        <div class="card-title">📋 Plan Select</div>
        <div class="plan-grid">
          <?php foreach ($plans as $key => $plan): ?>
          <div class="plan-card <?=$key===$preplanGET?'selected':''?>" id="pc-<?=$key?>" onclick="selectPlan('<?=$key?>',<?=$plan['price']?>)">
            <?php if ($key==='annual'): ?><div style="font-size:10px;font-weight:700;color:var(--gold);margin-bottom:4px">⭐ POPULAR</div><?php endif; ?>
            <div class="plan-card-name"><?=$plan['name']?></div>
            <div class="plan-card-price">₹<?=number_format($plan['price'],0)?></div>
            <div class="plan-card-days"><?=$plan['days']?> days</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Details -->
      <div class="card mb-16">
        <div class="card-title">💰 Details</div>
        <div class="form-group">
          <label class="form-label">Start Date</label>
          <input class="form-control" type="date" name="start_date" value="<?=date('Y-m-d')?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Amount (₹)</label>
          <input class="form-control" type="number" name="amount" id="amountField" value="299" oninput="updateFinal()"/>
        </div>
        <div class="form-group">
          <label class="form-label">Festival Discount (%)</label>
          <input class="form-control" type="number" name="discount_pct" id="discField" min="0" max="100" value="0" placeholder="0" oninput="updateFinal()"/>
        </div>
        <div id="finalDisplay" style="display:none;background:var(--gold-light);border-radius:var(--radius);padding:10px 14px;margin-bottom:12px;font-size:13px">
          <span class="text-muted">Final Amount: </span><strong id="finalAmt" style="color:var(--gold-dark);font-size:16px"></strong>
          <span id="savedAmt" style="color:var(--success);font-size:11px;margin-left:6px"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Mode</label>
          <select class="form-control" name="payment_mode">
            <option value="cash">Cash</option><option value="online">Online</option>
            <option value="upi">UPI</option><option value="free">Free (Gift)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Extend Existing (days) <span class="text-muted" style="font-weight:400">(0 = new plan)</span></label>
          <input class="form-control" type="number" name="extend_days" value="0" min="0" placeholder="0"/>
        </div>
        <div class="form-group">
          <label class="form-label">Notes / Festival Offer Name</label>
          <input class="form-control" type="text" name="notes" placeholder="e.g. Holi Festival 50% off"/>
        </div>
      </div>

      <div class="alert alert-warning mb-16" style="font-size:12px">
        ⚠ New plan → old active plan expire hogi. Extend Days use karo agar sirf badhaana ho.
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" class="btn btn-gold btn-lg" id="submitBtn">✔ Apply Subscription</button>
        <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
      </div>
    </form>
  </main>
</div>

<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
const planPrices = {trial:0,monthly:299,halfyearly:1499,annual:2999};
let basePlanPrice = <?=$plans[$preplanGET]['price'] ?? 299?>;

function selectPlan(key, price) {
  basePlanPrice = price;
  document.getElementById('planField').value   = key;
  document.getElementById('amountField').value = price;
  document.getElementById('discField').value   = '0';
  document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
  document.getElementById('pc-'+key)?.classList.add('selected');
  updateFinal();
}

function updateFinal() {
  const disc  = parseFloat(document.getElementById('discField').value) || 0;
  const final = disc > 0 ? Math.round(basePlanPrice*(1-disc/100)) : basePlanPrice;
  document.getElementById('amountField').value = final;
  const disp = document.getElementById('finalDisplay');
  const fa   = document.getElementById('finalAmt');
  const sa   = document.getElementById('savedAmt');
  if (disc > 0) {
    disp.style.display = 'block';
    fa.textContent = '₹'+final.toLocaleString('en-IN');
    sa.textContent = '(Save ₹'+(basePlanPrice-final).toLocaleString('en-IN')+')';
  } else {
    disp.style.display = 'none';
  }
}

function setMode(mode) {
  document.getElementById('modeField').value = mode;
  document.getElementById('singlePanel').style.display = mode==='single'?'block':'none';
  document.getElementById('bulkPanel').style.display   = mode==='bulk'?'flex':'none';
  document.getElementById('btnSingle').className = 'btn '+(mode==='single'?'btn-gold':'btn-outline');
  document.getElementById('btnBulk').className   = 'btn '+(mode==='bulk'?'btn-gold':'btn-outline');
  document.getElementById('submitBtn').textContent = mode==='bulk'?'🎉 Apply to ALL Shops':'✔ Apply Subscription';
}

async function rejectReq(id) {
  if (!confirm('Reject karein?')) return;
  const r = await apiPost('../php/admin_shop_action.php',{action:'reject_sub_req',req_id:id});
  if (r.success){showAlert('Rejected','success');setTimeout(()=>location.reload(),800);}
}

// Init
updateFinal();
// Pre-select shop if from URL
<?php if ($preselect): ?>
document.getElementById('shopSelect').value = '<?=addslashes($preselect)?>';
<?php endif; ?>
</script>
</body>
</html>
