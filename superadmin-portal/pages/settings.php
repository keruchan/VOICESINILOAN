<?php
// pages/settings.php
$admin = [];
try {
    $admin = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $admin->execute([$_SESSION['user_id']]);
    $admin = $admin->fetch();
} catch(PDOException $e){}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $contact   = trim($_POST['contact'] ?? '');
        try {
            $pdo->prepare("UPDATE users SET full_name=?, email=?, contact_number=?, updated_at=NOW() WHERE id=?")
                ->execute([$full_name, $email, $contact, $_SESSION['user_id']]);
            $_SESSION['user_name'] = $full_name;
            $success = 'Profile updated successfully.';
            $admin['full_name']     = $full_name;
            $admin['email']         = $email;
            $admin['contact_number']= $contact;
        } catch(PDOException $e) { $error = 'Update failed: '.$e->getMessage(); }
    }
    if ($_POST['action'] === 'change_password') {
        $current = $_POST['current_pw'] ?? '';
        $new_pw  = $_POST['new_pw']     ?? '';
        $confirm = $_POST['confirm_pw'] ?? '';
        if (!password_verify($current, $admin['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_pw !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new_pw, PASSWORD_BCRYPT, ['cost'=>12]);
            try {
                $pdo->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
                $success = 'Password changed successfully.';
            } catch(PDOException $e) { $error = 'Failed to update password.'; }
        }
    }
}
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Settings</h2>
    <p>Manage your superadmin account and system preferences</p>
  </div>
</div>

<?php if ($success): ?>
<div class="alert alert-emerald mb22"><svg class="alert-icon-emerald" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 8.5l3 3 7-7"/></svg><div class="alert-text"><strong><?= htmlspecialchars($success) ?></strong></div></div>
<?php elseif ($error): ?>
<div class="alert alert-rose mb22"><svg class="alert-icon-rose" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11" r=".5" fill="currentColor"/></svg><div class="alert-text"><strong><?= htmlspecialchars($error) ?></strong></div></div>
<?php endif; ?>

<div class="g2" style="align-items:start">
  <!-- Profile -->
  <div>
    <div class="card mb16">
      <div class="card-header"><span class="card-title">Superadmin Profile</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div style="text-align:center;margin-bottom:20px">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--indigo-600);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;margin:0 auto 8px">
              <?= strtoupper(substr($admin['full_name']??'SA',0,2)) ?>
            </div>
            <div style="font-size:13px;font-weight:600;color:var(--ink-900)"><?= htmlspecialchars($admin['full_name']??'') ?></div>
            <div style="font-size:11px;color:var(--ink-400)">System Administrator</div>
          </div>
          <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars($admin['full_name']??'') ?>"></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($admin['email']??'') ?>"></div>
          <div class="form-group"><label>Contact</label><input type="text" name="contact" value="<?= htmlspecialchars($admin['contact_number']??'') ?>" placeholder="09XXXXXXXXX"></div>
          <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Change Password</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="form-group"><label>Current Password</label><input type="password" name="current_pw" placeholder="••••••••"></div>
          <div class="form-group"><label>New Password</label><input type="password" name="new_pw" placeholder="Min. 8 characters"></div>
          <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_pw" placeholder="Repeat new password"></div>
          <button type="submit" class="btn btn-outline btn-sm">Update Password</button>
        </form>
      </div>
    </div>
  </div>

  <!-- System info -->
  <div>
    <div class="card mb16">
      <div class="card-header"><span class="card-title">System Information</span></div>
      <div class="card-body">
        <?php
        $sys = ['total_barangays'=>0,'total_users'=>0,'total_blotters'=>0,'db_version'=>'—'];
        try {
            $sys['total_barangays'] = $pdo->query("SELECT COUNT(*) FROM barangays WHERE is_active=1")->fetchColumn();
            $sys['total_users']     = $pdo->query("SELECT COUNT(*) FROM users WHERE role!='superadmin'")->fetchColumn();
            $sys['total_blotters']  = $pdo->query("SELECT COUNT(*) FROM blotters")->fetchColumn();
            $sys['db_version']      = $pdo->query("SELECT VERSION()")->fetchColumn();
        } catch(PDOException $e){}
        $info = [
            'PHP Version'       => phpversion(),
            'MySQL Version'     => $sys['db_version'],
            'Active Barangays'  => $sys['total_barangays'],
            'Total Users'       => $sys['total_users'],
            'Total Blotters'    => $sys['total_blotters'],
            'Server Time'       => date('Y-m-d H:i:s'),
            'Your IP'           => $_SERVER['REMOTE_ADDR'] ?? '—',
        ];
        foreach ($info as $k => $v): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--surface-2);font-size:13px">
            <span style="color:var(--ink-400)"><?= htmlspecialchars($k) ?></span>
            <span style="font-weight:500;color:var(--ink-900);font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars((string)$v) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mb16">
      <div class="card-header"><span class="card-title">Quick Links</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <a href="?page=users&filter=pending" class="btn btn-outline btn-sm" style="justify-content:flex-start">👤 View Pending Approvals</a>
        <a href="?page=barangays" class="btn btn-outline btn-sm" style="justify-content:flex-start">🏘 Manage Barangays</a>
        <a href="?page=reports" class="btn btn-outline btn-sm" style="justify-content:flex-start">📊 View Reports</a>
        <a href="../connection/logout.php" class="btn btn-danger btn-sm" style="justify-content:flex-start">🚪 Logout</a>
      </div>
    </div>

    <div class="alert alert-indigo">
      <svg class="alert-icon-indigo" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 7v5M8 5.5v-.5"/></svg>
      <div class="alert-text">
        <strong>Superadmin access</strong>
        <span>You have full read/write access across all barangays. All actions are logged in the activity log.</span>
      </div>
    </div>
  </div>
</div>
