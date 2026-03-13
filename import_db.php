<?php
// ⚠️ DELETE THIS FILE AFTER USE - SECURITY RISK
$host = getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$port = (int)(getenv('MYSQLPORT') ?: 3306);
$dbname = getenv('MYSQL_DATABASE') ?: 'railway';

echo "<h2>🏦 Digital Bandhak - Database Setup</h2>";
echo "<p>Connecting: <b>$host:$port</b> user:<b>$user</b> db:<b>$dbname</b></p>";

$conn = new mysqli($host, $user, $pass, '', $port);
if ($conn->connect_error) {
    die("<p style='color:red'>❌ Failed: " . $conn->connect_error . "</p>");
}
echo "<p>✅ Connected!</p>";

$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);
echo "<p>✅ Database: <b>$dbname</b></p>";

$sql = file_get_contents(__DIR__ . '/sql/fresh_install.sql');
$lines = explode("\n", $sql);
$clean = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '--') === 0) continue;
    $clean[] = $line;
}
$statements = array_filter(array_map('trim', explode(';', implode("\n", $clean))));

$success = 0; $errors = [];
foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    if ($conn->query($stmt)) { $success++; }
    else { $errors[] = $conn->error . " → " . substr($stmt, 0, 60); }
}
$conn->close();

echo "<p>✅ <b>$success statements done!</b></p>";
if (!empty($errors)) {
    echo "<ul>";
    foreach (array_slice($errors, 0, 5) as $e) echo "<li style='color:orange;font-size:12px'>$e</li>";
    echo "</ul>";
}
echo "<hr><h3>🎉 Done!</h3>";
echo "<p><a href='/setup_admin.php' style='background:#e67e22;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-size:18px;'>👉 Admin Password Set Karo</a></p>";
echo "<br><p style='color:red'><b>⚠️ Baad mein import_db.php aur setup_admin.php GitHub se delete karna!</b></p>";
