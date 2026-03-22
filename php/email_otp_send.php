<?php
// php/email_otp_send.php
require_once '../includes/config.php';

$email   = trim($_POST['email'] ?? '');
$purpose = trim($_POST['purpose'] ?? 'forgot_password');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success'=>false,'msg'=>'Valid email daalo']);
}

// Check if email exists
$adminRow = $pdo->prepare("SELECT id,'admin' as type FROM super_admin WHERE email=?"); $adminRow->execute([$email]); $found=$adminRow->fetch();
if (!$found) {
    $shopRow = $pdo->prepare("SELECT id,'shop' as type FROM shops WHERE owner_email=?"); $shopRow->execute([$email]); $found=$shopRow->fetch();
}
if (!$found) {
    jsonResponse(['success'=>false,'msg'=>'Yeh email registered nahi hai']);
}

// Generate OTP
$otp     = str_pad(mt_rand(0,999999),6,'0',STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time()+600);

// Ensure table
try { $pdo->exec("CREATE TABLE IF NOT EXISTS email_otps (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(150) NOT NULL, otp VARCHAR(10) NOT NULL, purpose VARCHAR(50) DEFAULT 'forgot_password', expires_at DATETIME NOT NULL, is_used TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"); } catch(Exception $e){}

$pdo->prepare("UPDATE email_otps SET is_used=1 WHERE email=? AND purpose=? AND is_used=0")->execute([$email,$purpose]);
$pdo->prepare("INSERT INTO email_otps (email,otp,purpose,expires_at) VALUES (?,?,?,?)")->execute([$email,$otp,$purpose,$expires]);

$mailSent = false;
$mailError = '';

// Try PHPMailer first, fallback to PHP mail()
$smtpHost = defined('SMTP_HOST') ? SMTP_HOST : '';
$smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
$smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';

if ($smtpHost && $smtpUser && file_exists(dirname(__DIR__).'/vendor/phpmailer/src/PHPMailer.php')) {
    // PHPMailer
    require dirname(__DIR__).'/vendor/phpmailer/src/PHPMailer.php';
    require dirname(__DIR__).'/vendor/phpmailer/src/SMTP.php';
    require dirname(__DIR__).'/vendor/phpmailer/src/Exception.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $mail->setFrom($smtpUser, 'Digital Bandhak');
        $mail->addAddress($email);
        $mail->Subject = 'Digital Bandhak — OTP: '.$otp;
        $mail->Body    = "Aapka OTP hai: <b>$otp</b><br/><br/>Yeh OTP 10 minute mein expire ho jaayega.<br/><br/>Digital Bandhak Team";
        $mail->isHTML(true);
        $mail->send();
        $mailSent = true;
    } catch(Exception $e) {
        $mailError = $e->getMessage();
    }
}

if (!$mailSent) {
    // Fallback: PHP mail()
    $subject = 'Digital Bandhak OTP: '.$otp;
    $body    = "Aapka OTP: $otp\n\nYeh OTP 10 minute mein expire ho jaayega.\n\nDigital Bandhak Team";
    $headers = 'From: noreply@digitalbandhak.in'."\r\n".'Content-Type: text/plain; charset=UTF-8';
    $mailSent = @mail($email, $subject, $body, $headers);
}

// Log for dev
error_log("=== DB EMAIL OTP === Email:$email | OTP:$otp | Sent:".($mailSent?'yes':'no').($mailError?" | Error:$mailError":''));

jsonResponse([
    'success'   => true,
    'msg'       => 'OTP sent to '.substr($email,0,3).'***'.strstr($email,'@'),
    'dev_otp'   => $otp,        // REMOVE IN PRODUCTION
    'mail_sent' => $mailSent,
    'mail_err'  => $mailError ?: null,
]);
