<?php
// Custom Error Handling to return JSON instead of HTML/Text text
ini_set('display_errors', 0); // Don't print locally
error_reporting(E_ALL);       // Report everything

// 1. Handle Warnings/Notices
function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    // Check if error was suppressed with @
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    // clean buffers
    while (ob_get_level()) { ob_end_clean(); }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error', 
        'message' => "PHP Error ($errno): $errstr in " . basename($errfile) . ":$errline"
    ]);
    exit;
}
set_error_handler("jsonErrorHandler");

// 2. Handle Fatal Errors (Shutdown)
function jsonShutdownHandler() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        // clean buffers
        while (ob_get_level()) { ob_end_clean(); }
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => "Fatal System Error: {$error['message']} in " . basename($error['file']) . ":{$error['line']}"
        ]);
    }
}
register_shutdown_function("jsonShutdownHandler");

// Start output buffering to catch any stray text before JSON by default
ob_start();

// Helper to send clean JSON response
function sendJson($data, $code = 200) {
    // Discard any buffered output (warnings, whitespace from includes, etc.)
    while (ob_get_level()) { ob_clean(); }
    
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Set the content type to JSON (initial header, though sendJson will override)
header('Content-Type: application/json');

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- BASIC RATE LIMITING ---
    session_start();
    if (isset($_SESSION['last_submission']) && (time() - $_SESSION['last_submission']) < 60) {
        sendJson(['status' => 'error', 'message' => 'Please wait a moment before submitting another message.'], 429);
    }

    // --- HONEYPOT SPAM CHECK ---
    // If the hidden 'fax' field is filled out, it's likely a bot.
    if (!empty($_POST['fax'])) {
        sendJson(['status' => 'success', 'message' => 'Message sent successfully!']);
    }

    // --- CONFIGURATION ---
    // Set the recipient email address
    // Use the constant if defined, otherwise fallback or error
    $recipient_email = defined('CONTACT_FORM_RECIPIENT') ? CONTACT_FORM_RECIPIENT : 'marketing@shridhan.com';
    // Set the subject of the email
    $subject = "New Contact Form Submission from Shridhan Website";

    // --- DATA SANITIZATION ---
    $name = htmlspecialchars(strip_tags(trim($_POST["name"] ?? '')), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(strip_tags(trim($_POST["phone"] ?? '')), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(strip_tags(trim($_POST["message"] ?? '')), ENT_QUOTES, 'UTF-8');

    // --- VALIDATION ---
    $errors = [];
    if (empty($name) || strlen($name) < 2) { $errors[] = "Name is required."; }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "A valid email is required."; }
    if (empty($message) || strlen($message) < 10) { $errors[] = "Message must be at least 10 characters."; }
    if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $phone)) { $errors[] = "Please enter a valid phone number."; }

    // Basic spam content detection
    $spam_keywords = ['viagra', 'cialis', 'casino', 'lottery', 'winner', '[url=', '[link='];
    $content_to_check = strtolower($name . ' ' . $message);
    foreach ($spam_keywords as $keyword) {
        if (strpos($content_to_check, $keyword) !== false) {
            // Silently reject spam
            sendJson(['status' => 'success', 'message' => 'Message sent successfully!']);
        }
    }

    if (!empty($errors)) {
        sendJson(['status' => 'error', 'message' => implode(' ', $errors)], 400);
    }

    // --- EMAIL COMPOSITION & SENDING (PHPMailer) ---
    
    // Check if configuration exists
    if (!file_exists('mail-config.php')) {
        sendJson(['status' => 'error', 'message' => 'Server configuration error: mail-config.php is missing.'], 500);
    }

    // Load PHPMailer manually (since we don't have Composer autoload)
    require 'vendor/PHPMailer/src/Exception.php';
    require 'vendor/PHPMailer/src/PHPMailer.php';
    require 'vendor/PHPMailer/src/SMTP.php';
    require 'mail-config.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        //Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipient_email);
        $mail->addReplyTo($email, $name);

        //Content
        $mail->isHTML(false); // Plain text for now
        $mail->Subject = $subject;
        
        // Construct Body
        $email_body = "You have received a new message from your website contact form.\n\n";
        $email_body .= "Here are the details:\n\n";
        $email_body .= "================\n";
        $email_body .= "Name: " . $name . "\n";
        $email_body .= "Email: " . $email . "\n";
        if (!empty($phone)) {
            $email_body .= "Phone: " . $phone . "\n";
        }
        $email_body .= "Submitted: " . date('F j, Y, g:i a T') . "\n";
        $email_body .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Not available') . "\n\n";
        $email_body .= "Message:\n";
        $email_body .= "========\n";
        $email_body .= $message . "\n";

        $mail->Body = $email_body;

        $mail->send();
        
        // Success
        $_SESSION['last_submission'] = time();
        sendJson(['status' => 'success', 'message' => 'Thank you! Your message has been sent.']);

    } catch (Exception $e) {
        // Log the actual error to server error log, but don't show to user
        error_log("Mailer Error: {$mail->ErrorInfo}");
        sendJson(['status' => 'error', 'message' => 'Sorry, the message could not be sent. Please try again later.'], 500);
    }

} else {
    sendJson(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}
?>