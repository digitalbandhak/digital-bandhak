<?php
require_once __DIR__.'/config.php';

$action = $_POST['action'] ?? '';

// ── ADMIN LOGIN ──────────────────────────────────────────────
if ($action === 'login_admin') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    $login_ok = false;
    $admin_name = ADMIN_NAME;

    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            $stored = $admin['password'];
            // Case 1: Proper bcrypt hash
            if (strlen($stored) > 20 && $stored[0] === '$' && password_verify($pass, $stored)) {
                $login_ok = true;
                $admin_name = $admin['name'];
            }
            // Case 2: SETUP_REQUIRED or plain — compare against config
            elseif (($stored === 'SETUP_REQUIRED' || $stored === $pass) 
                    && $email === ADMIN_EMAIL && $pass === ADMIN_PASS) {
                // Auto-update to proper hash now
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE admin SET password=?, name=?, mobile=? WHERE email=?")
                    ->execute([$hash, ADMIN_NAME, ADMIN_MOBILE, $email]);
                $login_ok = true;
                $admin_name = ADMIN_NAME;
            }
        }
    }

    // Fallback: no DB at all
    if (!$pdo && $email === ADMIN_EMAIL && $pass === ADMIN_PASS) {
        $login_ok = true;
    }

    if ($login_ok) {
        $_SESSION['role']  = 'admin';
        $_SESSION['name']  = $admin_name;
        $_SESSION['email'] = $email;
        // Load photo from DB
        if ($pdo) {
            try { $row = $pdo->query("SELECT photo FROM admin WHERE id=1")->fetch(); if (!empty($row['photo'])) $_SESSION['photo'] = $row['photo']; } catch(Exception $e) {}
        }
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Email ya password galat hai']);
    }
    exit;
}

// ── SHOP LOGIN ───────────────────────────────────────────────
if ($action === 'login_shop') {
    $shop_id = strtoupper(trim($_POST['shop_id'] ?? ''));
    $pass    = trim($_POST['password'] ?? '');

    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE id=? LIMIT 1");
        $stmt->execute([$shop_id]);
        $shop = $stmt->fetch();
        if ($shop && password_verify($pass, $shop['password'])) {
            if ($shop['status'] === 'inactive' || $shop['status'] === 'suspended') {
                echo json_encode(['ok'=>false,'blocked'=>true,'msg'=>'Aapka account block/inactive hai. Admin se contact karein.']);
            } else {
                $_SESSION['role']      = 'shop';
                $_SESSION['shop_id']   = $shop['id'];
                $_SESSION['name']      = $shop['owner_name'];
                $_SESSION['shop_name'] = $shop['name'];
                $_SESSION['photo'] = $shop['photo'] ?? $shop['logo'] ?? '';
                echo json_encode(['ok'=>true]);
            }
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Shop ID ya password galat hai']);
        }
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Database connect nahi hai. Pehle database setup karein.']);
    }
    exit;
}

// ── OTP SEND ─────────────────────────────────────────────────
if ($action === 'send_otp') {
    $mobile     = trim($_POST['mobile'] ?? '');
    $bandhak_id = trim($_POST['bandhak_id'] ?? '');
    $otp = str_pad(rand(100000,999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_mobile']  = $mobile;
    $_SESSION['otp_bandhak'] = $bandhak_id;
    $_SESSION['otp_code']    = $otp;
    // Production mein: SMS bhejo
    // Demo mein OTP screen par dikhao
    echo json_encode(['ok'=>true,'demo_otp'=>$otp]);
    exit;
}

// ── OTP VERIFY ───────────────────────────────────────────────
if ($action === 'verify_otp') {
    $otp = trim($_POST['otp'] ?? '');
    if ($otp === ($_SESSION['otp_code'] ?? '')) {
        $cust_name   = 'Customer';
        $cust_mobile = $_SESSION['otp_mobile'] ?? '';
        $cust_aadhaar= '';
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT customer_name,customer_mobile,customer_aadhaar FROM pawns WHERE id=? LIMIT 1");
            $stmt->execute([$_SESSION['otp_bandhak']??'']);
            $row = $stmt->fetch();
            if ($row) {
                $cust_name   = $row['customer_name'];
                $cust_mobile = $row['customer_mobile'] ?: $cust_mobile;
                $cust_aadhaar= $row['customer_aadhaar'] ?: '';
            }
        }
        $_SESSION['role']       = 'customer';
        $_SESSION['name']       = $cust_name;
        $_SESSION['mobile']     = $cust_mobile;
        $_SESSION['aadhaar']    = $cust_aadhaar;
        $_SESSION['bandhak_id'] = $_SESSION['otp_bandhak'] ?? '';
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'OTP galat hai']);
    }
    exit;
}

// ── FORGOT SEND OTP ──────────────────────────────────────────
if ($action === 'forgot_send_otp') {
    $mobile  = trim($_POST['mobile'] ?? '');
    $shop_id = strtoupper(trim($_POST['shop_id'] ?? ''));
    $otp = str_pad(rand(100000,999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['reset_otp']    = $otp;
    $_SESSION['reset_mobile'] = $mobile;
    $_SESSION['reset_shop']   = $shop_id;
    echo json_encode(['ok'=>true,'demo_otp'=>$otp]);
    exit;
}

// ── FORGOT VERIFY OTP ────────────────────────────────────────
if ($action === 'forgot_verify_otp') {
    $otp = trim($_POST['otp'] ?? '');
    if ($otp === ($_SESSION['reset_otp'] ?? '')) {
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'OTP galat hai']);
    }
    exit;
}

