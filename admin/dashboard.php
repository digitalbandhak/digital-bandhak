<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$total    = $pdo->query("SELECT COUNT(*) FROM shops")->fetchColumn();
$active   = $pdo->query("SELECT COUNT(*) FROM shops WHERE status='active'")->fetchColumn();
$expired  = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND end_date < CURDATE()")->fetchColumn();
$trial    = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE plan_type='trial' AND status='active' AND end_date>=CURDATE()")->fetchColumn();
$revenue  = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM subscriptions WHERE payment_mode!='free'")->fetchColumn();
// Extra stats
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalPawns     = $pdo->query("SELECT COUNT(*) FROM pawn_entries WHERE status='active'")->fetchColumn();
$totalClosed    = $pdo->query("SELECT COUNT(*) FROM pawn_entries WHERE status='closed'")->fetchColumn();
$totalCollected = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
// Extra stats
$totalBandhak   = $pdo->query("SELECT COUNT(*) FROM pawn_entries WHERE status!='deleted'")->fetchColumn();
$activeBandhak  = $pdo->query("SELECT COUNT(*) FROM pawn_entries WHERE status='active'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(DISTINCT id) FROM customers")->fetchColumn();
$totalPayments  = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();

$shopList = $pdo->query("
  SELECT s.*,
    sub.plan_type, sub.end_date as sub_end, sub.status as sub_status,
    (SELECT COUNT(*) FROM pawn_entries WHERE shop_id=s.shop_id AND status='active') as active_pawns
  FROM shops s
  LEFT JOIN subscriptions sub ON s.shop_id=sub.shop_id AND sub.id=(SELECT MAX(id) FROM subscriptions WHERE shop_id=s.shop_id)
  ORDER BY s.created_at DESC
")->fetchAll();

$logs    = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 30")->fetchAll();
$subs    = $pdo->query("SELECT sub.*,s.shop_name FROM subscriptions sub JOIN shops s ON sub.shop_id=s.shop_id ORDER BY sub.created_at DESC LIMIT 50")->fetchAll();
$unreadChats = $pdo->query("SELECT shop_id,COUNT(*) as cnt FROM admin_chat_messages WHERE sender_type='owner' AND is_read=0 GROUP BY shop_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$unreadCount = array_sum($unreadChats);
$pendingShops = $pdo->query("SELECT * FROM shops WHERE status='inactive' ORDER BY created_at DESC")->fetchAll();
// Subscription requests
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_requests (id INT AUTO_INCREMENT PRIMARY KEY, shop_id VARCHAR(30), plan_type VARCHAR(20) DEFAULT 'monthly', message TEXT, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    $subRequests = $pdo->query("SELECT sr.*, s.shop_name, s.owner_name FROM subscription_requests sr JOIN shops s ON sr.shop_id=s.shop_id WHERE sr.status='pending' ORDER BY sr.created_at DESC")->fetchAll();
} catch(Exception $e){ $subRequests=[]; }
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin Dashboard — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="dashboard-layout">
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="active"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="shop_add.php"><span class="sidebar-icon">🏪</span> Add Shop</a>
    <a href="subscription_add.php"><span class="sidebar-icon">🔁</span> Subscriptions</a>
    <div class="sidebar-divider"></div>
    <a href="audit_logs.php"><span class="sidebar-icon">📋</span> Audit Logs</a>
    <a href="transactions.php"><span class="sidebar-icon">💳</span> Transactions</a>
    <div class="sidebar-divider"></div>
    <a href="chat.php"><span class="sidebar-icon">💬</span> Private Chat<?php if($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?></a>
    <a href="settings.php"><span class="sidebar-icon">⚙️</span> Site Settings</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content">
    <h2 style="margin-bottom:20px">Admin Dashboard</h2>
    <?php if (!empty($pendingShops)): ?>
    <div class="alert alert-warning mb-16">🆕 <strong><?=count($pendingShops)?> pending registration<?=count($pendingShops)>1?'s':''?>!</strong> <a href="chat.php" style="color:var(--gold);font-weight:700;margin-left:8px">Review →</a></div>
    <?php endif; ?>
    <?php if (!empty($subRequests)): ?>
    <div class="card mb-16" style="border-color:var(--gold)">
      <div class="card-title" style="color:var(--gold)">📨 Subscription Requests (<?=count($subRequests)?>)</div>
      <?php foreach ($subRequests as $sr): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border2);flex-wrap:wrap;gap:8px">
        <div>
          <strong><?=htmlspecialchars($sr['shop_id'])?></strong> — <?=htmlspecialchars($sr['shop_name'])?>
          <div class="text-small text-muted"><?=ucfirst($sr['plan_type'])?> Plan · <?=date('d M H:i',strtotime($sr['created_at']))?>
          <?php if($sr['message']): ?> · "<?=htmlspecialchars(substr($sr['message'],0,40))?>"<?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:6px">
          <a href="subscription_add.php?shop_id=<?=htmlspecialchars($sr['shop_id'])?>&plan=<?=$sr['plan_type']?>&req_id=<?=$sr['id']?>" class="btn btn-success btn-sm">✔ Approve</a>
          <button class="btn btn-danger btn-sm" onclick="rejectSubReq(<?=$sr['id']?>)">✖ Reject</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-label">Total Shops</div><div class="stat-value"><?=$total?></div></div>
      <div class="stat-card"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--success)"><?=$active?></div></div>
      <div class="stat-card"><div class="stat-label">Expired</div><div class="stat-value" style="color:var(--danger)"><?=$expired?></div></div>
      <div class="stat-card"><div class="stat-label">Trial</div><div class="stat-value" style="color:var(--warning)"><?=$trial?></div></div>
      <div class="stat-card"><div class="stat-label">Revenue</div><div class="stat-value" style="font-size:18px">₹<?=number_format($revenue,0)?></div></div>
    </div>
    <div class="stat-grid" style="margin-top:-10px">
      <div class="stat-card" style="border-color:var(--gold)"><div class="stat-label">Total Bandhak</div><div class="stat-value" style="color:var(--gold)"><?=$totalPawns+$totalClosed?></div></div>
      <div class="stat-card"><div class="stat-label">Active Bandhak</div><div class="stat-value" style="color:var(--success)"><?=$totalPawns?></div></div>
      <div class="stat-card"><div class="stat-label">Total Customers</div><div class="stat-value"><?=$totalCustomers?></div></div>
      <div class="stat-card"><div class="stat-label">Total Collected</div><div class="stat-value" style="font-size:18px;color:var(--success)">₹<?=number_format($totalCollected,0)?></div></div>
    </div>
    <div class="card">
      <div class="tabs" id="adminTabs">
        <button class="tab-btn active" onclick="switchTab('adminTabs','adminPanes',0)">🏪 Shops</button>
        <button class="tab-btn" onclick="switchTab('adminTabs','adminPanes',1)">🔁 Subscriptions</button>
        <button class="tab-btn" onclick="switchTab('adminTabs','adminPanes',2)">📋 Audit Logs</button>
      </div>
      <div id="adminPanes">
        <!-- Shops -->
        <div class="tab-pane active">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
            <h3>All Shops</h3>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="text" class="form-control" id="shopSearch" placeholder="Search..." style="width:160px" oninput="filterShops(this.value)"/>
              <a href="shop_add.php" class="btn btn-gold btn-sm">+ Add Shop</a>
            </div>
          </div>
          <div class="table-wrap">
            <table id="shopTable">
              <thead><tr><th>Shop ID</th><th>Shop Name</th><th>Owner</th><th>Mobile</th><th>Subscription</th><th>Pawns</th><th>Status</th><th>History</th></tr></thead>
              <tbody>
              <?php foreach ($shopList as $sh):
                $blocked = !empty($sh['blocked']);
                $dispStatus = $blocked ? 'blocked' : $sh['status'];
              ?>
              <tr data-search="<?=strtolower($sh['shop_id'].' '.$sh['shop_name'].' '.$sh['owner_name'])?>">
                <td><strong><?=htmlspecialchars($sh['shop_id'])?></strong></td>
                <td><?=htmlspecialchars($sh['shop_name'])?><br/><small class="text-muted"><?=htmlspecialchars($sh['city']??'')?></small></td>
                <td><?=htmlspecialchars($sh['owner_name'])?></td>
                <td><?=htmlspecialchars($sh['owner_mobile'])?></td>
                <td>
                  <?php if($sh['sub_end']): ?>
                  <span class="badge badge-<?=$sh['sub_status']??'expired'?>"><?=ucfirst($sh['plan_type']??'')?> · <?=date('d M y',strtotime($sh['sub_end']))?></span>
                  <?php else: ?><span class="badge badge-expired">No Sub</span><?php endif; ?>
                </td>
                <td style="text-align:center"><?=$sh['active_pawns']?></td>
                <td><span class="badge badge-<?=$dispStatus==='active'?'active':($dispStatus==='blocked'?'expired':'expired')?>"><?=$dispStatus==='blocked'?'Blocked':ucfirst($dispStatus)?></span></td>
                <td><button class="btn btn-outline btn-sm" onclick="openHistory('<?=addslashes($sh['shop_id'])?>','<?=addslashes($sh['shop_name'])?>','<?=addslashes($sh['owner_name'])?>','<?=addslashes($sh['owner_mobile'])?>','<?=addslashes($sh['owner_email']??'')?>','<?=addslashes($sh['city']??'')?>','<?=addslashes($sh['gst_number']??'')?>')">📜 History</button></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Subscriptions -->
        <div class="tab-pane">
          <div style="display:flex;justify-content:space-between;margin-bottom:14px"><h3>Subscriptions</h3><a href="subscription_add.php" class="btn btn-gold btn-sm">+ Add/Extend</a></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Shop ID</th><th>Shop</th><th>Plan</th><th>Start</th><th>End</th><th>Amount</th><th>Mode</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($subs as $s): ?>
              <tr>
                <td><?=htmlspecialchars($s['shop_id'])?></td>
                <td><?=htmlspecialchars($s['shop_name'])?></td>
                <td><?=ucfirst($s['plan_type'])?></td>
                <td><?=date('d M y',strtotime($s['start_date']))?></td>
                <td><?=date('d M y',strtotime($s['end_date']))?></td>
                <td>₹<?=number_format($s['amount'],0)?></td>
                <td><?=strtoupper($s['payment_mode'])?></td>
                <td><span class="badge badge-<?=$s['status']?>"><?=ucfirst($s['status'])?></span></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Audit Logs -->
        <div class="tab-pane">
          <h3 style="margin-bottom:14px">Audit Logs</h3>
          <div style="max-height:480px;overflow-y:auto">
          <?php foreach ($logs as $log): ?>
          <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border2)">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--gold-light);color:var(--gold-dark);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"><?=strtoupper(substr($log['action_type'],0,1))?></div>
            <div><div style="font-weight:600;font-size:13px"><?=htmlspecialchars($log['action_description'])?></div><div class="text-small text-muted"><?=htmlspecialchars($log['performed_by_name']??'')?> · <?=htmlspecialchars($log['shop_id']??'')?> · <?=date('d M y H:i',strtotime($log['created_at']))?></div></div>
          </div>
          <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- History Modal -->
