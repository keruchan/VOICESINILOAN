<?php
// pages/assigned-cases.php
// Uses respondent_user_id (direct link) AND respondent_name match (walk-in records)
$uid  = (int)$user['id'];
$bid  = (int)$user['barangay_id'];
$uname = $user['name'] ?? '';

// Cases where I am the named respondent (direct user link) — ALL statuses
$direct_cases = [];
try {
    $direct_cases = $pdo->query("
        SELECT b.*,
               (SELECT COUNT(*) FROM mediation_schedules ms WHERE ms.blotter_id=b.id AND ms.status='scheduled' AND ms.hearing_date>=CURDATE()) AS upcoming_med
        FROM blotters b
        WHERE b.barangay_id=$bid AND b.respondent_user_id=$uid
        ORDER BY b.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// Cases where name matches (unlinked walk-in records — respondent_user_id IS NULL)
// Build multiple LIKE conditions for name parts to handle comma vs no-comma variations
$name_cases = [];
if ($uname) {
    try {
        // Split name into parts (words) and search for each significant part
        $parts = array_filter(preg_split('/[\s,]+/', $uname), fn($p) => strlen($p) > 2);
        $likes = [];
        foreach ($parts as $part) {
            $likes[] = "b.respondent_name LIKE '%" . addslashes($part) . "%'";
        }
        // Must match ALL significant name parts to reduce false positives
        $name_cond = !empty($likes) ? '(' . implode(' AND ', $likes) . ')' : '1=0';
        // Exclude blotters already returned by direct link
        $exclude_ids = !empty($direct_cases) ? 'AND b.id NOT IN (' . implode(',', array_column($direct_cases,'id')) . ')' : '';

        $name_cases = $pdo->query("
            SELECT b.*,
                   (SELECT COUNT(*) FROM mediation_schedules ms WHERE ms.blotter_id=b.id AND ms.status='scheduled' AND ms.hearing_date>=CURDATE()) AS upcoming_med
            FROM blotters b
            WHERE b.barangay_id=$bid
              AND b.respondent_user_id IS NULL
              AND $name_cond
              $exclude_ids
            ORDER BY b.created_at DESC
        ")->fetchAll();
    } catch (PDOException $e) {}
}

$all_against_ids = array_merge(
    array_column($direct_cases, 'id'),
    array_column($name_cases,   'id')
);

// Penalties against me (missed_party = respondent or both, blotter in my cases)
$my_penalties = [];
if (!empty($all_against_ids)) {
    try {
        $in = implode(',', $all_against_ids);
        $my_penalties = $pdo->query("
            SELECT p.*, b.case_number
            FROM penalties p
            JOIN blotters b ON b.id = p.blotter_id
            WHERE p.blotter_id IN ($in)
              AND p.missed_party IN ('respondent','both')
            ORDER BY p.created_at DESC
        ")->fetchAll();
    } catch (PDOException $e) {}
}

// Upcoming mediations where I am respondent
$my_hearings = [];
if (!empty($all_against_ids)) {
    try {
        $in = implode(',', $all_against_ids);
        $my_hearings = $pdo->query("
            SELECT ms.*, b.case_number, b.complainant_name
            FROM mediation_schedules ms
            JOIN blotters b ON b.id = ms.blotter_id
            WHERE ms.blotter_id IN ($in)
              AND ms.status = 'scheduled'
              AND CONCAT(ms.hearing_date, ' ', COALESCE(ms.hearing_time, '23:59:59')) > NOW()
            ORDER BY ms.hearing_date ASC, ms.hearing_time ASC
        ")->fetchAll();
    } catch (PDOException $e) {}
}

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-green','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate','dismissed'=>'ch-slate','cfa_issued'=>'ch-violet'];

$total = count($direct_cases) + count($name_cases);
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Cases Against Me</h2>
    <p>Blotters where you are named as the respondent / violator</p>
  </div>
</div>

<?php if ($total === 0): ?>
  <div class="empty-state"><div class="es-icon">✅</div><div class="es-title">No cases on record</div><div class="es-sub">You have no active violations or cases filed against you.</div></div>
<?php else: ?>

<!-- ── Upcoming hearings I must attend ── -->
<?php if (!empty($my_hearings)): ?>
<div class="alert alert-amber mb16" style="align-items:center">
  <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="var(--amber-600)" stroke-width="1.5" stroke-linecap="round" style="flex-shrink:0"><rect x="2" y="3" width="14" height="13" rx="2"/><path d="M2 7.5h14M6 3V1.5M12 3V1.5"/></svg>
  <div class="alert-text" style="flex:1">
    <strong>You have <?= count($my_hearings) ?> upcoming mediation hearing(s) you must attend</strong>
    <span>Missing a hearing may result in legal consequences. See below for details.</span>
  </div>
  <a href="?page=mediation" class="btn btn-outline btn-sm" style="flex-shrink:0;border-color:var(--amber-400);color:var(--amber-600);white-space:nowrap">
    📅 View Schedule
  </a>
</div>
<div class="g2 mb22">
  <?php foreach ($my_hearings as $h): ?>
  <div class="card" style="border-top:3px solid var(--amber-400)">
    <div class="card-hdr" style="background:var(--amber-50)">
      <div><div class="card-title"><?= e($h['case_number']) ?></div><div class="card-sub">You must attend as respondent</div></div>
      <span class="chip ch-amber" style="font-size:10px"><?= date('M j',strtotime($h['hearing_date'])) ?></span>
    </div>
    <div class="card-body" style="padding:12px 16px">
      <div class="dr"><span class="dr-lbl">📅 Date</span><span class="dr-val" style="font-weight:700;color:var(--amber-600)"><?= date('D, M j, Y',strtotime($h['hearing_date'])) ?></span></div>
      <div class="dr"><span class="dr-lbl">⏰ Time</span><span class="dr-val"><?= $h['hearing_time']?date('g:i A',strtotime($h['hearing_time'])):'TBD' ?></span></div>
      <div class="dr"><span class="dr-lbl">📍 Venue</span><span class="dr-val"><?= e($h['venue']?:'Barangay Hall') ?></span></div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($h['complainant_name']) ?></span></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Penalties ── -->
<?php if (!empty($my_penalties)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-400);margin-bottom:10px">Penalties Against Me</div>
<div class="card mb22">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Reason</th><th>Amount</th><th>Community Service</th><th>Due Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($my_penalties as $p):
        $pc = ['pending'=>'ch-amber','paid'=>'ch-green','overdue'=>'ch-rose','waived'=>'ch-slate'][$p['status']]??'ch-slate';
      ?>
        <tr>
          <td class="td-mono"><?= e($p['case_number']) ?></td>
          <td class="td-main"><?= e($p['reason']) ?></td>
          <td style="font-weight:700;color:var(--rose-600)">₱<?= number_format((float)$p['amount']) ?></td>
          <td style="font-size:12px"><?= $p['community_hours']?$p['community_hours'].' hrs':'—' ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= $p['due_date']?date('M j, Y',strtotime($p['due_date'])):'—' ?></td>
          <td><span class="chip <?= $pc ?>"><?= ucfirst($p['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Direct cases (respondent_user_id linked) ── -->
<?php if (!empty($direct_cases)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-400);margin-bottom:10px">
  Cases Filed Against You
</div>
<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:22px">
  <?php foreach ($direct_cases as $b): ?>
  <div class="card">
    <div class="card-body" style="padding:16px 18px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;gap:10px">
        <div>
          <div style="font-size:15px;font-weight:700;color:var(--ink-900)"><?= e($b['case_number']) ?></div>
          <div style="font-size:12px;color:var(--ink-400);margin-top:2px"><?= e($b['incident_type']) ?> · <?= date('M j, Y',strtotime($b['incident_date'])) ?></div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span>
          <span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span>
        </div>
      </div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($b['complainant_name']) ?></span></div>
      <div class="dr"><span class="dr-lbl">Location</span><span class="dr-val"><?= e($b['incident_location']?:'—') ?></span></div>
      <div class="dr"><span class="dr-lbl">Prescribed Action</span><span class="dr-val"><?= e(ucwords(str_replace('_',' ',$b['prescribed_action']??'pending'))) ?></span></div>
      <?php if ($b['upcoming_med'] > 0): ?>
      <div class="dr"><span class="dr-lbl">Hearings Scheduled</span><span class="dr-val"><span class="chip ch-amber"><?= (int)$b['upcoming_med'] ?> upcoming</span></span></div>
      <?php endif; ?>
      <?php if ($b['status'] === 'cfa_issued'): ?>
      <div style="margin-top:10px;padding:10px;background:var(--violet-50);border:1px solid #ddd6fe;border-radius:var(--r-sm);font-size:12px;color:var(--violet-600)">
        ⚠️ <strong>Certification to File Action (CFA) issued.</strong> The complainant may now bring this case to court.
      </div>
      <?php endif; ?>
      <?php if ($b['status'] === 'dismissed'): ?>
      <div style="margin-top:10px;padding:10px;background:var(--green-50);border:1px solid var(--green-100);border-radius:var(--r-sm);font-size:12px;color:var(--green-700)">
        ✅ This case has been dismissed. No further action required from you at this time.
      </div>
      <?php endif; ?>
    </div>
    <div class="card-foot" style="display:flex;gap:8px">
      <button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View Full Case</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Name-matched cases (walk-in, unlinked) ── -->
<?php if (!empty($name_cases)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-400);margin-bottom:6px">Other Matched Records (by name)</div>
<div style="font-size:12px;color:var(--ink-400);margin-bottom:12px">These records match your name but may not have been directly linked to your account.</div>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Type</th><th>Level</th><th>Status</th><th>Complainant</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($name_cases as $b): ?>
        <tr>
          <td class="td-mono"><?= e($b['case_number']) ?></td>
          <td class="td-main"><?= e($b['incident_type']) ?></td>
          <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
          <td><span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
          <td><?= e($b['complainant_name']) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y',strtotime($b['created_at'])) ?></td>
          <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; // end $total === 0 ?>