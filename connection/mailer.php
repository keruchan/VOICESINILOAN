<?php
/**
 * mailer.php
 * ─────────────────────────────────────────────────────────────
 * Thin wrapper around PHPMailer (vendored in ./phpmailer/src, no
 * Composer required) configured for SMTP via mail_config.php.
 *
 * Usage:
 *   require_once __DIR__ . '/mailer.php';
 *   sendAppMail('resident@email.com', 'Juan Dela Cruz', 'Subject', '<p>HTML body</p>');
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send an HTML email through the configured Hostinger SMTP mailbox.
 *
 * @return true on success. On failure, the error is written to the PHP
 *         error log and false is returned — callers should not treat a
 *         failed send as a fatal error (see register.php).
 */
function sendAppMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $mail->SMTPDebug   = 2;
            $mail->Debugoutput = function ($str) { error_log('[SMTP] ' . $str); };
        }

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)));

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[VOICE2 Mailer] Failed to send to ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}
