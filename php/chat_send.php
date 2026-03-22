<?php
require_once '../includes/config.php';
if (!isLoggedIn()) jsonResponse(['success'=>false,'msg'=>'Not logged in']);

$shopId      = trim($_POST['shop_id'] ?? '');
$message     = trim($_POST['message'] ?? '');
$senderTypeP = $_POST['sender_type'] ?? '';
$userType    = $_SESSION['user_type'] ?? '';

if (!$shopId) jsonResponse(['success'=>false,'msg'=>'Shop ID missing']);

// Determine sender type
$senderType = ($userType==='super_admin') ? 'admin' : 'owner';
if ($senderTypeP && in_array($senderTypeP,['admin','owner'])) $senderType=$senderTypeP;

// Auth check
if ($senderType==='owner' && !empty($_SESSION['shop_id']) && $_SESSION['shop_id']!==$shopId)
    jsonResponse(['success'=>false,'msg'=>'Unauthorized']);
if ($senderType==='admin' && $userType!=='super_admin')
    jsonResponse(['success'=>false,'msg'=>'Unauthorized']);

$filePath = null; $fileType = null; $fileName = null;

// Handle file upload
if (!empty($_FILES['chat_file']['name']) && $_FILES['chat_file']['error']===UPLOAD_ERR_OK) {
    $f   = $_FILES['chat_file'];
    $ext = strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xlsx','txt'];
    $maxSize = 5*1024*1024; // 5MB

    if (!in_array($ext,$allowed)) { jsonResponse(['success'=>false,'msg'=>'File type not allowed']); }
    if ($f['size']>$maxSize)      { jsonResponse(['success'=>false,'msg'=>'File too large (max 5MB)']); }

    $chatDir = UPLOAD_PATH . 'chat/';
    if (!is_dir($chatDir)) mkdir($chatDir,0755,true);

    $fname = 'chat_'.time().'_'.mt_rand(1000,9999).'.'.$ext;
    if (move_uploaded_file($f['tmp_name'],$chatDir.$fname)) {
        $filePath = 'chat/'.$fname;
        $fileType = in_array($ext,['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
        $fileName = $f['name'];
        if (!$message) $message = $fileType==='image' ? '📷 Photo' : '📎 '.$fileName;
    }
}

if (!$message && !$filePath) jsonResponse(['success'=>false,'msg'=>'Message ya file daalo']);

$stmt = $pdo->prepare("INSERT INTO admin_chat_messages (shop_id, sender_type, sender_id, message, file_path, file_type, file_name) VALUES (?,?,?,?,?,?,?)");
$stmt->execute([$shopId, $senderType, $_SESSION['user_id'], $message, $filePath, $fileType, $fileName]);

jsonResponse(['success'=>true, 'id'=>$pdo->lastInsertId()]);
