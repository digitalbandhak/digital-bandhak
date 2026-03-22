<?php
require_once '../includes/config.php';

$bandhakId = strtoupper(trim($_POST['bandhak_id'] ?? ''));
$mobile    = preg_replace('/\D/', '', $_POST['mobile'] ?? '');

// Remove leading 91 or 0
$mobile = ltrim($mobile, '91');
$mobile = ltrim($mobile, '0');
// Keep last 10 digits
$mobile = substr($mobile, -10);

if (!$bandhakId || strlen($mobile) < 10) {
    jsonResponse(['success'=>false, 'msg'=>'Valid Bandhak ID aur 10-digit mobile daalo']);
}

// Find customer — match last 10 digits of stored mobile
$stmt = $pdo->prepare("SELECT c.*, pe.bandhak_id as pawn_bdid FROM customers c JOIN pawn_entries pe ON pe.customer_id=c.id WHERE pe.bandhak_id=? AND pe.status!='deleted' LIMIT 1");
$stmt->execute([$bandhakId]);
$cust = $stmt->fetch();

if (!$cust) {
    jsonResponse(['success'=>false, 'msg'=>'Bandhak ID nahi mila']);
}

// Normalize stored mobile
$storedMobile = preg_replace('/\D/', '', $cust['mobile']);
$storedMobile = substr($storedMobile, -10);

if ($storedMobile !== $mobile) {
    jsonResponse(['success'=>false, 'msg'=>'Mobile number match nahi hua. Registered mobile use karo.']);
}

// Generate 6-digit OTP
$otp     = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

// Invalidate old OTPs
$pdo->prepare("UPDATE customer_otps SET is_used=1 WHERE bandhak_id=? AND is_used=0")->execute([$bandhakId]);

// Insert new OTP
$pdo->prepare("INSERT INTO customer_otps (bandhak_id, mobile, otp, expires_at) VALUES (?,?,?,?)")
    ->execute([$bandhakId, $mobile, $otp, $expires]);

// TODO: Real SMS gateway — Fast2SMS / MSG91 / Twilio
// Example Fast2SMS:
// $apiKey = 'YOUR_FAST2SMS_KEY';
// $url = "https://www.fast2sms.com/dev/bulkV2?authorization=$apiKey&route=otp&variables_values=$otp&flash=0&numbers=$mobile";
// file_get_contents($url);

// For development — log OTP
error_log("=== DIGITAL BANDHAK OTP === Bandhak: $bandhakId | Mobile: $mobile | OTP: $otp | Expires: $expires");

// DEV MODE — return OTP in response (REMOVE IN PRODUCTION!)
jsonResponse([
    'success'  => true,
    'msg'      => 'OTP sent to +91 XXXXXX' . substr($mobile, -4),
    'dev_otp'  => $otp, // ← REMOVE THIS LINE IN PRODUCTION
]);