// ── RESET PASSWORD ───────────────────────────────────────────
if ($action === 'reset_password') {
    $new_pass  = trim($_POST['new_pass'] ?? '');
    $conf_pass = trim($_POST['conf_pass'] ?? '');
    if ($new_pass !== $conf_pass) {
        echo json_encode(['ok'=>false,'msg'=>'Passwords match nahi kar rahe']);
        exit;
    }
    if ($pdo && !empty($_SESSION['reset_shop'])) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE shops SET password=? WHERE id=?");
        $stmt->execute([$hash, $_SESSION['reset_shop']]);
    }
    echo json_encode(['ok'=>true]);
    exit;
}

// ── REGISTER SHOP ────────────────────────────────────────────
if ($action === 'register_shop') {
    $data = [
        'id'         => 'SH'.str_pad(rand(10,999), 3, '0', STR_PAD_LEFT),
        'name'       => trim($_POST['shop_name'] ?? ''),
        'owner_name' => trim($_POST['owner_name'] ?? ''),
        'mobile'     => trim($_POST['mobile'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'pincode'    => trim($_POST['pincode'] ?? ''),
        'state'      => trim($_POST['state'] ?? 'Bihar'),
        'address'    => trim($_POST['address'] ?? ''),
        'gst'        => trim($_POST['gst'] ?? ''),
        'licence'    => trim($_POST['licence'] ?? ''),
        'password'   => trim($_POST['password'] ?? ''),
        'conf_pass'  => trim($_POST['conf_pass'] ?? ''),
    ];
    if ($data['password'] !== $data['conf_pass']) {
        echo json_encode(['ok'=>false,'msg'=>'Passwords match nahi kar rahe']);
        exit;
    }
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id FROM shops WHERE mobile=?");
        $stmt->execute([$data['mobile']]);
        if ($stmt->fetch()) {
            echo json_encode(['ok'=>false,'msg'=>'Yeh mobile already registered hai']);
            exit;
        }
        $hash  = password_hash($data['password'], PASSWORD_DEFAULT);
        $start  = date('Y-m-d');
        $expiry = date('Y-m-d', strtotime('+7 days'));
        $stmt = $pdo->prepare("INSERT INTO shops (id,name,owner_name,email,mobile,password,address,state,pincode,gst,licence,status,subscription,sub_start,sub_expiry) VALUES (?,?,?,?,?,?,?,?,?,?,?,'inactive','Trial',?,?)");
        $stmt->execute([$data['id'],$data['name'],$data['owner_name'],$data['email'],$data['mobile'],$hash,$data['address'],$data['state'],$data['pincode'],$data['gst'],$data['licence'],$start,$expiry]);
        log_audit($pdo,$data['id'],$data['owner_name'],'Shop Owner','Shop Registered',$data['id']);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Database connect nahi hai']);
        exit;
    }
    echo json_encode(['ok'=>true,'shop_id'=>$data['id'],'msg'=>'Registration successful! Admin approval ke baad login kar sakte hain.']);
    exit;
}

// ── SHOP LOGIN BY MOBILE ────────────────────────────────────
if ($action === 'login_shop_by_mobile') {
    $mobile = trim($_POST['mobile'] ?? '');
    $pass   = trim($_POST['password'] ?? '');
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE mobile=? LIMIT 1");
        $stmt->execute([$mobile]);
        $shop = $stmt->fetch();
        if ($shop && password_verify($pass, $shop['password'])) {
            if (in_array($shop['status'], ['inactive','suspended'])) {
                echo json_encode(['ok'=>false,'blocked'=>true,'msg'=>'Aapka account block/inactive hai. Admin se contact karein.']); 
            } else {
                $_SESSION['role']='shop'; $_SESSION['shop_id']=$shop['id'];
                $_SESSION['name']=$shop['owner_name']; $_SESSION['shop_name']=$shop['name'];
                $_SESSION['photo'] = $shop['photo'] ?? $shop['logo'] ?? '';
                echo json_encode(['ok'=>true]);
            }
        } else { echo json_encode(['ok'=>false,'msg'=>'Mobile ya password galat hai']); }
    } else { echo json_encode(['ok'=>false,'msg'=>'DB error']); }
    exit;
}

// ── SHOP LOGIN BY EMAIL ─────────────────────────────────────
if ($action === 'login_shop_by_email') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $shop = $stmt->fetch();
        if ($shop && password_verify($pass, $shop['password'])) {
            if (in_array($shop['status'], ['inactive','suspended'])) {
                echo json_encode(['ok'=>false,'blocked'=>true,'msg'=>'Aapka account block/inactive hai. Admin se contact karein.']);
            } else {
                $_SESSION['role']='shop'; $_SESSION['shop_id']=$shop['id'];
                $_SESSION['name']=$shop['owner_name']; $_SESSION['shop_name']=$shop['name'];
                $_SESSION['photo'] = $shop['photo'] ?? $shop['logo'] ?? '';
                echo json_encode(['ok'=>true]);
            }
        } else { echo json_encode(['ok'=>false,'msg'=>'Email ya password galat hai']); }
    } else { echo json_encode(['ok'=>false,'msg'=>'DB error']); }
    exit;
}

// ── LOGOUT ───────────────────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok'=>true]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Unknown action']);
?>
