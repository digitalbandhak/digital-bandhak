<?php
define('IS_SHOP', true);
require_once '../includes/config.php';
requireLogin('owner', '../index.php');
$shopId      = $_SESSION['shop_id'];
$unreadCount = 0;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Chat Admin — Digital Bandhak</title>
  <link rel="stylesheet" href="../css/style.css"/>
  <style>
    .chat-file-preview{max-width:200px;border-radius:8px;margin-top:6px;cursor:pointer;}
    .chat-file-link{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;background:rgba(0,0,0,0.1);border-radius:8px;font-size:12px;text-decoration:none;color:inherit;margin-top:4px;}
    .msg-row.owner .chat-file-link{background:rgba(255,255,255,0.15);color:#fff;}
    .upload-preview-area{padding:8px 14px;background:var(--surface);border-top:1px solid var(--border);display:none;align-items:center;gap:10px;font-size:13px;}
    .upload-preview-area img{height:48px;width:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border);}
    #imageModal{position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px;}
    #imageModal img{max-width:90vw;max-height:90vh;border-radius:12px;}
    #imageModal .close{position:fixed;top:20px;right:24px;color:#fff;font-size:28px;cursor:pointer;z-index:10000;}
  </style>
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
    <a href="interest_calc.php"><span class="sidebar-icon">🧮</span> Interest Calc</a>
    <a href="subscription.php"><span class="sidebar-icon">🔁</span> Subscription</a>
    <a href="staff.php"><span class="sidebar-icon">👷</span> Staff</a>
    <a href="chat.php" class="active"><span class="sidebar-icon">💬</span> Chat Admin</a>
    <a href="profile.php"><span class="sidebar-icon">👤</span> My Profile</a>
    <a href="../terms.php"><span class="sidebar-icon">📜</span> Terms</a>
    <a href="../php/logout.php" style="margin-top:auto;color:var(--danger)"><span class="sidebar-icon">🚪</span> Logout</a>
  </aside>
  <main class="main-content" style="padding:20px;display:flex;flex-direction:column">
    <h2 style="margin-bottom:14px">💬 Chat with Admin</h2>
    <div class="chat-wrap" style="height:calc(100vh - 160px);min-height:400px">
      <div class="chat-header">
        <div class="nav-avatar" style="background:var(--info);color:#fff;font-size:14px">AD</div>
        <div>
          <strong>Super Admin</strong>
          <div class="text-small text-muted">Private Thread · <?=htmlspecialchars($shopId)?></div>
        </div>
        <div style="margin-left:auto;width:8px;height:8px;border-radius:50%;background:var(--success)"></div>
      </div>
      <div class="chat-messages" id="chatMessages"></div>

      <!-- File preview area -->
      <div class="upload-preview-area" id="uploadPreview">
        <div id="previewContent"></div>
        <button onclick="clearFile()" style="background:none;border:none;color:var(--danger);font-size:18px;cursor:pointer;margin-left:auto">✕</button>
      </div>

      <div class="chat-input-wrap">
        <label style="cursor:pointer;padding:6px;border-radius:8px;border:1px solid var(--border2);display:flex;align-items:center;transition:background .15s" title="Attach file/photo">
          📎
          <input type="file" id="chatFileInp" accept="image/*,.pdf,.doc,.docx,.xlsx,.txt" style="display:none" onchange="previewFile(this)"/>
        </label>
        <input class="chat-input" id="chatInput" placeholder="Admin ko message likho… (Enter to send)"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"/>
        <button class="btn btn-gold btn-sm" onclick="sendMsg()">Send</button>
      </div>
    </div>
  </main>
</div>

<!-- Image lightbox -->
<div id="imageModal" onclick="document.getElementById('imageModal').style.display='none'">
  <span class="close">✕</span>
  <img id="modalImg" src="" alt=""/>
</div>

<?php include '../includes/mobile_nav.php'; ?>
<script src="../js/app.js"></script>
<script>
window.CHAT_BASE = '../';
const SHOP_ID    = '<?=addslashes($shopId)?>';
let selectedFile = null;

initChat(SHOP_ID, 'owner');

function previewFile(inp) {
  const file = inp.files[0];
  if (!file) return;
  selectedFile = file;
  const prev = document.getElementById('uploadPreview');
  const cont = document.getElementById('previewContent');
  prev.style.display = 'flex';
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = e => { cont.innerHTML = `<img src="${e.target.result}" style="height:48px;width:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border)"/> <span class="text-small">${file.name}</span>`; };
    reader.readAsDataURL(file);
  } else {
    cont.innerHTML = `<span style="font-size:24px">📎</span> <span class="text-small">${file.name} (${(file.size/1024).toFixed(1)}KB)</span>`;
  }
}

function clearFile() {
  selectedFile = null;
  document.getElementById('chatFileInp').value = '';
  document.getElementById('uploadPreview').style.display = 'none';
  document.getElementById('previewContent').innerHTML = '';
}

async function sendMsg() {
  const inp = document.getElementById('chatInput');
  const msg = inp.value.trim();
  if (!msg && !selectedFile) return;

  const fd = new FormData();
  fd.append('shop_id', SHOP_ID);
  fd.append('sender_type', 'owner');
  if (msg) fd.append('message', msg);
  if (selectedFile) fd.append('chat_file', selectedFile);

  inp.value = '';
  clearFile();

  const r = await fetch('../php/chat_send.php', { method:'POST', body:fd });
  const data = await r.json();
  if (data.success) loadMessages(SHOP_ID, 'owner');
  else showAlert(data.msg || 'Send failed', 'danger');
}

// Override loadMessages to render files
const origRender = window._chatRenderMsg;
window._chatRenderMsg = function(m, role) {
  const mine = (role==='owner' && m.sender_type==='owner') || (role==='admin' && m.sender_type==='admin');
  const div = document.createElement('div');
  div.className = `msg-row ${mine?'owner':'admin'}`;
  let content = `<div class="msg-bubble">${escHtml(m.message)}`;
  if (m.file_path && m.file_type === 'image') {
    content += `<br/><img src="${m.file_url}" class="chat-file-preview" onclick="openImage('${m.file_url}')" alt="Photo"/>`;
  } else if (m.file_path) {
    content += `<br/><a href="${m.file_url}" target="_blank" class="chat-file-link">📎 ${escHtml(m.file_name||'File')}</a>`;
  }
  content += `</div><div class="msg-time">${mine?'You':(m.sender_type==='admin'?'Admin':'Owner')} · ${m.time}</div>`;
  div.innerHTML = `<div>${content}</div>`;
  return div;
};

function openImage(url) {
  document.getElementById('modalImg').src = url;
  document.getElementById('imageModal').style.display = 'flex';
}
</script>
</body>
</html>
