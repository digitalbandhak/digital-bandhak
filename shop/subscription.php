<?php
define('IS_SHOP', true);
// shop/subscription.php
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId  = $_SESSION['shop_id'];
$ownerId = $_SESSION['user_id'];

$success = ''; $error = '';

// Handle renewal request (creates pending sub, admin approves)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan    = $_POST['plan'] ?? 'monthly';
    $mode    = $_POST['payment_mode'] ?? 'cash';
    $amount  = floatval($_POST['amount'] ?? 0);

    $durations = ['trial'=>30, 'monthly'=>30, 'halfyearly'=>180, 'annual'=>365];
    $days = $durations[$plan] ?? 30;

    // Find active sub end date to extend from
    $lastSub = $pdo->prepare("SELECT end_date FROM subscriptions WHERE shop_id=? AND status='active' ORDER BY end_date DESC LIMIT 1");
    $lastSub->execute([$shopId]);
    $last = $lastSub->fetch();
    $startFrom = ($last && $last['end_date'] >= date('Y-m-d')) ? $last['end_date'] : date('Y-m-d');
    $endDate   = date('Y-m-d', strtotime($startFrom . " + $days days"));

    $ins = $pdo->prepare("INSERT INTO subscriptions (shop_id, plan_type, start_date, end_date, amount, payment_mode, status) VALUES (?,?,?,?,?,?,'active')");
    $ins->execute([$shopId, $plan, $startFrom, $endDate, $amount, $mode]);

    auditLog($pdo, $shopId, 'subscription_renewed', "Plan: $plan, Amount: ₹$amount", 'owner', $ownerId, $_SESSION['user_name']);
    $success = "Subscription request submit ho gayi. Admin approval ke baad activate hogi.";
}

// Current subscription
$curSub = $pdo->prepare("SELECT * FROM subscriptions WHERE shop_id=? AND status='active' AND end_date>=CURDATE() ORDER BY end_date DESC LIMIT 1");
$curSub->execute([$shopId]);
$sub = $curSub->fetch();

// All history
$hist = $pdo->prepare("SELECT * FROM subscriptions WHERE shop_id=? ORDER BY created_at DESC");
$hist->execute([$shopId]);
$history = $hist->fetchAll();

$plans = [
    'trial'      => ['name'=>'Free Trial',  'days'=>30,  'price'=>0],
    'monthly'    => ['name'=>'Monthly',     'days'=>30,  'price'=>299],
    'halfyearly' => ['name'=>'6 Months',    'days'=>180, 'price'=>1499],
    'annual'     => ['name'=>'Annual',      'days'=>365, 'price'=>2999],
];
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Subscription — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<nav class="navbar">
  <a class="navbar-brand" href="dashboard.php">🏅 Digital <span>Bandhak</span></a>
  <div class="navbar-right"><a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a></div>
</nav>

<div class="main-content container">
  <h2 style="margin-bottom:20px">🔁 Subscription Management</h2>

  <?php if ($success): ?><div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger">✖ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Current Status -->
  <?php if ($sub): ?>
  <?php
