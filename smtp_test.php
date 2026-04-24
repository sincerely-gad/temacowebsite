<?php
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "<h2>SMTP Configuration Test</h2>";

// Load config
$cfg = include 'smtp_config.php';

echo "<h3>Current Configuration:</h3>";
echo "<ul>";
echo "<li>Host: " . $cfg['host'] . "</li>";
echo "<li>Port: " . $cfg['port'] . "</li>";
echo "<li>Username: " . $cfg['username'] . "</li>";
echo "<li>Password: " . (empty($cfg['password']) ? 'NOT SET' : '***SET***') . "</li>";
echo "<li>Security: " . $cfg['secure'] . "</li>";
echo "</ul>";

echo "<h3>Testing SMTP Connection...</h3>";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->Host = $cfg['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $cfg['username'];
    $mail->Password = $cfg['password'];
    
    // Try different SSL options
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    if ($cfg['secure'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Port = $cfg['port'];

    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addAddress('info@temacobuilders.com');
    
    $mail->isHTML(false);
    $mail->Subject = 'SMTP Test - ' . date('Y-m-d H:i:s');
    $mail->Body = 'This is a test email to verify SMTP configuration is working.';

    $mail->send();
    echo "<p style='color: green;'><strong>✅ SUCCESS!</strong> Email sent successfully.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ ERROR:</strong> " . $mail->ErrorInfo . "</p>";
    echo "<p><strong>Exception:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Alternative Configurations to Try:</h3>";
echo "<p>If the above failed, try these settings in smtp_config.php:</p>";

echo "<h4>Option 1: Standard SSL (Port 465)</h4>";
echo "<pre>
'host' => 'mail.temacobuilders.com',
'port' => 465,
'secure' => 'ssl',
</pre>";

echo "<h4>Option 2: TLS (Port 587)</h4>";
echo "<pre>
'host' => 'mail.temacobuilders.com',
'port' => 587,
'secure' => 'tls',
</pre>";

echo "<h4>Option 3: Alternative Host</h4>";
echo "<pre>
'host' => 'smtp.temacobuilders.com',
'port' => 465,
'secure' => 'ssl',
</pre>";
?>
