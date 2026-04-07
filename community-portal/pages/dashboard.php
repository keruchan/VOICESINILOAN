<?php
// pages/dashboard.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

$kpi = ['filed'=>0,'active'=>0,'as_violator'=>0,'pending_med'=>0];
try {
    $kpi['filed']       = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid")->fetchColumn();
    $kpi['active']      = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid AND status NOT IN ('resolved','closed','transferred')")->fetchColumn();
    $kpi['as_violator'] = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE user_id=$uid")->fetchColumn();
    $kpi['pending_med'] = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.complainant_user_id=$uid AND ms.status='scheduled' AND ms.hearing_date>=CURDATE()")->fetchColumn();
} catch (PDOException $e) {}

// Recent blotters
$recent = [];
try {
    $recent = $pdo->query("SELECT id,case_number,incident_type,violation_level,status,created_at FROM blotters WHERE complainant_user_id=$uid ORDER BY created_at DESC LIMIT 6")->fetchAll();
} catch (PDOException $e) {}

// Upcoming hearings
$hearings = [];
try {
    $hearings = $pdo->query("SELECT ms.hearing_date,ms.hearing_time,ms.venue,b.case_number FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.complainant_user_id=$uid AND ms.status='scheduled' AND ms.hearing_date>=CURDATE() ORDER BY ms.hearing_date ASC LIMIT 3")->fetchAll();
} catch (PDOException $e) {}

// Unread notices
$notices_count = 0;
try {
    $notices_count = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE recipient_user_id=$uid AND acknowledged_at IS NULL")->fetchColumn();
} catch (PDOException $e) {}

// Active penalties - UPDATED to include p.community_hours
$penalties = [];
try {
    $penalties = $pdo->query("SELECT p.reason, p.amount, p.community_hours, p.due_date, p.status FROM penalties p JOIN blotters b ON b.id=p.blotter_id WHERE b.complainant_user_id=$uid OR EXISTS(SELECT 1 FROM violations v WHERE v.blotter_id=b.id AND v.user_id=$uid) ORDER BY p.created_at DESC LIMIT 3")->fetchAll();
} catch (PDOException $e) {}

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-green','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'];
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Welcome, <?= e(explode(' ', $user['name'] ?? 'Resident')[0]) ?></h2>
    <p><?= e($bgy_name) ?> · <?= date('F j, Y') ?></p>
  </div>
  <div class="page-hdr-actions">
    <a href="?page=file-report" class="btn btn-primary">+ File a Report</a>
  </div>
</div>

<?php if ($notices_count > 0): ?>
<div class="alert alert-amber mb16">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--amber-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11.5" r=".5" fill="currentColor"/></svg>
  <div class="alert-text">
    <strong>You have <?= $notices_count ?> unread notice(s) from your barangay</strong>
    <span><a href="?page=notices" style="color:var(--amber-600);font-weight:600">View notices →</a></span>
  </div>
</div>
<?php endif; ?>

<div class="kpi-grid">
  <div class="kpi-card kc-green">
    <div class="kpi-top"><div class="kpi-icon ki-green"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M5 7h8M5 10h5"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['filed'] ?></div><div class="kpi-lbl">Reports Filed</div>
  </div>
  <div class="kpi-card kc-amber">
    <div class="kpi-top"><div class="kpi-icon ki-amber"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="9" r="7"/><path d="M9 5.5v3.5l2.5 2"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['active'] ?></div><div class="kpi-lbl">Active Cases</div>
  </div>
  <div class="kpi-card kc-rose">
    <div class="kpi-top"><div class="kpi-icon ki-rose"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="7" r="3.5"/><path d="M3 17c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['as_violator'] ?></div><div class="kpi-lbl">Cases Against Me</div>
  </div>
  <div class="kpi-card kc-teal">
    <div class="kpi-top"><div class="kpi-icon ki-teal"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="2" y="3" width="14" height="13" rx="2"/><path d="M2 7.5h14M6 3V1.5M12 3V1.5"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['pending_med'] ?></div><div class="kpi-lbl">Upcoming Hearings</div>
  </div>
</div>

