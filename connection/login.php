<?php
/**
 * login.php
 * ─────────────────────────────────────────────────────────────
 * Handles login for all user roles:
 *   - community  → community-portal/index.html
 *   - barangay   → barangay-portal/index.html
 *   - superadmin → superadmin-portal/index.html
 *
 * GET  → show login form
 * POST → validate credentials, set session, redirect
 * ─────────────────────────────────────────────────────────────
 */

require_once 'connect.php';

// Already logged in? Redirect to their portal.
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT id, full_name, email, password_hash, role, barangay_id, is_active
                FROM users
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    $error = 'Your account is pending verification by the barangay. Please wait for approval.';
                } else {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['user_id']     = $user['id'];
                    $_SESSION['user_name']   = $user['full_name'];
                    $_SESSION['user_role']   = $user['role'];
                    $_SESSION['barangay_id'] = $user['barangay_id'];

                    // Update last login timestamp
                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                        ->execute([$user['id']]);

                    redirectByRole($user['role']);
                }
            } else {
                $error = 'Incorrect email or password.';
            }
        } catch (PDOException $e) {
            error_log('[VOICE2 Login Error] ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}

/**
 * Redirect user to their respective portal based on role.
 */
function redirectByRole(string $role): void {
    switch ($role) {
        case 'community':
            redirect('../community-portal/index.php');
            break;
        case 'barangay':
            redirect('../barangay-portal/index.php');
            break;
        case 'superadmin':
            redirect('../superadmin-portal/index.php');
            break;
        default:
            redirect('../index.html');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — VOICE Blotter System</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue-50:  #E6F1FB; --blue-400: #378ADD; --blue-600: #185FA5; --blue-800: #0C447C;
      --gray-50:  #F8F7F4; --gray-100: #F1EFE8; --gray-200: #D3D1C7; --gray-400: #888780;
      --gray-600: #5F5E5A; --gray-900: #1A1A18;
      --rose-50:  #FCEBEB; --rose-400: #E24B4A; --rose-600: #A32D2D;
    }
    html, body {
      height: 100%; font-family: 'DM Sans', sans-serif;
      background: var(--gray-50); color: var(--gray-900);
    }
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }

    .page-wrap { display: flex; width: 100%; max-width: 900px; min-height: 520px; border-radius: 18px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.12); }

    /* Left panel */
    .left-panel {
      flex: 1; background: var(--blue-800); color: #fff;
      padding: 48px 40px; display: flex; flex-direction: column;
      justify-content: space-between;
    }
    .brand { margin-bottom: auto; }
    .brand-eye { font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--blue-400); margin-bottom: 10px; }
    .brand-name { font-family: 'DM Serif Display', serif; font-size: 32px; line-height: 1.2; color: #fff; font-style: italic; }
    .brand-tagline { font-size: 13px; color: rgba(255,255,255,0.5); margin-top: 8px; line-height: 1.6; }
    .left-links { display: flex; flex-direction: column; gap: 6px; }
    .left-link { font-size: 12px; color: rgba(255,255,255,0.4); text-decoration: none; }
    .left-link:hover { color: rgba(255,255,255,0.8); }

    /* Right panel */
    .right-panel { flex: 1; background: #fff; padding: 48px 44px; display: flex; flex-direction: column; justify-content: center; }

    .form-title { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--gray-900); margin-bottom: 6px; }
    .form-subtitle { font-size: 13px; color: var(--gray-400); margin-bottom: 28px; }

    .form-group { margin-bottom: 16px; }
    label { display: block; font-size: 12px; font-weight: 500; color: var(--gray-600); margin-bottom: 5px; }
    input[type=email], input[type=password], input[type=text] {
      width: 100%; padding: 10px 12px;
      border: 1px solid var(--gray-200); border-radius: 8px;
      font-family: inherit; font-size: 14px; color: var(--gray-900);
      background: #fff; outline: none;
      transition: border-color 0.12s, box-shadow 0.12s;
    }
    input:focus { border-color: var(--blue-400); box-shadow: 0 0 0 3px rgba(55,138,221,0.12); }

    .error-box {
      background: var(--rose-50); border: 1px solid #f7c1c1;
      border-radius: 8px; padding: 10px 14px; margin-bottom: 18px;
      font-size: 13px; color: var(--rose-600); display: flex; align-items: center; gap: 8px;
    }

    .btn-login {
      width: 100%; padding: 11px; background: var(--blue-600); color: #fff;
      border: none; border-radius: 8px; font-family: inherit;
      font-size: 14px; font-weight: 500; cursor: pointer;
      transition: background 0.12s; margin-top: 4px;
    }
    .btn-login:hover { background: var(--blue-800); }

    .divider { display: flex; align-items: center; gap: 12px; margin: 18px 0; }
    .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--gray-200); }
    .divider span { font-size: 12px; color: var(--gray-400); }

    .register-link { text-align: center; font-size: 13px; color: var(--gray-400); }
    .register-link a { color: var(--blue-600); text-decoration: none; font-weight: 500; }
    .register-link a:hover { text-decoration: underline; }

    .show-pass-wrap { position: relative; }
    .show-pass-wrap input { padding-right: 44px; }
    .show-pass-btn {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: var(--gray-400); padding: 0;
    }

    @media (max-width: 640px) {
      .page-wrap { flex-direction: column; border-radius: 0; }
      .left-panel { padding: 28px 24px; }
      .right-panel { padding: 32px 24px; }
    }
  </style>
</head>
<body>
<div class="page-wrap">

  <!-- Left Branding Panel -->
  <div class="left-panel">
    <div class="brand">
      <div class="brand-eye">VOICE System</div>
      <div class="brand-name">Barangay<br>Blotter<br>System</div>
      <div class="brand-tagline">A unified platform for barangay blotter management, mediation scheduling, and community safety reporting.</div>
    </div>
    <div class="left-links">
      <a href="../index.html" class="left-link">← Back to Home</a>
      <a href="register.php" class="left-link">Create a community account</a>
    </div>
  </div>

  <!-- Right Login Form -->
  <div class="right-panel">
    <div class="form-title">Welcome back</div>
    <div class="form-subtitle">Sign in to continue to your portal</div>

    <?php if ($error): ?>
    <div class="error-box">
      <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="7.5" cy="7.5" r="6"/><path d="M7.5 4.5v4"/><circle cx="7.5" cy="10.5" r=".5" fill="currentColor"/></svg>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="you@email.com" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <div class="show-pass-wrap">
          <input type="password" id="password" name="password" placeholder="••••••••" required>
          <button type="button" class="show-pass-btn" onclick="togglePassword()" title="Show/hide password">
            <svg id="eye-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 9s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/><circle cx="9" cy="9" r="2.5"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">Sign In</button>
    </form>

    <div class="divider"><span>or</span></div>

    <div class="register-link">
      New to the system? <a href="register.php">Create a community account</a>
    </div>

    <div class="register-link" style="margin-top:10px;font-size:12px;color:var(--gray-400)">
      Barangay officer access is managed by your Punong Barangay.
    </div>
  </div>

</div>

<script>
function togglePassword() {
  const input = document.getElementById('password');
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
