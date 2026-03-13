<?php
// ⚠️ DELETE THIS FILE AFTER USE - SECURITY RISK
// Digital Bandhak - Database Importer

$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$port = getenv('MYSQLPORT') ?: 3306;

echo "<h2>🏦 Digital Bandhak - Database Setup</h2>";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p>✅ Database connected!</p>";

    $sql = file_get_contents(__DIR__ . '/sql/fresh_install.sql');

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $success = 0;
    $errors = [];

    foreach ($statements as $stmt) {
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }

    echo "<p>✅ <strong>$success statements executed!</strong></p>";

    if (!empty($errors)) {
        echo "<p>⚠️ Some warnings (usually safe to ignore):</p><ul>";
        foreach (array_slice($errors, 0, 5) as $err) {
            echo "<li style='color:orange'>$err</li>";
        }
        echo "</ul>";
    }

    echo "<hr>";
    echo "<h3>🎉 Database setup complete!</h3>";
    echo "<p><a href='/setup_admin.php'>👉 Click here to set Admin Password</a></p>";
    echo "<p style='color:red'><strong>⚠️ Delete this file (import_db.php) from GitHub after use!</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Connection failed: " . $e->getMessage() . "</p>";
}
