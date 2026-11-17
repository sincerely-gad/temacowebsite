<?php
// SMTP configuration for the TEMACO contact form.
// Set SMTP_PASSWORD in the hosting environment; never commit the password.

return [
    'host' => 'localhost',                 // On cPanel hosting, use localhost
    'port' => 587,                         // TLS port
    'secure' => 'tls',                     // TLS encryption
    'username' => getenv('SMTP_USERNAME') ?: 'info@temaco.rw',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'from_email' => 'info@temaco.rw',      // Use the actual email account
    'from_name' => 'TEMACO Builders Contact Form',
];

?>




