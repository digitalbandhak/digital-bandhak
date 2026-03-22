<?php
// php/subscription_request.php
require_once '../includes/config.php';
requireLogin('owner', '../index.php');

$shopId   = $_SESSION['shop_id'];
$action   = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'send') {
    $plan    = trim($_POST['plan'] ?? 'monthly');
    $message = trim($_POST['message'] ?? '');

    // Ensure table exists
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
    } catch(Exception $e) {}

    // Cancel old pending requests
    $pdo->prepare("UPDATE subscription_requests SET status='rejected' WHERE shop_id=? AND status='pending'")->execute([$shopId]);

    // Insert new request
    $pdo->prepare("INSERT INTO subscription_requests (shop_id,plan_type,message) VALUES (?,?,?)")->execute([$shopId,$plan,$message]);

    // Notify admin via chat
    $shopRow = $pdo->prepare("SELECT shop_name,owner_name FROM shops WHERE shop_id=?"); $shopRow->execute([$shopId]); $sh=$shopRow->fetch();
    $msg = "📨 Subscription Request: ".htmlspecialchars($sh['shop_name']??$shopId)." — Plan: $plan".($message?" — Note: $message":'');
    try { $pdo->prepare("INSERT INTO admin_chat_messages (shop_id,sender_type,sender_id,message) VALUES (?,'owner',?,?)")->execute([$shopId,$_SESSION['user_id'],$msg]); } catch(Exception $e){}

    jsonResponse(['success'=>true,'msg'=>'Request sent! Admin approve karenge.']);
}

jsonResponse(['success'=>false,'msg'=>'Unknown action']);
