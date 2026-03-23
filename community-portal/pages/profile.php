<?php // community-portal/pages/profile.php
$uid = (int)$user['id'];
$me = [];
try { $s=$pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1"); $s->execute([$uid]); $me=$s->fetch()??[]; } catch(PDOException $e){}

$ok=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['_action'])){
    if($_POST['_action']==='update'){
        $name    = trim($_POST['full_name']      ?? '');
        $contact = trim($_POST['contact_number'] ?? '');
        if(!$name){ $err='Full name is required.'; }
        else {
            try {
                $pdo->prepare("UPDATE users SET full_name=?,contact_number=?,updated_at=NOW() WHERE id=?")->execute([$name,$contact,$uid]);
                $_SESSION['user_name'] = $name;
                $me['full_name']=$name; $me['contact_number']=$contact;
                $ok='Profile updated successfully.';
            } catch(PDOException $e){ $err='Update failed. Please try again.'; }
        }
    }
    if($_POST['_action']==='change_pw'){
        $cur=$_POST['current']??''; $new=$_POST['new']??''; $con=$_POST['confirm']??'';
        if(!password_verify($cur,$me['password_hash']??'')) $err='Current password is incorrect.';
        elseif(strlen($new)<8) $err='New password must be at least 8 characters.';
        elseif($new!==$con)    $err='Passwords do not match.';
        else {
            try {
                $pdo->prepare("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>12]),$uid]);
                $ok='Password changed successfully.';
            } catch(PDOException $e){ $err='Failed to update password.'; }
        }
    }
}

// Stats
$stats=['filed'=>0,'resolved'=>0,'violations'=>0,'pending_notices'=>0];
try {
    $stats['filed']           = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid")->fetchColumn();
    $stats['resolved']        = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid AND status IN ('resolved','closed')")->fetchColumn();
    $stats['violations']      = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE user_id=$uid")->fetchColumn();
    $stats['pending_notices'] = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE recipient_user_id=$uid AND acknowledged_at IS NULL")->fetchColumn();
} catch(PDOException $e){}
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>My Profile</h2><p>Manage your account information</p></div>
</div>

<?php if($ok): ?>
<div class="alert alert-green mb16">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--green-600)" stroke-width="1.5" stroke-linecap="round"><path d="M3 8.5l3 3 7-7"/></svg>
  <div class="alert-text"><strong><?= e($ok) ?></strong></div>
</div>
<?php endif; ?>
<?php if($err): ?>
<div class="alert alert-rose mb16">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--rose-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/></svg>
  <div class="alert-text"><strong><?= e($err) ?></strong></div>
</div>
<?php endif; ?>

<div class="g21">
  <div>
    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Personal Information</span></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--green-600);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0">
            <?= strtoupper(substr($me['full_name']??'',0,2)) ?>
          </div>
          <div>
            <div style="font-size:16px;font-weight:700;color:var(--ink-900)"><?= e($me['full_name']??'') ?></div>
            <div style="font-size:13px;color:var(--ink-400)"><?= e($me['email']??'') ?></div>
            <span class="chip ch-green" style="margin-top:4px">Community Member</span>
            <?php if(!($me['is_active']??0)): ?><span class="chip ch-amber" style="margin-top:4px;margin-left:4px">Pending Approval</span><?php endif; ?>
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="update">
          <div class="fg"><label>Full Name</label><input type="text" name="full_name" value="<?= e($me['full_name']??'') ?>" required></div>
          <div class="fg">
            <label>Email <small style="color:var(--ink-300)">(cannot change)</small></label>
            <input type="email" value="<?= e($me['email']??'') ?>" readonly style="background:var(--surface);color:var(--ink-400)">
          </div>
          <div class="fg"><label>Mobile Number</label><input type="tel" name="contact_number" value="<?= e($me['contact_number']??'') ?>" placeholder="09XXXXXXXXX"></div>
          <div class="fg">
            <label>Barangay</label>
            <input type="text" value="<?= e($bgy_name) ?>" readonly style="background:var(--surface);color:var(--ink-400)">
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-hdr"><span class="card-title">Change Password</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_action" value="change_pw">
          <div class="fg"><label>Current Password</label><input type="password" name="current" placeholder="••••••••"></div>
          <div class="fg"><label>New Password</label><input type="password" name="new" placeholder="Min. 8 characters"></div>
          <div class="fg"><label>Confirm New Password</label><input type="password" name="confirm" placeholder="Repeat new password"></div>
          <button type="submit" class="btn btn-outline btn-sm">Update Password</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">My Activity</span></div>
      <div class="card-body" style="padding:12px 18px">
        <?php foreach([
          ['Reports Filed',   'filed',           'ch-teal'],
          ['Resolved Cases',  'resolved',        'ch-green'],
          ['Cases vs. Me',    'violations',      'ch-rose'],
          ['Unread Notices',  'pending_notices', 'ch-amber'],
        ] as [$lbl,$key,$ch]): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--surface-2)">
          <span style="font-size:13px;color:var(--ink-600)"><?= $lbl ?></span>
          <span class="chip <?= $ch ?>"><?= $stats[$key] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Account Details</span></div>
      <div class="card-body" style="padding:12px 18px">
        <div class="dr"><span class="dr-lbl">Member Since</span><span class="dr-val"><?= $me['created_at']?date('M j, Y',strtotime($me['created_at'])):'—' ?></span></div>
        <div class="dr"><span class="dr-lbl">Last Login</span><span class="dr-val"><?= $me['last_login']?date('M j, Y g:i A',strtotime($me['last_login'])):'—' ?></span></div>
        <div class="dr"><span class="dr-lbl">Account Status</span><span class="dr-val"><span class="chip <?= ($me['is_active']??0)?'ch-green':'ch-amber' ?>"><?= ($me['is_active']??0)?'Active':'Pending Approval' ?></span></span></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <a href="?page=file-report" class="btn btn-primary" style="justify-content:flex-start">📝 File a Report</a>
        <a href="?page=notices"     class="btn btn-outline" style="justify-content:flex-start">🔔 View Notices</a>
        <a href="../connection/logout.php" class="btn btn-danger" style="justify-content:flex-start">🚪 Sign Out</a>
      </div>
    </div>
  </div>
</div>
