<?php
/**
 * E-Mail Funktionen mit PHPMailer
 *
 * Sendet transaktionale E-Mails (Passwort-Reset, etc.)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sendet eine Passwort-Reset-E-Mail
 *
 * @param string $email Empfänger E-Mail-Adresse
 * @param string $token Reset-Token
 * @return void
 * @throws Exception Wenn E-Mail nicht gesendet werden konnte
 */
function sendPasswordResetEmail(string $email, string $token): void
{
    // Validate SMTP configuration before attempting to send
    if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
        $logger = getLogger();
        $logger->error('SMTP configuration incomplete', [
            'smtp_host_set' => !empty(SMTP_HOST),
            'smtp_user_set' => !empty(SMTP_USER),
            'smtp_pass_set' => !empty(SMTP_PASS),
        ]);
        throw new Exception('SMTP-Konfiguration ist unvollständig. Bitte prüfen Sie die Einstellungen in der .env Datei.');
    }

    require_once __DIR__ . '/vendor/autoload.php';

    $mail = new PHPMailer(true);

    try {
        // SMTP-Konfiguration
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE; // 'tls' oder 'ssl'
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Enable detailed debug output for troubleshooting
        // 0 = off, 1 = client messages, 2 = client and server messages
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        $mail->Debugoutput = function($str, $level) {
            $logger = getLogger();
            $logger->debug('SMTP Debug', ['message' => $str, 'level' => $level]);
        };

        // Absender
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Empfänger
        $mail->addAddress($email);

        // E-Mail-Inhalt
        $resetLink = APP_URL . '?reset_token=' . urlencode($token);

        $mail->isHTML(true);
        $mail->Subject = 'Passwort zurücksetzen - MikesMarkdown';

        // HTML-Version
        $mail->Body = '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">🔐 Passwort zurücksetzen</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px; margin-bottom: 20px;">Hallo,</p>

        <p style="font-size: 16px; margin-bottom: 20px;">
            Sie haben einen Passwort-Reset für Ihr MikesMarkdown-Konto angefordert.
        </p>

        <p style="font-size: 16px; margin-bottom: 30px;">
            Klicken Sie auf den folgenden Button, um Ihr Passwort zurückzusetzen:
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="' . htmlspecialchars($resetLink) . '"
               style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                      color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px;
                      font-weight: 600; font-size: 16px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                Passwort zurücksetzen
            </a>
        </div>

        <p style="font-size: 14px; color: #6c757d; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            <strong>Wichtig:</strong> Dieser Link ist nur 1 Stunde gültig.
        </p>

        <p style="font-size: 14px; color: #6c757d; margin-bottom: 10px;">
            Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.
            Ihr Passwort bleibt unverändert.
        </p>

        <p style="font-size: 12px; color: #adb5bd; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
            <a href="' . htmlspecialchars($resetLink) . '" style="color: #667eea; word-break: break-all;">' . htmlspecialchars($resetLink) . '</a>
        </p>

        <div style="margin-top: 40px; text-align: center; color: #6c757d; font-size: 14px;">
            <p style="margin: 5px 0;">Viele Grüße,</p>
            <p style="margin: 5px 0; font-weight: 600;">Das MikesMarkdown Team</p>
        </div>
    </div>

    <div style="text-align: center; margin-top: 20px; padding: 20px; color: #adb5bd; font-size: 12px;">
        <p style="margin: 5px 0;">MikesMarkdown - Life-altering Notes</p>
        <p style="margin: 5px 0;">
            <a href="https://mikesmarkdown.de" style="color: #667eea; text-decoration: none;">mikesmarkdown.de</a>
        </p>
    </div>
</body>
</html>';

        // Plain-Text-Alternative für E-Mail-Clients ohne HTML
        $mail->AltBody = "Passwort zurücksetzen\n\n" .
                        "Hallo,\n\n" .
                        "Sie haben einen Passwort-Reset für Ihr MikesMarkdown-Konto angefordert.\n\n" .
                        "Bitte öffnen Sie den folgenden Link in Ihrem Browser:\n" .
                        $resetLink . "\n\n" .
                        "Dieser Link ist nur 1 Stunde gültig.\n\n" .
                        "Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.\n\n" .
                        "Viele Grüße,\n" .
                        "Das MikesMarkdown Team";

        $mail->send();

        // Log erfolgreichen Versand
        $logger = getLogger();
        $logger->info('Password reset email sent successfully', [
            'email' => $email,
            'smtp_host' => SMTP_HOST,
            'smtp_port' => SMTP_PORT,
        ]);

    } catch (Exception $e) {
        // Log Fehler mit detaillierten Informationen
        $logger = getLogger();
        $logger->error('Failed to send password reset email', [
            'email' => $email,
            'error' => $mail->ErrorInfo,
            'exception' => $e->getMessage(),
            'smtp_host' => SMTP_HOST,
            'smtp_port' => SMTP_PORT,
            'smtp_secure' => SMTP_SECURE,
            'smtp_user' => SMTP_USER,
            'from_email' => SMTP_FROM_EMAIL,
        ]);

        // Re-throw exception so it can be caught and displayed to user
        throw new Exception('E-Mail-Versand fehlgeschlagen: ' . $e->getMessage());
    }
}
