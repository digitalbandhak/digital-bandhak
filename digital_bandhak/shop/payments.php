<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId  = $_SESSION['shop_id'];
$ownerId = $_SESSION['user_id'];

// Block payments if subscription expired
$subCheck = checkSubscription($pdo, $shopId);
if (!$subCheck) {
    header("Location: subscription.php?expired=1"); exit;
}
$success = ''; $error = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pawnId  = intval($_POST['pawn_id']);
    $amount  = floatval($_POST['amount']);
    $mode    = $_POST['payment_mode'] ?? 'cash';
    $date    = $_POST['payment_date'] ?? date('Y-m-d');
    $ownerPw = $_POST['owner_password'] ?? '';
    $ref     = trim($_POST['transaction_ref'] ?? '');

    // Verify owner password
    $shopRow = $pdo->prepare("SELECT password FROM shops WHERE shop_id=?"); $shopRow->execute([$shopId]); $sh = $shopRow->fetch();
    if (!$sh || !password_verify($ownerPw, $sh['password'])) {
        $error = 'Owner password galat hai!';
    } elseif ($amount <= 0) {
        $error = 'Valid amount daalo';
    } else {
        // Get pawn
        $pawnStmt = $pdo->prepare("SELECT * FROM pawn_entries WHERE id=? AND shop_id=?");
        $pawnStmt->execute([$pawnId, $shopId]);
        $pawn = $pawnStmt->fetch();
        if (!$pawn) { $error = 'Pawn entry nahi mili'; }
        else {
            $newTotalPaid = $pawn['total_paid'] + $amount;
            $monthsElapsed = max(1, round((time() - strtotime($pawn['pawn_date'])) / (30*24*3600)));
            $newRemaining  = calcRemaining($pawn['loan_amount'], $pawn['interest_rate'], $monthsElapsed, $newTotalPaid);
            $newStatus = ($newRemaining <= 0) ? 'closed' : 'active';

            // Record payment
            $ins = $pdo->prepare("INSERT INTO payments (pawn_id, bandhak_id, shop_id, amount, payment_mode, payment_date, transaction_ref, remaining_after, confirmed_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $ins->execute([$pawnId, $pawn['bandhak_id'], $shopId, $amount, $mode, $date, $ref, $newRemaining, $ownerId]);

            // Update pawn entry
            $upd = $pdo->prepare("UPDATE pawn_entries SET total_paid=?, remaining_amount=?, status=?, updated_at=NOW() WHERE id=?");
            $upd->execute([$newTotalPaid, $newRemaining, $newStatus, $pawnId]);

            auditLog($pdo, $shopId, 'payment_received', "Payment ₹$amount for {$pawn['bandhak_id']}", 'owner', $ownerId, $_SESSION['user_name'], $pawn['bandhak_id']);

            $success = "Payment ₹" . number_format($amount, 2) . " record ho gayi! Remaining: ₹" . number_format($newRemaining, 2);
        }
    }
}

// Get active pawns
$pawns = $pdo->prepare("SELECT pe.*, c.full_name, c.mobile FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id WHERE pe.shop_id=? AND pe.status='active' ORDER BY pe.pawn_date DESC");
$pawns->execute([$shopId]);
$activePawns = $pawns->fetchAll();

// Customer search (AJAX)
if (!empty($_GET['search_pay'])) {
    $q = '%'.trim($_GET['search_pay']).'%';
    $ss = $pdo->prepare("SELECT pe.id, pe.bandhak_id, pe.remaining_amount, pe.loan_amount, c.full_name, c.mobile FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id WHERE pe.shop_id=? AND pe.status='active' AND (c.full_name LIKE ? OR c.mobile LIKE ? OR pe.bandhak_id LIKE ?) ORDER BY pe.pawn_date DESC LIMIT 10");
    $ss->execute([$shopId,$q,$q,$q]);
    header('Content-Type: application/json');
    echo json_encode($ss->fetchAll());
    exit;
}

