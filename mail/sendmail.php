<?php
/**
 * Email Sender for Contact Form
 * Handles POST requests and sends emails via Microsoft 365 SMTP
 */

require_once 'config.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Only POST method allowed'], 405);
}

// Validate SMTP configuration
if (!validate_smtp_config()) {
    json_response(['ok' => false, 'error' => 'SMTP configuration incomplete. Please update config.php with valid credentials.'], 500);
}

// Get and validate input data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

// Basic validation
if (empty($name) || empty($email) || empty($message)) {
    json_response(['ok' => false, 'error' => 'Name, email, and message are required fields.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Please provide a valid email address.'], 400);
}

// Sanitize inputs to prevent header injection
$name = str_replace(["\r", "\n", "\t"], ' ', $name);
$email = str_replace(["\r", "\n", "\t"], '', $email);
$message = str_replace(["\r\n", "\r", "\n"], "\n", $message);

// Get SMTP configuration
$config = get_smtp_config();

// Prepare email content
$subject = "New Contact Form Submission from {$name}";
$emailBody = "You have received a new contact form submission.\n\n";
$emailBody .= "Name: {$name}\n";
$emailBody .= "Email: {$email}\n";
$emailBody .= "Message:\n{$message}\n\n";
$emailBody .= "---\n";
$emailBody .= "Sent from TEMACO Builders website contact form\n";
$emailBody .= "Date: " . date('Y-m-d H:i:s') . "\n";

try {
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = $config['auth'];
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = $config['secure'];
    $mail->Port = $config['port'];
    
    // SSL/TLS options for Microsoft 365
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    // Email settings
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email']);
    $mail->addReplyTo($email, $name);
    
    // Content
    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body = $emailBody;
    
    // Send email
    $mail->send();
    
    json_response(['ok' => true, 'message' => "Thank you, {$name}! Your message has been sent successfully."]);
    
} catch (Exception $e) {
    // Log error for debugging (in production, log to file)
    error_log("PHPMailer Error: " . $mail->ErrorInfo);
    
    json_response(['ok' => false, 'error' => 'Failed to send email. Please try again later.'], 500);
}
?>
