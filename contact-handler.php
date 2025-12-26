<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- BASIC RATE LIMITING ---
    session_start();
    if (isset($_SESSION['last_submission']) && (time() - $_SESSION['last_submission']) < 60) {
        http_response_code(429); // Too Many Requests
        echo json_encode(['status' => 'error', 'message' => 'Please wait a moment before submitting another message.']);
        exit;
    }

    // --- HONEYPOT SPAM CHECK ---
    // If the hidden 'fax' field is filled out, it's likely a bot.
    if (!empty($_POST['fax'])) {
        echo json_encode(['status' => 'success', 'message' => 'Message sent successfully!']); // Pretend it was successful
        exit;
    }

    // --- CONFIGURATION ---
    // Set the recipient email address
    $recipient_email = CONTACT_FORM_RECIPIENT; // Defined in mail-config.php
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
            // Silently reject spam but pretend success
            echo json_encode(['status' => 'success', 'message' => 'Message sent successfully!']);
            exit;
        }
    }

    if (!empty($errors)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
        exit;
    }

    // --- EMAIL COMPOSITION & SENDING (PHPMailer) ---
    
    // Check if configuration exists
    if (!file_exists('mail-config.php')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: mail-config.php is missing.']);
        exit;
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
        echo json_encode(['status' => 'success', 'message' => 'Thank you! Your message has been sent.']);

    } catch (Exception $e) {
        http_response_code(500);
        // Log the actual error to server error log, but don't show to user
        error_log("Mailer Error: {$mail->ErrorInfo}");
        echo json_encode(['status' => 'error', 'message' => 'Sorry, the message could not be sent. Please try again later.']);
    }

} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
}
exit;
?>