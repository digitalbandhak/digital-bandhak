<?php
// includes/navbar.php — V10 FINAL
$unreadCount = $unreadCount ?? 0;
$userType    = $_SESSION['user_type'] ?? '';
$userName    = $_SESSION['user_name'] ?? '';
$shopName    = $_SESSION['shop_name'] ?? '';
$avatarText  = strtoupper(substr($userName, 0, 2)) ?: 'U';

$profileLink = '#';
$homeLink    = '../index.php';
if ($userType === 'super_admin') { $profileLink = 'profile.php'; $homeLink = 'dashboard.php'; }
elseif ($userType === 'owner')   { $profileLink = 'profile.php'; $homeLink = 'dashboard.php'; }
elseif ($userType === 'staff')   { $profileLink = 'staff_panel.php'; $homeLink = 'staff_panel.php'; }

$logoUrl  = getSiteLogo($pdo, defined('IS_ADMIN')||defined('IS_SHOP') ? '../' : '');
$siteName = getSiteName($pdo);
$logoutUrl = defined('IS_ADMIN')||defined('IS_SHOP') ? '../php/logout.php' : 'php/logout.php';

$pic     = $_SESSION['profile_pic'] ?? '';
$picBase = (defined('IS_ADMIN')||defined('IS_SHOP')) ? '../' : '';
$picPath = ($pic && file_exists(UPLOAD_PATH.$pic)) ? $picBase.'uploads/'.htmlspecialchars($pic) : null;
?>
<style>
.navbar{position:fixed;top:0;left:0;right:0;z-index:300;height:54px;background:var(--nav-bg);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 14px;gap:10px;box-shadow:var(--shadow);}
.nav-logo-wrap{display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--gold-dark);font-weight:700;font-size:15px;flex-shrink:0;}
[data-theme="dark"] .nav-logo-wrap{color:var(--gold);}
.nav-logo-wrap img{height:32px;width:32px;object-fit:contain;border-radius:7px;flex-shrink:0;}
.nav-spacer{flex:1;}
.nav-right{display:flex;align-items:center;gap:8px;}
.nav-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold);color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:pointer;flex-shrink:0;}
.nav-avatar img{width:100%;height:100%;object-fit:cover;}
.nav-uname{font-size:12px;font-weight:600;color:var(--text);max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.nav-badge{background:var(--danger);color:#fff;font-size:10px;font-weight:700;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;position:absolute;top:-3px;right:-3px;}
.ham-btn{display:none;width:36px;height:36px;align-items:center;justify-content:center;border:1px solid var(--border2);border-radius:8px;background:none;cursor:pointer;color:var(--text);font-size:17px;flex-shrink:0;}

/* Mobile: hide logout button, show in menu */
@media(max-width:768px){
  .ham-btn{display:flex !important;}
  .nav-logout-btn{display:none !important;} /* hide on mobile */
  .nav-uname{display:none;}
  .navbar{padding:0 10px;gap:6px;}
  .nav-logo-wrap span{font-size:14px;}
  .dashboard-layout{margin-top:54px;}
}
</style>

<nav class="navbar" id="mainNavbar">
  <!-- Hamburger — mobile only -->
  <button class="ham-btn" onclick="toggleSidebar()" aria-label="Menu">☰</button>

  <!-- Brand -->
  <a class="nav-logo-wrap" href="<?=$homeLink?>">
    <?php if ($logoUrl): ?>
      <img src="<?=htmlspecialchars($logoUrl)?>" alt="Logo"/>
    <?php else: ?>
      <span style="font-size:20px">🏛️</span>
    <?php endif; ?>
    <span><?=htmlspecialchars($siteName)?></span>
  </a>

  <div class="nav-spacer"></div>

  <div class="nav-right">
    <!-- Unread chat -->
    <?php if ($unreadCount > 0): ?>
    <a href="<?=defined('IS_ADMIN')?'chat.php':'chat.php'?>" style="position:relative;text-decoration:none;display:flex">
      <span style="font-size:18px">💬</span>
      <span class="nav-badge"><?=$unreadCount?></span>
    </a>
    <?php endif; ?>

    <!-- Theme toggle -->
    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Theme" style="width:32px;height:32px;font-size:15px">🌙</button>

    <!-- Profile avatar → click goes to profile -->
    <?php if ($userType && $userType !== 'customer'): ?>
    <a href="<?=$profileLink?>" style="display:flex;align-items:center;gap:6px;text-decoration:none">
      <div class="nav-avatar">
        <?php if ($picPath): ?><img src="<?=$picPath?>" alt=""/><?php else: echo $avatarText; endif; ?>
      </div>
      <span class="nav-uname"><?=htmlspecialchars($shopName?:$userName)?></span>
    </a>
    <?php endif; ?>

    <!-- Logout — hidden on mobile (in sidebar instead) -->
    <?php if ($userType): ?>
    <a href="<?=$logoutUrl?>" class="btn btn-outline btn-sm no-print nav-logout-btn" style="font-size:11px;padding:5px 12px">Logout</a>
    <?php endif; ?>
  </div>
</nav>

<!-- Sidebar overlay for mobile -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
