<?php
// run_once/update_admin.php
// ⚠ RUN ONCE then click DELETE button below!
require_once '../includes/config.php';
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
body{font-family:sans-serif;max-width:700px;margin:40px auto;padding:20px;background:#1A0E05;color:#F0E6D0}
h2{color:#F0C060} .ok{color:#5A9;margin:4px 0} .warn{color:#F0C060;margin:4px 0} .err{color:#E55;margin:4px 0}
code{background:#2A1F10;padding:2px 8px;border-radius:4px;font-size:12px}
.btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:700}
.btn-del{background:#8B1E1E;color:#fff} .btn-go{background:#B8760A;color:#fff}
hr{border:1px solid #2A1F10;margin:16px 0}
</style></head><body>";

echo "<h2>🔧 Digital Bandhak — V5 Setup Script</h2>";

// Helper: safe column add (check if exists first)
function safeAddColumn($pdo, $table, $column, $definition) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($column, $cols)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "<p class='ok'>✔ Added column: $table.$column</p>";
        } else {
            echo "<p class='warn'>⚠ Already exists: $table.$column</p>";
        }
    } catch(Exception $e) {
        echo "<p class='err'>✖ Error on $table.$column: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// 1. Admin credentials
$newEmail = 'digitalbandhak@gmail.com';
$newPass  = 'Digitalbandhak@2026#';
$newUser  = 'superadmin';
$hash     = password_hash($newPass, PASSWORD_DEFAULT);

safeAddColumn($pdo, 'super_admin', 'profile_pic', 'VARCHAR(255) DEFAULT NULL');

$count = $pdo->query("SELECT COUNT(*) FROM super_admin")->fetchColumn();
if ($count > 0) {
    $pdo->prepare("UPDATE super_admin SET email=?,username=?,password=? WHERE id=1")->execute([$newEmail,$newUser,$hash]);
    echo "<p class='ok'>✔ Admin credentials updated!</p>";
} else {
    $pdo->prepare("INSERT INTO super_admin (username,email,password) VALUES (?,?,?)")->execute([$newUser,$newEmail,$hash]);
    echo "<p class='ok'>✔ Admin account created!</p>";
}

// 2. site_settings
$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (`key` VARCHAR(100) PRIMARY KEY, `value` TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$pdo->prepare("INSERT IGNORE INTO site_settings (`key`,`value`) VALUES ('site_name','Digital Bandhak'),('site_tagline','Aapki Girvee, Hamaari Zimmedaari'),('whatsapp_number',''),('support_email','digitalbandhak@gmail.com')")->execute();
echo "<p class='ok'>✔ site_settings table ready</p>";

// 3. uploads folders
foreach(['uploads/','uploads/chat/','uploads/products/','uploads/receipts/'] as $dir) {
    $path = __DIR__.'/../'.$dir;
    if (!is_dir($path)) { mkdir($path,0755,true); echo "<p class='ok'>✔ Created: $dir</p>"; }
    else echo "<p class='warn'>⚠ Already exists: $dir</p>";
}

// 4. shops table columns
safeAddColumn($pdo,'shops','gst_number','VARCHAR(20) DEFAULT NULL');
safeAddColumn($pdo,'shops','license_number','VARCHAR(50) DEFAULT NULL');
safeAddColumn($pdo,'shops','terms_accepted','TINYINT(1) DEFAULT 0');
safeAddColumn($pdo,'shops','blocked','TINYINT(1) DEFAULT 0');
safeAddColumn($pdo,'shops','username','VARCHAR(50) DEFAULT NULL');
safeAddColumn($pdo,'shops','pincode','VARCHAR(10) DEFAULT NULL');
safeAddColumn($pdo,'shops','district','VARCHAR(100) DEFAULT NULL');

// 5. chat file columns
safeAddColumn($pdo,'admin_chat_messages','file_path','VARCHAR(255) DEFAULT NULL');
safeAddColumn($pdo,'admin_chat_messages','file_type','VARCHAR(50) DEFAULT NULL');
safeAddColumn($pdo,'admin_chat_messages','file_name','VARCHAR(200) DEFAULT NULL');

// 6. pawn_entries columns
safeAddColumn($pdo,'pawn_entries','duration_unit','VARCHAR(10) DEFAULT \'months\'');
safeAddColumn($pdo,'pawn_entries','payment_mode_entry','VARCHAR(30) DEFAULT \'Cash\'');

// 7. customers columns (address parts)
safeAddColumn($pdo,'customers','district','VARCHAR(100) DEFAULT NULL');
safeAddColumn($pdo,'customers','city','VARCHAR(100) DEFAULT NULL');
safeAddColumn($pdo,'customers','state','VARCHAR(100) DEFAULT NULL');
safeAddColumn($pdo,'customers','pincode','VARCHAR(10) DEFAULT NULL');
safeAddColumn($pdo,'customers','father_spouse','VARCHAR(150) DEFAULT NULL');

// 8. pawn_entries counter table for short IDs
$pdo->exec("CREATE TABLE IF NOT EXISTS bandhak_counter (
    shop_id VARCHAR(30) NOT NULL,
    year_code VARCHAR(4) NOT NULL,
    last_num INT DEFAULT 0,
    PRIMARY KEY (shop_id, year_code)
)");
echo "<p class='ok'>✔ bandhak_counter table created</p>";

// 9. email_otps table for email OTP
$pdo->exec("CREATE TABLE IF NOT EXISTS email_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    otp VARCHAR(10) NOT NULL,
    purpose VARCHAR(50) DEFAULT 'login',
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "<p class='ok'>✔ email_otps table created</p>";

echo "<hr/><h2>✅ Setup Complete!</h2>";
echo "<p><strong>Admin Login:</strong></p>";
echo "<p>Email: <code>$newEmail</code></p>";
echo "<p>Password: <code>$newPass</code></p>";
echo "<hr/>";
echo "<p class='warn'>⚠ DELETE this file immediately!</p>";
echo "<form method='POST' style='display:inline-block;margin-right:10px'><button class='btn btn-del' name='self_delete'>🗑 Delete This File Now</button></form>";
echo "<a href='../admin/dashboard.php' class='btn btn-go'>→ Admin Dashboard</a>";

if (isset($_POST['self_delete'])) {
    @unlink(__FILE__);
    echo "<p class='ok' style='margin-top:10px'>✔ File deleted! <a href='../admin/dashboard.php' style='color:#B8760A'>Go to Dashboard →</a></p>";
}
echo "</body></html>";
// This line won't work at end of file, so let's check it's handled in pawn_add.php


// Create subscription_requests table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shop_id VARCHAR(30) NOT NULL,
        plan_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
        message TEXT,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p class='ok'>✔ subscription_requests table created</p>";
} catch(Exception $e) { echo "<p class='warn'>⚠ subscription_requests: ".$e->getMessage()."</p>"; }
