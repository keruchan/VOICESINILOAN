<?php
/**
 * email_verification.php
 * ─────────────────────────────────────────────────────────────
 * Shared helpers for the community self-registration email-verification
 * flow. Used by register.php (issue on signup) and
 * resend_verification.php (re-issue on request).
 *
 * The token itself is only ever shown to the user inside the emailed
 * link — the database stores a SHA-256 hash of it, never the raw
 * value (same principle as a password reset token).
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/mailer.php';

const EMAIL_VERIFY_TTL_HOURS = 24;

/**
 * Build the absolute base URL of the app (scheme + host), so emailed
 * links work regardless of what host/port the app is served from.
 */
function appBaseUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host";
}

/**
 * Detect the app's base path under the web root — '' when VOICE2 IS the
 * web root (e.g. production at yourdomain.com), or '/VOICE2' when it's
 * served from a subfolder (e.g. XAMPP's htdocs/VOICE2 on localhost).
 *
 * Derived from SCRIPT_NAME of the currently-running script, which always
 * lives directly inside connection/ — so two dirname() calls strip
 * "/connection/xxx.php" back to the app root, whatever that root is.
 */
function appBasePath(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base   = dirname(dirname($script));
    return $base === '/' || $base === '\\' ? '' : $base;
}

/**
 * Generate a new verification token for the given user, store its hash
 * (with an expiry), and email the verification link to them.
 *
 * @return true if the email was sent successfully; false if sending
 *         failed (the token is still saved, so "Resend" can try again).
 */
function issueVerificationEmail(PDO $pdo, int $userId, string $email, string $firstName): bool {
    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + EMAIL_VERIFY_TTL_HOURS * 3600);

    $pdo->prepare("
        UPDATE users
        SET email_verify_token = ?, email_verify_expires = ?
        WHERE id = ?
    ")->execute([$hash, $expires, $userId]);

    $link = appBaseUrl() . appBasePath() . '/connection/verify_email.php?token=' . $token;

    $subject = 'Verify your VOICE account';
    $body = "
      <div style='font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;color:#1a1a2e'>
        <div style='background:#0B1F3A;padding:20px 24px;border-radius:8px 8px 0 0'>
          <span style='color:#C9A84C;font-size:18px;font-weight:bold'>VOICE</span>
          <span style='color:#fff;font-size:12px;margin-left:8px'>Siniloan Barangay Blotter System</span>
        </div>
        <div style='border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 8px 8px'>
          <p>Hi " . htmlspecialchars($firstName, ENT_QUOTES) . ",</p>
          <p>Thanks for registering with VOICE. Please confirm your email address to activate your account:</p>
          <p style='text-align:center;margin:28px 0'>
            <a href='" . htmlspecialchars($link, ENT_QUOTES) . "'
               style='background:#0B1F3A;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold;display:inline-block'>
              Verify My Email
            </a>
          </p>
          <p style='font-size:12px;color:#666'>Or copy and paste this link into your browser:<br>" . htmlspecialchars($link, ENT_QUOTES) . "</p>
          <p style='font-size:12px;color:#666'>This link expires in " . EMAIL_VERIFY_TTL_HOURS . " hours. If you didn't create this account, you can ignore this email.</p>
        </div>
      </div>
    ";

    return sendAppMail($email, $firstName, $subject, $body);
}
