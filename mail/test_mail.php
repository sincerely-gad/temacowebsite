<?php
/**
 * SMTP Test Script for Microsoft 365
 * Sends a test email to verify SMTP configuration
 */

require_once 'config.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Get recipient from query parameter or use default
$to_email = $_GET['to'] ?? TO_EMAIL;

if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
    die('<h2>Error: Invalid email address</h2>');
}

echo "<h2>SMTP Test - Microsoft 365</h2>";
echo "<p><strong>Testing email to:</strong> {$to_email}</p>";

// Display current configuration
$config = get_smtp_config();
echo "<h3>Current SMTP Configuration:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> {$config['host']}</li>";
echo "<li><strong>Port:</strong> {$config['port']}</li>";
echo "<li><strong>Security:</strong> {$config['secure']}</li>";
echo "<li><strong>Username:</strong> {$config['username']}</li>";
echo "<li><strong>Password:</strong> " . (empty($config['password']) || $config['password'] === 'YOUR_PASSWORD_HERE' ? '<span style="color:red;">NOT SET</span>' : '<span style="color:green;">SET</span>') . "</li>";
echo "</ul>";

// Validate configuration
if (!validate_smtp_config()) {
    echo '<div style="color:red; background:#ffebee; padding:10px; border:1px solid #f44336; margin:10px 0;">';
    echo '<strong>❌ Configuration Error:</strong> Please update config.php with valid Microsoft 365 credentials.';
    echo '</div>';
    exit;
}

echo "<h3>Sending Test Email...</h3>";

try {
    $mail = new PHPMailer(true);
    
    // Enable verbose debug output (set to 0 to disable)
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';
    
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
    
    // Email content
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($to_email);
    
    $mail->isHTML(false);
    $mail->Subject = 'SMTP Test - ' . date('Y-m-d H:i:s');
    $mail->Body = "This is a test email sent from TEMACO Builders website.\n\n";
    $mail->Body .= "SMTP Configuration Test Results:\n";
    $mail->Body .= "- Host: {$config['host']}\n";
    $mail->Body .= "- Port: {$config['port']}\n";
    $mail->Body .= "- Security: {$config['secure']}\n";
    $mail->Body .= "- Username: {$config['username']}\n";
    $mail->Body .= "- Test Time: " . date('Y-m-d H:i:s') . "\n\n";
    $mail->Body .= "If you received this email, your SMTP configuration is working correctly!";
    
    $mail->send();
    
    echo '<div style="color:green; background:#e8f5e8; padding:15px; border:1px solid #4caf50; margin:15px 0;">';
    echo '<h3>✅ SUCCESS!</h3>';
    echo '<p>Test email sent successfully to <strong>' . $to_email . '</strong></p>';
    echo '<p>Check your inbox to confirm delivery.</p>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div style="color:red; background:#ffebee; padding:15px; border:1px solid #f44336; margin:15px 0;">';
    echo '<h3>❌ ERROR</h3>';
    echo '<p><strong>PHPMailer Error:</strong> ' . $mail->ErrorInfo . '</p>';
    echo '<p><strong>Exception:</strong> ' . $e->getMessage() . '</p>';
    echo '</div>';
    
    echo '<h3>Troubleshooting Tips:</h3>';
    echo '<ul>';
    echo '<li>Verify your Microsoft 365 username and password are correct</li>';
    echo '<li>If MFA is enabled, use an App Password instead of your regular password</li>';
    echo '<li>Ensure SMTP authentication is enabled in Microsoft 365 admin center</li>';
    echo '<li>Check that your account has permission to send emails via SMTP</li>';
    echo '<li>Verify the from email address exists in your Microsoft 365 tenant</li>';
    echo '</ul>';
}

echo '<hr>';
echo '<p><a href="?to=' . urlencode($to_email) . '">🔄 Test Again</a> | ';
echo '<a href="../public/contact.html">📧 Test Contact Form</a></p>';
?>
