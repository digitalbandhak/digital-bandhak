<?php
define('IS_SHOP', true);
// shop/staff_panel.php
require_once '../includes/config.php';
requireLogin('staff', '../index.php');

$shopId   = $_SESSION['shop_id'];
$staffId  = $_SESSION['user_id'];
$staffName= $_SESSION['user_name'];

// Get pawns for this shop (read only)
$pawns = $pdo->prepare("SELECT pe.*, c.full_name, c.mobile FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id WHERE pe.shop_id=? AND pe.status != 'deleted' ORDER BY pe.created_at DESC");
$pawns->execute([$shopId]);
$pawnList = $pawns->fetchAll();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Staff Panel — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="main-content" style="padding-top:64px">
  <div class="alert alert-warning" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span style="background:var(--warning-bg);color:var(--warning);padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;flex-shrink:0">👷 Staff Mode</span>
    <span>Sirf <strong>New Bandhak Entry</strong> aur <strong>List</strong> access hai. Payment/delete allowed nahi.</span>
  </div>

  <div class="tabs" id="staffTabs">
    <button class="tab-btn active" onclick="switchTab('staffTabs','staffPanes',0)">➕ New Bandhak Entry</button>
    <button class="tab-btn" onclick="switchTab('staffTabs','staffPanes',1)">📋 Bandhak List</button>
  </div>

  <div id="staffPanes">
    <!-- New Entry (submit to owner for approval) -->
    <div class="tab-pane active">
      <div class="card">
        <div class="card-title">➕ New Bandhak Entry</div>
        <p class="text-muted mb-16">Entry submit hogi. Owner password se final save hogi.</p>
        <form action="pawn_add.php" method="GET">
          <button type="submit" class="btn btn-gold">Open Entry Form →</button>
        </form>
        <p class="text-small text-muted mt-8">Note: Final save ke liye Owner ka password zaroori hai.</p>
      </div>
    </div>

    <!-- Bandhak List (read only) -->
    <div class="tab-pane">
      <div class="card">
        <div class="card-title">📋 Bandhak List (Read Only)</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Bandhak ID</th><th>Customer</th><th>Item</th><th>Loan</th><th>Remaining</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($pawnList as $p): ?>
            <tr>
              <td><strong><?= htmlspecialchars($p['bandhak_id']) ?></strong></td>
              <td><?= htmlspecialchars($p['full_name']) ?><br/><small class="text-muted"><?= htmlspecialchars($p['mobile']) ?></small></td>
              <td><?= htmlspecialchars($p['item_type']) ?></td>
              <td>₹<?= number_format($p['loan_amount'],0) ?></td>
              <td style="color:var(--danger)">₹<?= number_format($p['remaining_amount'],0) ?></td>
              <td><?= date('d M y', strtotime($p['pawn_date'])) ?></td>
              <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pawnList)): ?>
            <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text3)">Koi entry nahi</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <p class="text-small text-muted mt-8">Read-only view. Payment, delete, report access nahi hai.</p>
      </div>
    </div>
  </div>
</div>
<script src="../js/app.js"></script>
</body>
</html>
