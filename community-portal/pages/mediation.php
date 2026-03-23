<?php // community-portal/pages/mediation.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

$upcoming = []; $past = [];
try {
    // Hearings for blotters I filed
    $upcoming = $pdo->query("
        SELECT ms.hearing_date, ms.hearing_time, ms.venue, ms.id AS med_id,
               b.id AS blotter_id, b.case_number, b.incident_type, b.violation_level,
               b.complainant_name, b.respondent_name
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.complainant_user_id = $uid
          AND ms.status = 'scheduled' AND ms.hearing_date >= CURDATE()
        UNION
        SELECT ms.hearing_date, ms.hearing_time, ms.venue, ms.id AS med_id,
               b.id AS blotter_id, b.case_number, b.incident_type, b.violation_level,
               b.complainant_name, b.respondent_name
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        JOIN violations v ON v.blotter_id = b.id AND v.user_id = $uid
        WHERE ms.status = 'scheduled' AND ms.hearing_date >= CURDATE()
        ORDER BY hearing_date ASC
    ")->fetchAll();

    $past = $pdo->query("
        SELECT ms.hearing_date, ms.hearing_time, ms.venue, ms.status,
               ms.outcome, ms.complainant_attended, ms.respondent_attended,
               b.case_number, b.incident_type
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.complainant_user_id = $uid
          AND (ms.status != 'scheduled' OR ms.hearing_date < CURDATE())
        ORDER BY ms.hearing_date DESC LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) {}

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sc = ['completed'=>'ch-green','cancelled'=>'ch-rose','missed'=>'ch-amber','rescheduled'=>'ch-navy'];
?>
<div class="page-hdr">
  <div class="page-hdr-left"><h2>Mediation Schedule</h2><p>All hearings related to your cases</p></div>
</div>

<?php if (!empty($upcoming)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-400);margin-bottom:12px">UPCOMING HEARINGS</div>
<div class="g2 mb22">
  <?php foreach ($upcoming as $h): ?>
  <div class="card" style="border-top:3px solid var(--green-500)">
    <div class="card-body" style="padding:16px 18px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div>
          <div style="font-size:15px;font-weight:700;color:var(--ink-900)"><?= e($h['case_number']) ?></div>
          <div style="font-size:12px;color:var(--ink-400)"><?= e($h['incident_type']) ?></div>
        </div>
        <span class="chip <?= $lm[$h['violation_level']]??'ch-slate' ?>"><?= ucfirst($h['violation_level']) ?></span>
      </div>
      <div style="background:var(--green-50);border:1px solid var(--green-100);border-radius:var(--r-md);padding:12px;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700;color:var(--green-700)"><?= date('D, M j, Y', strtotime($h['hearing_date'])) ?></div>
        <?php if ($h['hearing_time']): ?><div style="font-size:14px;color:var(--green-600);font-weight:600;margin-top:2px"><?= date('g:i A', strtotime($h['hearing_time'])) ?></div><?php endif; ?>
        <div style="font-size:12px;color:var(--ink-500);margin-top:4px">📍 <?= e($h['venue'] ?: 'Barangay Hall') ?></div>
      </div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($h['complainant_name']) ?></span></div>
      <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val"><?= e($h['respondent_name'] ?: 'Unknown') ?></span></div>
    </div>
    <div class="card-foot">
      <button class="act-btn" onclick="viewBlotter(<?= $h['blotter_id'] ?>)">View Case Details</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state mb22"><div class="es-icon">📅</div><div class="es-title">No upcoming hearings</div><div class="es-sub">Your barangay will notify you when a hearing is scheduled</div></div>
<?php endif; ?>

<?php if (!empty($past)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-400);margin-bottom:12px">PAST HEARINGS</div>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Type</th><th>Date</th><th>Status</th><th>You Attended</th><th>Outcome</th></tr></thead>
      <tbody>
      <?php foreach ($past as $h): ?>
        <tr>
          <td class="td-mono"><?= e($h['case_number']) ?></td>
          <td><?= e($h['incident_type']) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($h['hearing_date'])) ?></td>
          <td><span class="chip <?= $sc[$h['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$h['status'])) ?></span></td>
          <td>
            <?php if ($h['complainant_attended'] !== null): ?>
              <span class="chip <?= $h['complainant_attended']?'ch-green':'ch-rose' ?>"><?= $h['complainant_attended']?'Yes':'No' ?></span>
            <?php else: ?><span style="color:var(--ink-300)">—</span><?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--ink-500);max-width:200px;white-space:normal"><?= e(mb_strimwidth($h['outcome']??'—',0,70,'…')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
