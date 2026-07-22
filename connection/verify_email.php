<?php
/**
 * verify_email.php
 * ─────────────────────────────────────────────────────────────
 * Landing page for the link emailed by issueVerificationEmail().
 * Looks up the token's hash, and if valid & unexpired, activates
 * the account (is_active = 1) — no barangay officer approval step.
 * ─────────────────────────────────────────────────────────────
 */

require_once 'connect.php';
require_once 'email_verification.php';

$token = trim($_GET['token'] ?? '');

$status    = 'invalid'; // invalid | expired | success | already
$firstName = '';
$userEmail = '';

if ($token !== '') {
    $hash = hash('sha256', $token);
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, email, is_active, email_verify_expires
            FROM users
            WHERE email_verify_token = ?
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $u = $stmt->fetch();

        if ($u) {
            $firstName = $u['first_name'] ?: 'there';
            $userEmail = $u['email'];

            if ($u['email_verify_expires'] !== null && strtotime($u['email_verify_expires']) < time()) {
                $status = 'expired';
            } else {
                $pdo->prepare("
                    UPDATE users
                    SET is_active = 1, email_verified_at = NOW(),
                        email_verify_token = NULL, email_verify_expires = NULL
                    WHERE id = ?
                ")->execute([$u['id']]);

                try {
                    $pdo->prepare("
                        INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, created_at)
                        VALUES (?, 'email_verified', 'user', ?, 'Community account verified via email link', NOW())
                    ")->execute([$u['id'], $u['id']]);
                } catch (PDOException $e) {}

                $status = 'success';
            }
        }
    } catch (PDOException $e) {
        error_log('[VOICE2 verify_email] ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Email — VOICE Blotter System</title>
  <link href="../assets/img/favicon.png" rel="icon">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue-50: #E6F1FB; --blue-100: #d3e6f8; --blue-400: #378ADD; --blue-600: #185FA5; --blue-800: #0C447C;
      --navy-900: #0B1F3A;
      --gray-50: #F8F7F4; --gray-200: #D3D1C7; --gray-400: #888780; --gray-600: #5F5E5A; --gray-900: #1A1A18;
      --rose-50: #FCEBEB; --rose-200: #f7c1c1; --rose-400: #E24B4A; --rose-600: #A32D2D;
      --green-50: #EAF3DE; --green-200: #bfe0a0; --green-600: #3B6D11;
    }
    html, body { height: 100%; font-family: 'DM Sans', sans-serif; background: var(--gray-50); color: var(--gray-900); }
    body {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      min-height: 100vh; padding: 32px 20px;
    }

    /* ── Brand lockup, above the card ── */
    .brand-lockup {
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 22px; text-decoration: none; color: inherit;
      opacity: 0; animation: rise .5s ease forwards;
    }
    .brand-mark {
      width: 38px; height: 38px; border-radius: 9px; flex-shrink: 0;
      background: linear-gradient(155deg, var(--blue-600), var(--navy-900));
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-family: 'DM Serif Display', serif; font-size: 20px; font-weight: 700;
      box-shadow: 0 4px 14px rgba(12,68,124,.28);
    }
    .brand-text-title { font-family: 'DM Serif Display', serif; font-size: 19px; line-height: 1.1; color: var(--gray-900); }
    .brand-text-sub { font-size: 10.5px; color: var(--gray-400); letter-spacing: .1em; text-transform: uppercase; margin-top: 2px; }

    /* ── Card ── */
    .card {
      width: 100%; max-width: 440px; background: #fff; border-radius: 18px;
      box-shadow: 0 24px 64px rgba(11,31,58,.12), 0 2px 8px rgba(11,31,58,.06);
      padding: 44px 38px; text-align: center;
      opacity: 0; animation: rise .55s ease forwards; animation-delay: .07s;
    }
    @keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) {
      .brand-lockup, .card { animation: none; opacity: 1; }
    }

    .icon-ring {
      width: 76px; height: 76px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 22px; position: relative;
    }
    .icon-ring::before {
      content: ''; position: absolute; inset: -6px; border-radius: 50%;
      border: 1px solid currentColor; opacity: .18;
    }
    .icon-ok   { background: var(--green-50); border: 2px solid var(--green-200); color: var(--green-600); }
    .icon-bad  { background: var(--rose-50);  border: 2px solid var(--rose-200);  color: var(--rose-600); }
    .icon-warn { background: var(--blue-50);  border: 2px solid var(--blue-100);  color: var(--blue-600); }

    h1 { font-family: 'DM Serif Display', serif; font-size: 24px; color: var(--gray-900); margin-bottom: 10px; letter-spacing: -.01em; }
    p.msg { font-size: 14px; color: var(--gray-600); line-height: 1.75; margin-bottom: 26px; }
    p.msg strong { color: var(--gray-900); }

    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      padding: 12px 26px; background: var(--blue-600); color: #fff;
      border-radius: 9px; text-decoration: none; font-size: 13.5px; font-weight: 600;
      border: none; cursor: pointer; font-family: inherit;
      transition: background .15s, transform .12s, box-shadow .15s;
    }
    .btn:hover { background: var(--blue-800); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(24,95,165,.28); }
    .btn-ghost { background: none; color: var(--blue-600); border: 1px solid var(--gray-200); box-shadow: none; }
    .btn-ghost:hover { background: var(--blue-50); transform: none; box-shadow: none; }

    form.resend { margin-top: 4px; text-align: left; }
    .field-label { display: block; font-size: 12px; font-weight: 500; color: var(--gray-600); margin-bottom: 5px; }
    input[type=email] {
      width: 100%; padding: 11px 13px; border: 1px solid var(--gray-200); border-radius: 9px;
      font-family: inherit; font-size: 13.5px; color: var(--gray-900); margin-bottom: 14px;
      outline: none; transition: border-color .12s, box-shadow .12s;
    }
    input[type=email]:focus { border-color: var(--blue-400); box-shadow: 0 0 0 3px rgba(55,138,221,.12); }

    .divider { height: 1px; background: var(--gray-200); opacity: .6; margin: 26px 0 20px; }
    .row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .row .btn, .row .btn-ghost { flex: 1; min-width: 130px; }

    .ttl-note { font-size: 12px; color: var(--gray-400); margin-top: 14px; }

    @media (max-width: 480px) {
      .card { padding: 34px 24px; border-radius: 14px; }
      h1 { font-size: 21px; }
      .row .btn, .row .btn-ghost { font-size: 12.5px; padding: 11px 8px; min-width: 0; white-space: nowrap; }
    }
  </style>