<div class="modal-backdrop" id="modal-history" style="display:none">
  <div class="modal-box" style="max-width:640px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div class="modal-title" style="margin:0">📜 <span id="history-shop-id"></span></div>
      <button onclick="closeModal('modal-history')" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text3)">✕</button>
    </div>
    <div class="tabs" id="histTabs">
      <button class="tab-btn active" onclick="switchTab('histTabs','histPanes',0)">📊 Stats</button>
      <button class="tab-btn" onclick="switchTab('histTabs','histPanes',1)">🔁 Subscriptions</button>
      <button class="tab-btn" onclick="switchTab('histTabs','histPanes',2)">✏️ Edit & Actions</button>
    </div>
    <div id="histPanes">
      <div class="tab-pane active" id="hist-pays"><div class="text-muted" style="padding:20px;text-align:center">Loading...</div></div>
      <div class="tab-pane" id="hist-subs"><div class="text-muted" style="padding:20px;text-align:center">Loading...</div></div>
      <div class="tab-pane">
        <!-- Actions -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;padding:12px;background:var(--surface);border-radius:var(--radius)">
          <button class="btn btn-success btn-sm" onclick="shopAction('activate')">✔ Activate</button>
          <button class="btn btn-danger btn-sm" onclick="shopAction('block')">🚫 Block</button>
          <button class="btn btn-outline btn-sm" onclick="shopAction('unblock')">🔓 Unblock</button>
          <a id="hist-sub-link" href="#" class="btn btn-gold btn-sm">+ Add Subscription</a>
          <a id="hist-chat-link" href="#" class="btn btn-outline btn-sm">💬 Chat</a>
        </div>
        <!-- Edit fields -->
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Shop Name</label><input class="form-control" type="text" id="edit-shop-name"/></div>
          <div class="form-group"><label class="form-label">Owner Name</label><input class="form-control" type="text" id="edit-owner-name"/></div>
          <div class="form-group"><label class="form-label">Mobile</label><input class="form-control" type="tel" id="edit-mobile"/></div>
          <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" id="edit-email"/></div>
          <div class="form-group"><label class="form-label">City</label><input class="form-control" type="text" id="edit-city"/></div>
          <div class="form-group"><label class="form-label">GST Number</label><input class="form-control" type="text" id="edit-gst"/></div>
          <div class="form-group"><label class="form-label">New Password (optional)</label><input class="form-control" type="text" id="edit-newpw" placeholder="Leave blank to keep"/></div>
        </div>
        <button class="btn btn-gold mt-8" onclick="saveShopEdit()">💾 Save Changes</button>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/admin_mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
