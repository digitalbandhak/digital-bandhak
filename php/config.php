<?php
// ══════════════════════════════════════════════════════════════
// DIGITAL BANDHAK - config.php
// Railway.app pe auto env variables se DB connect hota hai
// ══════════════════════════════════════════════════════════════

// Railway MySQL env variables auto-detect
$_db_host = getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'localhost';
$_db_user = getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root';
$_db_pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
$_db_name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'digitalbandhak';
$_db_port = getenv('MYSQLPORT')     ?: '3306';

define('DB_HOST', $_db_host);
define('DB_USER', $_db_user);
define('DB_PASS', $_db_pass);
define('DB_NAME', $_db_name);
define('DB_PORT', $_db_port);

// Admin credentials - Railway env se ya setup_admin.php se set karein
define('ADMIN_NAME',   getenv('ADMIN_NAME')   ?: 'Digital Bandhak');
define('ADMIN_EMAIL',  getenv('ADMIN_EMAIL')  ?: 'digitalbandhak@gmail.com');
define('ADMIN_MOBILE', getenv('ADMIN_MOBILE') ?: '9900000001');
define('ADMIN_PASS',   getenv('ADMIN_PASS')   ?: '');

$pdo = null;
try {
    // First connect WITHOUT dbname to create DB if needed
    $pdoTemp = new PDO(
        "mysql:host=".DB_HOST.";port=".DB_PORT.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdoTemp = null;
    
    // Now connect with dbname
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    // Auto-migrate: add new columns if they don't exist
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE pawns ADD COLUMN IF NOT EXISTS photos TEXT DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE pawns ADD COLUMN IF NOT EXISTS item_photos TEXT DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE shops ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS setting_key VARCHAR(100)"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS sender_name VARCHAR(200) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS sender_role VARCHAR(20) DEFAULT NULL"); } catch(Exception $e) {}
} catch(PDOException $e) {
    // Will use fallback mode if DB not available
}

session_start();

function fmt_inr($n) {
    return '₹' . number_format($n, 0, '.', ',');
}

function next_pawn_id($pdo, $shop_id) {
    $year = date('Y');
    if (!$pdo) return 'BDK-'.$year.'-'.str_pad(rand(40,99), 3, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM pawns WHERE shop_id=? AND YEAR(created_at)=?");
    $stmt->execute([$shop_id, $year]);
    $row = $stmt->fetch();
    $num = $row['c'] + 1;
    return 'BDK-'.$year.'-'.str_pad($num, 3, '0', STR_PAD_LEFT);
}

function log_audit($pdo, $shop_id, $user_name, $role, $action, $target) {
    if (!$pdo) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (shop_id,user_name,user_role,action,target,ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$shop_id, $user_name, $role, $action, $target, $ip]);
}

function add_notification($pdo, $shop_id, $icon, $title, $body, $type='info') {
    if (!$pdo) return;
    $stmt = $pdo->prepare("INSERT INTO notifications (shop_id,icon,title,body,type) VALUES (?,?,?,?,?)");
    $stmt->execute([$shop_id, $icon, $title, $body, $type]);
}

// ── DEMO PAWNS (DB nahi ho tab use hoga) ─────────────────────
$DEMO_PAWNS = [
  ['id'=>'BDK-2025-001','customer'=>'Amit Kumar','mobile'=>'9876543210','item'=>'Gold Chain 20g','loan'=>28000,'paid'=>8000,'remaining'=>22400,'date'=>'2025-01-10','status'=>'active','interest'=>2,'payments'=>[['date'=>'2025-01-10','amount'=>5000,'mode'=>'Cash','note'=>'Pehli payment'],['date'=>'2025-01-28','amount'=>3000,'mode'=>'UPI','note'=>'Second installment']]],
  ['id'=>'BDK-2025-002','customer'=>'Priya Singh','mobile'=>'9712345670','item'=>'Silver Anklet Set','loan'=>8500,'paid'=>8500,'remaining'=>0,'date'=>'2025-01-22','status'=>'closed','interest'=>1.5,'payments'=>[['date'=>'2025-01-22','amount'=>4000,'mode'=>'Cash','note'=>'Advance'],['date'=>'2025-02-15','amount'=>4500,'mode'=>'Cash','note'=>'Final payment']]],
  ['id'=>'BDK-2025-003','customer'=>'Rajesh Verma','mobile'=>'9823456789','item'=>'Gold Ring 8g','loan'=>12000,'paid'=>4000,'remaining'=>9200,'date'=>'2025-02-05','status'=>'active','interest'=>2,'payments'=>[['date'=>'2025-02-05','amount'=>2000,'mode'=>'Cash','note'=>''],['date'=>'2025-02-25','amount'=>2000,'mode'=>'UPI','note'=>'Online transfer']]],
  ['id'=>'BDK-2025-004','customer'=>'Sunita Devi','mobile'=>'9900112233','item'=>'TV 43 inch','loan'=>15000,'paid'=>5000,'remaining'=>11500,'date'=>'2025-02-18','status'=>'active','interest'=>2.5,'payments'=>[['date'=>'2025-02-18','amount'=>5000,'mode'=>'Cash','note'=>'First installment']]],
];

$DEMO_SHOPS = [
  ['id'=>'SH001','name'=>'Sharma Bandhak Ghar','owner'=>'Ramesh Sharma','city'=>'Patna','status'=>'active','sub'=>'Standard','expiry'=>'2025-08-30','balance'=>'₹4,200'],
  ['id'=>'SH002','name'=>'Soni Jewellers','owner'=>'Suresh Soni','city'=>'Gaya','status'=>'active','sub'=>'Trial','expiry'=>'2025-03-15','balance'=>'₹0'],
  ['id'=>'SH003','name'=>'Gupta Bandhak Seva','owner'=>'Dinesh Gupta','city'=>'Muzaffarpur','status'=>'inactive','sub'=>'Expired','expiry'=>'2024-12-01','balance'=>'₹1,800'],
];

// Auto-create customer_accounts table (only if DB connected)
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_accounts (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(200) NOT NULL,
          mobile VARCHAR(15) NOT NULL,
          address TEXT,
          aadhaar VARCHAR(20),
          shop_id VARCHAR(10),
          status ENUM('pending','active','blocked') DEFAULT 'pending',
          registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          activated_at TIMESTAMP NULL,
          activated_by VARCHAR(200),
          UNIQUE KEY uniq_mobile (mobile)
        )");
    } catch(Exception $e) { /* table already exists or DB issue */ }
}

?>