</head>
<body>

  <a href="../index.php" class="brand-lockup">
    <div class="brand-mark">V</div>
    <div>
      <div class="brand-text-title">VOICE</div>
      <div class="brand-text-sub">Siniloan, Laguna</div>
    </div>
  </a>

  <div class="card">

    <?php if ($status === 'success'): ?>
      <div class="icon-ring icon-ok">
        <svg width="34" height="34" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 17l7 7 13-15"/></svg>
      </div>
      <h1>Email verified!</h1>
      <p class="msg">Welcome, <strong><?= e($firstName) ?></strong> — your account is now active. You can log in and start using VOICE right away.</p>
      <a href="login.php" class="btn" style="width:100%">Go to Login →</a>

    <?php elseif ($status === 'expired'): ?>
      <div class="icon-ring icon-warn">
        <svg width="34" height="34" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="16" cy="16" r="13"/><path d="M16 9v7l5 3"/></svg>
      </div>
      <h1>Link expired</h1>
      <p class="msg">This verification link has expired. Enter your email below and we'll send you a fresh one.</p>
      <form class="resend" method="POST" action="resend_verification.php">
        <label class="field-label" for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="you@email.com" value="<?= e($userEmail) ?>" required>
        <button type="submit" class="btn" style="width:100%">Resend Verification Email</button>
      </form>
      <p class="ttl-note">Links are valid for <?= EMAIL_VERIFY_TTL_HOURS ?> hours after they're sent.</p>

    <?php else: ?>
      <div class="icon-ring icon-bad">
        <svg width="34" height="34" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="16" cy="16" r="13"/><path d="M11 11l10 10M21 11L11 21"/></svg>
      </div>
      <h1>Invalid verification link</h1>
      <p class="msg">This link is invalid, already used, or malformed. If you still need to verify your account, request a new one below.</p>
      <form class="resend" method="POST" action="resend_verification.php">
        <label class="field-label" for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="you@email.com" required>
        <button type="submit" class="btn" style="width:100%">Resend Verification Email</button>
      </form>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="row">
      <a href="login.php" class="btn btn-ghost">Back to Login</a>
      <a href="../index.php" class="btn btn-ghost">Home</a>
    </div>
  </div>
</body>
</html>
