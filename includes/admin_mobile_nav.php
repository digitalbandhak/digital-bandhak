<?php
// includes/admin_mobile_nav.php
// Mobile bottom nav for Super Admin
$userType = $_SESSION['user_type'] ?? '';
if ($userType !== 'super_admin') return;
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<style>
.admin-mobile-nav {
  display:none;
  position:fixed; bottom:0; left:0; right:0;
  height:62px;
  background:var(--nav-bg);
  border-top:1px solid var(--border);
  z-index:300;
  justify-content:space-around;
  align-items:center;
  box-shadow:0 -4px 20px rgba(0,0,0,0.1);
}
.admin-mob-item {
  display:flex; flex-direction:column; align-items:center; gap:2px;
  text-decoration:none; color:var(--text3);
  font-size:10px; font-weight:500;
  padding:6px 10px; border-radius:10px;
  transition:all .15s; min-width:50px;
}
.admin-mob-item.active,.admin-mob-item:hover { color:var(--gold); }
.admin-mob-item .mob-icon { font-size:20px; line-height:1; }
@media(max-width:768px) {
  .admin-mobile-nav { display:flex; }
  .main-content { padding-bottom:74px; }
}
</style>
<nav class="admin-mobile-nav">
  <a href="dashboard.php"      class="admin-mob-item <?=$currentFile==='dashboard.php'?'active':''?>"><span class="mob-icon">📊</span>Dashboard</a>
  <a href="shop_add.php"       class="admin-mob-item <?=$currentFile==='shop_add.php'?'active':''?>"><span class="mob-icon">🏪</span>Shops</a>
  <a href="subscription_add.php" class="admin-mob-item <?=$currentFile==='subscription_add.php'?'active':''?>"><span class="mob-icon">🔁</span>Subs</a>
  <a href="chat.php"           class="admin-mob-item <?=$currentFile==='chat.php'?'active':''?>"><span class="mob-icon">💬</span>Chat</a>
  <a href="profile.php"        class="admin-mob-item <?=$currentFile==='profile.php'?'active':''?>"><span class="mob-icon">👤</span>Profile</a>
</nav>
