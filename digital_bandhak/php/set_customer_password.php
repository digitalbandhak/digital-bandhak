<?php
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$custId   = intval($_POST['customer_id'] ?? 0);
$bandhakId= strtoupper(trim($_POST['bandhak_id'] ?? ''));
$newPw    = trim($_POST['new_password'] ?? '');

if (!$custId || !$newPw || strlen($newPw) < 4) {
    header('Location: ../shop/pawn_view.php?error=password_short');
    exit;
}

// Ensure column exists
try {
    $cols = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('cust_password', $cols)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN cust_password VARCHAR(255) DEFAULT NULL");
    }
} catch(Exception $e) {}

// Set password (hashed)
$pdo->prepare("UPDATE customers SET cust_password=? WHERE id=? AND shop_id=?")
    ->execute([password_hash($newPw, PASSWORD_DEFAULT), $custId, $_SESSION['shop_id']]);

// Find pawn to redirect back
$pawnStmt = $pdo->prepare("SELECT id FROM pawn_entries WHERE customer_id=? AND shop_id=? ORDER BY id DESC LIMIT 1");
$pawnStmt->execute([$custId, $_SESSION['shop_id']]);
$pawn = $pawnStmt->fetch();

$pawnId = $pawn['id'] ?? 0;
header("Location: ../shop/pawn_view.php?id=$pawnId&pw_set=1&pw_plain=".urlencode($newPw));
exit;