define('IS_SHOP', true);
    $totalDays = (new DateTime($sub['end_date']))->diff(new DateTime($sub['start_date']))->days;
    $usedDays  = max(0,(new DateTime())->diff(new DateTime($sub['start_date']))->days);
    $leftDays  = max(0,(new DateTime($sub['end_date']))->diff(new DateTime())->days);
    $pct       = min(100, round($usedDays / max(1,$totalDays) * 100));
  ?>
  <div class="card mb-16" style="border-color:var(--gold);border-width:1.5px">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:16px;font-weight:700"><?= $plans[$sub['plan_type']]['name'] ?? ucfirst($sub['plan_type']) ?> Plan</div>
        <div class="text-muted text-small"><?= date('d M Y', strtotime($sub['start_date'])) ?> → <?= date('d M Y', strtotime($sub['end_date'])) ?></div>
      </div>
      <span class="badge badge-active" style="font-size:13px">Active</span>
    </div>
    <div class="sub-progress mt-12"><div class="sub-bar" style="width:<?= $pct ?>%"></div></div>
    <div class="text-small text-muted"><?= $pct ?>% elapsed · <strong><?= $leftDays ?> din bache</strong></div>
    <?php if (!empty($sub['notes'])): ?>
    <div class="alert alert-warning mt-8" style="font-size:13px">🎉 <strong>Offer:</strong> <?=htmlspecialchars($sub['notes'])?></div>
    <?php endif; ?>
    <?php if ($leftDays <= 15): ?>
    <div class="alert alert-warning mt-12">⚠ Subscription jaldi expire ho rahi hai! Abhi renew karo.</div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="alert alert-danger mb-16">✖ Koi active subscription nahi hai. Neeche se plan choose karo ya Admin ko request bhejo.</div>
  <?php endif; ?>

  <!-- Request to Admin -->
  <div class="card mb-16" style="border-color:var(--gold)">
    <div class="card-title">📨 Admin Ko Subscription Request Bhejo</div>
    <p class="text-muted text-small mb-12">Plan choose karo → Request bhejo → Admin approve karne par activate hogi.</p>
    <?php
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_requests (id INT AUTO_INCREMENT PRIMARY KEY, shop_id VARCHAR(30), plan_type VARCHAR(20) DEFAULT 'monthly', message TEXT, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
        $pendReq = $pdo->prepare("SELECT * FROM subscription_requests WHERE shop_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
        $pendReq->execute([$shopId]); $pendingReq = $pendReq->fetch();
    } catch(Exception $e){ $pendingReq=false; }
    ?>
    <?php if ($pendingReq): ?>
    <div class="alert alert-warning" style="font-size:13px">
      ⏳ Ek request already pending hai — <strong><?=ucfirst($pendingReq['plan_type'])?> Plan</strong> — <?=date('d M Y H:i',strtotime($pendingReq['created_at']))?><br/>
      Admin approve karte hain to automatically activate ho jaayegi.
    </div>
    <?php else: ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:150px">
        <label class="form-label">Plan Select Karo</label>
        <select class="form-control" id="reqPlanSel">
          <option value="monthly">Monthly — ₹299</option>
          <option value="halfyearly">6 Month — ₹1,499</option>
          <option value="annual" selected>Annual — ₹2,999 ⭐</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;flex:2;min-width:180px">
        <label class="form-label">Message (optional)</label>
        <input class="form-control" type="text" id="reqMsg" placeholder="e.g. UPI payment kar diya"/>
      </div>
      <button class="btn btn-gold" onclick="sendSubRequest()">📨 Request Bhejo</button>
    </div>
    <?php endif; ?>
  </div>

  <!-- Plan Cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px">
    <?php foreach ($plans as $key => $plan): ?>
    <div class="card" style="cursor:pointer;transition:all .15s;<?= ($key==='annual'?'border:2px solid var(--gold);':'')?>" onclick="selectPlan('<?= $key ?>','<?= $plan['price'] ?>')">
      <?php if ($key==='annual'): ?><div style="background:var(--gold);color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:8px;display:inline-block;margin-bottom:8px">Most Popular</div><?php endif; ?>
      <div style="font-size:18px;font-weight:700"><?= $plan['name'] ?></div>
      <div style="font-size:24px;font-weight:700;color:var(--gold);margin:6px 0">₹<?= number_format($plan['price'],0) ?></div>
      <div class="text-small text-muted"><?= $plan['days'] ?> din</div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Renew Form -->
  <div class="card mb-16">
    <div class="card-title">Renew / Extend Subscription</div>
    <form method="POST">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Plan *</label>
          <select class="form-control" name="plan" id="plan_select" onchange="updateAmount(this.value)">
            <?php foreach ($plans as $key => $plan): ?>
            <option value="<?= $key ?>" data-price="<?= $plan['price'] ?>"><?= $plan['name'] ?> — ₹<?= number_format($plan['price'],0) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Mode</label>
          <select class="form-control" name="payment_mode">
            <option value="cash">💵 Cash</option>
            <option value="online">💻 Online/NEFT</option>
            <option value="upi">📱 UPI</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Amount (₹)</label>
          <input class="form-control" type="number" name="amount" id="amount_field" step="0.01" value="2999"/>
        </div>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-gold">🔁 Submit Renewal Request</button>
      </div>
      <p class="text-small text-muted mt-8">Note: Super Admin approval ke baad subscription activate hogi.</p>
    </form>
  </div>

  <!-- History -->
  <div class="card">
    <div class="card-title">📜 Subscription History</div>
    <?php if (empty($history)): ?>
    <p class="text-muted" style="text-align:center;padding:20px">Koi history nahi</p>
    <?php else: ?>
    <?php foreach ($history as $h):
      $startD = date('d M Y',strtotime($h['start_date']));
      $endD   = date('d M Y',strtotime($h['end_date']));
      $planName = $plans[$h['plan_type']]['name'] ?? ucfirst($h['plan_type']);
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border2)">
      <div style="width:40px;height:40px;border-radius:10px;background:var(--gold-light);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">🔁</div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:13px"><?=$planName?> <span class="badge badge-<?=$h['status']?>" style="font-size:10px"><?=ucfirst($h['status'])?></span></div>
        <div class="text-small text-muted"><?=$startD?> → <?=$endD?></div>
        <?php if (!empty($h['notes'])): ?><div class="text-small" style="color:var(--warning)">🎉 <?=htmlspecialchars($h['notes'])?></div><?php endif; ?>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-weight:700;color:var(--gold)">₹<?=number_format($h['amount'],0)?></div>
        <div class="text-small text-muted"><?=strtoupper($h['payment_mode'])?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Terms Link -->
  <div style="text-align:center;margin-top:20px">
    <a href="../terms.php" class="text-small" style="color:var(--gold)">Terms & Conditions padhein →</a>
  </div>
</div>
<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
const prices = <?= json_encode(array_combine(array_keys($plans), array_column($plans,'price'))) ?>;
function updateAmount(plan) { document.getElementById('amount_field').value = prices[plan] || 0; }
function selectPlan(plan, price) {
  document.getElementById('plan_select').value = plan;
  document.getElementById('amount_field').value = price;
}
async function sendSubRequest() {
  const plan = document.getElementById('reqPlanSel')?.value || 'monthly';
  const msg  = document.getElementById('reqMsg')?.value || '';
  const r = await apiPost('../php/subscription_request.php', { action:'send', plan, message:msg });
  if (r.success) { showAlert(r.msg,'success'); setTimeout(()=>location.reload(),1500); }
  else showAlert(r.msg||'Error','danger');
}
</script>
</body>
</html>
