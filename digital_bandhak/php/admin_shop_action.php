<?php
require_once '../includes/config.php';
requireLogin('super_admin', '../index.php');

$shopId = trim($_POST['shop_id'] ?? '');
$action = trim($_POST['action'] ?? '');

if (!$shopId) jsonResponse(['success'=>false,'msg'=>'Shop ID missing']);

switch ($action) {
    case 'reject':
        $pdo->prepare("UPDATE shops SET status='suspended', blocked=1 WHERE shop_id=?")->execute([$shopId]);
        auditLog($pdo,$shopId,'shop_rejected','Shop registration rejected',$_SESSION['user_type'],$_SESSION['user_id'],$_SESSION['user_name'],$shopId);
        jsonResponse(['success'=>true,'msg'=>'Shop rejected!']);

    case 'activate':
        $pdo->prepare("UPDATE shops SET status='active', blocked=0 WHERE shop_id=?")->execute([$shopId]);
        // Auto add trial if no active subscription
        $sub=$pdo->prepare("SELECT id FROM subscriptions WHERE shop_id=? AND status='active' AND end_date>=CURDATE()"); $sub->execute([$shopId]);
        if (!$sub->fetch()) {
            $pdo->prepare("INSERT INTO subscriptions (shop_id,plan_type,start_date,end_date,amount,payment_mode,status) VALUES (?,'trial',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 30 DAY),0,'free','active')")->execute([$shopId]);
        }
        $pdo->prepare("INSERT INTO admin_chat_messages (shop_id,sender_type,sender_id,message) VALUES (?,'admin',?,?)")
            ->execute([$shopId,$_SESSION['user_id'],'✅ Aapki shop activate ho gayi! Ab login kar sakte hain. Free trial 30 din ke liye shuru.']);
        auditLog($pdo,$shopId,'shop_activated','Shop activated',$_SESSION['user_type'],$_SESSION['user_id'],$_SESSION['user_name'],$shopId);
        jsonResponse(['success'=>true,'msg'=>'Shop activated!']);

    case 'block':
        $pdo->prepare("UPDATE shops SET blocked=1, status='suspended' WHERE shop_id=?")->execute([$shopId]);
        auditLog($pdo,$shopId,'shop_blocked','Shop blocked',$_SESSION['user_type'],$_SESSION['user_id'],$_SESSION['user_name'],$shopId);
        jsonResponse(['success'=>true,'msg'=>'Shop blocked!']);

    case 'unblock':
        $pdo->prepare("UPDATE shops SET blocked=0, status='active' WHERE shop_id=?")->execute([$shopId]);
        auditLog($pdo,$shopId,'shop_unblocked','Shop unblocked',$_SESSION['user_type'],$_SESSION['user_id'],$_SESSION['user_name'],$shopId);
        jsonResponse(['success'=>true,'msg'=>'Shop unblocked!']);

    case 'edit':
        $shopName  = trim($_POST['shop_name']  ?? '');
        $ownerName = trim($_POST['owner_name'] ?? '');
        $mobile    = trim($_POST['mobile']     ?? '');
        $email     = trim($_POST['email']      ?? '');
        $city      = trim($_POST['city']       ?? '');
        $gst       = trim($_POST['gst_number'] ?? '');
        $newPw     = $_POST['new_password']    ?? '';

        if ($newPw && strlen($newPw) >= 6) {
            $pdo->prepare("UPDATE shops SET shop_name=?,owner_name=?,owner_email=?,owner_mobile=?,city=?,gst_number=?,password=? WHERE shop_id=?")
                ->execute([$shopName,$ownerName,$email,$mobile,$city,$gst,password_hash($newPw,PASSWORD_DEFAULT),$shopId]);
        } else {
            $pdo->prepare("UPDATE shops SET shop_name=?,owner_name=?,owner_email=?,owner_mobile=?,city=?,gst_number=? WHERE shop_id=?")
                ->execute([$shopName,$ownerName,$email,$mobile,$city,$gst,$shopId]);
        }
        auditLog($pdo,$shopId,'shop_edited','Shop info updated by admin',$_SESSION['user_type'],$_SESSION['user_id'],$_SESSION['user_name'],$shopId);
        jsonResponse(['success'=>true,'msg'=>'Shop updated!']);

    default:
        // Reject subscription request
        if ($action === 'reject_sub_req') {
            $reqId = intval($_POST['req_id'] ?? 0);
            try {
                $pdo->prepare("UPDATE subscription_requests SET status='rejected' WHERE id=?")->execute([$reqId]);
            } catch(Exception $e){}
            jsonResponse(['success'=>true,'msg'=>'Request rejected!']);
        }
        jsonResponse(['success'=>false,'msg'=>'Unknown action']);
}
// Note: reject_sub_req case handled below
