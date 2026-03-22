<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId  = $_SESSION['shop_id'];
$shopName= $_SESSION['shop_name'];

// Filters
$filterType   = $_GET['filter_type'] ?? 'monthly';
$filterMonth  = $_GET['month'] ?? date('m');
$filterYear   = $_GET['year']  ?? date('Y');
$filterStatus = $_GET['status'] ?? 'all';
$dateFrom     = $_GET['date_from'] ?? date('Y-m-01');
$dateTo       = $_GET['date_to']   ?? date('Y-m-d');
$search       = trim($_GET['search'] ?? '');

// Build query
$where  = ["pe.shop_id = ?"];
$params = [$shopId];

if ($filterType === 'monthly') {
    $where[]  = "MONTH(pe.pawn_date)=? AND YEAR(pe.pawn_date)=?";
    $params[] = $filterMonth; $params[] = $filterYear;
} elseif ($filterType === 'yearly') {
    $where[]  = "YEAR(pe.pawn_date)=?";
    $params[] = $filterYear;
} elseif ($filterType === 'custom') {
    $where[]  = "pe.pawn_date BETWEEN ? AND ?";
    $params[] = $dateFrom; $params[] = $dateTo;
}

if ($filterStatus !== 'all') { $where[] = "pe.status=?"; $params[] = $filterStatus; }
if ($search) { $where[] = "(c.full_name LIKE ? OR pe.bandhak_id LIKE ? OR pe.item_type LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; }

$whereStr = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT pe.*, c.full_name, c.mobile, c.aadhaar_masked FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id WHERE $whereStr ORDER BY pe.pawn_date DESC");
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Totals
$totalLoan    = array_sum(array_column($entries, 'loan_amount'));
$totalPaid    = array_sum(array_column($entries, 'total_paid'));
$totalPending = array_sum(array_column($entries, 'remaining_amount'));
$totalEntries = count($entries);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Reports — <?= htmlspecialchars($shopName) ?></title>
  <link rel="stylesheet" href="../css/style.css"/>
  <style>
    @media print {
      .no-print { display:none !important; }
      .navbar, .sidebar { display:none !important; }
      .main-content { padding:0 !important; }
      .card { box-shadow:none !important; border:1px solid #ddd !important; }
      body { background:#fff; }
    }
  </style>
</head>
<body>
<nav class="navbar no-print">
  <a class="navbar-brand" href="dashboard.php">🏅 Digital <span>Bandhak</span></a>
  <div class="navbar-right">
    <a href="dashboard.php" class="btn btn-outline btn-sm no-print">← Dashboard</a>
  </div>
</nav>

<div class="main-content container">
  <!-- Filters -->
  <div class="card no-print mb-16">
    <div class="card-title">📄 Reports — <?= htmlspecialchars($shopName) ?></div>
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:130px">
        <label class="form-label">Report Type</label>
        <select class="form-control" name="filter_type" onchange="this.form.submit()">
          <option value="monthly" <?= $filterType=='monthly'?'selected':'' ?>>Monthly</option>
          <option value="yearly"  <?= $filterType=='yearly'?'selected':'' ?>>Yearly</option>
          <option value="custom"  <?= $filterType=='custom'?'selected':'' ?>>Custom Range</option>
          <option value="all"     <?= $filterType=='all'?'selected':'' ?>>All Time</option>
        </select>
      </div>
      <?php if ($filterType === 'monthly'): ?>
      <div class="form-group" style="margin:0;min-width:100px">
        <label class="form-label">Month</label>
        <select class="form-control" name="month">
          <?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==$filterMonth?'selected':'' ?>><?= date('M', mktime(0,0,0,$m,1)) ?></option><?php endfor; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:80px">
        <label class="form-label">Year</label>
        <select class="form-control" name="year">
          <?php for ($y=date('Y');$y>=2023;$y--): ?><option value="<?= $y ?>" <?= $y==$filterYear?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
        </select>
      </div>
      <?php elseif ($filterType === 'custom'): ?>
      <div class="form-group" style="margin:0"><label class="form-label">From</label><input class="form-control" type="date" name="date_from" value="<?= $dateFrom ?>"/></div>
      <div class="form-group" style="margin:0"><label class="form-label">To</label><input class="form-control" type="date" name="date_to" value="<?= $dateTo ?>"/></div>
      <?php elseif ($filterType === 'yearly'): ?>
      <div class="form-group" style="margin:0">
        <label class="form-label">Year</label>
        <select class="form-control" name="year"><?php for ($y=date('Y');$y>=2023;$y--): ?><option value="<?= $y ?>" <?= $y==$filterYear?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select>
      </div>
      <?php endif; ?>
      <div class="form-group" style="margin:0;min-width:100px">
        <label class="form-label">Status</label>
        <select class="form-control" name="status">
          <option value="all" <?= $filterStatus=='all'?'selected':'' ?>>All</option>
          <option value="active"  <?= $filterStatus=='active'?'selected':'' ?>>Active</option>
          <option value="closed"  <?= $filterStatus=='closed'?'selected':'' ?>>Closed</option>
          <option value="deleted" <?= $filterStatus=='deleted'?'selected':'' ?>>Deleted</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:160px">
        <label class="form-label">Search</label>
        <input class="form-control" type="text" name="search" placeholder="Name / Bandhak ID / Item" value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <button type="submit" class="btn btn-gold">🔍 Generate</button>
      <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Print</button>
      <a href="reports.php?<?= $_SERVER['QUERY_STRING'] ?>&export=pdf" class="btn btn-outline">📥 Export PDF</a>
    </form>
  </div>

  <!-- Summary Stats -->
  <div class="stat-grid no-print">
    <div class="stat-card"><div class="stat-label">Total Entries</div><div class="stat-value"><?= $totalEntries ?></div></div>
    <div class="stat-card"><div class="stat-label">Total Loan</div><div class="stat-value" style="font-size:18px">₹<?= number_format($totalLoan,0) ?></div></div>
    <div class="stat-card"><div class="stat-label">Total Collected</div><div class="stat-value" style="font-size:18px;color:var(--success)">₹<?= number_format($totalPaid,0) ?></div></div>
    <div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:18px;color:var(--danger)">₹<?= number_format($totalPending,0) ?></div></div>
  </div>

  <!-- Report Table -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div>
        <h3>Report — <?= htmlspecialchars($shopName) ?></h3>
        <p class="text-small text-muted">Generated: <?= date('d M Y H:i') ?> · Total: <?= $totalEntries ?> entries</p>
      </div>
    </div>
    <!-- DESKTOP: table (hidden on mobile) -->
    <div class="pawn-table-wrap">
      <div style="overflow-x:auto">
      <table style="min-width:700px">
        <thead>
          <tr>
            <th>Bandhak ID</th><th>Customer</th><th>Item</th><th>Loan</th>
            <th>Interest</th><th>Paid</th><th>Remaining</th><th>Date</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $e): ?>
        <tr>
          <td><strong><?= htmlspecialchars($e['bandhak_id']) ?></strong></td>
          <td><?= htmlspecialchars($e['full_name']) ?><br/><small class="text-muted">+91 <?= htmlspecialchars($e['mobile']) ?></small></td>
          <td><?= htmlspecialchars($e['item_type']) ?><br/><small class="text-muted"><?= htmlspecialchars(substr($e['item_description'],0,20)) ?></small></td>
          <td>₹<?= number_format($e['loan_amount'],0) ?></td>
          <td><?= $e['interest_rate'] ?>%/mo</td>
          <td style="color:var(--success)">₹<?= number_format($e['total_paid'],0) ?></td>
          <td style="color:<?= $e['remaining_amount']>0?'var(--danger)':'var(--success)' ?>">₹<?= number_format($e['remaining_amount'],0) ?></td>
          <td><?= date('d M y', strtotime($e['pawn_date'])) ?></td>
          <td><span class="badge badge-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
        <tr><td colspan="9" style="text-align:center;padding:20px;color:var(--text3)">Koi data nahi mila</td></tr>
        <?php endif; ?>
        <tfoot style="background:var(--gold-light)">
          <tr>
            <td colspan="3" style="padding:10px 14px;font-weight:700">TOTAL (<?= $totalEntries ?> entries)</td>
            <td style="font-weight:700">₹<?= number_format($totalLoan,0) ?></td>
            <td></td>
            <td style="font-weight:700;color:var(--success)">₹<?= number_format($totalPaid,0) ?></td>
            <td style="font-weight:700;color:var(--danger)">₹<?= number_format($totalPending,0) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>

    <!-- MOBILE: card list -->
    <div class="pawn-card-list">
      <?php if (empty($entries)): ?>
      <p class="text-muted" style="text-align:center;padding:20px">Koi data nahi mila</p>
      <?php endif; ?>
      <?php foreach ($entries as $e): ?>
      <div class="pawn-card" onclick="location.href='pawn_view.php?id=<?=$e['id']?>'">
        <div class="pawn-card-top">
          <div>
            <div class="pawn-card-id"><?= htmlspecialchars($e['bandhak_id']) ?></div>
            <div class="pawn-card-name"><?= htmlspecialchars($e['full_name']) ?></div>
            <div class="pawn-card-sub">+91 <?= htmlspecialchars($e['mobile']) ?> · <?= htmlspecialchars($e['item_type']) ?></div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
            <span class="badge badge-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span>
            <span style="font-size:10px;color:var(--text3)"><?= date('d M y',strtotime($e['pawn_date'])) ?></span>
          </div>
        </div>
        <div class="pawn-card-amounts">
          <div class="pawn-card-amt"><div class="pawn-card-amt-label">Loan</div><div class="pawn-card-amt-val" style="color:var(--gold)">₹<?= number_format($e['loan_amount'],0) ?></div></div>
          <div class="pawn-card-amt"><div class="pawn-card-amt-label">Paid</div><div class="pawn-card-amt-val" style="color:var(--success)">₹<?= number_format($e['total_paid'],0) ?></div></div>
          <div class="pawn-card-amt"><div class="pawn-card-amt-label">Remaining</div><div class="pawn-card-amt-val" style="color:<?= $e['remaining_amount']>0?'var(--danger)':'var(--success)' ?>">₹<?= number_format($e['remaining_amount'],0) ?></div></div>
        </div>
        <div style="margin-top:8px;font-size:11px;color:var(--text3)"><?= $e['interest_rate'] ?>%/mo · Due: <?= date('d M y',strtotime($e['due_date'])) ?></div>
      </div>
      <?php endforeach; ?>
      <!-- Mobile totals -->
      <div style="background:var(--gold-light);border-radius:var(--radius);padding:12px;margin-top:8px">
        <div style="font-size:12px;font-weight:700;color:var(--gold-dark);margin-bottom:8px">TOTAL — <?= $totalEntries ?> entries</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center">
          <div><div style="font-size:10px;color:var(--text3)">Loan</div><div style="font-weight:700">₹<?= number_format($totalLoan,0) ?></div></div>
          <div><div style="font-size:10px;color:var(--text3)">Paid</div><div style="font-weight:700;color:var(--success)">₹<?= number_format($totalPaid,0) ?></div></div>
          <div><div style="font-size:10px;color:var(--text3)">Outstanding</div><div style="font-weight:700;color:var(--danger)">₹<?= number_format($totalPending,0) ?></div></div>
        </div>
      </div>
    </div>
    <p class="text-small text-muted mt-8">Note: Aadhaar masked hai. Duplicate print par watermark aayega.</p>
  </div>
</div>

<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
</body>
</html>
