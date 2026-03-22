<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId   = $_SESSION['shop_id'];
$ownerId  = $_SESSION['user_id'];
$shopName = $_SESSION['shop_name'];

// Check subscription
$subActive = checkSubscription($pdo, $shopId);
$subExpired = !$subActive;

$active     = $pdo->prepare("SELECT COUNT(*) FROM pawn_entries WHERE shop_id=? AND status='active'"); $active->execute([$shopId]); $activeCount=$active->fetchColumn();
$closed     = $pdo->prepare("SELECT COUNT(*) FROM pawn_entries WHERE shop_id=? AND status='closed'"); $closed->execute([$shopId]); $closedCount=$closed->fetchColumn();
$monthly    = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE shop_id=? AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())"); $monthly->execute([$shopId]); $monthlyAmt=$monthly->fetchColumn();
$pendingS   = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM pawn_entries WHERE shop_id=? AND status='active'"); $pendingS->execute([$shopId]); $pendingAmt=$pendingS->fetchColumn();

$subStmt = $pdo->prepare("SELECT * FROM subscriptions WHERE shop_id=? AND status='active' AND end_date>=CURDATE() ORDER BY end_date DESC LIMIT 1"); $subStmt->execute([$shopId]); $sub=$subStmt->fetch();
$entries = $pdo->prepare("SELECT pe.*,c.full_name,c.mobile FROM pawn_entries pe JOIN customers c ON pe.customer_id=c.id WHERE pe.shop_id=? AND pe.status!='deleted' ORDER BY pe.created_at DESC LIMIT 20"); $entries->execute([$shopId]); $pawns=$entries->fetchAll();
$unread  = $pdo->prepare("SELECT COUNT(*) FROM admin_chat_messages WHERE shop_id=? AND sender_type='admin' AND is_read=0"); $unread->execute([$shopId]); $unreadCount=$unread->fetchColumn();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= htmlspecialchars($shopName) ?> — Dashboard</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="dashboard-layout">
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="active"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="pawn_add.php"><span class="sidebar-icon">➕</span> New Pawn Entry</a>
    <a href="pawn_list.php"><span class="sidebar-icon">📋</span> Pawn List</a>
    <a href="payments.php"><span class="sidebar-icon">💰</span> Payments</a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-section">More</div>
    <a href="reports.php"><span class="sidebar-icon">📄</span> Reports</a>
    <a href="interest_calc.php"><span class="sidebar-icon">🧮</span> Interest Calc</a>
    <a href="subscription.php"><span class="sidebar-icon">🔁</span> Subscription</a>
    <a href="staff.php"><span class="sidebar-icon">👷</span> Staff Manage</a>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Chat Support<?php if($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?></a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../terms.php"><span class="sidebar-icon">📜</span> Terms</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>

  <main class="main-content">
    <?php if ($subExpired): ?>
    <div style="background:var(--danger-bg);border:2px solid var(--danger);border-radius:var(--radius-lg);padding:20px;margin-bottom:20px;text-align:center">
      <div style="font-size:32px;margin-bottom:8px">🔒</div>
      <h3 style="color:var(--danger);margin-bottom:6px">Subscription Expired!</h3>
      <p class="text-muted mb-12">New entries aur payments block hain. Data safe hai.</p>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a href="subscription.php" class="btn btn-gold">🔁 Renew Subscription</a>
        <a href="subscription.php?request=1" class="btn btn-outline">📨 Admin Ko Request Bhejo</a>
      </div>
    </div>
    <?php else: ?>
      <?php
        $daysLeft = $subActive ? (int)((new DateTime($subActive['end_date']))->diff(new DateTime())->days) : 0;
        if ($subActive && $daysLeft <= 15):
      ?>
      <div class="alert alert-warning">⚠ Subscription <strong><?= $daysLeft ?></strong> din mein expire hogi. <a href="subscription.php" style="color:var(--gold);font-weight:700">Renew karo →</a></div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="stat-grid">
      <div class="stat-card"><div class="stat-label">Active Bandhak</div><div class="stat-value"><?= $activeCount ?></div></div>
      <div class="stat-card"><div class="stat-label">Closed</div><div class="stat-value" style="color:var(--success)"><?= $closedCount ?></div></div>
      <div class="stat-card"><div class="stat-label">Monthly Collection</div><div class="stat-value" style="font-size:18px">₹<?= number_format($monthlyAmt,0) ?></div></div>
      <div class="stat-card"><div class="stat-label">Pending Amount</div><div class="stat-value" style="font-size:18px;color:var(--danger)">₹<?= number_format($pendingAmt,0) ?></div></div>
    </div>

    <div class="card">
      <div class="tabs" id="ownerTabs">
        <button class="tab-btn active" onclick="switchTab('ownerTabs','ownerPanes',0)">📋 Pawn Entries</button>
        <button class="tab-btn" onclick="switchTab('ownerTabs','ownerPanes',1)">🔁 Subscription</button>
        <button class="tab-btn" onclick="switchTab('ownerTabs','ownerPanes',2);initOwnerChat()">💬 Admin Chat<?php if($unreadCount): ?> <span class="badge-count"><?=$unreadCount?></span><?php endif; ?></button>
      </div>

      <div id="ownerPanes">
        <!-- Pawn list -->
        <div class="tab-pane active">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
            <h3>Recent Pawn Entries</h3>
            <div style="display:flex;gap:8px">
              <a href="pawn_list.php" class="btn btn-outline btn-sm">View All</a>
              <a href="pawn_add.php" class="btn btn-gold btn-sm">+ New Entry</a>
            </div>
          </div>

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
                  <?php endif; ?>
                  <button class="btn btn-outline btn-sm" onclick="printReceipt(<?= $p['id'] ?>)">🖨</button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pawns)): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3)">Koi entry nahi — <a href="pawn_add.php">Pehli entry add karo →</a></td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- MOBILE CARD LIST -->
          <div class="pawn-card-list">
            <?php if (empty($pawns)): ?>
            <div style="text-align:center;padding:30px;color:var(--text3)">Koi entry nahi — <a href="pawn_add.php" style="color:var(--gold)">Pehli entry add karo →</a></div>
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
                <div class="pawn-card-amt">
                  <div class="pawn-card-amt-label">Loan</div>
                  <div class="pawn-card-amt-val" style="color:var(--gold)">₹<?= number_format($p['loan_amount'],0) ?></div>
                </div>
                <div class="pawn-card-amt">
                  <div class="pawn-card-amt-label">Paid</div>
                  <div class="pawn-card-amt-val" style="color:var(--success)">₹<?= number_format($p['total_paid'],0) ?></div>
                </div>
                <div class="pawn-card-amt">
                  <div class="pawn-card-amt-label">Remaining</div>
                  <div class="pawn-card-amt-val" style="color:<?= $p['remaining_amount']>0?'var(--danger)':'var(--success)' ?>">₹<?= number_format($p['remaining_amount'],0) ?></div>
                </div>
              </div>
              <div class="pawn-card-actions" onclick="event.stopPropagation()">
                <a href="pawn_view.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center">👁 View</a>
                <?php if ($p['status']==='active'): ?>
                <a href="payments.php?pawn_id=<?= $p['id'] ?>" class="btn btn-success btn-sm" style="flex:1;text-align:center">💰 Pay</a>
                <?php endif; ?>
                <button class="btn btn-outline btn-sm" onclick="printReceipt(<?= $p['id'] ?>)">🖨</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <!-- Subscription tab -->
        <div class="tab-pane">
          <?php if ($sub):
            $td=(new DateTime($sub['end_date']))->diff(new DateTime($sub['start_date']))->days;
            $ud=max(0,(new DateTime())->diff(new DateTime($sub['start_date']))->days);
            $pct=min(100,round($ud/max(1,$td)*100));
            $ld=max(0,(new DateTime($sub['end_date']))->diff(new DateTime())->days);
          ?>
          <div class="card" style="max-width:420px;border-color:var(--gold)">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div><h3><?= ucfirst($sub['plan_type']) ?> Plan</h3><p class="text-small text-muted"><?= date('d M Y',strtotime($sub['start_date'])) ?> → <?= date('d M Y',strtotime($sub['end_date'])) ?></p></div>
              <span class="badge badge-active">Active</span>
            </div>
            <div class="sub-progress"><div class="sub-bar" style="width:<?= $pct ?>%"></div></div>
            <p class="text-small text-muted"><?= $pct ?>% elapsed · <strong><?= $ld ?> din bache</strong></p>
            <a href="subscription.php" class="btn btn-gold btn-sm mt-12">Renew / Extend →</a>
          </div>
          <?php else: ?>
          <div class="alert alert-danger">No active subscription. <a href="subscription.php">Renew karo.</a></div>
          <?php endif; ?>
        </div>

        <!-- Chat tab -->
        <div class="tab-pane">
          <div class="chat-wrap">
            <div class="chat-header">
              <div class="nav-avatar" style="background:var(--info)">AD</div>
              <div><strong>Super Admin</strong><div class="text-small text-muted">Private Thread · <?= htmlspecialchars($shopId) ?></div></div>
            </div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-wrap">
              <input class="chat-input" id="chatInput" placeholder="Admin ko message…" onkeydown="if(event.key==='Enter')sendChatMessage('<?= $shopId ?>','owner')"/>
              <button class="btn btn-gold btn-sm" onclick="sendChatMessage('<?= $shopId ?>','owner')">Send</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Delete Modal -->
<div class="modal-backdrop" id="modal-delete" style="display:none">
  <div class="modal-box">
    <div class="modal-title">⚠ Pawn Entry Delete?</div>
    <div class="modal-body">Entry <strong id="delete_bandhak_label"></strong> soft-delete ho jayegi. Audit log record hoga.</div>
    <div class="form-group"><label class="form-label">Owner Password *</label><input class="form-control" type="password" id="delete_owner_pw" placeholder="Confirm karo"/></div>
    <input type="hidden" id="delete_pawn_id"/>
    <div class="modal-footer">
      <button class="btn btn-danger" onclick="submitDelete()">Delete Karo</button>
      <button class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button>
    </div>
  </div>
</div>

<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
window.CHAT_BASE = '../';
window.API_BASE  = '../';
let ownerChatInited = false;
function initOwnerChat() {
  if (!ownerChatInited) {
    initChat('<?= $shopId ?>', 'owner');
    ownerChatInited = true;
  }
}
</script>
</body>
</html>