// Selected pawn
$selectedPawn = null;
if (!empty($_GET['pawn_id'])) {
    $sel = $pdo->prepare("SELECT pe.*, c.full_name FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id WHERE pe.id=? AND pe.shop_id=?");
    $sel->execute([intval($_GET['pawn_id']), $shopId]);
    $selectedPawn = $sel->fetch();
}

// Payment history
$history = $pdo->prepare("SELECT p.*, pe.bandhak_id, c.full_name FROM payments p JOIN pawn_entries pe ON p.pawn_id=pe.id JOIN customers c ON pe.customer_id=c.id WHERE p.shop_id=? ORDER BY p.created_at DESC LIMIT 30");
$history->execute([$shopId]);
$payHistory = $history->fetchAll();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Payments — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<nav class="navbar">
  <a class="navbar-brand" href="dashboard.php">🏅 Digital <span>Bandhak</span></a>
  <div class="navbar-right"><a href="dashboard.php" class="btn btn-outline btn-sm">← Dashboard</a></div>
</nav>
<div class="main-content container">
  <h2 style="margin-bottom:20px">💰 Payment Management</h2>

  <?php if ($success): ?><div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-danger">✖ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Responsive: 2-col desktop, 1-col mobile -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="payments-grid">
    <!-- Payment Form -->
    <div class="card">
      <div class="card-title">💳 Record Payment</div>
      <form method="POST">
        <!-- Customer Search -->
        <div class="form-group">
          <label class="form-label">🔍 Customer Search</label>
          <input class="form-control" type="text" id="custPaySearch" placeholder="Name, Mobile, Bandhak ID..." oninput="searchPayCustomer(this.value)"/>
          <div id="custPayResults" style="margin-top:6px"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Select Bandhak Entry *</label>
          <select class="form-control" name="pawn_id" id="pawn_select" required onchange="loadPawnDetails(this.value)">
            <option value="">-- Bandhak ID select karo --</option>
            <?php foreach ($activePawns as $p): ?>
            <option value="<?= $p['id'] ?>" data-remaining="<?= $p['remaining_amount'] ?>" data-loan="<?= $p['loan_amount'] ?>" data-name="<?= htmlspecialchars($p['full_name']) ?>" <?= ($selectedPawn && $selectedPawn['id']==$p['id'])?'selected':'' ?>>
              <?= htmlspecialchars($p['bandhak_id']) ?> — <?= htmlspecialchars($p['full_name']) ?> (+91 <?= htmlspecialchars($p['mobile']) ?>) · ₹<?= number_format($p['remaining_amount'],0) ?> baki
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" id="current_remaining" value="<?= $selectedPawn['remaining_amount'] ?? 0 ?>"/>

        <div id="pawn-details-box" style="<?= $selectedPawn?'':'display:none' ?>">
          <div class="stat-grid" style="margin:8px 0;grid-template-columns:1fr 1fr">
            <div class="stat-card"><div class="stat-label">Loan</div><div class="stat-value" style="font-size:17px" id="det-loan">₹<?= $selectedPawn?number_format($selectedPawn['loan_amount'],0):0 ?></div></div>
            <div class="stat-card" style="border-color:var(--danger)"><div class="stat-label">Remaining</div><div class="stat-value" style="font-size:17px;color:var(--danger)" id="det-remaining">₹<?= $selectedPawn?number_format($selectedPawn['remaining_amount'],0):0 ?></div></div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Mode</label>
          <select class="form-control" name="payment_mode">
            <option value="cash">💵 Cash</option>
            <option value="online">💻 Online/NEFT</option>
            <option value="upi">📱 UPI</option>
            <option value="qr">📷 QR Scan</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Amount (₹) *</label>
          <input class="form-control" type="number" name="amount" id="pay_amount" step="0.01" required placeholder="0" oninput="calcPaymentRemaining()"/>
        </div>
        <div class="form-group">
          <label class="form-label">Transaction Ref (optional)</label>
          <input class="form-control" type="text" name="transaction_ref" placeholder="UTR / ref number"/>
        </div>

        <div class="stat-card" style="margin:10px 0">
          <div class="stat-label">Remaining After Payment</div>
          <div class="stat-value" style="font-size:20px" id="remaining-after">₹<?= $selectedPawn?number_format($selectedPawn['remaining_amount'],2):'—' ?></div>
        </div>

        <div class="form-group">
          <label class="form-label">Owner Password (Confirm) *</label>
          <input class="form-control" type="password" name="owner_password" required placeholder="••••••••"/>
        </div>
        <button type="submit" class="btn btn-gold btn-lg w-full">✔ Confirm Payment</button>
      </form>
    </div>

    <!-- Interest Calculator (replaces QR on mobile) -->
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title">🧮 Quick Interest Calc</div>
        <p class="text-muted text-small mb-12">Current interest calculate karo.</p>
        <div class="form-group">
          <label class="form-label">Loan Amount (₹)</label>
          <input class="form-control" type="number" id="qc_loan" placeholder="0" oninput="quickCalc()"/>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Rate (%/mo)</label>
            <input class="form-control" type="number" id="qc_rate" value="2" step="0.1" oninput="quickCalc()"/>
          </div>
          <div class="form-group">
            <label class="form-label">Months</label>
            <input class="form-control" type="number" id="qc_months" value="1" min="1" oninput="quickCalc()"/>
          </div>
        </div>
        <div id="qc_result" style="background:var(--surface);border-radius:var(--radius);padding:12px;text-align:center;display:none">
          <div style="font-size:12px;color:var(--text3)">Total Due</div>
          <div style="font-size:24px;font-weight:700;color:var(--danger)" id="qc_total">₹0</div>
          <div style="font-size:11px;color:var(--text3)" id="qc_interest">Interest: ₹0</div>
        </div>
      </div>
    </div>
  </div>

  <style>
  @media(max-width:768px){
    .payments-grid{grid-template-columns:1fr !important;}
  }
  </style>

  <!-- Payment History — Cards on mobile, Table on desktop -->
  <div class="card mt-16">
    <div class="card-title">📜 Recent Payment History</div>
    <!-- Desktop table -->
    <div class="pawn-table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Bandhak ID</th><th>Customer</th><th>Amount</th><th>Mode</th><th>Remaining</th></tr></thead>
        <tbody>
        <?php foreach ($payHistory as $ph): ?>
        <tr>
          <td><?= date('d M y',strtotime($ph['payment_date'])) ?></td>
          <td><strong><?= htmlspecialchars($ph['bandhak_id']) ?></strong></td>
          <td><?= htmlspecialchars($ph['full_name']) ?></td>
          <td style="color:var(--success);font-weight:600">₹<?= number_format($ph['amount'],2) ?></td>
          <td><?= strtoupper($ph['payment_mode']) ?></td>
          <td style="color:<?= $ph['remaining_after']==0?'var(--success)':'var(--danger)' ?>">₹<?= number_format($ph['remaining_after'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($payHistory)): ?>
        <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text3)">Koi payment nahi mili</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Mobile card list -->
    <div class="pawn-card-list">
      <?php if (empty($payHistory)): ?>
      <div style="text-align:center;padding:20px;color:var(--text3)">Koi payment nahi mili</div>
      <?php endif; ?>
      <?php foreach ($payHistory as $ph): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border2)">
        <div style="width:36px;height:36px;border-radius:50%;background:rgba(26,107,58,.12);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">💰</div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($ph['bandhak_id']) ?></div>
          <div class="text-muted text-small"><?= htmlspecialchars($ph['full_name']) ?> · <?= date('d M y',strtotime($ph['payment_date'])) ?> · <?= strtoupper($ph['payment_mode']) ?></div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div style="font-weight:700;color:var(--success);font-size:14px">₹<?= number_format($ph['amount'],0) ?></div>
          <div style="font-size:11px;color:<?= $ph['remaining_after']==0?'var(--success)':'var(--text3)' ?>">Rem: ₹<?= number_format($ph['remaining_after'],0) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
