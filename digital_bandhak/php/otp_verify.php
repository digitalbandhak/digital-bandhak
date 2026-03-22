<?php
require_once '../includes/config.php';

$bandhakId = strtoupper(trim($_POST['bandhak_id'] ?? ''));
$otp       = trim($_POST['otp'] ?? '');

if (!$bandhakId) jsonResponse(['success'=>false,'msg'=>'Bandhak ID missing']);
if (strlen($otp) < 6) jsonResponse(['success'=>false,'msg'=>'6-digit OTP daalo']);

// Find valid OTP — flexible check (ignore mobile mismatch, just check bandhak_id + otp)
$stmt = $pdo->prepare("
    SELECT * FROM customer_otps
    WHERE bandhak_id=? AND otp=? AND is_used=0 AND expires_at > NOW()
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$bandhakId, $otp]);
$otpRow = $stmt->fetch();

if (!$otpRow) {
    // Debug info
    $debug = $pdo->prepare("SELECT otp, expires_at, is_used, created_at FROM customer_otps WHERE bandhak_id=? ORDER BY id DESC LIMIT 1");
    $debug->execute([$bandhakId]);
    $d = $debug->fetch();
    if (!$d) {
        jsonResponse(['success'=>false,'msg'=>'Pehle OTP request karo (Send OTP button dabao)']);
    } elseif ($d['is_used']) {
        jsonResponse(['success'=>false,'msg'=>'Yeh OTP pehle use ho chuka hai. Resend karo.']);
    } elseif ($d['otp'] !== $otp) {
        jsonResponse(['success'=>false,'msg'=>'OTP galat hai. DEV: correct OTP = '.$d['otp']]);
    } else {
        jsonResponse(['success'=>false,'msg'=>'OTP expire ho gaya (10 min). Resend karo.']);
    }
}

// Mark used
$pdo->prepare("UPDATE customer_otps SET is_used=1 WHERE id=?")->execute([$otpRow['id']]);

// Find customer — first try pawn_entries
$custStmt = $pdo->prepare("
    SELECT c.*, pe.shop_id
    FROM customers c
    JOIN pawn_entries pe ON pe.customer_id = c.id
    WHERE pe.bandhak_id = ? AND pe.status != 'deleted'
    LIMIT 1
");
$custStmt->execute([$bandhakId]);
$customer = $custStmt->fetch();

// Fallback: try customers table directly
if (!$customer) {
    $cDirect = $pdo->prepare("SELECT * FROM customers WHERE bandhak_id=? LIMIT 1");
    $cDirect->execute([$bandhakId]);
    $customer = $cDirect->fetch();
}

if (!$customer) {
    jsonResponse(['success'=>false,'msg'=>'Customer data nahi mila. Bandhak ID check karo ya shop owner se contact karo.']);
}

// Set session
$_SESSION['user_type']       = 'customer';
$_SESSION['user_id']         = $customer['id'];
$_SESSION['bandhak_id']      = $bandhakId;
$_SESSION['customer_name']   = $customer['full_name'];
$_SESSION['customer_shopid'] = $customer['shop_id'] ?? '';

jsonResponse(['success'=>true, 'redirect'=>'customer_dashboard.php']);
