<?php
// pages/settings.php
$uid = (int)$user['id'];

// Load full officer row
$officer = [];
try { $s=$pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1"); $s->execute([$uid]); $officer=$s->fetch()??[]; } catch(PDOException $e){}

$ok=''; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['_action'])) {
    if ($_POST['_action']==='profile') {
        $name    = trim($_POST['full_name']      ?? '');
        $contact = trim($_POST['contact_number'] ?? '');
        if (!$name) { $err='Name is required.'; }
        else {
            try {
                $pdo->prepare("UPDATE users SET full_name=?, contact_number=?, updated_at=NOW() WHERE id=?")->execute([$name,$contact,$uid]);
                $_SESSION['user_name'] = $name;
                $ok = 'Profile updated.';
                $officer['full_name']=$name; $officer['contact_number']=$contact;
            } catch(PDOException $e){ $err='Update failed.'; }
        }
    }
    if ($_POST['_action']==='password') {
        $cur=$_POST['cur']??''; $new=$_POST['new']??''; $con=$_POST['con']??'';
        if (!password_verify($cur, $officer['password_hash']??'')) $err='Current password is incorrect.';
        elseif (strlen($new)<8) $err='Password must be at least 8 characters.';
        elseif ($new!==$con)    $err='New passwords do not match.';
        else {
            try { $pdo->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>12]),$uid]); $ok='Password updated.'; }
            catch (PDOException $e){ $err='Update failed.'; }
        }
    }
}

// Stats
$stats = [];
try {
    $stats['total']    = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE barangay_id=$bid")->fetchColumn();
    $stats['resolved'] = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE barangay_id=$bid AND status IN ('resolved','closed')")->fetchColumn();
    $stats['pending']  = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE barangay_id=$bid AND status='pending_review'")->fetchColumn();
    $stats['med']      = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.barangay_id=$bid AND ms.status='scheduled' AND ms.hearing_date>=CURDATE()")->fetchColumn();
} catch(PDOException $e){}
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Settings</h2><p>Manage your officer account</p></div>
</div>

<?php if ($ok): ?><div class="alert alert-emerald mb16"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--emerald-600)" stroke-width="1.5" stroke-linecap="round"><path d="M3 8.5l3 3 7-7"/></svg><div class="alert-text"><strong><?= e($ok) ?></strong></div></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-rose mb16"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--rose-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/></svg><div class="alert-text"><strong><?= e($err) ?></strong></div></div><?php endif; ?>

<div class="g2">
  <!-- Left column -->
  <div>
    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Officer Profile</span></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
          <div style="width:52px;height:52px;border-radius:50%;background:var(--teal-600);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700">
            <?= strtoupper(substr($officer['full_name']??'',0,2)) ?>
          </div>
          <div>
            <div style="font-size:15px;font-weight:700;color:var(--ink-900)"><?= e($officer['full_name']??'') ?></div>
            <div style="font-size:13px;color:var(--ink-400)"><?= e($officer['email']??'') ?></div>
            <span class="chip ch-teal" style="margin-top:4px">Barangay Officer</span>
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="_action" value="profile">
          <div class="fg"><label>Full Name</label><input type="text" name="full_name" value="<?= e($officer['full_name']??'') ?>"></div>
          <div class="fg"><label>Email <small style="color:var(--ink-300)">(cannot change)</small></label><input type="email" value="<?= e($officer['email']??'') ?>" readonly style="background:var(--surface);color:var(--ink-400)"></div>
          <div class="fg"><label>Contact Number</label><input type="tel" name="contact_number" value="<?= e($officer['contact_number']??'') ?>" placeholder="09XXXXXXXXX"></div>
          <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-hdr"><span class="card-title">Change Password</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_action" value="password">
          <div class="fg"><label>Current Password</label><input type="password" name="cur" placeholder="••••••••"></div>
          <div class="fg"><label>New Password</label><input type="password" name="new" placeholder="Min. 8 characters"></div>
          <div class="fg"><label>Confirm Password</label><input type="password" name="con" placeholder="Repeat new password"></div>
          <button type="submit" class="btn btn-outline btn-sm">Update Password</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right column -->
  <div>
    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Barangay Overview</span></div>
      <div class="card-body" style="padding:12px 18px">
        <?php foreach ([
          'Barangay'    => $bgy['name'],
          'Municipality'=> $bgy['municipality'],
          'Province'    => $bgy['province'],
          'Captain'     => $bgy['captain_name'],
          'Contact'     => $bgy['contact_no'],
        ] as $k => $v): ?>
          <div class="dr"><span class="dr-lbl"><?= $k ?></span><span class="dr-val"><?= e((string)$v ?: '—') ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Quick Stats</span></div>
      <div class="card-body" style="padding:12px 18px">
        <?php foreach ([
          ['Total Blotters',   $stats['total']   ?? 0, 'ch-teal'],
          ['Resolved',         $stats['resolved']?? 0, 'ch-emerald'],
          ['Pending Review',   $stats['pending'] ?? 0, 'ch-amber'],
          ['Upcoming Hearings',$stats['med']     ?? 0, 'ch-navy'],
        ] as [$lbl,$val,$ch]): ?>
          <div class="dr" style="align-items:center">
            <span class="dr-lbl"><?= $lbl ?></span>
            <span class="chip <?= $ch ?>"><?= $val ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-hdr"><span class="card-title">Quick Links</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <a href="?page=blotter-management&status=pending_review" class="btn btn-outline btn-sm" style="justify-content:flex-start">📋 Pending Reviews</a>
        <a href="?page=mediation&tab=upcoming" class="btn btn-outline btn-sm" style="justify-content:flex-start">📅 Upcoming Hearings</a>
        <a href="?page=violator-monitor" class="btn btn-outline btn-sm" style="justify-content:flex-start">👁 Violator Monitor</a>
        <a href="../connection/logout.php" class="btn btn-danger btn-sm" style="justify-content:flex-start">🚪 Logout</a>
      </div>
    </div>
  </div>
</div>
