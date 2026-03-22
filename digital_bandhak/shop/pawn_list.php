<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId = $_SESSION['shop_id'];
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where  = ["pe.shop_id=?","pe.status!='deleted'"]; $params=[$shopId];
if ($status !== 'all') { $where[]="pe.status=?"; $params[]=$status; }
if ($search) { $where[]="(c.full_name LIKE ? OR pe.bandhak_id LIKE ? OR pe.item_type LIKE ?)"; $s="%$search%"; $params[]=$s;$params[]=$s;$params[]=$s; }
$sql = "SELECT pe.*,c.full_name,c.mobile FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id WHERE ".implode(' AND ',$where)." ORDER BY pe.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $pawns=$stmt->fetchAll();
$unreadCount=0;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Pawn List — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="dashboard-layout">
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="pawn_add.php"><span class="sidebar-icon">➕</span> New Entry</a>
    <a href="pawn_list.php" class="active"><span class="sidebar-icon">📋</span> Pawn List</a>
    <a href="payments.php"><span class="sidebar-icon">💰</span> Payments</a>
    <div class="sidebar-divider"></div>
    <a href="reports.php"><span class="sidebar-icon">📄</span> Reports</a>
    <a href="interest_calc.php"><span class="sidebar-icon">🧮</span> Interest Calc</a>
    <a href="subscription.php"><span class="sidebar-icon">🔁</span> Subscription</a>
    <a href="staff.php"><span class="sidebar-icon">👷</span> Staff</a>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat Admin</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
      <h2>📋 Pawn List</h2>
      <a href="pawn_add.php" class="btn btn-gold">+ New Pawn Entry</a>
    </div>
    <!-- Filters -->
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
      <select class="form-control" name="status" style="width:130px" onchange="this.form.submit()">
        <option value="all" <?= $status==='all'?'selected':'' ?>>All Status</option>
        <option value="active"  <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="closed"  <?= $status==='closed'?'selected':'' ?>>Closed</option>
      </select>
      <input class="form-control" type="text" name="search" placeholder="Search name / ID / item…" value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:180px"/>
      <button type="submit" class="btn btn-gold">Search</button>
      <?php if ($search||$status!=='all'): ?><a href="pawn_list.php" class="btn btn-outline">Clear</a><?php endif; ?>
    </form>
    <div class="card">
      <!-- DESKTOP TABLE -->
      <div class="pawn-table-wrap">
        <table>
          <thead><tr><th>Bandhak ID</th><th>Customer</th><th>Item</th><th>Loan</th><th>Remaining</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($pawns as $p): ?>
          <tr>
            <td><strong><?= htmlspecialchars($p['bandhak_id']) ?></strong></td>
            <td><?= htmlspecialchars($p['full_name']) ?><br/><small class="text-muted">+91 <?= htmlspecialchars($p['mobile']) ?></small></td>
            <td><?= htmlspecialchars($p['item_type']) ?></td>
            <td>₹<?= number_format($p['loan_amount'],0) ?></td>
            <td style="color:<?= $p['remaining_amount']>0?'var(--danger)':'var(--success)' ?>">₹<?= number_format($p['remaining_amount'],0) ?></td>
            <td><?= date('d M y',strtotime($p['pawn_date'])) ?></td>
            <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="pawn_view.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">View</a>
              <?php if ($p['status']==='active'): ?>
              <a href="payments.php?pawn_id=<?= $p['id'] ?>" class="btn btn-success btn-sm">Pay</a>
              <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $p['id'] ?>,'<?= htmlspecialchars($p['bandhak_id']) ?>')">Del</button>
              <?php endif; ?>
              <button class="btn btn-outline btn-sm" onclick="printReceipt(<?= $p['id'] ?>)">🖨</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($pawns)): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3)">Koi entry nahi</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- MOBILE CARD LIST -->
      <div class="pawn-card-list">
        <?php if (empty($pawns)): ?>
        <div style="text-align:center;padding:30px;color:var(--text3)">Koi entry nahi</div>
        <?php endif; ?>
        <?php foreach ($pawns as $p): ?>
        <div class="pawn-card" onclick="location.href='pawn_view.php?id=<?= $p['id'] ?>'">
          <div class="pawn-card-top">
            <div>
              <div class="pawn-card-id"><?= htmlspecialchars($p['bandhak_id']) ?></div>
              <div class="pawn-card-name"><?= htmlspecialchars($p['full_name']) ?></div>
              <div class="pawn-card-sub">+91 <?= htmlspecialchars($p['mobile']) ?> · <?= htmlspecialchars($p['item_type']) ?></div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
              <span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
              <span style="font-size:10px;color:var(--text3)"><?= date('d M y',strtotime($p['pawn_date'])) ?></span>
            </div>
          </div>
          <div class="pawn-card-amounts">
            <div class="pawn-card-amt"><div class="pawn-card-amt-label">Loan</div><div class="pawn-card-amt-val" style="color:var(--gold)">₹<?= number_format($p['loan_amount'],0) ?></div></div>
            <div class="pawn-card-amt"><div class="pawn-card-amt-label">Paid</div><div class="pawn-card-amt-val" style="color:var(--success)">₹<?= number_format($p['total_paid'],0) ?></div></div>
            <div class="pawn-card-amt"><div class="pawn-card-amt-label">Remaining</div><div class="pawn-card-amt-val" style="color:<?= $p['remaining_amount']>0?'var(--danger)':'var(--success)' ?>">₹<?= number_format($p['remaining_amount'],0) ?></div></div>
          </div>
          <div class="pawn-card-actions" onclick="event.stopPropagation()">
            <a href="pawn_view.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center">👁 View</a>
            <?php if ($p['status']==='active'): ?>
            <a href="payments.php?pawn_id=<?= $p['id'] ?>" class="btn btn-success btn-sm" style="flex:1;text-align:center">💰 Pay</a>
            <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $p['id'] ?>,'<?= htmlspecialchars($p['bandhak_id']) ?>')">Del</button>
            <?php endif; ?>
            <button class="btn btn-outline btn-sm" onclick="printReceipt(<?= $p['id'] ?>)">🖨</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div>
  </main>
</div>
<div class="modal-backdrop" id="modal-delete" style="display:none">
  <div class="modal-box">
    <div class="modal-title">⚠ Delete Entry?</div>
    <div class="modal-body">Entry <strong id="delete_bandhak_label"></strong> soft-delete hogi. Audit log record hoga.</div>
    <div class="form-group"><label class="form-label">Owner Password *</label><input class="form-control" type="password" id="delete_owner_pw"/></div>
    <input type="hidden" id="delete_pawn_id"/>
    <div class="modal-footer">
      <button class="btn btn-danger" onclick="submitDelete()">Delete</button>
      <button class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button>
    </div>
  </div>
</div>
<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>window.API_BASE='../';</script>
</body>
</html>
