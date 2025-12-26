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
    $recipient_email = "marketing@shridhan.com"; // <-- IMPORTANT: Replace with your email address
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

    // --- EMAIL COMPOSITION ---
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

    // --- EMAIL HEADERS (Optimized for deliverability) ---
    $headers = [];
    $headers[] = "From: Shridhan Contact Form <noreply@softeem.ca>";
    $headers[] = "Reply-To: " . $name . " <" . $email . ">";
    $headers[] = "Return-Path: noreply@softeem.ca"; // Important for bounce handling
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";

    // --- SEND EMAIL ---
    if (mail($recipient_email, $subject, $email_body, implode("\r\n", $headers))) {
        $_SESSION['last_submission'] = time();
        echo json_encode(['status' => 'success', 'message' => 'Thank you! Your message has been sent.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Sorry, there was a server error. Please try again later.']);
    }

} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
}
exit;
?>