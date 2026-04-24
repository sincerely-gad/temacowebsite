<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

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
$emailContent .= "\n---\n";
$emailContent .= "Sent from: TEMACO Builders Website Contact Form\n";
$emailContent .= "Time: " . date('Y-m-d H:i:s') . "\n";

// For localhost testing - simulate email sending
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
    strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false) {
    
    // Log the email to a file for localhost testing
    $logFile = __DIR__ . '/email_log.txt';
    $logEntry = "\n" . str_repeat('=', 50) . "\n";
    $logEntry .= "EMAIL SENT: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "FROM: info@temaco.rw\n";
    $logEntry .= "TO: {$to}\n";
    $logEntry .= "SUBJECT: {$subject}\n";
    $logEntry .= str_repeat('-', 30) . "\n";
    $logEntry .= $emailContent;
    $logEntry .= "\n" . str_repeat('=', 50) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Return success for localhost
    echo json_encode([
        'status' => 'success', 
        'message' => "Thank you, {$safeName}. Your message has been logged for testing. Check email_log.txt file.",
        'debug' => 'Localhost testing mode - email logged to file'
    ]);
    exit;
}

// For live hosting - use PHP mail function
$headers = array();
$headers[] = "From: TEMACO Builders Contact Form <info@temaco.rw>";
$headers[] = "Reply-To: {$safeName} <{$safeEmail}>";
$headers[] = "X-Mailer: PHP/" . phpversion();
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";

$mailSuccess = mail($to, $subject, $emailContent, implode("\r\n", $headers));

if ($mailSuccess) {
    echo json_encode([
        'status' => 'success', 
        'message' => "Thank you, {$safeName}. We'll get back to you soon!",
        'method' => 'php_mail'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unable to send email. Please try again or contact us directly at info@temacobuilders.com',
        'debug' => 'PHP mail function failed'
    ]);
}
?>