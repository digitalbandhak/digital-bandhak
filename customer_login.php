<?php
// customer_login.php — Password-based customer login
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$bandhakId = strtoupper(trim($_POST['bandhak_id'] ?? ''));
$custPw    = trim($_POST['cust_password'] ?? '');

if (!$bandhakId || !$custPw) {
    $_SESSION['cust_error'] = 'Bandhak ID aur password zaroori hain';
    header('Location: index.php'); exit;
}

// Find customer — check pawn_entries first, then customers table
$stmt = $pdo->prepare("
    SELECT c.*
    FROM customers c
    JOIN pawn_entries pe ON pe.customer_id = c.id
    WHERE pe.bandhak_id = ? AND pe.status != 'deleted'
    LIMIT 1
");
$stmt->execute([$bandhakId]);
$customer = $stmt->fetch();

if (!$customer) {
    // Try customers table directly (bandhak_id stored there too)
    $s2 = $pdo->prepare("SELECT * FROM customers WHERE bandhak_id=? LIMIT 1");
    $s2->execute([$bandhakId]);
    $customer = $s2->fetch();
}

if (!$customer) {
    $_SESSION['cust_error'] = 'Bandhak ID nahi mila. Shop owner se confirm karo.';
    header('Location: index.php'); exit;
}

// Check password — customers have cust_password column (check safely)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('cust_password', $cols)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN cust_password VARCHAR(255) DEFAULT NULL");
    }
} catch(Exception $e) {}

// Reload customer with password
$stmt2 = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$stmt2->execute([$customer['id']]);
$customer = $stmt2->fetch();

if (empty($customer['cust_password'])) {
    // No password set yet — first time, set this as password
    $pdo->prepare("UPDATE customers SET cust_password=? WHERE id=?")
        ->execute([password_hash($custPw, PASSWORD_DEFAULT), $customer['id']]);
    // Allow login
} else {
    // Verify password
    if (!password_verify($custPw, $customer['cust_password'])) {
        // Also check plain text (in case owner gave plain text password)
        if ($custPw !== $customer['cust_password']) {
            // try if it's stored as plain text hash of something
            $_SESSION['cust_error'] = 'Password galat hai. Shop owner se password lo.';
            header('Location: index.php'); exit;
        }
        // Plain text match — upgrade to hashed
        $pdo->prepare("UPDATE customers SET cust_password=? WHERE id=?")
            ->execute([password_hash($custPw, PASSWORD_DEFAULT), $customer['id']]);
    }
}

// Success
$_SESSION['user_type']       = 'customer';
$_SESSION['user_id']         = $customer['id'];
$_SESSION['bandhak_id']      = $bandhakId;
$_SESSION['customer_name']   = $customer['full_name'];
$_SESSION['customer_shopid'] = $customer['shop_id'] ?? '';

header('Location: customer_dashboard.php');
exit;
