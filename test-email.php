<?php
// test-email.php - Diagnostics Tool
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>SMTP Diagnostics</h1>";

// 1. Check Config
echo "<h2>1. Configuration Check</h2>";
if (file_exists('mail-config.php')) {
    echo "<p style='color:green'>[PASS] mail-config.php found.</p>";
    define('MAIL_CONFIG_INCLUDED', true); // Security: Allow config file to load
    require 'mail-config.php';
    echo "SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT DEFINED') . "<br>";
    echo "SMTP_USER: " . (defined('SMTP_USER') ? SMTP_USER : 'NOT DEFINED') . "<br>";
    echo "CONTACT_FORM_RECIPIENT: " . (defined('CONTACT_FORM_RECIPIENT') ? CONTACT_FORM_RECIPIENT : 'NOT DEFINED') . "<br>";
} else {
    echo "<p style='color:red'>[FAIL] mail-config.php NOT FOUND. Please upload it.</p>";
}

// 2. Check PHPMailer Files
echo "<h2>2. PHPMailer Files Check</h2>";
$files = [
    'vendor/PHPMailer/src/Exception.php',
    'vendor/PHPMailer/src/PHPMailer.php',
    'vendor/PHPMailer/src/SMTP.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color:green'>[PASS] Found $file</p>";
    } else {
        echo "<p style='color:red'>[FAIL] Missing $file</p>";
    }
}

// 3. Try to Load and Send
echo "<h2>3. Sending Test</h2>";
require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress(CONTACT_FORM_RECIPIENT);

    $mail->isHTML(false);
    $mail->Subject = 'Test Email from Diagnostics Script';
    $mail->Body    = 'This is a test email to verify SMTP settings.';

    $mail->send();
    echo "<p style='color:green; font-weight:bold'>[SUCCESS] Email sent successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red; font-weight:bold'>[ERROR] Message could not be sent.</p>";
    echo "Mailer Error: " . htmlspecialchars($mail->ErrorInfo);
}
?>
