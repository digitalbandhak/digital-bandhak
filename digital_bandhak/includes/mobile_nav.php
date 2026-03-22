<?php
// includes/mobile_nav.php
$userType    = $_SESSION['user_type'] ?? '';
$currentFile = basename($_SERVER['PHP_SELF']);
$shopId      = $_SESSION['shop_id'] ?? '';

// Only show for shop owner, staff, super_admin (NOT customer)
if (!in_array($userType, ['owner','staff','super_admin'])) return;

if ($userType === 'super_admin') {
    $navItems = [
        ['icon'=>'📊','label'=>'Dashboard', 'href'=>'dashboard.php'],
        ['icon'=>'🏪','label'=>'Shops',     'href'=>'shop_add.php'],
        ['icon'=>'💬','label'=>'Chat',      'href'=>'chat.php', 'center'=>true],
        ['icon'=>'🔁','label'=>'Subs',      'href'=>'subscription_add.php'],
        ['icon'=>'👤','label'=>'Profile',   'href'=>'profile.php'],
    ];
    $base = '../';
} elseif ($userType === 'staff') {
    $navItems = [
        ['icon'=>'📊','label'=>'Dashboard', 'href'=>'staff_panel.php'],
        ['icon'=>'📋','label'=>'List',      'href'=>'staff_panel.php'],
        ['icon'=>'➕','label'=>'Add',       'href'=>'pawn_add.php', 'center'=>true],
        ['icon'=>'💬','label'=>'Chat',      'href'=>'../index.php'],
        ['icon'=>'🚪','label'=>'Logout',    'href'=>'../php/logout.php'],
    ];
    $base = '../';
} else {
    // owner
    $navItems = [
        ['icon'=>'📊','label'=>'Dashboard', 'href'=>'dashboard.php'],
        ['icon'=>'📋','label'=>'Bandhak',   'href'=>'pawn_list.php'],
        ['icon'=>'➕','label'=>'Add',       'href'=>'pawn_add.php', 'center'=>true],
        ['icon'=>'💰','label'=>'Payments',  'href'=>'payments.php'],
        ['icon'=>'👤','label'=>'Profile',   'href'=>'profile.php'],
    ];
    $base = '../';
}
?>
<style>
/* ========== MOBILE NAV ========== */
.mob-nav{
  display:none;
  position:fixed;
  bottom:0;left:0;right:0;
  height:60px;
  background:var(--nav-bg);
  border-top:1px solid var(--border);
  z-index:500;
  align-items:center;
  justify-content:space-around;
  box-shadow:0 -2px 16px rgba(0,0,0,0.12);
  padding:0 4px;
}
.mob-nav-item{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:2px;text-decoration:none;color:var(--text3);
  font-size:10px;font-weight:500;
  padding:6px 8px;border-radius:10px;
  transition:all .15s;min-width:44px;flex:1;
  -webkit-tap-highlight-color:transparent;
}
.mob-nav-item.active,.mob-nav-item:active{color:var(--gold);}
.mob-nav-icon{font-size:19px;line-height:1;}
.mob-nav-center{
  width:50px;height:50px;
  border-radius:50%;
  background:linear-gradient(135deg,#B8760A,#D4920F);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:22px;
  box-shadow:0 3px 14px rgba(184,118,10,.45);
  text-decoration:none;flex-shrink:0;
  transition:transform .15s;
  -webkit-tap-highlight-color:transparent;
  margin-bottom:6px;
}
.mob-nav-center:active{transform:scale(.92);}

/* NAVBAR — fix for mobile: compact */
@media(max-width:768px){
  .mob-nav{display:flex;}
  /* Body padding so content not hidden behind bottom nav */
  body{padding-bottom:64px;}
  /* Fix navbar on mobile — no overflow */
  .navbar{
    padding:0 12px;
    height:54px;
    position:fixed;
    top:0;left:0;right:0;
    z-index:300;
  }
  .navbar-brand{font-size:15px;margin-right:8px;}
  .navbar-brand span{font-size:15px;}
  /* Hide full nav-user name on mobile */
  .nav-user-name{display:none;}
  .nav-user{padding:4px 8px 4px 4px;}
  /* Dashboard layout adjust */
  .dashboard-layout{min-height:calc(100vh - 54px);margin-top:54px;}
  .sidebar{top:54px;height:calc(100vh - 54px);}
  .main-content{padding:14px 12px 16px;}
  /* Fix table overflow */
  .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
  /* Stat grid 2 col on mobile */
  .stat-grid{grid-template-columns:1fr 1fr!important;}
}
@media(max-width:420px){
  .stat-grid{grid-template-columns:1fr!important;}
  .form-grid,.form-grid-3{grid-template-columns:1fr!important;}
}
</style>

<nav class="mob-nav" id="mobNav">
  <?php foreach ($navItems as $item):
    $isActive = ($currentFile === $item['href'] || ($currentFile === 'dashboard.php' && $item['href']==='dashboard.php'));
  ?>
    <?php if (!empty($item['center'])): ?>
      <a href="<?=$item['href']?>" class="mob-nav-center" title="<?=$item['label']?>">
        <?=$item['icon']?>
      </a>
    <?php else: ?>
      <a href="<?=$item['href']?>" class="mob-nav-item <?=$isActive?'active':''?>">
        <span class="mob-nav-icon"><?=$item['icon']?></span>
        <span><?=$item['label']?></span>
      </a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>