let currentHistShopId = '';

function filterShops(q){
  q=q.toLowerCase();
  document.querySelectorAll('#shopTable tbody tr').forEach(tr=>{
    tr.style.display=tr.dataset.search.includes(q)?'':'none';
  });
}

async function openHistory(shopId,shopName,ownerName,mobile,email,city,gst){
  currentHistShopId=shopId;
  document.getElementById('history-shop-id').textContent=shopId+' — '+shopName;
  document.getElementById('hist-sub-link').href='../admin/subscription_add.php?shop_id='+shopId;
  document.getElementById('hist-chat-link').href='../admin/chat.php?shop_id='+shopId;
  document.getElementById('edit-shop-name').value=shopName;
  document.getElementById('edit-owner-name').value=ownerName;
  document.getElementById('edit-mobile').value=mobile;
  document.getElementById('edit-email').value=email;
  document.getElementById('edit-city').value=city;
  document.getElementById('edit-gst').value=gst;
  openModal('modal-history');

  // Load stats
  const r=await fetch('../php/admin_shop_history.php?shop_id='+encodeURIComponent(shopId)+'&type=pays');
  const d=await r.json();
  let h='<div class="stat-grid">';
  h+=`<div class="stat-card"><div class="stat-label">Total Pawns</div><div class="stat-value">${d.total_pawns||0}</div></div>`;
  h+=`<div class="stat-card"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--success)">${d.active_pawns||0}</div></div>`;
  h+=`<div class="stat-card"><div class="stat-label">Collected</div><div class="stat-value" style="font-size:18px">₹${parseInt(d.total_collected||0).toLocaleString('en-IN')}</div></div>`;
  h+=`<div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value" style="font-size:18px;color:var(--danger)">₹${parseInt(d.pending||0).toLocaleString('en-IN')}</div></div>`;
  h+=`</div><p class="text-small text-muted mt-8">Registered: ${d.shop?.created_at||'—'}</p>`;
  document.getElementById('hist-pays').innerHTML=h;

  // Load sub history
  const r2=await fetch('../php/admin_shop_history.php?shop_id='+encodeURIComponent(shopId)+'&type=subs');
  const d2=await r2.json();
  let h2='<div class="table-wrap"><table><thead><tr><th>Plan</th><th>Start</th><th>End</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
  if(d2.subs&&d2.subs.length) d2.subs.forEach(s=>{h2+=`<tr><td>${s.plan_type}</td><td>${s.start_date}</td><td>${s.end_date}</td><td>₹${s.amount}</td><td><span class="badge badge-${s.status}">${s.status}</span></td></tr>`;});
  else h2+='<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text3)">Koi subscription nahi</td></tr>';
  h2+='</tbody></table></div>';
  document.getElementById('hist-subs').innerHTML=h2;
}

