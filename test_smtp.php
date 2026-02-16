<?php
/**
 * SMTP Configuration Test Script
 *
 * This script tests your SMTP configuration and helps identify connection issues.
 * Run this from command line: php test_smtp.php
 * Or access via browser: https://yourdomain.com/test_smtp.php
 *
 * SECURITY WARNING: Delete this file after testing!
 */

require_once __DIR__ . '/config.php';

echo "=== SMTP Configuration Test ===\n\n";

// Check if .env file exists
if (!file_exists(__DIR__ . '/.env')) {
    echo "❌ ERROR: .env file not found!\n";
    echo "   Please copy .env.example to .env and configure it.\n\n";
    exit(1);
}

echo "✅ .env file found\n\n";

// Display configuration (masked password)
echo "--- Current Configuration ---\n";
echo "SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT SET') . "\n";
echo "SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NOT SET') . "\n";
echo "SMTP_SECURE: " . (defined('SMTP_SECURE') ? SMTP_SECURE : 'NOT SET') . "\n";
echo "SMTP_USER: " . (defined('SMTP_USER') ? SMTP_USER : 'NOT SET') . "\n";
echo "SMTP_PASS: " . (defined('SMTP_PASS') && !empty(SMTP_PASS) ? str_repeat('*', min(strlen(SMTP_PASS), 20)) : 'NOT SET') . "\n";
echo "SMTP_FROM_EMAIL: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'NOT SET') . "\n";
echo "SMTP_FROM_NAME: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NOT SET') . "\n\n";

// Check if configuration is complete
if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
    echo "❌ ERROR: SMTP configuration is incomplete!\n";
    echo "   Please set SMTP_HOST, SMTP_USER, and SMTP_PASS in .env file.\n\n";
    exit(1);
}

echo "✅ Basic configuration looks complete\n\n";

// Test with PHPMailer
echo "--- Testing SMTP Connection ---\n";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2; // 0 = off, 1 = client, 2 = client and server
    $mail->Debugoutput = function($str, $level) {
        echo "  [DEBUG] $str\n";
    };

    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';

    // Set sender
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

    // Set recipient (change this to your email for testing!)
    $testEmail = SMTP_USER; // Send test to yourself
    $mail->addAddress($testEmail);

    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test - MikesMarkdown';
    $mail->Body = '<h1>Test erfolgreich!</h1><p>Ihre SMTP-Konfiguration funktioniert korrekt.</p>';
    $mail->AltBody = 'Test erfolgreich! Ihre SMTP-Konfiguration funktioniert korrekt.';

    echo "\nAttempting to send test email to: $testEmail\n\n";

    $mail->send();

    echo "\n✅ SUCCESS! Test email sent successfully!\n";
    echo "   Check your inbox at: $testEmail\n\n";
    echo "=== Test Complete ===\n";
    echo "Your SMTP configuration is working correctly.\n";
    echo "⚠️  IMPORTANT: Delete this test_smtp.php file for security!\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: Failed to send test email\n";
    echo "   Error: {$mail->ErrorInfo}\n";
    echo "   Exception: {$e->getMessage()}\n\n";

    echo "--- Troubleshooting Tips ---\n";

    if (strpos($e->getMessage(), 'Could not authenticate') !== false) {
        echo "❌ Authentication Error\n";
        echo "   Possible causes:\n";
        echo "   1. Wrong password in .env file (SMTP_PASS)\n";
        echo "   2. Wrong username in .env file (SMTP_USER)\n";
        echo "   3. Email account 'passwort@mikesmarkdown.de' doesn't exist\n";
        echo "   4. Two-factor authentication enabled (need app password)\n\n";
    } elseif (strpos($e->getMessage(), 'connect') !== false) {
        echo "❌ Connection Error\n";
        echo "   Possible causes:\n";
        echo "   1. Wrong SMTP server address\n";
        echo "   2. Wrong port number\n";
        echo "   3. Firewall blocking outgoing connections\n";
        echo "   4. SSL/TLS settings incorrect\n\n";
    } else {
        echo "   Check the error message above for details.\n\n";
    }

    echo "=== Recommended Settings for KAS Server (All-Inkl) ===\n";
    echo "SMTP_HOST=w01faadd.kasserver.com\n";
    echo "SMTP_PORT=465\n";
    echo "SMTP_SECURE=ssl\n";
    echo "SMTP_USER=passwort@mikesmarkdown.de\n";
    echo "SMTP_PASS=your_actual_email_password\n\n";

    exit(1);
}
