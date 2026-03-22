<?php
require_once '../includes/config.php';
if (!isLoggedIn()) jsonResponse(['success'=>false,'msg'=>'Not logged in']);

$shopId = trim($_POST['shop_id'] ?? '');
$lastId = intval($_POST['last_id'] ?? 0);

if (!$shopId) jsonResponse(['messages'=>[]]);

// Mark as read
if ($_SESSION['user_type']==='owner') {
    $pdo->prepare("UPDATE admin_chat_messages SET is_read=1 WHERE shop_id=? AND sender_type='admin' AND is_read=0")->execute([$shopId]);
}
if ($_SESSION['user_type']==='super_admin') {
    $pdo->prepare("UPDATE admin_chat_messages SET is_read=1 WHERE shop_id=? AND sender_type='owner' AND is_read=0")->execute([$shopId]);
}

$stmt = $pdo->prepare("SELECT id, sender_type, message, file_path, file_type, file_name, DATE_FORMAT(created_at,'%d %b %H:%i') as time FROM admin_chat_messages WHERE shop_id=? AND id>? ORDER BY created_at ASC");
$stmt->execute([$shopId, $lastId]);
$msgs = $stmt->fetchAll();

// Build correct file URL using SITE_URL constant from config
foreach ($msgs as &$m) {
    if (!empty($m['file_path'])) {
        $m['file_url'] = SITE_URL . '/uploads/' . $m['file_path'];
    }
}
unset($m);

jsonResponse(['messages'=>$msgs]);
