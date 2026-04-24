<?php
/**
 * SMTP Configuration for Microsoft 365 (Outlook)
 * 
 * This file contains SMTP settings for sending emails via Microsoft 365.
 * Environment variables take precedence over hardcoded constants.
 */

// SMTP Configuration Constants
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'mail.temacobuilders.rw');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 465);
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'ssl');
define('SMTP_AUTH', $_ENV['SMTP_AUTH'] ?? true);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? 'info@temacobuilders.rw');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? 'YOUR_EMAIL_PASSWORD');
define('FROM_EMAIL', $_ENV['FROM_EMAIL'] ?? 'info@temacobuilders.rw');
define('FROM_NAME', $_ENV['FROM_NAME'] ?? 'TEMACO Builders');
define('TO_EMAIL', $_ENV['TO_EMAIL'] ?? 'info@temacobuilders.rw');

/**
 * Helper function to output JSON response
 * 
 * @param array $data Response data
 * @param int $code HTTP status code
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get SMTP configuration as array
 * 
 * @return array SMTP settings
 */
function get_smtp_config() {
    return [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'secure' => SMTP_SECURE,
        'auth' => SMTP_AUTH,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'from_email' => FROM_EMAIL,
        'from_name' => FROM_NAME,
        'to_email' => TO_EMAIL
    ];
}

/**
 * Validate SMTP configuration
 * 
 * @return bool True if config is valid
 */
function validate_smtp_config() {
    $config = get_smtp_config();
    
    if (empty($config['username']) || $config['username'] === 'noreply@temacobuilders.com') {
        return false;
    }
    
    if (empty($config['password']) || $config['password'] === 'YOUR_PASSWORD_HERE') {
        return false;
    }
    
    return true;
}
?>
