<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Destination email
// Destination email
$to = 'info@temacobuilders.com';

// Collect and sanitize inputs
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$project = isset($_POST['project']) ? trim($_POST['project']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Basic validation
if ($name === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Name and email are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
    exit;
}

// Build the message
$safeName = str_replace(["\r", "\n"], ' ', $name);
$safeEmail = str_replace(["\r", "\n"], '', $email);
$subject = "New Contact Form Submission from {$safeName}";
$emailContent = "You received a new contact form submission.\n\n";
$emailContent .= "Name: {$safeName}\n";
$emailContent .= "Email: {$safeEmail}\n";
$emailContent .= ($phone !== '' ? "Phone: {$phone}\n" : '');
$emailContent .= ($project !== '' ? "Project Type: {$project}\n" : '');
$emailContent .= "\nMessage:\n{$message}\n";

// SMTP-only mode
$smtpConfigPath = __DIR__ . '/smtp_config.php';
if (!file_exists($smtpConfigPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'SMTP is not configured. Please create smtp_config.php.']);
    exit;
}

$cfg = include $smtpConfigPath;
if (!is_array($cfg) || empty($cfg['host']) || empty($cfg['username']) || empty($cfg['password'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'SMTP config incomplete.']);
    exit;
}

// PHPMailer is already loaded via require statements above

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $cfg['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $cfg['username'];
    $mail->Password = $cfg['password'];
    $mail->SMTPDebug = 0; // Set to 2 for detailed debug output
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $secure = isset($cfg['secure']) ? $cfg['secure'] : 'ssl';
    if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Port = isset($cfg['port']) ? (int)$cfg['port'] : 465;

    $fromEmail = $cfg['from_email'] ?? 'no-reply@temacobuilders.com';
    $fromName = $cfg['from_name'] ?? 'TEMACO Builders';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($to);
    $mail->addReplyTo($safeEmail, $safeName);

    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body = $emailContent;

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => "Thank you, {$safeName}. We'll get back to you soon."]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'SMTP Error: ' . $mail->ErrorInfo]);
    exit;
}
?>