<?php
require_once __DIR__.'/config.php';

header('Content-Type: application/json');
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$role    = $_SESSION['role'] ?? '';
// SECURITY: Never use a default shop_id - always from session
$shop_id = $_SESSION['shop_id'] ?? '';

// ── DATA ISOLATION: Shop role can ONLY see their own data ────
if ($role === 'shop') {
    if (empty($shop_id)) {
        echo json_encode(['ok'=>false,'msg'=>'Session expired. Please login again.']);
        exit;
    }
    // Force all shop_id params to session value - prevents shop SH001 accessing SH002 data
    $_GET['shop_id']  = $shop_id;
    $_POST['shop_id'] = $shop_id;
}

// ─── GET PAWNS ────────────────────────────────────────────────
if ($action === 'get_pawns') {
    if (!$pdo) { echo json_encode(['ok'=>true,'pawns'=>$DEMO_PAWNS]); exit; }
    
    // Customer: show only their pawn(s) by bandhak_id
    if ($role === 'customer') {
        $bandhak_id = $_SESSION['bandhak_id'] ?? '';
        $stmt = $pdo->prepare("SELECT p.*, 
            (SELECT COUNT(*) FROM payments WHERE pawn_id=p.id) as pay_count
            FROM pawns p WHERE p.customer_mobile=? OR p.id=? ORDER BY p.created_at DESC");
        $stmt->execute([$_SESSION['mobile']??'', $bandhak_id]);
    } else {
        $target_shop = ($role === 'admin') ? ($_GET['shop_id'] ?? $_POST['shop_id'] ?? $shop_id) : $shop_id;
        if ($role === 'admin' && $target_shop === 'all') {
            // Admin: get all pawns from all shops
            $stmt = $pdo->prepare("SELECT p.*, 
                (SELECT COUNT(*) FROM payments WHERE pawn_id=p.id) as pay_count
                FROM pawns p ORDER BY p.created_at DESC");
            $stmt->execute([]);
        } else {
            $stmt = $pdo->prepare("SELECT p.*, 
                (SELECT COUNT(*) FROM payments WHERE pawn_id=p.id) as pay_count
                FROM pawns p WHERE p.shop_id=? ORDER BY p.created_at DESC");
            $stmt->execute([$target_shop]);
        }
    }
    $pawns = $stmt->fetchAll();
    // Get payments for each pawn
    foreach ($pawns as &$p) {
        $ps = $pdo->prepare("SELECT * FROM payments WHERE pawn_id=? ORDER BY payment_date ASC");
        $ps->execute([$p['id']]);
        $p['payments'] = $ps->fetchAll();
        $p['loan'] = (float)$p['loan_amount'];
        $p['paid'] = (float)$p['total_paid'];
        $p['remaining'] = (float)$p['total_remaining'];
        $p['customer'] = $p['customer_name'];
        $p['mobile'] = $p['customer_mobile'];
        $p['item'] = $p['item_description'];
        $p['interest'] = (float)$p['interest_rate'];
        $p['date'] = $p['loan_date'];
    }
    echo json_encode(['ok'=>true,'pawns'=>$pawns]);
    exit;
}

// ─── ADD PAWN ─────────────────────────────────────────────────
if ($action === 'add_pawn') {
    $sid = $shop_id;
    $pid = next_pawn_id($pdo, $sid);
    $loan = (float)($_POST['loan_amount'] ?? 0);
    
    if (!$pdo) { echo json_encode(['ok'=>true,'id'=>$pid]); exit; }
    
    // ── SUBSCRIPTION CHECK ──────────────────────────────────
    if ($role === 'shop') {
        $subStmt = $pdo->prepare("SELECT subscription, sub_expiry, status FROM shops WHERE id=? LIMIT 1");
        $subStmt->execute([$sid]);
        $shopRow = $subStmt->fetch();
        if ($shopRow) {
            $today = date('Y-m-d');
            $expiry = $shopRow['sub_expiry'] ?? $today;
            $isExpired = ($expiry < $today);
            $isTrial   = strtolower($shopRow['subscription'] ?? '') === 'trial';
            $isSuspended = ($shopRow['status'] === 'suspended' || $shopRow['status'] === 'inactive');
            
            if ($isSuspended) {
                echo json_encode(['ok'=>false,'msg'=>'Aapka account suspended hai. Admin se contact karein: 6206869543']);
                exit;
            }
            if ($isExpired) {
                echo json_encode(['ok'=>false,'msg'=>'Subscription expire ho gayi hai! Nayi entry nahi ho sakti. Admin se renew karwayein.','sub_expired'=>true]);
                exit;
            }
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO pawns (id,shop_id,customer_name,customer_mobile,customer_aadhaar,customer_father,customer_address,item_category,item_description,item_weight,item_condition,market_value,loan_amount,interest_rate,loan_date,return_date,duration,payment_mode,status,total_paid,total_remaining) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',0,?)");
    $stmt->execute([
        $pid, $sid,
        $_POST['customer_name'] ?? '',
        $_POST['customer_mobile'] ?? '',
        $_POST['customer_aadhaar'] ?? '',
        $_POST['father_name'] ?? '',
        $_POST['address'] ?? '',
        $_POST['item_category'] ?? 'Gold Jewellery',
        $_POST['item_description'] ?? '',
        $_POST['item_weight'] ?? '',
        $_POST['item_condition'] ?? 'Good',
        $_POST['market_value'] ?? 0,
        $loan,
        $_POST['interest_rate'] ?? 2,
        $_POST['loan_date'] ?? date('Y-m-d'),
        $_POST['return_date'] ?? null,
        $_POST['duration'] ?? '3 Months',
        $_POST['payment_mode'] ?? 'Cash',
        $loan
    ]);
    
    log_audit($pdo, $sid, $_SESSION['name'] ?? 'Shop Owner', 'Shop Owner', 'Bandhak Added', $pid);
    add_notification($pdo, $sid, '🆕', 'New Entry Added', $pid.' - '.($_POST['customer_name']??'').' - '.($_POST['item_description']??''), 'info');
    
    // Handle photo uploads (base64 strings)
    $photos = [];
    $uploadDir = __DIR__.'/../uploads/pawns/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    for ($pi=1; $pi<=4; $pi++) {
        $key = 'photo_'.$pi;
        if (!empty($_POST[$key]) && strpos($_POST[$key],'data:image')===0) {
            $b64data = explode(',', $_POST[$key]);
            if (count($b64data)===2) {
                $imgData = base64_decode($b64data[1]);
                $ext = (strpos($b64data[0],'png')!==false) ? 'png' : 'jpg';
                $fname = $pid.'_photo'.$pi.'_'.time().'.'.$ext;
                file_put_contents($uploadDir.$fname, $imgData);
                $photos[] = 'uploads/pawns/'.$fname;
            }
        }
    }
    if (!empty($photos)) {
        $photosJson = json_encode($photos);
        // Store in both columns for compatibility
        try { $pdo->prepare("UPDATE pawns SET photos=?, item_photos=? WHERE id=?")->execute([$photosJson, $photosJson, $pid]); }
        catch(Exception $e) { $pdo->prepare("UPDATE pawns SET photos=? WHERE id=?")->execute([$photosJson, $pid]); }
    }
    
    echo json_encode(['ok'=>true,'id'=>$pid]);
    exit;
}

// ─── ADD PAYMENT ──────────────────────────────────────────────
if ($action === 'add_payment') {
    $pawn_id = trim($_POST['pawn_id'] ?? '');
    $amount  = (float)($_POST['amount'] ?? 0);
    $mode    = $_POST['mode'] ?? 'Cash';
    $note    = $_POST['note'] ?? '';
    $date    = date('Y-m-d');
    
    if (!$pdo) {
        echo json_encode(['ok'=>true,'msg'=>'Payment saved (demo)']);
        exit;
    }
    
    // Get current pawn
    $stmt = $pdo->prepare("SELECT * FROM pawns WHERE id=? LIMIT 1");
    $stmt->execute([$pawn_id]);
    $pawn = $stmt->fetch();
    
    if (!$pawn) { echo json_encode(['ok'=>false,'msg'=>'Pawn not found']); exit; }
    
    $new_paid = $pawn['total_paid'] + $amount;
    $new_remaining = max(0, $pawn['total_remaining'] - $amount);
    $new_status = $new_remaining == 0 ? 'closed' : 'active';
    
    // Insert payment
    $ps = $pdo->prepare("INSERT INTO payments (pawn_id,shop_id,amount,payment_mode,note,payment_date) VALUES (?,?,?,?,?,?)");
    $ps->execute([$pawn_id, $shop_id, $amount, $mode, $note, $date]);
    
    // Update pawn
    $up = $pdo->prepare("UPDATE pawns SET total_paid=?,total_remaining=?,status=? WHERE id=?");
    $up->execute([$new_paid, $new_remaining, $new_status, $pawn_id]);
    
    log_audit($pdo, $shop_id, $_SESSION['name']??'Shop Owner', 'Shop Owner', 'Payment Collected', $pawn_id);
    add_notification($pdo, $shop_id, '💵', 'Payment Received', ($pawn['customer_name']??'').' ne '.fmt_inr($amount).' pay kiya - '.$pawn_id, 'success');
    
    echo json_encode(['ok'=>true,'new_paid'=>$new_paid,'new_remaining'=>$new_remaining,'status'=>$new_status]);
    exit;
}

// ─── DELETE PAWN ──────────────────────────────────────────────
if ($action === 'delete_pawn') {
    $pawn_id = trim($_POST['pawn_id'] ?? '');
    if (!$pdo) { echo json_encode(['ok'=>true]); exit; }
    
    $stmt = $pdo->prepare("SELECT customer_name FROM pawns WHERE id=?");
    $stmt->execute([$pawn_id]);
    $p = $stmt->fetch();
    
    $del = $pdo->prepare("DELETE FROM pawns WHERE id=? AND shop_id=?");
    $del->execute([$pawn_id, $shop_id]);
    
    log_audit($pdo, $shop_id, $_SESSION['name']??'Shop Owner', 'Shop Owner', 'Bandhak Deleted', $pawn_id);
    
    // If photo not in session, try DB
    if (empty($_SESSION['photo']) && $pdo) {
        if ($role === 'admin') {
            $r = $pdo->query("SELECT photo FROM admin WHERE id=1")->fetch();
            if (!empty($r['photo'])) $_SESSION['photo'] = $r['photo'];
        } elseif ($role === 'shop' && $shop_id) {
            $r = $pdo->prepare("SELECT photo FROM shops WHERE id=?"); $r->execute([$shop_id]); $r=$r->fetch();
            if (!empty($r['photo'])) $_SESSION['photo'] = $r['photo'];
        }
    }
    echo json_encode(['ok'=>true,'photo'=>$_SESSION['photo']??'']);
    exit;
}

// ─── ADMIN STATS (LIVE) ──────────────────────────────────────
if ($action === 'get_admin_stats') {
    if (!$pdo) {
        echo json_encode(['ok'=>true,'stats'=>[
            'total_shops'=>0,'active_shops'=>0,'total_customers'=>0,
            'active_pawns'=>0,'total_loan'=>0,'total_revenue'=>0,'pending_amount'=>0,'new_shops_month'=>0
        ]]);
        exit;
    }
    $stats = [];
    // Total shops
    $stats['total_shops'] = $pdo->query("SELECT COUNT(*) FROM shops")->fetchColumn();
    // Active shops
    $stats['active_shops'] = $pdo->query("SELECT COUNT(*) FROM shops WHERE status='active'")->fetchColumn();
    // New shops this month
    $stats['new_shops_month'] = $pdo->query("SELECT COUNT(*) FROM shops WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
    // Total unique customers (from pawns)
    $stats['total_customers'] = $pdo->query("SELECT COUNT(DISTINCT customer_mobile) FROM pawns")->fetchColumn();
    // Active pawns
    $stats['active_pawns'] = $pdo->query("SELECT COUNT(*) FROM pawns WHERE status='active'")->fetchColumn();
    // Total loan amount
    $stats['total_loan'] = $pdo->query("SELECT COALESCE(SUM(loan_amount),0) FROM pawns")->fetchColumn();
    // Total collected (payments)
    $stats['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(total_paid),0) FROM pawns")->fetchColumn();
    // Pending amount
    $stats['pending_amount'] = $pdo->query("SELECT COALESCE(SUM(total_remaining),0) FROM pawns WHERE status='active'")->fetchColumn();
    
    echo json_encode(['ok'=>true,'stats'=>$stats]);
    exit;
}

// ─── GET PAYMENTS FOR PAWN ────────────────────────────────────
if ($action === 'get_payments') {
    $pawn_id = trim($_GET['pawn_id'] ?? '');
    if (!$pdo) {
        foreach ($DEMO_PAWNS as $p) {
            if ($p['id'] === $pawn_id) {
                echo json_encode(['ok'=>true,'pawn'=>$p,'payments'=>$p['payments']]);
                exit;
            }
        }
        echo json_encode(['ok'=>false]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM pawns WHERE id=? LIMIT 1");
    $stmt->execute([$pawn_id]);
    $pawn = $stmt->fetch();
    $ps = $pdo->prepare("SELECT * FROM payments WHERE pawn_id=? ORDER BY payment_date ASC");
    $ps->execute([$pawn_id]);
    $payments = $ps->fetchAll();
    echo json_encode(['ok'=>true,'pawn'=>$pawn,'payments'=>$payments]);
    exit;
}

// ─── GET SHOPS (admin) ────────────────────────────────────────
if ($action === 'get_shops') {
    if (!$pdo) { echo json_encode(['ok'=>true,'shops'=>$DEMO_SHOPS]); exit; }
    $stmt = $pdo->query("SELECT * FROM shops ORDER BY created_at DESC");
    echo json_encode(['ok'=>true,'shops'=>$stmt->fetchAll()]);
    exit;
}

// ─── UPDATE SHOP STATUS (admin) ───────────────────────────────
if ($action === 'update_shop_status') {
    $sid    = trim($_POST['shop_id'] ?? '');
    $status = trim($_POST['status'] ?? '');
    if (!$pdo) { echo json_encode(['ok'=>true]); exit; }
    $stmt = $pdo->prepare("UPDATE shops SET status=? WHERE id=?");
    $stmt->execute([$status, $sid]);
    log_audit($pdo, $sid, 'Super Admin', 'Admin', $status==='active'?'Shop Activated':'Shop Deactivated', $sid);
    echo json_encode(['ok'=>true]);
    exit;
}

// ─── EXTEND SUBSCRIPTION (admin) ─────────────────────────────
if ($action === 'extend_sub') {
    $sid      = trim($_POST['shop_id'] ?? '');
    $duration = trim($_POST['duration'] ?? '1 Month');
    $amount   = (float)($_POST['amount'] ?? 0);
    if (!$pdo) { echo json_encode(['ok'=>true]); exit; }
    
    // Calculate new expiry
    $months = ['1 Month'=>1,'3 Months'=>3,'6 Months'=>6,'1 Year'=>12][$duration] ?? 1;
    $stmt = $pdo->prepare("SELECT sub_expiry FROM shops WHERE id=?");
    $stmt->execute([$sid]);
    $shop = $stmt->fetch();
    $base = max(date('Y-m-d'), $shop['sub_expiry'] ?? date('Y-m-d'));
    $new_expiry = date('Y-m-d', strtotime($base.' +'.$months.' months'));
    
    $up = $pdo->prepare("UPDATE shops SET sub_expiry=?,subscription='Standard',status='active',balance=balance+? WHERE id=?");
    $up->execute([$new_expiry, $amount, $sid]);
    
    log_audit($pdo, $sid, 'Super Admin', 'Admin', 'Subscription Extended', $sid);
    echo json_encode(['ok'=>true,'new_expiry'=>$new_expiry]);
    exit;
}

// ─── CHAT MESSAGES ────────────────────────────────────────────
if ($action === 'get_chat') {
    $chat_shop = ($role === 'admin') ? trim($_GET['shop_id'] ?? $shop_id) : $shop_id;
    if (!$pdo) { echo json_encode(['ok'=>true,'messages':[]]); exit; }
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE shop_id=? ORDER BY created_at ASC");
    $stmt->execute([$chat_shop]);
    $msgs = $stmt->fetchAll();
    // Normalize fields for JS
    $msgs = array_map(function($m) {
        $m['sender_role'] = $m['sender'] ?? 'shop';
        $m['sender_name'] = $m['sender_name'] ?? ($m['sender'] === 'admin' ? 'Super Admin' : ($m['shop_id'] ?? 'Shop'));
        $m['image_url']   = $m['image_path'] ?? $m['image_url'] ?? null;
        return $m;
    }, $msgs);
    // Mark as read
    $who = $role === 'admin' ? 'shop' : 'admin';
    $pdo->prepare("UPDATE chat_messages SET is_read=1 WHERE shop_id=? AND sender=?")->execute([$chat_shop,$who]);
    echo json_encode(['ok'=>true,'messages'=>$msgs]);
    exit;
}

if ($action === 'send_chat') {
    $chat_shop = ($role === 'admin') ? trim($_POST['shop_id'] ?? $shop_id) : $shop_id;
    $msg = trim($_POST['message'] ?? '');
    $img = null;
    
    // Handle image upload - FILE or base64
    if (!empty($_FILES['image']['tmp_name'])) {
        // File upload
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $allowed_ext = ['jpg','jpeg','png','gif','webp'];
        if (in_array(strtolower($ext), $allowed_ext)) {
            $fname = 'chat_'.time().'_'.rand(100,999).'.'.$ext;
            $chat_dir = __DIR__.'/../uploads/chat/';
            if (!is_dir($chat_dir)) mkdir($chat_dir, 0755, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $chat_dir.$fname)) {
                $img = 'uploads/chat/'.$fname;
            }
        }
    } elseif (!empty($_POST['image']) && strpos($_POST['image'], 'data:image') === 0) {
        // Base64 image from JS
        $base64 = $_POST['image'];
        if (preg_match('/^data:image\/([a-zA-Z]+);base64,/', $base64, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            $allowed_ext = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed_ext)) {
                $imgData = base64_decode(preg_replace('/^data:image\/[a-zA-Z]+;base64,/', '', $base64));
                if ($imgData) {
                    $fname = 'chat_'.time().'_'.rand(100,999).'.'.$ext;
                    $chat_dir = __DIR__.'/../uploads/chat/';
                    if (!is_dir($chat_dir)) mkdir($chat_dir, 0755, true);
                    if (file_put_contents($chat_dir.$fname, $imgData)) {
                        $img = 'uploads/chat/'.$fname;
                    }
                }
            }
        }
    }
    
    if (!$pdo) {
        echo json_encode(['ok'=>true,'id'=>rand(100,999),'time'=>date('h:i A')]);
        exit;
    }
    
    $sender      = ($role === 'admin') ? 'admin' : 'shop';
    $sender_name = trim($_POST['sender_name'] ?? ($role === 'admin' ? 'Super Admin' : $shop_id));
    // Try insert with sender_name column, fallback without
    try {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (shop_id,sender,sender_name,message,image_path) VALUES (?,?,?,?,?)");
        $stmt->execute([$chat_shop, $sender, $sender_name, $msg, $img]);
    } catch(Exception $e2) {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (shop_id,sender,message,image_path) VALUES (?,?,?,?)");
        $stmt->execute([$chat_shop, $sender, $msg, $img]);
    }
    $new_id = $pdo->lastInsertId();
    echo json_encode(['ok'=>true,'id'=>$new_id,'image'=>$img,'time'=>date('h:i A')]);
    exit;
}

// ─── GET NOTIFICATIONS ────────────────────────────────────────
if ($action === 'get_notifs') {
    if (!$pdo) { echo json_encode(['ok'=>true,'notifs':[]]); exit; }
    $target = $role === 'admin' ? null : $shop_id;
    if ($target) {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE shop_id=? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$target]);
    } else {
        $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20");
    }
    echo json_encode(['ok'=>true,'notifs'=>$stmt->fetchAll()]);
    exit;
}

if ($action === 'mark_notifs_read') {
    if (!$pdo) { echo json_encode(['ok'=>true]); exit; }
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE shop_id=?")->execute([$shop_id]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ─── GET AUDIT LOGS (admin) ───────────────────────────────────
if ($action === 'get_audit') {
    $limit = min(50, (int)($_GET['limit'] ?? $_POST['limit'] ?? 20));
    if (!$pdo) { echo json_encode(['ok'=>true,'logs':[]]); exit; }
    $stmt = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
    echo json_encode(['ok'=>true,'logs'=>$stmt->fetchAll()]);
    exit;
}

// ─── SAVE PROFILE ─────────────────────────────────────────────
if ($action === 'save_profile') {
    if (!$pdo) { echo json_encode(['ok'=>true]); exit; }
    
    // Handle photo upload
    $photo_path = '';
    if (!empty($_FILES['photo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed) && $_FILES['photo']['size'] < 5*1024*1024) {
            $upload_dir = __DIR__.'/../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $fname = 'profile_'.($role==='admin'?'admin':$shop_id).'_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir.$fname)) {
                $photo_path = 'uploads/profiles/'.$fname;
                $_SESSION['photo'] = $photo_path;
            }
        }
    }
    
    if ($role === 'admin') {
        $adminSaved = false;
        // Try with photo
        if ($photo_path) {
            try { $pdo->prepare("UPDATE admin SET name=?,email=?,mobile=?,photo=? WHERE id=1")->execute([$_POST['name']??'', $_POST['email']??'', $_POST['mobile']??'', $photo_path]); $adminSaved=true; } catch(Exception $e) {}
        }
        if (!$adminSaved) {
            try { $pdo->prepare("UPDATE admin SET name=?,email=?,mobile=? WHERE id=1")->execute([$_POST['name']??'', $_POST['email']??'', $_POST['mobile']??'']); $adminSaved=true; } catch(Exception $e) {}
        }
        $_SESSION['name']  = $_POST['name']  ?? $_SESSION['name'];
        $_SESSION['email'] = $_POST['email'] ?? $_SESSION['email'];
        $_SESSION['mobile']= $_POST['mobile']?? $_SESSION['mobile'];
    } elseif ($role === 'shop') {
        try {
            if ($photo_path) {
                $pdo->prepare("UPDATE shops SET owner_name=?,email=?,mobile=?,name=?,photo=? WHERE id=?")->execute([$_POST['name']??'',$_POST['email']??'',$_POST['mobile']??'',$_POST['shop_name']??'',$photo_path,$shop_id]);
            } else {
                $pdo->prepare("UPDATE shops SET owner_name=?,email=?,mobile=?,name=? WHERE id=?")->execute([$_POST['name']??'',$_POST['email']??'',$_POST['mobile']??'',$_POST['shop_name']??'',$shop_id]);
            }
        } catch(Exception $e2) {
            // Minimal fallback
            try { $pdo->prepare("UPDATE shops SET owner_name=?,email=?,mobile=? WHERE id=?")->execute([$_POST['name']??'',$_POST['email']??'',$_POST['mobile']??'',$shop_id]); } catch(Exception $e3) {}
        }
        $_SESSION['name']     = $_POST['name']     ?? $_SESSION['name'];
        $_SESSION['shop_name']= $_POST['shop_name']?? $_SESSION['shop_name'];
        $_SESSION['email']    = $_POST['email']    ?? $_SESSION['email'];
        $_SESSION['mobile']   = $_POST['mobile']   ?? $_SESSION['mobile'];
    }
    // Password change
    if (!empty($_POST['new_pass']) && $_POST['new_pass'] === $_POST['conf_pass']) {
        $hash = password_hash($_POST['new_pass'], PASSWORD_DEFAULT);
        if ($role === 'admin') {
            $pdo->prepare("UPDATE admin SET password=? WHERE id=1")->execute([$hash]);
        } else {
            $pdo->prepare("UPDATE shops SET password=? WHERE id=?")->execute([$hash, $shop_id]);
        }
    }
    // If photo not in session, try DB
    if (empty($_SESSION['photo']) && $pdo) {
        if ($role === 'admin') {
            $r = $pdo->query("SELECT photo FROM admin WHERE id=1")->fetch();
            if (!empty($r['photo'])) $_SESSION['photo'] = $r['photo'];
        } elseif ($role === 'shop' && $shop_id) {
            $r = $pdo->prepare("SELECT photo FROM shops WHERE id=?"); $r->execute([$shop_id]); $r=$r->fetch();
            if (!empty($r['photo'])) $_SESSION['photo'] = $r['photo'];
        }
    }
    echo json_encode(['ok'=>true,'photo'=>$_SESSION['photo']??'']);
    exit;
}

// ─── UNREAD CHAT COUNT ────────────────────────────────────────
if ($action === 'chat_unread') {
    if (!$pdo) { echo json_encode(['ok'=>true,'count'=>1]); exit; }
    $sender_type = $role === 'admin' ? 'shop' : 'admin';
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM chat_messages WHERE shop_id=? AND sender=? AND is_read=0");
    $stmt->execute([$shop_id, $sender_type]);
    $row = $stmt->fetch();
    echo json_encode(['ok'=>true,'count'=>$row['c']]);
    exit;
}

// ─── CHANGE PASSWORD (from Settings modal) ───────────────────
if ($action === 'change_password') {
    $cur  = trim($_POST['current_pass'] ?? '');
    $new  = trim($_POST['new_pass'] ?? '');
    if (strlen($new) < 6) { echo json_encode(['ok'=>false,'msg'=>'Password kam se kam 6 characters ka hona chahiye']); exit; }
    if (!$pdo) { echo json_encode(['ok'=>true]); exit; }
    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT password FROM admin WHERE email=?");
        $stmt->execute([$_SESSION['email']??'']);
        $row = $stmt->fetch();
        if (!$row || !password_verify($cur, $row['password'])) {
            echo json_encode(['ok'=>false,'msg'=>'Current password galat hai']); exit;
        }
        $pdo->prepare("UPDATE admin SET password=? WHERE email=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['email']]);
    } else {
        $stmt = $pdo->prepare("SELECT password FROM shops WHERE id=?");
        $stmt->execute([$shop_id]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($cur, $row['password'])) {
            echo json_encode(['ok'=>false,'msg'=>'Current password galat hai']); exit;
        }
        $pdo->prepare("UPDATE shops SET password=? WHERE id=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $shop_id]);
    }
    echo json_encode(['ok'=>true]);
    exit;
}

// ─── SAVE SETTINGS (full) ─────────────────────────────────────
if ($action === 'save_settings') {
    $interest = $_POST['default_interest'] ?? 2;
    $sms      = $_POST['sms_alerts'] ?? '0';
    $wp       = $_POST['wp_alerts'] ?? '0';
    $dark     = $_POST['dark_mode'] ?? '1';
    $platname = $_POST['platform_name'] ?? 'Digital Bandhak';
    
    $_SESSION['default_interest'] = $interest;
    $_SESSION['sms_alerts'] = $sms;
    $_SESSION['wp_alerts']  = $wp;
    
    if ($pdo) {
        $settings = [
            ['default_interest', $interest],
            ['sms_alerts', $sms],
            ['wp_alerts', $wp],
            ['dark_mode', $dark],
            ['platform_name', $platname],
        ];
        foreach ($settings as [$key, $val]) {
            try {
                $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                    ->execute([$key, $val, $val]);
            } catch(Exception $e) {}
        }
    }
    echo json_encode(['ok'=>true]);
    exit;
}

// ─── SET GLOBAL OFFER (Admin) ────────────────────────────────
if ($action === 'set_global_offer') {
    $data = json_decode($_POST['offer_data'] ?? '{}', true);
    // Store in a simple file or session
    $_SESSION['global_offer'] = $data;
    if ($pdo) {
        // Try to store in DB if settings table exists
        try {
            $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('global_offer',?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([json_encode($data), json_encode($data)]);
        } catch(Exception $e) {}
    }
    echo json_encode(['ok'=>true]);
    exit;
}

// ─── GET SHOP SUBSCRIPTION ────────────────────────────────────
if ($action === 'get_shop_sub') {
    if (!$pdo) {
        echo json_encode(['ok'=>true,'sub'=>['plan'=>'Standard','expiry'=>date('Y-m-d',strtotime('+6 months'))],'payments'=>[]]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT subscription,sub_expiry FROM shops WHERE id=? LIMIT 1");
    $stmt->execute([$shop_id]);
    $shop = $stmt->fetch();
    $sub = ['plan'=>$shop['subscription']??'Standard','expiry'=>$shop['sub_expiry']??date('Y-m-d',strtotime('+1 year'))];
    $ps = $pdo->prepare("SELECT * FROM payments WHERE shop_id=? ORDER BY payment_date DESC LIMIT 20");
    $ps->execute([$shop_id]);
    echo json_encode(['ok'=>true,'sub'=>$sub,'payments'=>$ps->fetchAll()]);
    exit;
}

// ─── RENEW REQUEST ────────────────────────────────────────────
if ($action === 'renew_request') {
    $plan_id = $_POST['plan_id'] ?? '';
    $amount  = (float)($_POST['amount'] ?? 0);
    $mode    = $_POST['mode'] ?? 'UPI';
    if ($pdo) {
        add_notification($pdo, 'ADMIN', '💳', 'Renewal Request',
            ($_SESSION['name']??'Shop').' ne renewal request ki — ₹'.number_format($amount).' ('.$plan_id.')', 'info');
        log_audit($pdo, $shop_id, $_SESSION['name']??'Shop', 'Shop Owner',
            'Renewal Request Sent', 'Plan:'.$plan_id.' Amount:₹'.$amount.' Mode:'.$mode);
    }
    echo json_encode(['ok'=>true,'msg'=>'Request sent!']);
    exit;
}

// ─── SET GLOBAL OFFER ─────────────────────────────────────────
if ($action === 'set_global_offer') {
    $offer_data = $_POST['offer_data'] ?? '{"active":false}';
    if ($pdo) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('global_offer',?) ON DUPLICATE KEY UPDATE setting_value=?");
        $stmt->execute([$offer_data, $offer_data]);
    }
    echo json_encode(['ok'=>true]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Unknown action: '.$action]);

// ─── CUSTOMER REGISTRATION ────────────────────────────────────
if ($action === 'register_customer') {
    $name    = trim($_POST['name'] ?? '');
    $mobile  = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $aadhaar = trim($_POST['aadhaar'] ?? '');
    $shop_id_req = trim($_POST['shop_id'] ?? $shop_id);
    
    if (!$name || !$mobile) {
        echo json_encode(['ok'=>false,'msg'=>'Naam aur mobile zaroori hai']);
        exit;
    }
    if (strlen($mobile) !== 10 || !ctype_digit($mobile)) {
        echo json_encode(['ok'=>false,'msg'=>'Valid 10-digit mobile number daalein']);
        exit;
    }
    if (!$pdo) {
        echo json_encode(['ok'=>false,'msg'=>'Database se connection nahi ho pa raha']);
        exit;
    }
    
    // Check if already registered
    $chk = $pdo->prepare("SELECT id,status FROM customer_accounts WHERE mobile=? LIMIT 1");
    $chk->execute([$mobile]);
    $existing = $chk->fetch();
    
    if ($existing) {
        if ($existing['status'] === 'active') {
            echo json_encode(['ok'=>false,'already_active'=>true,'msg'=>'Yeh mobile number pehle se registered aur active hai. Seedha login karein.']);
        } elseif ($existing['status'] === 'pending') {
            echo json_encode(['ok'=>false,'pending'=>true,'msg'=>'Aapka account already registered hai aur admin activation ka wait kar raha hai.']);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Yeh account blocked hai. Admin se contact karein.']);
        }
        exit;
    }
    
    // Register new customer
    $ins = $pdo->prepare("INSERT INTO customer_accounts (name,mobile,address,aadhaar,shop_id,status) VALUES (?,?,?,?,?,'pending')");
    $ins->execute([$name, $mobile, $address, $aadhaar, $shop_id_req]);
    
    // Notify admin
    log_audit($pdo, $shop_id_req, $name, 'Customer', 'Customer Registered', $mobile);
    add_notification($pdo, 'admin', '👤', 'New Customer Registration', $name.' ('.$mobile.') ne register kiya - activation pending', 'info');
    
    echo json_encode(['ok'=>true,'msg'=>'Registration successful! Admin approval ka wait karein.']);
    exit;
}

// ─── GET PENDING CUSTOMERS (Admin) ───────────────────────────
if ($action === 'get_pending_customers') {
    if ($role !== 'admin') { echo json_encode(['ok'=>false,'msg'=>'Access denied']); exit; }
    if (!$pdo) { echo json_encode(['ok'=>true,'customers',[]]); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM customer_accounts ORDER BY registered_at DESC");
    $stmt->execute();
    echo json_encode(['ok'=>true,'customers'=>$stmt->fetchAll()]);
    exit;
}

// ─── ACTIVATE CUSTOMER (Admin) ───────────────────────────────
if ($action === 'activate_customer') {
    if ($role !== 'admin') { echo json_encode(['ok'=>false,'msg'=>'Access denied']); exit; }
    if (!$pdo) { echo json_encode(['ok'=>false,'msg'=>'DB not connected']); exit; }
    
    $cust_id = $_POST['customer_id'] ?? '';
    $new_status = $_POST['status'] ?? 'active'; // active or blocked
    
    $upd = $pdo->prepare("UPDATE customer_accounts SET status=?,activated_at=NOW(),activated_by=? WHERE id=?");
    $upd->execute([$new_status, $_SESSION['name']??'Admin', $cust_id]);
    
    // Get customer info for notification
    $cust = $pdo->prepare("SELECT name,mobile FROM customer_accounts WHERE id=? LIMIT 1");
    $cust->execute([$cust_id]);
    $custRow = $cust->fetch();
    
    if ($new_status === 'active' && $custRow) {
        // Mark notification for customer (they'll see it on next login)
        $pdo->prepare("INSERT INTO notifications (shop_id,icon,title,body,type) VALUES ('CUST_'||?,'✅','Account Activate Ho Gaya!','Aapka account approve ho gaya. Ab aap login kar sakte hain.','success')")->execute([$custRow['mobile']]);
    }
    
    log_audit($pdo, '', $_SESSION['name']??'Admin', 'Admin', 'Customer '.ucfirst($new_status), $custRow['mobile']??'');
    echo json_encode(['ok'=>true,'msg'=>'Customer '.$new_status.' kar diya gaya']);
    exit;
}

// ─── CHECK CUSTOMER STATUS (for login flow) ──────────────────
if ($action === 'check_customer_status') {
    $mobile = trim($_POST['mobile'] ?? $_GET['mobile'] ?? '');
    if (!$pdo || !$mobile) { echo json_encode(['ok'=>false]); exit; }
    
    $stmt = $pdo->prepare("SELECT status,name FROM customer_accounts WHERE mobile=? LIMIT 1");
    $stmt->execute([$mobile]);
    $row = $stmt->fetch();
    
    if (!$row) {
        echo json_encode(['ok'=>true,'status'=>'not_registered']);
    } else {
        echo json_encode(['ok'=>true,'status'=>$row['status'],'name'=>$row['name']]);
    }
    exit;
}

// ─── GET SHOP INFO ───────────────────────────────────────────
if ($action === 'get_shop_info') {
    $sid = trim($_GET['shop_id'] ?? $_POST['shop_id'] ?? $shop_id);
    if (!$pdo || !$sid) { echo json_encode(['ok'=>true,'shop',(object)[]]); exit; }
    $stmt = $pdo->prepare("SELECT id,name,owner_name,email,mobile,subscription,sub_start,sub_expiry,status,balance,default_interest,photo,logo FROM shops WHERE id=? LIMIT 1");
    $stmt->execute([$sid]);
    $s = $stmt->fetch();
    echo json_encode(['ok'=>true,'shop'=>$s?:new stdClass()]);
    exit;
}
