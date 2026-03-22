<?php
// ============================================
// DIGITAL BANDHAK - Config & DB Connection
// includes/config.php
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // apna MySQL username
define('DB_PASS', '');            // apna MySQL password
define('DB_NAME', 'digital_bandhak');

define('SITE_NAME', 'Digital Bandhak');
// ============================================
// SMTP CONFIG — Gmail App Password se set karo
// Gmail: myaccount.google.com → Security → 2FA → App Passwords
// ============================================
if (!defined('SMTP_HOST'))   define('SMTP_HOST',   'smtp.gmail.com');
if (!defined('SMTP_USER'))   define('SMTP_USER',   'digitalbandhak@gmail.com'); // Your Gmail
if (!defined('SMTP_PASS'))   define('SMTP_PASS',   '');  // ← PASTE APP PASSWORD HERE
if (!defined('SMTP_PORT'))   define('SMTP_PORT',   587);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');
// ============================================

// Check if shop subscription is active
function checkSubscription($pdo, $shopId) {
    $stmt = $pdo->prepare("SELECT id, plan_type, end_date FROM subscriptions WHERE shop_id=? AND status='active' AND end_date>=CURDATE() ORDER BY end_date DESC LIMIT 1");
    $stmt->execute([$shopId]);
    return $stmt->fetch(); // false if expired/none
}

// Enforce subscription — call at top of shop pages
function requireSubscription($pdo, $shopId, $currentFile='') {
    $sub = checkSubscription($pdo, $shopId);
    if (!$sub) {
        // Check if request already pending
        $reqStmt = $pdo->prepare("SELECT id FROM subscription_requests WHERE shop_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
        try { $reqStmt->execute([$shopId]); $pending=$reqStmt->fetch(); } catch(Exception $e){ $pending=false; }
        $pendingReq = $pending ? true : false;
        // Output subscription expired popup and stop
        $_SESSION['sub_expired_shopid'] = $shopId;
        // Allow: logout, subscription page itself
        $allowedFiles = ['subscription.php','php/logout.php'];
        foreach ($allowedFiles as $f) { if (strpos($currentFile,$f)!==false) return true; }
        return false; // Subscription expired
    }
    return true;
}
if (!defined('SITE_URL')) {
    $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Get the base folder (e.g. /digital-bandhak)
    $script  = $_SERVER['SCRIPT_NAME'] ?? '';
    $parts   = explode('/', trim($script, '/'));
    // The first part after domain is the app folder
    $appFolder = '';
    if (count($parts) > 0) {
        // Walk up to find index.php or similar root
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $appPath = dirname(dirname(dirname(__FILE__))); // go up from includes/
        if ($docRoot && strpos($appPath, $docRoot) === 0) {
            $appFolder = str_replace('\\', '/', substr($appPath, strlen($docRoot)));
        }
    }
    define('SITE_URL', rtrim($proto.'://'.$host.$appFolder, '/'));
}
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('RECEIPT_PATH', __DIR__ . '/../uploads/receipts/');
define('PRODUCT_PATH', __DIR__ . '/../uploads/products/');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB Connection (PDO)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'msg' => 'DB Error: ' . $e->getMessage()]));
}

// ============================================
// HELPER FUNCTIONS
// ============================================

// Short Shop ID: SH0001, SH0002...
function generateShopId($pdo = null) {
    if ($pdo) {
        // Get next number
        $max = $pdo->query("SELECT MAX(CAST(SUBSTRING(shop_id, 3) AS UNSIGNED)) FROM shops WHERE shop_id REGEXP '^SH[0-9]+'")
                   ->fetchColumn();
        $next = ($max ? $max + 1 : 1);
        return 'SH' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
    return 'SH' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateBandhakId($shopId) {
    return 'DBK-' . date('Y') . '-' . mt_rand(1, 9999);
}

function generateReceiptNumber($shopId) {
    return 'RC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function maskAadhaar($aadhaar) {
    $clean = preg_replace('/\D/', '', $aadhaar);
    if (strlen($clean) < 4) return 'XXXX XXXX XXXX';
    return 'XXXX XXXX ' . substr($clean, -4);
}

function auditLog($pdo, $shopId, $actionType, $desc, $byType, $byId, $byName, $ref = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (shop_id, action_type, action_description, performed_by_type, performed_by_id, performed_by_name, ip_address, reference_id) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$shopId, $actionType, $desc, $byType, $byId, $byName, $ip, $ref]);
}