function loadPawnDetails(pawnId) {
  const sel = document.getElementById('pawn_select');
  const opt = sel.options[sel.selectedIndex];
  const remaining = parseFloat(opt.dataset.remaining || 0);
  const loan      = parseFloat(opt.dataset.loan || 0);
  document.getElementById('current_remaining').value = remaining;
  document.getElementById('det-loan').textContent = '₹' + loan.toLocaleString('en-IN');
  document.getElementById('det-remaining').textContent = '₹' + remaining.toLocaleString('en-IN');
  document.getElementById('pawn-details-box').style.display = pawnId ? 'block' : 'none';
  document.getElementById('remaining-after').textContent = '₹' + remaining.toLocaleString('en-IN', {minimumFractionDigits:2});
}

let paySearchTimer = null;
async function searchPayCustomer(q) {
  clearTimeout(paySearchTimer);
  const box = document.getElementById('custPayResults');
  if (q.length < 2) { box.innerHTML=''; return; }
  paySearchTimer = setTimeout(async () => {
    const r = await fetch('payments.php?search_pay='+encodeURIComponent(q));
    const data = await r.json();
    if (!data.length) { box.innerHTML='<p class="text-small text-muted">Koi entry nahi mili</p>'; return; }
    box.innerHTML = data.map(p=>`
      <div onclick="selectPayEntry(${p.id},'${escHtml(p.bandhak_id)}','${escHtml(p.full_name)}',${p.remaining_amount},${p.loan_amount})"
        style="padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:4px;cursor:pointer;background:var(--surface);transition:border-color .1s"
        onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='var(--border)'">
        <strong>${escHtml(p.bandhak_id)}</strong> — ${escHtml(p.full_name)} · +91 ${escHtml(p.mobile)}
        <span style="float:right;color:var(--danger);font-weight:700">₹${parseInt(p.remaining_amount).toLocaleString('en-IN')}</span>
      </div>`).join('');
  }, 350);
}

