<?php
// Production Contact Form Handler
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Simple JSON sender
function sendJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJson(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

// Load config
if (!file_exists('mail-config.php')) {
    sendJson(['status' => 'error', 'message' => 'Config missing'], 500);
}
define('MAIL_CONFIG_INCLUDED', true); // Security: Allow config file to load
require 'mail-config.php';

// Honeypot spam check - if filled, it's a bot
if (!empty($_POST['website'])) {
    // Pretend success to fool the bot, but don't send email
    sendJson(['status' => 'success', 'message' => 'Message sent!']);
}

// Get form data
$name = trim($_POST["name"] ?? '');
$email = trim($_POST["email"] ?? '');
$phone = trim($_POST["phone"] ?? '');
$message = trim($_POST["message"] ?? '');

// Basic validation
if (empty($name) || empty($email) || empty($message)) {
    sendJson(['status' => 'error', 'message' => 'All fields required'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJson(['status' => 'error', 'message' => 'Invalid email'], 400);
}

// Load PHPMailer
require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // SMTP setup
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
    $mail->Subject = "Message from Shridhan website";
    
    // Build email body
    $body = "Name: $name\nEmail: $email\n";
    if (!empty($phone)) {
        $body .= "Phone: $phone\n";
    }
    $body .= "\nMessage:\n$message";
    
    $mail->Body = $body;

    $mail->send();
    sendJson(['status' => 'success', 'message' => 'Message sent!']);

} catch (Exception $e) {
    // Log to custom file for easy debugging
    $log_entry = date('[Y-m-d H:i:s]') . " Mailer Error: {$mail->ErrorInfo}\n";
    file_put_contents(__DIR__ . '/contact-errors.log', $log_entry, FILE_APPEND);
    
    sendJson(['status' => 'error', 'message' => 'Message could not be sent. Please try again later.'], 500);
}