async function shopAction(action){
  if(!currentHistShopId) return;
  if(!confirm('Shop '+currentHistShopId+' ko '+action+' karein?')) return;
  const r=await apiPost('../php/admin_shop_action.php',{shop_id:currentHistShopId,action});
  if(r.success){showAlert(r.msg,'success');setTimeout(()=>location.reload(),1200);}
  else showAlert(r.msg||'Error','danger');
}

async function saveShopEdit(){
  const newpw=document.getElementById('edit-newpw').value;
  const data={
    action:'edit',shop_id:currentHistShopId,
    shop_name:document.getElementById('edit-shop-name').value,
    owner_name:document.getElementById('edit-owner-name').value,
    mobile:document.getElementById('edit-mobile').value,
    email:document.getElementById('edit-email').value,
    city:document.getElementById('edit-city').value,
    gst_number:document.getElementById('edit-gst').value,
  };
  if(newpw) data.new_password=newpw;
  const r=await apiPost('../php/admin_shop_action.php',data);
  if(r.success){showAlert('Shop updated!','success');setTimeout(()=>location.reload(),1200);}
  else showAlert(r.msg||'Error','danger');
}

async function rejectSubReq(reqId){
  if(!confirm('Request reject karein?')) return;
  const r=await apiPost('../php/admin_shop_action.php',{action:'reject_sub_req',req_id:reqId});
  if(r.success){showAlert('Rejected!','success');setTimeout(()=>location.reload(),800);}
  else showAlert(r.msg||'Error','danger');
}
</script>
</body>
</html>