function selectPayEntry(id, bid, name, remaining, loan) {
  const sel = document.getElementById('pawn_select');
  // Select in dropdown if exists
  for (let opt of sel.options) {
    if (opt.value == id) { sel.value = id; break; }
  }
  document.getElementById('current_remaining').value = remaining;
  document.getElementById('det-loan').textContent = '₹' + loan.toLocaleString('en-IN');
  document.getElementById('det-remaining').textContent = '₹' + remaining.toLocaleString('en-IN');
  document.getElementById('pawn-details-box').style.display = 'block';
  document.getElementById('remaining-after').textContent = '₹' + remaining.toLocaleString('en-IN', {minimumFractionDigits:2});
  document.getElementById('custPayResults').innerHTML = '';
  document.getElementById('custPaySearch').value = bid + ' — ' + name;
  showAlert('Entry selected!','success');
  // Set hidden pawn_id if select doesn't have option
  if (!document.querySelector('#pawn_select option[value="'+id+'"]')) {
    let hidden = document.getElementById('hidden_pawn_id');
    if (!hidden) { hidden=document.createElement('input'); hidden.type='hidden'; hidden.name='pawn_id'; hidden.id='hidden_pawn_id'; sel.parentNode.appendChild(hidden); }
    hidden.value = id;
  }
}
function quickCalc() {
  const loan   = parseFloat(document.getElementById('qc_loan')?.value) || 0;
  const rate   = parseFloat(document.getElementById('qc_rate')?.value) || 0;
  const months = parseInt(document.getElementById('qc_months')?.value) || 1;
  const interest = loan * (rate/100) * months;
  const total    = loan + interest;
  const res = document.getElementById('qc_result');
  if (res) {
    res.style.display = loan > 0 ? 'block' : 'none';
    document.getElementById('qc_total').textContent = '₹' + Math.round(total).toLocaleString('en-IN');
    document.getElementById('qc_interest').textContent = 'Interest: ₹' + Math.round(interest).toLocaleString('en-IN');
  }
}
</script>
</body>
</html>
