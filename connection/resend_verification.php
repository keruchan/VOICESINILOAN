<?php
/**
 * resend_verification.php
 * ─────────────────────────────────────────────────────────────
 * POST { email } → issues a fresh verification token/email for an
 * unverified community account. Always shows the same generic
 * confirmation regardless of whether the email exists, to avoid
 * leaking which addresses are registered.
 * ─────────────────────────────────────────────────────────────
 */

require_once 'connect.php';
require_once 'email_verification.php';

$email = trim($_POST['email'] ?? '');
$sent  = false;

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, email_verify_expires
            FROM users
            WHERE email = ? AND role = 'community' AND is_active = 0 AND email_verified_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u) {
            // Basic cooldown: don't re-send more than once per 60s
            // (email_verify_expires was set to issue-time + 24h).
            $issuedAgo = $u['email_verify_expires']
                ? (time() - (strtotime($u['email_verify_expires']) - EMAIL_VERIFY_TTL_HOURS * 3600))
                : 999;

            if ($issuedAgo >= 60) {
                issueVerificationEmail($pdo, (int)$u['id'], $email, $u['first_name'] ?: 'there');
            }
            $sent = true; // report success either way (already sent recently is fine)
        }
    } catch (PDOException $e) {
        error_log('[VOICE2 resend_verification] ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resend Verification — VOICE Blotter System</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --blue-50:#E6F1FB; --blue-600:#185FA5; --blue-800:#0C447C; --gray-50:#F8F7F4; --gray-200:#D3D1C7; --gray-600:#5F5E5A; --gray-900:#1A1A18; --green-50:#EAF3DE; --green-600:#3B6D11; }
    html, body { height: 100%; font-family: 'DM Sans', sans-serif; background: var(--gray-50); color: var(--gray-900); }
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
    .card { width: 100%; max-width: 440px; background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.1); padding: 40px 36px; text-align: center; }
    .icon { width: 68px; height: 68px; border-radius: 50%; background: var(--green-50); border: 2px solid #bfe0a0; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    h1 { font-family: 'DM Serif Display', serif; font-size: 22px; margin-bottom: 8px; }
    p.msg { font-size: 13.5px; color: var(--gray-600); line-height: 1.7; margin-bottom: 24px; }
    .btn { display: inline-block; padding: 11px 26px; background: var(--blue-600); color: #fff; border-radius: 8px; text-decoration: none; font-size: 13.5px; font-weight: 500; }
    .btn:hover { background: var(--blue-800); }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg width="30" height="30" viewBox="0 0 30 30" fill="none" stroke="var(--green-600)" stroke-width="2" stroke-linecap="round"><rect x="3" y="6" width="24" height="18" rx="2"/><path d="M4 8l11 8 11-8"/></svg>
    </div>
    <h1>Check your inbox</h1>
    <p class="msg">If an unverified VOICE account exists for that email address, we've sent a fresh verification link. It expires in <?= EMAIL_VERIFY_TTL_HOURS ?> hours.</p>
    <a href="login.php" class="btn">Back to Login</a>
  </div>
</body>
</html>
