<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');
$unreadCount = 0;
$shopId = $_SESSION['shop_id'];
$unread = $pdo->prepare("SELECT COUNT(*) FROM admin_chat_messages WHERE shop_id=? AND sender_type='admin' AND is_read=0");
$unread->execute([$shopId]); $unreadCount = $unread->fetchColumn();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Interest Calculator — Digital Bandhak</title>
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
    <a href="interest_calc.php" class="active"><span class="sidebar-icon">🧮</span> Interest Calculator</a>
    <a href="subscription.php"><span class="sidebar-icon">🔁</span> Subscription</a>
    <a href="staff.php"><span class="sidebar-icon">👷</span> Staff</a>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat Admin<?php if($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?></a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../terms.php"><span class="sidebar-icon">📜</span> Terms</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>

  <main class="main-content">
    <h2 style="margin-bottom:6px">🧮 Interest Calculator</h2>
    <p class="text-muted text-small mb-16">Loan, interest aur total due calculate karo</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <!-- Calculator Input -->
      <div class="card">
        <div class="card-title">Calculate Karo</div>
        <div class="form-group">
          <label class="form-label">Loan Amount (₹) *</label>
          <input class="form-control" type="number" id="calc_loan" placeholder="15000" oninput="calcInterest()" min="0"/>
        </div>
        <div class="form-group">
          <label class="form-label">Interest Rate (% per month) *</label>
          <input class="form-control" type="number" id="calc_rate" placeholder="2" step="0.1" oninput="calcInterest()" min="0"/>
        </div>
        <div class="form-group">
          <label class="form-label">Duration</label>
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
            <input class="form-control" type="number" id="calc_duration" placeholder="90" oninput="calcInterest()" min="1"/>
            <select class="form-control" id="calc_unit" onchange="calcInterest()">
              <option value="days">Days</option>
              <option value="months" selected>Months</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Loan Start Date</label>
          <input class="form-control" type="date" id="calc_start" value="<?= date('Y-m-d') ?>" oninput="calcInterest()"/>
        </div>
        <button class="btn btn-gold w-full" onclick="calcInterest()">Calculate ▶</button>
      </div>

      <!-- Result -->
      <div>
        <div class="card" id="calc-result" style="display:none">
          <div class="card-title">📊 Result</div>
          <div class="stat-grid" style="grid-template-columns:1fr 1fr">
            <div class="stat-card">
              <div class="stat-label">Principal</div>
              <div class="stat-value" style="font-size:20px" id="res-principal">—</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Total Interest</div>
              <div class="stat-value" style="font-size:20px;color:var(--warning)" id="res-interest">—</div>
            </div>
            <div class="stat-card" style="grid-column:1/-1;border:1.5px solid var(--gold)">
              <div class="stat-label">Total Due Amount</div>
              <div class="stat-value" style="color:var(--danger)" id="res-total">—</div>
            </div>
          </div>
          <div style="margin-top:14px;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
            <table style="width:100%;font-size:13px;border-collapse:collapse">
              <thead><tr style="background:var(--table-head)">
                <th style="padding:8px 12px;text-align:left;font-size:11px;color:var(--gold-dark)">Month</th>
                <th style="padding:8px 12px;text-align:right;font-size:11px;color:var(--gold-dark)">Interest</th>
                <th style="padding:8px 12px;text-align:right;font-size:11px;color:var(--gold-dark)">Cumulative Due</th>
              </tr></thead>
              <tbody id="calc-table"></tbody>
            </table>
          </div>
          <div style="margin-top:10px;padding:10px 12px;background:var(--surface);border-radius:var(--radius);font-size:12px;color:var(--text2)">
            📅 Due Date: <strong id="res-due-date">—</strong>
          </div>
        </div>

        <!-- Quick Presets -->
        <div class="card mt-16">
          <div class="card-title">⚡ Quick Presets</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <button class="btn btn-outline" onclick="setPreset(10000,2,3)">₹10k · 2%/mo · 3mo</button>
            <button class="btn btn-outline" onclick="setPreset(15000,2,6)">₹15k · 2%/mo · 6mo</button>
            <button class="btn btn-outline" onclick="setPreset(25000,2.5,6)">₹25k · 2.5%/mo · 6mo</button>
            <button class="btn btn-outline" onclick="setPreset(50000,2,12)">₹50k · 2%/mo · 12mo</button>
            <button class="btn btn-outline" onclick="setPreset(5000,3,3)">₹5k · 3%/mo · 3mo</button>
            <button class="btn btn-outline" onclick="setPreset(100000,1.5,12)">₹1L · 1.5%/mo · 12mo</button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<!-- Mobile Bottom Nav -->
<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
function calcInterest() {
  const loan     = parseFloat(document.getElementById('calc_loan').value) || 0;
  const rate     = parseFloat(document.getElementById('calc_rate').value) || 0;
  const dur      = parseFloat(document.getElementById('calc_duration').value) || 0;
  const unit     = document.getElementById('calc_unit').value;
  const startStr = document.getElementById('calc_start').value;
  if (!loan || !rate || !dur) return;

  const months = unit === 'days' ? dur / 30 : dur;
  const totalInterest = parseFloat((loan * (rate / 100) * months).toFixed(2));
  const totalDue      = loan + totalInterest;

  document.getElementById('calc-result').style.display = 'block';
  document.getElementById('res-principal').textContent = '₹' + loan.toLocaleString('en-IN');
  document.getElementById('res-interest').textContent  = '₹' + totalInterest.toLocaleString('en-IN');
  document.getElementById('res-total').textContent     = '₹' + totalDue.toLocaleString('en-IN', {minimumFractionDigits:2});

  // Due date
  if (startStr) {
    const d = new Date(startStr);
    if (unit === 'days') d.setDate(d.getDate() + dur);
    else d.setMonth(d.getMonth() + Math.round(dur));
    document.getElementById('res-due-date').textContent = d.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
  }

  // Month-by-month table
  const tbody = document.getElementById('calc-table');
  tbody.innerHTML = '';
  const totalMonths = Math.ceil(months);
  for (let m = 1; m <= Math.min(totalMonths, 24); m++) {
    const mInterest = parseFloat((loan * (rate / 100)).toFixed(2));
    const cumDue    = parseFloat((loan + mInterest * m).toFixed(2));
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="padding:7px 12px;border-bottom:1px solid var(--border2)">Month ${m}</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border2);text-align:right;color:var(--warning)">₹${mInterest.toLocaleString('en-IN')}</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border2);text-align:right;color:var(--danger);font-weight:600">₹${cumDue.toLocaleString('en-IN')}</td>`;
    tbody.appendChild(tr);
  }
}

function setPreset(loan, rate, months) {
  document.getElementById('calc_loan').value     = loan;
  document.getElementById('calc_rate').value     = rate;
  document.getElementById('calc_duration').value = months;
  document.getElementById('calc_unit').value     = 'months';
  calcInterest();
}

// Auto calc on load if values present
calcInterest();
</script>
</body>
</html>
