<?php
/**
 * register.php
 * ─────────────────────────────────────────────────────────────
 * Community user self-registration.
 * After registration, the account is set to inactive (is_active = 0)
 * and must be approved by the barangay officer before login.
 *
 * GET  → show registration form
 * POST → validate, insert user, redirect to login with success message
 * ─────────────────────────────────────────────────────────────
 */

require_once 'connect.php';

// Already logged in?
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role']);
}

$errors  = [];
$success = '';

// ── Fetch list of barangays for dropdown ──────────────────────
$barangays = [];
try {
    $barangays = $pdo->query("SELECT id, name, municipality FROM barangays WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    // Table may not exist yet — silently continue
    error_log('[VOICE2 Register] Could not load barangays: ' . $e->getMessage());
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');
    $email        = trim($_POST['email']        ?? '');
    $contact      = trim($_POST['contact']      ?? '');
    $address      = trim($_POST['address']      ?? '');
    $barangay_id  = (int)($_POST['barangay_id'] ?? 0);
    $password     = $_POST['password']          ?? '';
    $confirm_pass = $_POST['confirm_password']  ?? '';
    $birth_date   = trim($_POST['birth_date']   ?? '');

    // ── Validation ────────────────────────────────────────────
    if (empty($first_name))  $errors[] = 'First name is required.';
    if (empty($last_name))   $errors[] = 'Last name is required.';
    if (empty($address))     $errors[] = 'Home address is required.';
    if (empty($birth_date))  $errors[] = 'Date of birth is required.';
    if ($barangay_id <= 0)   $errors[] = 'Please select your barangay.';

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($contact)) {
        $errors[] = 'Contact number is required.';
    } elseif (!preg_match('/^(09|\+639)\d{9}$/', $contact)) {
        $errors[] = 'Please enter a valid Philippine mobile number (e.g. 09171234567).';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm_pass) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Check for duplicate email ─────────────────────────────
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists. Please log in instead.';
            }
        } catch (PDOException $e) {
            error_log('[VOICE2 Register] Duplicate check error: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }

    // ── Insert user if no errors ──────────────────────────────
    if (empty($errors)) {
        try {
            $full_name    = $last_name . ', ' . $first_name . ' ' . $middle_name;
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (full_name, first_name, middle_name, last_name,
                     email, password_hash, contact_number, address,
                     birth_date, barangay_id, role, is_active, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'community', 0, NOW())
            ");
            $stmt->execute([
                $full_name, $first_name, $middle_name, $last_name,
                $email, $password_hash, $contact, $address,
                $birth_date, $barangay_id
            ]);

            // Redirect to login with a success message
            redirect('login.php?registered=1');

        } catch (PDOException $e) {
            error_log('[VOICE2 Register] Insert error: ' . $e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

function redirectByRole(string $role): void {
    switch ($role) {
        case 'community':  redirect('../community-portal/index.html'); break;
        case 'barangay':   redirect('../barangay-portal/index.html');  break;
        case 'superadmin': redirect('../superadmin-portal/index.html'); break;
        default:           redirect('../index.html');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — VOICE2 Blotter System</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue-50:  #E6F1FB; --blue-400: #378ADD; --blue-600: #185FA5; --blue-800: #0C447C;
      --gray-50:  #F8F7F4; --gray-100: #F1EFE8; --gray-200: #D3D1C7; --gray-400: #888780;
      --gray-600: #5F5E5A; --gray-900: #1A1A18;
      --rose-50:  #FCEBEB; --rose-400: #E24B4A; --rose-600: #A32D2D;
      --green-50: #EAF3DE; --green-600: #3B6D11;
    }
    html, body { font-family: 'DM Sans', sans-serif; background: var(--gray-50); color: var(--gray-900); min-height: 100vh; }
    body { display: flex; align-items: flex-start; justify-content: center; padding: 32px 16px; }

    .page-wrap { display: flex; width: 100%; max-width: 920px; border-radius: 18px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.1); }

    /* Left branding */
    .left-panel {
      width: 260px; min-width: 260px; background: var(--blue-800);
      color: #fff; padding: 40px 28px; display: flex;
      flex-direction: column; justify-content: space-between;
    }
    .brand-eye { font-size: 10px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--blue-400); margin-bottom: 10px; }
    .brand-name { font-family: 'DM Serif Display', serif; font-size: 26px; line-height: 1.25; color: #fff; font-style: italic; }
    .brand-note { font-size: 12px; color: rgba(255,255,255,0.45); margin-top: 14px; line-height: 1.7; }
    .steps-list { list-style: none; margin-top: 24px; display: flex; flex-direction: column; gap: 12px; }
    .step { display: flex; align-items: flex-start; gap: 10px; font-size: 12px; color: rgba(255,255,255,0.6); }
    .step-num { width: 20px; height: 20px; border-radius: 50%; background: rgba(255,255,255,0.12); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0; }
    .left-link { font-size: 12px; color: rgba(255,255,255,0.4); text-decoration: none; display: block; }
    .left-link:hover { color: rgba(255,255,255,0.8); }

    /* Right form */
    .right-panel { flex: 1; background: #fff; padding: 40px 44px; }
    .form-title { font-family: 'DM Serif Display', serif; font-size: 24px; color: var(--gray-900); margin-bottom: 4px; }
    .form-subtitle { font-size: 13px; color: var(--gray-400); margin-bottom: 24px; }

    .form-group { margin-bottom: 14px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
    label { display: block; font-size: 12px; font-weight: 500; color: var(--gray-600); margin-bottom: 4px; }
    .req { color: var(--rose-400); }
    input[type=text], input[type=email], input[type=password], input[type=date], input[type=tel], select {
      width: 100%; padding: 9px 11px;
      border: 1px solid var(--gray-200); border-radius: 8px;
      font-family: inherit; font-size: 13px; color: var(--gray-900);
      background: #fff; outline: none;
      transition: border-color 0.12s, box-shadow 0.12s;
    }
    input:focus, select:focus { border-color: var(--blue-400); box-shadow: 0 0 0 3px rgba(55,138,221,0.1); }
    select { cursor: pointer; }

    .section-label { font-size: 11px; font-weight: 600; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.07em; padding-bottom: 8px; border-bottom: 1px solid var(--gray-100); margin: 20px 0 14px; }

    .error-list { background: var(--rose-50); border: 1px solid #f7c1c1; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; }
    .error-list ul { list-style: none; display: flex; flex-direction: column; gap: 4px; }
    .error-list li { font-size: 13px; color: var(--rose-600); display: flex; align-items: flex-start; gap: 6px; }

    .password-hint { font-size: 11px; color: var(--gray-400); margin-top: 4px; }

    .btn-register {
      width: 100%; padding: 11px; background: var(--blue-600); color: #fff;
      border: none; border-radius: 8px; font-family: inherit;
      font-size: 14px; font-weight: 500; cursor: pointer;
      transition: background 0.12s; margin-top: 8px;
    }
    .btn-register:hover { background: var(--blue-800); }

    .login-link { text-align: center; font-size: 13px; color: var(--gray-400); margin-top: 14px; }
    .login-link a { color: var(--blue-600); text-decoration: none; font-weight: 500; }
    .login-link a:hover { text-decoration: underline; }

    .notice-box { background: var(--blue-50); border: 1px solid #c3d6fa; border-radius: 8px; padding: 12px 14px; margin-bottom: 20px; font-size: 12px; color: var(--blue-800); line-height: 1.6; }

    @media (max-width: 700px) {
      .page-wrap { flex-direction: column; border-radius: 0; }
      .left-panel { width: 100%; min-width: unset; }
      .right-panel { padding: 28px 20px; }
      .form-row, .form-row3 { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="page-wrap">

  <!-- Left Panel -->
  <div class="left-panel">
    <div>
      <div class="brand-eye">VOICE2 System</div>
      <div class="brand-name">Community<br>Registration</div>
      <div class="brand-note">Create your account to report incidents, track your cases, and receive barangay notifications.</div>
      <ul class="steps-list">
        <li class="step"><div class="step-num">1</div>Fill out the form with your accurate information.</li>
        <li class="step"><div class="step-num">2</div>Your account will be reviewed by your barangay officer.</li>
        <li class="step"><div class="step-num">3</div>You'll be notified once your account is approved and active.</li>
      </ul>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px">
      <a href="login.php" class="left-link">← Already have an account? Sign in</a>
      <a href="../index.html" class="left-link">Back to Home</a>
    </div>
  </div>

  <!-- Right Form -->
  <div class="right-panel">
    <div class="form-title">Create your account</div>
    <div class="form-subtitle">Community member registration · All fields marked <span style="color:var(--rose-400)">*</span> are required</div>

    <div class="notice-box">
      ℹ️ Your account will be <strong>pending approval</strong> after registration. The barangay officer will verify your information before you can log in.
    </div>

    <?php if (!empty($errors)): ?>
    <div class="error-list">
      <ul>
        <?php foreach ($errors as $err): ?>
        <li>
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" style="flex-shrink:0;margin-top:2px"><circle cx="7" cy="7" r="5.5"/><path d="M7 4.5v3"/><circle cx="7" cy="9.5" r=".5" fill="currentColor"/></svg>
          <?= e($err) ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>

      <!-- Personal Information -->
      <div class="section-label">Personal Information</div>

      <div class="form-row3">
        <div class="form-group">
          <label>First Name <span class="req">*</span></label>
          <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" placeholder="Juan" required>
        </div>
        <div class="form-group">
          <label>Middle Name</label>
          <input type="text" name="middle_name" value="<?= e($_POST['middle_name'] ?? '') ?>" placeholder="Santos">
        </div>
        <div class="form-group">
          <label>Last Name <span class="req">*</span></label>
          <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" placeholder="Dela Cruz" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Date of Birth <span class="req">*</span></label>
          <input type="date" name="birth_date" value="<?= e($_POST['birth_date'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Contact Number <span class="req">*</span></label>
          <input type="tel" name="contact" value="<?= e($_POST['contact'] ?? '') ?>" placeholder="09171234567" required>
        </div>
      </div>

      <div class="form-group">
        <label>Home Address <span class="req">*</span></label>
        <input type="text" name="address" value="<?= e($_POST['address'] ?? '') ?>" placeholder="House/Lot No., Street" required>
      </div>

      <div class="form-group">
        <label>Barangay <span class="req">*</span></label>
        <select name="barangay_id" required>
          <option value="">— Select your barangay —</option>
          <?php foreach ($barangays as $bgy): ?>
          <option value="<?= (int)$bgy['id'] ?>" <?= (($_POST['barangay_id'] ?? '') == $bgy['id']) ? 'selected' : '' ?>>
            <?= e($bgy['name']) ?><?= $bgy['municipality'] ? ' — ' . e($bgy['municipality']) : '' ?>
          </option>
          <?php endforeach; ?>
          <?php if (empty($barangays)): ?>
          <!-- Placeholder when DB is not yet connected -->
          <option value="1">Brgy. Acevida — Siniloan</option>
          <option value="2">Brgy. Pandeno — Siniloan</option>
          <option value="3">Brgy. Wawa — Siniloan</option>
          <?php endif; ?>
        </select>
      </div>

      <!-- Account Information -->
      <div class="section-label">Account Information</div>

      <div class="form-group">
        <label>Email Address <span class="req">*</span></label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="juan@email.com" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Password <span class="req">*</span></label>
          <input type="password" name="password" placeholder="Min. 8 characters" required>
          <div class="password-hint">At least 8 characters.</div>
        </div>
        <div class="form-group">
          <label>Confirm Password <span class="req">*</span></label>
          <input type="password" name="confirm_password" placeholder="Repeat password" required>
        </div>
      </div>

      <button type="submit" class="btn-register">Create Account</button>
    </form>

    <div class="login-link">
      Already have an account? <a href="login.php">Sign in here</a>
    </div>
  </div>

</div>
</body>
</html>
