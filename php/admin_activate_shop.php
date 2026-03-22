<?php
// php/admin_activate_shop.php
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$shopId = trim($_POST['shop_id'] ?? '');
$action = $_POST['action'] ?? '';

if (!$shopId || !in_array($action, ['activate','reject'])) {
    header('Location: ../admin/chat.php');
    exit;
}

if ($action === 'activate') {
    // Activate shop
    $pdo->prepare("UPDATE shops SET status='active' WHERE shop_id=?")->execute([$shopId]);

    // Add free trial subscription (30 days)
    $end = date('Y-m-d', strtotime('+30 days'));
    $pdo->prepare("INSERT INTO subscriptions (shop_id, plan_type, start_date, end_date, amount, payment_mode, status) VALUES (?,?,CURDATE(),?,'0','free','active')")
        ->execute([$shopId, 'trial', $end]);

    // Send notification via chat
    $pdo->prepare("INSERT INTO admin_chat_messages (shop_id, sender_type, sender_id, message) VALUES (?,?,?,?)")
        ->execute([$shopId, 'admin', $_SESSION['user_id'],
            '✅ Aapki shop activate ho gayi hai! Ab aap login kar sakte hain. Free trial: 30 din. Login ID: ' . $shopId]);

    auditLog($pdo, $shopId, 'shop_activated', "Shop activated by admin", 'super_admin', $_SESSION['user_id'], $_SESSION['user_name'], $shopId);

} elseif ($action === 'reject') {
    $pdo->prepare("UPDATE shops SET status='suspended' WHERE shop_id=?")->execute([$shopId]);
    auditLog($pdo, $shopId, 'shop_rejected', "Shop rejected by admin", 'super_admin', $_SESSION['user_id'], $_SESSION['user_name'], $shopId);
}

header('Location: ../admin/chat.php');
exit;
