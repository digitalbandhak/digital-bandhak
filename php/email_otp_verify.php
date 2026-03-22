<?php
// php/email_otp_verify.php
require_once '../includes/config.php';

$email   = trim($_POST['email']   ?? '');
$otp     = trim($_POST['otp']     ?? '');
$purpose = trim($_POST['purpose'] ?? 'forgot_password');

if (!$email || !$otp) jsonResponse(['success'=>false,'msg'=>'Email aur OTP daalo']);

try {
    $stmt = $pdo->prepare("SELECT * FROM email_otps WHERE email=? AND otp=? AND purpose=? AND is_used=0 AND expires_at>NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email,$otp,$purpose]);
    $row = $stmt->fetch();
} catch(Exception $e) {
    jsonResponse(['success'=>false,'msg'=>'OTP table nahi mili. run_once script chalao.']);
}

if (!$row) jsonResponse(['success'=>false,'msg'=>'OTP galat hai ya expire ho gaya']);

// Mark used
$pdo->prepare("UPDATE email_otps SET is_used=1 WHERE id=?")->execute([$row['id']]);

jsonResponse(['success'=>true, 'msg'=>'OTP verified!', 'email'=>$email]);
