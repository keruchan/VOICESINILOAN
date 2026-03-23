<?php
// pages/assigned-cases.php
$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

// Violations linked to this user
$violations = [];
try {
    $violations = $pdo->query("
        SELECT v.*, b.case_number, b.incident_type, b.violation_level, b.status AS blotter_status,
               b.complainant_name, b.incident_date, b.prescribed_action, b.narrative
        FROM violations v
        JOIN blotters b ON b.id = v.blotter_id
        WHERE v.user_id = $uid
        ORDER BY v.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// Penalties for this user's violations
$penalties = [];
try {
    $penalties = $pdo->query("
        SELECT p.*, b.case_number
        FROM penalties p
        JOIN violations v ON v.id = p.violation_id
        JOIN blotters b ON b.id = p.blotter_id
        WHERE v.user_id = $uid
        ORDER BY p.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// Blotters where respondent name matches (fallback for unlinked records)
$name_matches = [];
try {
    $uname = $user['name'] ?? '';
    if ($uname) {
        $name_matches = $pdo->query("
            SELECT b.id, b.case_number, b.incident_type, b.violation_level, b.status,
                   b.complainant_name, b.prescribed_action, b.incident_date, b.created_at
            FROM blotters b
            WHERE b.barangay_id = $bid
              AND b.respondent_name LIKE " . $pdo->quote("%$uname%") . "
              AND NOT EXISTS (SELECT 1 FROM violations v WHERE v.blotter_id=b.id AND v.user_id=$uid)
            ORDER BY b.created_at DESC
        ")->fetchAll();
    }
} catch (PDOException $e) {}

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-green','closed'=>'ch-slate','escalated'=>'ch-rose'];
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Cases Against Me</h2><p>Blotters where you are named as the respondent or violator</p></div>
</div>

<?php if (empty($violations) && empty($name_matches)): ?>
  <div class="empty-state"><div class="es-icon">✅</div><div class="es-title">No cases on record</div><div class="es-sub">You have no active or past violations recorded in the system.</div></div>
<?php else: ?>

<?php if (!empty($violations)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-400);margin-bottom:12px">VIOLATION RECORDS</div>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:22px">
  <?php foreach ($violations as $v): ?>
  <div class="card">
    <div class="card-body" style="padding:16px 18px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;gap:12px">
        <div>
          <div style="font-size:15px;font-weight:700;color:var(--ink-900)"><?= e($v['case_number']) ?></div>
          <div style="font-size:12px;color:var(--ink-400);margin-top:2px"><?= e($v['incident_type']) ?> · <?= date('M j, Y', strtotime($v['incident_date'])) ?></div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <span class="chip <?= $lm[$v['violation_level']]??'ch-slate' ?>"><?= ucfirst($v['violation_level']) ?></span>
          <span class="chip <?= $sm[$v['blotter_status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$v['blotter_status'])) ?></span>
        </div>
      </div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($v['complainant_name']) ?></span></div>
      <div class="dr"><span class="dr-lbl">Prescribed Action</span><span class="dr-val"><?= e(ucwords(str_replace('_',' ',$v['prescribed_action']??'Pending'))) ?></span></div>
      <div class="dr"><span class="dr-lbl">Risk Score</span><span class="dr-val">
        <div style="display:flex;align-items:center;gap:8px">
          <div style="width:60px;height:6px;background:var(--surface-2);border-radius:10px;overflow:hidden"><div style="width:<?= min(100,(int)$v['risk_score']) ?>%;height:100%;background:<?= $v['risk_score']>=75?'var(--rose-400)':($v['risk_score']>=50?'var(--amber-400)':'var(--green-500)') ?>;border-radius:10px"></div></div>
          <span style="font-size:12px;font-weight:600"><?= (int)$v['risk_score'] ?>/100</span>
        </div>
      </span></div>
      <?php if ($v['missed_hearings'] > 0): ?>
      <div class="dr"><span class="dr-lbl">Missed Hearings</span><span class="dr-val"><span class="chip ch-rose"><?= (int)$v['missed_hearings'] ?> missed</span></span></div>
      <?php endif; ?>
    </div>
    <div class="card-foot" style="display:flex;gap:8px">
      <button class="act-btn" onclick="viewBlotter(<?= $v['blotter_id'] ?>)">View Case</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($penalties)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-400);margin-bottom:12px">MY PENALTIES</div>
<div class="card mb22">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Reason</th><th>Amount</th><th>Due Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($penalties as $p):
        $pc = ['pending'=>'ch-amber','paid'=>'ch-green','overdue'=>'ch-rose','waived'=>'ch-slate'][$p['status']]??'ch-slate';
      ?>
        <tr>
          <td class="td-mono"><?= e($p['case_number']) ?></td>
          <td class="td-main"><?= e($p['reason']) ?></td>
          <td style="font-weight:700;color:var(--rose-600)">₱<?= number_format((float)$p['amount']) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= $p['due_date'] ? date('M j, Y', strtotime($p['due_date'])) : '—' ?></td>
          <td><span class="chip <?= $pc ?>"><?= ucfirst($p['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($name_matches)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-400);margin-bottom:12px">OTHER MATCHED RECORDS (by name)</div>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Incident</th><th>Level</th><th>Status</th><th>Complainant</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($name_matches as $b): ?>
        <tr>
          <td class="td-mono"><?= e($b['case_number']) ?></td>
          <td class="td-main"><?= e($b['incident_type']) ?></td>
          <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
          <td><span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
          <td><?= e($b['complainant_name']) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
          <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
