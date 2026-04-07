<?php
// barangay-portal/ajax/um_action.php
// Clean AJAX endpoint — outputs ONLY JSON, no HTML ever precedes this.

require_once '../../connection/auth.php';
guardRole('barangay');

header('Content-Type: application/json; charset=utf-8');

$user = currentUser();
$bid  = (int)$user['barangay_id'];

$action  = trim($_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '');
$user_id = (int)($_POST['user_id']    ?? $_GET['user_id']    ?? 0);

// ── GET: full user data + blotter cases ───────────────────────
if ($action === 'get_user' && $user_id > 0) {
    try {
        // Fetch the user — must belong to this barangay and be a community member
        $s = $pdo->prepare("
            SELECT id, full_name, first_name, middle_name, last_name,
                   email, contact_number, address, birth_date, is_active, created_at
            FROM users
            WHERE id = ? AND role = 'community'
            LIMIT 1
        ");
        $s->execute([$user_id]);
        $u = $s->fetch(PDO::FETCH_ASSOC);
        if (!$u) { echo json_encode(['success'=>false,'message'=>'User not found.']); exit; }

        $f = $pdo->prepare("SELECT id,case_number,incident_type,violation_level,status,incident_date,created_at FROM blotters WHERE complainant_user_id=? ORDER BY created_at DESC LIMIT 20");
        $f->execute([$user_id]); $u['cases_filed'] = $f->fetchAll(PDO::FETCH_ASSOC);

        $r = $pdo->prepare("SELECT id,case_number,incident_type,violation_level,status,incident_date,created_at FROM blotters WHERE respondent_user_id=? ORDER BY created_at DESC LIMIT 20");
        $r->execute([$user_id]); $u['cases_respondent'] = $r->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true,'user'=>$u]);
    } catch (PDOException $e) {
        error_log('um_action get_user: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ── POST: activate ────────────────────────────────────────────
if ($action === 'activate' && $user_id > 0) {
    try {
        $s = $pdo->prepare("UPDATE users SET is_active=1 WHERE id=? AND role='community'");
        $s->execute([$user_id]);
        if ($s->rowCount() === 0) {
            echo json_encode(['success'=>false,'message'=>'User not found or already active.']);
        } else {
            echo json_encode(['success'=>true,'message'=>'Account activated successfully.']);
        }
    } catch (PDOException $e) {
        error_log('um_action activate: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'Database error.']);
    }
    exit;
}

// ── POST: deactivate ──────────────────────────────────────────
if ($action === 'deactivate' && $user_id > 0) {
    try {
        $s = $pdo->prepare("UPDATE users SET is_active=0 WHERE id=? AND role='community'");
        $s->execute([$user_id]);
        if ($s->rowCount() === 0) {
            echo json_encode(['success'=>false,'message'=>'User not found or already inactive.']);
        } else {
            echo json_encode(['success'=>true,'message'=>'Account deactivated.']);
        }
    } catch (PDOException $e) {
        error_log('um_action deactivate: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'Database error.']);
    }
    exit;
}

// ── POST: update user info ────────────────────────────────────
if ($action === 'update_user' && $user_id > 0) {
    $admin_pass  = $_POST['admin_password']  ?? '';
    $first_name  = trim($_POST['first_name']     ?? '');
    $middle_name = trim($_POST['middle_name']    ?? '');
    $last_name   = trim($_POST['last_name']      ?? '');
    $email       = trim($_POST['email']          ?? '');
    $contact     = trim($_POST['contact_number'] ?? '');
    $address     = trim($_POST['address']        ?? '');
    $birth_date  = trim($_POST['birth_date']     ?? '');

    // Verify admin password
    try {
        $as = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
        $as->execute([$user['id']]);
        $ar = $as->fetch(PDO::FETCH_ASSOC);
        if (!$ar || !password_verify($admin_pass, $ar['password_hash'])) {
            echo json_encode(['success'=>false,'message'=>'Incorrect admin password. No changes saved.']); exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Could not verify admin password.']); exit;
    }

    if (empty($first_name) || empty($last_name))    { echo json_encode(['success'=>false,'message'=>'First and last name are required.']); exit; }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'A valid email is required.']); exit; }
    if (!empty($contact) && !preg_match('/^(09|\+639)\d{9}$/', $contact)) { echo json_encode(['success'=>false,'message'=>'Invalid PH mobile number format.']); exit; }

    // Email uniqueness check
    try {
        $ec = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
        $ec->execute([$email, $user_id]);
        if ($ec->fetch()) { echo json_encode(['success'=>false,'message'=>'Email is already used by another account.']); exit; }
    } catch (PDOException $e) {}

    try {
        $full = $last_name.', '.$first_name.($middle_name?' '.$middle_name:'');
        $upd  = $pdo->prepare("UPDATE users SET full_name=?,first_name=?,middle_name=?,last_name=?,email=?,contact_number=?,address=?,birth_date=? WHERE id=? AND role='community'");
        $upd->execute([$full,$first_name,$middle_name,$last_name,$email,$contact,$address,$birth_date?:null,$user_id]);
        echo json_encode(['success'=>true,'message'=>'User information updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Update failed. Please try again.']);
    }
    exit;
}

// ── POST: reset password ──────────────────────────────────────
if ($action === 'reset_password' && $user_id > 0) {
    $admin_pass  = $_POST['admin_password']    ?? '';
    $new_pass    = $_POST['new_password']      ?? '';
    $confirm     = $_POST['confirm_password']  ?? '';

    // Verify admin password
    try {
        $as = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
        $as->execute([$user['id']]);
        $ar = $as->fetch(PDO::FETCH_ASSOC);
        if (!$ar || !password_verify($admin_pass, $ar['password_hash'])) {
            echo json_encode(['success'=>false,'message'=>'Incorrect admin password. Password not changed.']); exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Could not verify admin password.']); exit;
    }

    if (strlen($new_pass) < 8)  { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit; }
    if ($new_pass !== $confirm)  { echo json_encode(['success'=>false,'message'=>'Passwords do not match.']); exit; }

    try {
        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $upd  = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='community'");
        $upd->execute([$hash, $user_id]);
        echo json_encode(['success'=>true,'message'=>'Password reset successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Password reset failed.']);
    }
    exit;
}

// ── POST: register new user ───────────────────────────────────
if ($action === 'register_user') {
    $first_name  = trim($_POST['first_name']  ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $email       = trim($_POST['email']       ?? '');
    $contact     = trim($_POST['contact']     ?? '');
    $address     = trim($_POST['address']     ?? '');
    $birth_date  = trim($_POST['birth_date']  ?? '');
    $password    = $_POST['password']         ?? '';

    $errs = [];
    if (empty($first_name))  $errs[]='First name is required.';
    if (empty($last_name))   $errs[]='Last name is required.';
    if (empty($address))     $errs[]='Address is required.';
    if (empty($birth_date))  $errs[]='Date of birth is required.';
    if (empty($email)||!filter_var($email,FILTER_VALIDATE_EMAIL)) $errs[]='A valid email is required.';
    if (empty($contact)||!preg_match('/^(09|\+639)\d{9}$/',$contact)) $errs[]='Valid PH mobile number required (e.g. 09171234567).';
    if (strlen($password)<8) $errs[]='Password must be at least 8 characters.';

    if (empty($errs)) {
        try {
            $chk=$pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $chk->execute([$email]);
            if($chk->fetch()) $errs[]='An account with this email already exists.';
        } catch (PDOException $e) { $errs[]='Database error.'; }
    }

    if (!empty($errs)) { echo json_encode(['success'=>false,'errors'=>$errs]); exit; }

    try {
        $full = $last_name.', '.$first_name.($middle_name?' '.$middle_name:'');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins  = $pdo->prepare("INSERT INTO users (full_name,first_name,middle_name,last_name,email,password_hash,contact_number,address,birth_date,barangay_id,role,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,'community',1,NOW())");
        $ins->execute([$full,$first_name,$middle_name,$last_name,$email,$hash,$contact,$address,$birth_date?:null,$bid]);
        echo json_encode(['success'=>true,'message'=>'User registered and activated.']);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'errors'=>['Registration failed. Please try again.']]);
    }
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action.']);
