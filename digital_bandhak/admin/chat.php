<?php
define('IS_ADMIN', true);
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$shopList   = $pdo->query("SELECT shop_id, shop_name, owner_name, status FROM shops ORDER BY shop_name")->fetchAll();
$unreadMap  = $pdo->query("SELECT shop_id, COUNT(*) as cnt FROM admin_chat_messages WHERE sender_type='owner' AND is_read=0 GROUP BY shop_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$unreadCount= array_sum($unreadMap);

// Pending shops
$pendingShops = $pdo->query("SELECT * FROM shops WHERE status='inactive' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Private Chat — Admin</title>
  <link rel="stylesheet" href="../css/style.css"/>
  <style>
    /* WhatsApp-style layout */
    .wa-wrap{display:flex;height:calc(100vh - 54px);overflow:hidden;}

    /* Thread list panel */
    .wa-list{
      width:280px;flex-shrink:0;
      border-right:1px solid var(--border);
      display:flex;flex-direction:column;
      background:var(--sidebar-bg);
    }
    .wa-list-header{
      padding:12px 14px;font-size:13px;font-weight:700;
      border-bottom:1px solid var(--border);
      background:var(--table-head);color:var(--gold-dark);
      display:flex;align-items:center;justify-content:space-between;
    }
    [data-theme="dark"] .wa-list-header{color:var(--gold);}
    .wa-search{padding:8px 10px;border-bottom:1px solid var(--border);}
    .wa-search input{width:100%;box-sizing:border-box;}
    .wa-threads{overflow-y:auto;flex:1;}
    .wa-thread{
      display:flex;align-items:center;gap:10px;
      padding:11px 14px;cursor:pointer;
      border-bottom:1px solid var(--border2);
      transition:background .12s;position:relative;
    }
    .wa-thread:hover,.wa-thread.active{background:var(--hover-bg);}
    .wa-thread-avatar{
      width:38px;height:38px;border-radius:50%;
      background:var(--gold-light);color:var(--gold-dark);
      font-weight:700;font-size:14px;flex-shrink:0;
      display:flex;align-items:center;justify-content:center;
    }
    .wa-thread-info{flex:1;min-width:0;}
    .wa-thread-name{font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .wa-thread-sub{font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .wa-thread-badge{background:var(--success);color:#fff;font-size:10px;font-weight:700;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

    /* Chat area */
    .wa-chat{flex:1;display:flex;flex-direction:column;min-width:0;}
    .wa-chat-header{
      padding:10px 14px;border-bottom:1px solid var(--border);
      background:var(--table-head);
      display:flex;align-items:center;gap:10px;flex-shrink:0;
    }
    .wa-back-btn{
      display:none;background:none;border:none;color:var(--gold);
      font-size:20px;cursor:pointer;padding:4px 8px;flex-shrink:0;
    }
    .wa-placeholder{
      flex:1;display:flex;align-items:center;justify-content:center;
      color:var(--text3);font-size:14px;text-align:center;padding:20px;
      background:var(--bg);
    }

    /* MOBILE: WhatsApp behavior */
    @media(max-width:768px){
      .wa-wrap{height:calc(100vh - 54px - 60px);}
      .wa-list{width:100%;border-right:none;}
      .wa-chat{position:fixed;inset:54px 0 60px 0;z-index:250;background:var(--bg);display:none;flex-direction:column;}
      .wa-chat.mobile-open{display:flex;}
      .wa-back-btn{display:block !important;}
      .wa-list.hidden{display:none;}
    }

    /* Message file/image */
    .msg-img-thumb{max-width:200px;border-radius:8px;margin-top:6px;cursor:pointer;display:block;}
  </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div style="margin-top:54px" class="dashboard-layout" style="padding:0">
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php"><span class="sidebar-icon">📊</span> Dashboard</a>
    <a href="shop_add.php"><span class="sidebar-icon">🏪</span> Add Shop</a>
    <a href="subscription_add.php"><span class="sidebar-icon">🔁</span> Subscriptions</a>
    <div class="sidebar-divider"></div>
    <a href="audit_logs.php"><span class="sidebar-icon">📋</span> Audit Logs</a>
    <a href="transactions.php"><span class="sidebar-icon">💳</span> Transactions</a>
    <div class="sidebar-divider"></div>
    <a href="chat.php" class="active"><span class="sidebar-icon">💬</span> Chat<?php if($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?></a>
    <a href="settings.php"><span class="sidebar-icon">⚙️</span> Settings</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>

  <main class="main-content" style="padding:0;overflow:hidden">
    <!-- Pending approvals banner -->
    <?php if (!empty($pendingShops)): ?>
    <div style="padding:8px 14px;background:var(--warning-bg);border-bottom:1px solid var(--border);font-size:12px;color:var(--warning);display:flex;align-items:center;gap:8px">
      🆕 <?=count($pendingShops)?> new registration<?=count($pendingShops)>1?'s':''?> pending!
      <?php foreach($pendingShops as $ps): ?>
        <button class="btn btn-success btn-sm" onclick="adminShopAction('<?=addslashes($ps['shop_id'])?>','activate')">✔ Activate <?=htmlspecialchars($ps['shop_id'])?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="wa-wrap" id="waWrap">
      <!-- Thread List -->
      <div class="wa-list" id="waList">
        <div class="wa-list-header">
          <span>💬 Shops (<?=count($shopList)?>)</span>
          <?php if ($unreadCount): ?><span class="badge-count"><?=$unreadCount?></span><?php endif; ?>
        </div>
        <div class="wa-search">
          <input class="form-control" type="text" placeholder="Search shop..." id="threadSearch" oninput="filterThreads(this.value)" style="font-size:12px;padding:7px 10px"/>
        </div>
        <div class="wa-threads" id="threadList">
          <?php foreach ($shopList as $sh):
            $unread = $unreadMap[$sh['shop_id']] ?? 0;
            $initials = strtoupper(substr($sh['shop_name'],0,2));
          ?>
          <div class="wa-thread" id="thread-<?=$sh['shop_id']?>"
            data-search="<?=strtolower($sh['shop_id'].' '.$sh['shop_name'].' '.$sh['owner_name'])?>"
            onclick="openChat('<?=addslashes($sh['shop_id'])?>','<?=addslashes($sh['shop_name'])?>','<?=addslashes($sh['owner_name'])?>')">
            <div class="wa-thread-avatar"><?=$initials?></div>
            <div class="wa-thread-info">
              <div class="wa-thread-name"><?=htmlspecialchars($sh['shop_id'])?></div>
              <div class="wa-thread-sub"><?=htmlspecialchars($sh['shop_name'])?></div>
            </div>
            <?php if ($unread): ?>
            <div class="wa-thread-badge"><?=$unread?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Chat Area -->
      <div class="wa-chat" id="waChat">
        <!-- Header with back button -->
        <div class="wa-chat-header">
          <button class="wa-back-btn" onclick="closeChat()">←</button>
          <div class="wa-thread-avatar" id="chatAvatar" style="width:36px;height:36px;font-size:13px">?</div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:13px" id="chatShopName">—</div>
            <div style="font-size:11px;color:var(--text3)" id="chatShopSub">Private Thread</div>
          </div>
          <a id="chatSubLink" href="#" class="btn btn-outline btn-sm" style="font-size:11px;flex-shrink:0">+ Sub</a>
        </div>
        <div class="chat-messages" id="chatMessages"></div>

        <!-- File preview -->
        <div id="filePreview" style="display:none;padding:8px 14px;background:var(--surface);border-top:1px solid var(--border);align-items:center;gap:10px">
          <div id="filePreviewContent" style="flex:1"></div>
          <button onclick="clearFile()" style="background:none;border:none;color:var(--danger);font-size:18px;cursor:pointer">✕</button>
        </div>

        <!-- Input area -->
        <div class="chat-input-wrap">
          <label style="cursor:pointer;padding:6px 8px;border:1px solid var(--border2);border-radius:8px;display:flex;align-items:center;color:var(--text3)" title="Attach">
            📎<input type="file" id="chatFile" accept="image/*,.pdf,.doc,.docx,.xlsx,.txt" style="display:none" onchange="previewFile(this)"/>
          </label>
          <input class="chat-input" id="chatInput" placeholder="Reply to shop..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"/>
          <button class="btn btn-gold btn-sm" onclick="sendMsg()">Send</button>
        </div>

        <!-- Placeholder (desktop: no shop selected) -->
        <div class="wa-placeholder" id="chatPlaceholder" style="display:none">
          ← Left side mein shop select karo to chat open ho
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Lightbox -->
<div id="imgModal" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <img id="imgModalSrc" src="" style="max-width:90vw;max-height:88vh;border-radius:12px" alt="Photo"/>
</div>

<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
window.CHAT_BASE = '../';
let currentShopId = '';
let selectedFile  = null;
let isMobile = window.innerWidth <= 768;

window.addEventListener('resize', () => { isMobile = window.innerWidth <= 768; });

function filterThreads(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.wa-thread').forEach(t => {
    t.style.display = t.dataset.search.includes(q) ? '' : 'none';
  });
}

function openChat(shopId, shopName, ownerName) {
  currentShopId = shopId;
  chatLastId = 0;
  document.getElementById('chatAvatar').textContent = shopName.substring(0,2).toUpperCase();
  document.getElementById('chatShopName').textContent = shopName + ' (' + shopId + ')';
  document.getElementById('chatShopSub').textContent = ownerName;
  document.getElementById('chatSubLink').href = '../admin/subscription_add.php?shop_id=' + shopId;
  document.getElementById('chatMessages').innerHTML = '';

  // Mobile: show chat, hide list
  if (isMobile) {
    document.getElementById('waChat').classList.add('mobile-open');
    document.getElementById('waList').classList.add('hidden');
  } else {
    document.getElementById('chatPlaceholder').style.display = 'none';
    document.getElementById('chatMessages').style.display = 'flex';
  }

  // Mark thread active
  document.querySelectorAll('.wa-thread').forEach(t => t.classList.remove('active'));
  const thread = document.getElementById('thread-' + shopId);
  if (thread) {
    thread.classList.add('active');
    // Remove badge
    const badge = thread.querySelector('.wa-thread-badge');
    if (badge) badge.remove();
  }

  clearInterval(window.chatInterval);
  loadMessages(shopId, 'admin');
  window.chatInterval = setInterval(() => loadMessages(shopId, 'admin'), 3000);
  document.getElementById('chatInput').focus();
}

function closeChat() {
  // Mobile: go back to list
  clearInterval(window.chatInterval);
  currentShopId = '';
  document.getElementById('waChat').classList.remove('mobile-open');
  document.getElementById('waList').classList.remove('hidden');
  document.querySelectorAll('.wa-thread').forEach(t => t.classList.remove('active'));
}

function previewFile(inp) {
  selectedFile = inp.files[0];
  if (!selectedFile) return;
  const prev = document.getElementById('filePreview');
  const cont = document.getElementById('filePreviewContent');
  prev.style.display = 'flex';
  if (selectedFile.type.startsWith('image/')) {
    const r = new FileReader();
    r.onload = e => { cont.innerHTML = `<img src="${e.target.result}" style="height:44px;width:44px;object-fit:cover;border-radius:6px"/> <span style="font-size:12px">${selectedFile.name}</span>`; };
    r.readAsDataURL(selectedFile);
  } else {
    cont.innerHTML = `<span style="font-size:20px">📎</span> <span style="font-size:12px">${selectedFile.name}</span>`;
  }
}

function clearFile() {
  selectedFile = null;
  document.getElementById('chatFile').value = '';
  document.getElementById('filePreview').style.display = 'none';
}

async function sendMsg() {
  if (!currentShopId) return;
  const inp = document.getElementById('chatInput');
  const msg = inp.value.trim();
  if (!msg && !selectedFile) return;
  const fd = new FormData();
  fd.append('shop_id', currentShopId);
  fd.append('sender_type', 'admin');
  if (msg) fd.append('message', msg);
  if (selectedFile) fd.append('chat_file', selectedFile);
  inp.value = '';
  clearFile();
  const r = await fetch('../php/chat_send.php', { method:'POST', body:fd });
  const data = await r.json();
  if (data.success) loadMessages(currentShopId, 'admin');
  else showAlert(data.msg || 'Send failed', 'danger');
}

async function adminShopAction(shopId, action) {
  if (!confirm(action + ' shop ' + shopId + '?')) return;
  const r = await apiPost('../php/admin_shop_action.php', { shop_id: shopId, action });
  if (r.success) { showAlert(r.msg, 'success'); setTimeout(() => location.reload(), 1200); }
  else showAlert(r.msg || 'Error', 'danger');
}

// Open image in lightbox
function openChatImage(url) {
  document.getElementById('imgModalSrc').src = url;
  document.getElementById('imgModal').style.display = 'flex';
}
</script>
</body>
</html>
