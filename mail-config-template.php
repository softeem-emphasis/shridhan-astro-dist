<?php
// SECURITY: Prevent direct access to this file
if (!defined('MAIL_CONFIG_INCLUDED')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// SMTP Configuration
define('SMTP_HOST', 'INSERT_SMTP_HOST_HERE'); // e.g. smtp.office365.com or mail.yourdomain.com
define('SMTP_PORT', 587); // 587 (TLS) or 465 (SSL)
define('SMTP_USER', 'INSERT_EMAIL_ADDRESS_HERE'); // Your full email address
define('SMTP_PASS', 'INSERT_PASSWORD_HERE'); // App Password recommended
define('SMTP_FROM_EMAIL', 'INSERT_EMAIL_ADDRESS_HERE'); // Usually same as SMTP_USER
define('SMTP_FROM_NAME', 'INSERT_SENDER_NAME_HERE'); // e.g. Website Contact Form

// Destination Email Address (Where the form submissions go)
define('CONTACT_FORM_RECIPIENT', 'INSERT_DESTINATION_EMAIL_HERE'); 
?>