function isLoggedIn($role = null) {
    if (!isset($_SESSION['user_type'])) return false;
    if ($role && $_SESSION['user_type'] !== $role) return false;
    return true;
}

function requireLogin($role, $redirect = 'index.php') {
    if (!isLoggedIn($role)) {
        header("Location: $redirect");
        exit;
    }
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function calcRemaining($loanAmount, $interestRate, $monthsElapsed, $totalPaid) {
    $interest = $loanAmount * ($interestRate / 100) * $monthsElapsed;
    $totalDue  = $loanAmount + $interest;
    return max(0, round($totalDue - $totalPaid, 2));
}

// ---- SITE SETTINGS (logo, name etc) ----
function getSiteSettings($pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $rows = $pdo->query("SELECT `key`,`value` FROM site_settings")->fetchAll();
        $cache = [];
        foreach ($rows as $r) $cache[$r['key']] = $r['value'];
    } catch (Exception $e) { $cache = []; }
    return $cache;
}

function getSiteLogo($pdo, $relPath = '') {
    $s = getSiteSettings($pdo);
    $logo = $s['site_logo'] ?? '';
    if ($logo && file_exists(UPLOAD_PATH . $logo)) {
        return $relPath . 'uploads/' . $logo;
    }
    return null; // use emoji fallback
}

function getSiteName($pdo) {
    $s = getSiteSettings($pdo);
    return $s['site_name'] ?? SITE_NAME;
}

function getSiteTagline($pdo) {
    $s = getSiteSettings($pdo);
    return $s['site_tagline'] ?? 'Aapki Girvee, Hamaari Zimmedaari';
}

// Sequential Bandhak ID: DBK-2025-1, DBK-2025-2... resets each year
function generateUniqueBandhakId($pdo, $shopId) {
    $year = date('Y');
    // Try to use counter table
    try {
        $pdo->beginTransaction();
        $row = $pdo->prepare("SELECT last_num FROM bandhak_counter WHERE shop_id=? AND year_code=? FOR UPDATE");
        $row->execute([$shopId, $year]);
        $current = $row->fetchColumn();
        if ($current === false) {
            // First entry this year for this shop — find max existing
            $maxStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(bandhak_id, '-', -1) AS UNSIGNED)) FROM pawn_entries WHERE shop_id=? AND bandhak_id LIKE ?");
            $maxStmt->execute([$shopId, 'DBK-'.$year.'-%']);
            $maxNum  = $maxStmt->fetchColumn() ?: 0;
            $nextNum = $maxNum + 1;
            $pdo->prepare("INSERT INTO bandhak_counter (shop_id, year_code, last_num) VALUES (?,?,?)")->execute([$shopId,$year,$nextNum]);
        } else {
            $nextNum = $current + 1;
            $pdo->prepare("UPDATE bandhak_counter SET last_num=? WHERE shop_id=? AND year_code=?")->execute([$nextNum,$shopId,$year]);
        }
        $pdo->commit();
        return 'DBK-' . $year . '-' . $nextNum;
    } catch(Exception $e) {
        try { $pdo->rollBack(); } catch(Exception $re) {}
        // Fallback: find max
        $maxStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(bandhak_id, '-', -1) AS UNSIGNED)) FROM pawn_entries WHERE shop_id=? AND bandhak_id LIKE ?");
        $maxStmt->execute([$shopId, 'DBK-'.$year.'-%']);
        $maxNum = $maxStmt->fetchColumn() ?: 0;
        return 'DBK-' . $year . '-' . ($maxNum + 1);
    }
}

// ---- NOTIFY ADMIN: new shop registered ----
function notifyAdminNewShop($pdo, $shopId, $shopName, $ownerName) {
    try {
        // Insert a system chat message to admin thread
        $pdo->prepare("INSERT INTO admin_chat_messages (shop_id, sender_type, sender_id, message) VALUES (?,?,?,?)")
            ->execute([$shopId, 'owner', 0, "🆕 New shop registered: $shopName ($shopId) by $ownerName — waiting for activation."]);
    } catch (Exception $e) {}
}