<div class="g21 mb22">
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">My Recent Blotters</div>
      <a href="?page=my-blotters" class="act-btn">View all →</a>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Case No.</th><th>Incident</th><th>Level</th><th>Status</th><th>Filed</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="es-icon">📋</div><div class="es-title">No reports yet</div><div class="es-sub"><a href="?page=file-report" style="color:var(--green-600)">File your first report →</a></div></div></td></tr>
        <?php else: foreach ($recent as $b): ?>
          <tr>
            <td class="td-mono"><?= e($b['case_number']) ?></td>
            <td class="td-main"><?= e($b['incident_type']) ?></td>
            <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
            <td><span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
            <td style="font-size:12px;color:var(--ink-400)"><?= date('M j', strtotime($b['created_at'])) ?></td>
            <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <?php if (!empty($hearings)): ?>
    <div class="card mb16">
      <div class="card-hdr"><div class="card-title">📅 Upcoming Hearings</div><a href="?page=mediation" class="act-btn">All</a></div>
      <div class="card-body" style="padding:0 18px">
        <?php foreach ($hearings as $h): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--surface-2)">
          <div style="font-size:13px;font-weight:600;color:var(--ink-900)"><?= e($h['case_number']) ?></div>
          <div style="font-size:12px;color:var(--green-700);font-weight:600;margin-top:2px"><?= date('D, M j, Y', strtotime($h['hearing_date'])) ?></div>
          <div style="font-size:11px;color:var(--ink-400)">
            <?= $h['hearing_time'] ? date('g:i A', strtotime($h['hearing_time'])) . ' · ' : '' ?><?= e($h['venue'] ?: 'Barangay Hall') ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($penalties)): ?>
    <div class="card mb16">
      <div class="card-hdr"><div class="card-title">⚖️ Active Sanctions</div><a href="?page=notices" class="act-btn">All</a></div>
      <div class="card-body" style="padding:0 18px">
        <?php foreach ($penalties as $pen):
          $pc = ['pending'=>'ch-amber','paid'=>'ch-green','overdue'=>'ch-rose','waived'=>'ch-slate'][$pen['status']]??'ch-slate';
        ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--surface-2)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div style="font-size:13px;font-weight:500;color:var(--ink-900);flex:1;margin-right:8px"><?= e($pen['reason']) ?></div>
            <span class="chip <?= $pc ?>"><?= ucfirst($pen['status']) ?></span>
          </div>
          
          <div style="font-size:13px;font-weight:700;color:var(--rose-600);margin-top:4px;display:flex;align-items:center;gap:6px;">
            <?php 
                $hasFine = (float)$pen['amount'] > 0;
                $hasService = isset($pen['community_hours']) && (int)$pen['community_hours'] > 0;
            ?>
            
            <?php if ($hasFine): ?>
                <span>₱<?= number_format((float)$pen['amount'], 2) ?></span>
            <?php endif; ?>
            
            <?php if ($hasFine && $hasService): ?>
                <span style="color:var(--ink-300);font-weight:400;">&bull;</span>
            <?php endif; ?>
            
            <?php if ($hasService): ?>
                <span style="color:var(--amber-600);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:text-bottom;margin-right:2px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= (int)$pen['community_hours'] ?> hrs Service
                </span>
            <?php endif; ?>

            <?php if (!$hasFine && !$hasService): ?>
                <span style="color:var(--ink-500);font-weight:500;">Warning / Documented</span>
            <?php endif; ?>
          </div>

          <?php if ($pen['due_date']): ?><div style="font-size:11px;color:var(--ink-400);margin-top:2px">Due: <?= date('M j, Y', strtotime($pen['due_date'])) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-hdr"><div class="card-title">Quick Actions</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <a href="?page=file-report" class="btn btn-primary" style="justify-content:flex-start">📝 File a New Report</a>
        <a href="?page=my-blotters" class="btn btn-outline" style="justify-content:flex-start">📋 View My Blotters</a>
        <a href="?page=assigned-cases" class="btn btn-outline" style="justify-content:flex-start">⚠️ Cases Against Me</a>
        <a href="?page=notices" class="btn btn-outline" style="justify-content:flex-start">🔔 Notices & Sanctions</a>
      </div>
    </div>
  </div>
</div>