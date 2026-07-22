<?php
// pages/mediation.php — KP Law compliant
$bid = (int)$user['barangay_id'];
$tab = $_GET['tab'] ?? 'upcoming';

finalize_due_settlements($pdo, $bid);

$upcoming = $overdue = $past = $active_cases = $settlements = [];

try {
    $upcoming = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name,
               b.incident_type, b.violation_level, b.id AS blotter_id,
               b.complainant_missed, b.respondent_missed
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid AND ms.status = 'scheduled' AND ms.hearing_date >= CURDATE()
        ORDER BY ms.hearing_date ASC
    ")->fetchAll();

    $overdue = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name,
               b.incident_type, b.violation_level, b.id AS blotter_id,
               b.complainant_missed, b.respondent_missed,
               DATEDIFF(CURDATE(), ms.hearing_date) AS days_overdue
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid AND ms.status = 'scheduled' AND ms.hearing_date < CURDATE()
        ORDER BY ms.hearing_date ASC
    ")->fetchAll();

    $past = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name,
               b.incident_type, b.id AS blotter_id,
               b.complainant_missed, b.respondent_missed, b.status AS blotter_status
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid AND ms.status != 'scheduled'
        ORDER BY ms.hearing_date DESC
        LIMIT 60
    ")->fetchAll();

    $active_cases = $pdo->query("
        SELECT id, case_number, complainant_name, respondent_name
        FROM blotters
        WHERE barangay_id = $bid AND status IN ('active','pending_review','mediation_set')
        ORDER BY created_at DESC
    ")->fetchAll();

    // Amicable settlements — active (within repudiation window) and resolved history
    $settlements = $pdo->query("
        SELECT s.*, b.case_number, b.complainant_name, b.respondent_name, b.id AS blotter_id,
               DATEDIFF(s.repudiation_deadline, CURDATE()) AS days_left
        FROM amicable_settlements s
        JOIN blotters b ON b.id = s.blotter_id
        WHERE s.barangay_id = $bid
        ORDER BY (s.status = 'active') DESC, s.settled_date DESC
        LIMIT 60
    ")->fetchAll();

} catch (PDOException $e) {}

$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sc = ['completed'=>'ch-emerald','cancelled'=>'ch-rose','missed'=>'ch-amber','rescheduled'=>'ch-navy'];
$at = ['cfa_issued'=>'ch-violet','dismissed'=>'ch-rose','warning_sent'=>'ch-amber','rescheduled_1st'=>'ch-navy','rescheduled_2nd'=>'ch-rose'];
$bs = [
    'cfa_issued'  => ['ch-violet','CFA Issued'],
    'dismissed'   => ['ch-rose',  'Dismissed'],
    'mediation_set'=>['ch-navy',  'Mediation Set'],
    'resolved'    => ['ch-emerald','Resolved'],
    'active'      => ['ch-teal',  'Active'],
];
?>

<div class="med-page">
<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Mediation Management</h2>
    <p><?= e($bgy['name']) ?> · Katarungang Pambarangay Process</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <button class="btn btn-primary" onclick="openModal('modal-new-med')">+ Schedule Hearing</button>
  </div>
</div>

<!-- Overdue alert -->
<?php if (count($overdue) > 0): ?>
<div class="alert alert-rose mb16">
  <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="var(--rose-600)" stroke-width="1.5" stroke-linecap="round" style="flex-shrink:0;margin-top:1px"><circle cx="9" cy="9" r="7"/><path d="M9 5.5v4"/><circle cx="9" cy="12.5" r=".7" fill="currentColor"/></svg>
  <div class="alert-text">
    <strong><?= count($overdue) ?> hearing(s) passed without documentation</strong>
    <span>These must be documented. Go to <a href="?page=mediation&tab=overdue" style="color:var(--rose-600);font-weight:600">Needs Documentation →</a></span>
  </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="tab-bar">
  <a class="tab-item <?= $tab==='upcoming'?'active':'' ?>" href="?page=mediation&tab=upcoming">
    Upcoming <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= count($upcoming) ?></span>
  </a>
  <a class="tab-item <?= $tab==='overdue'?'active':'' ?>" href="?page=mediation&tab=overdue" <?= count($overdue)>0?'style="color:var(--rose-600)"':'' ?>>
    Needs Documentation
    <?php if (count($overdue) > 0): ?><span style="font-size:10px;background:var(--rose-50);color:var(--rose-600);border:1px solid var(--rose-100);padding:0 6px;border-radius:10px;margin-left:3px;font-weight:700"><?= count($overdue) ?></span><?php endif; ?>
  </a>
  <a class="tab-item <?= $tab==='past'?'active':'' ?>" href="?page=mediation&tab=past">
    History <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= count($past) ?></span>
  </a>
  <?php $active_settlements = array_filter($settlements, fn($s) => $s['status']==='active'); ?>
  <a class="tab-item <?= $tab==='settlements'?'active':'' ?>" href="?page=mediation&tab=settlements">
    Settlements <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= count($active_settlements) ?></span>
  </a>
</div>

<?php /* ══════════ UPCOMING ══════════ */ if ($tab === 'upcoming'): ?>
<?php if (empty($upcoming)): ?>
  <div class="empty-state"><div class="es-icon">📅</div><div class="es-title">No upcoming hearings</div><div class="es-sub">Schedule hearings from the button above</div></div>
<?php else: ?>
<div class="g2">
  <?php foreach ($upcoming as $m):
    $days_left = (int)floor((strtotime($m['hearing_date']) - time()) / 86400);
    $is_today  = $m['hearing_date'] === date('Y-m-d');
    $urgent    = $days_left <= 1;
    $cm = (int)$m['complainant_missed']; $rm = (int)$m['respondent_missed'];
  ?>
  <div class="card" style="<?= $is_today?'border-top:3px solid var(--amber-400)':($urgent?'border-top:3px solid var(--teal-400)':'') ?>">
    <div class="card-hdr" style="background:<?= $is_today?'var(--amber-50)':'var(--teal-50)' ?>">
      <div>
        <div class="card-title"><?= e($m['case_number']) ?></div>
        <div class="card-sub"><?= e($m['incident_type']) ?></div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
        <span class="chip <?= $lm[$m['violation_level']]??'ch-slate' ?>"><?= ucfirst($m['violation_level']) ?></span>
        <?php if ($is_today): ?><span class="chip ch-amber" style="font-size:10px">TODAY</span>
        <?php elseif ($urgent): ?><span class="chip ch-amber" style="font-size:10px">TOMORROW</span><?php endif; ?>
      </div>
    </div>
    <div class="card-body" style="padding:14px 18px">
      <div class="dr"><span class="dr-lbl">📅 Date</span><span class="dr-val" style="font-weight:700;color:<?= $is_today?'var(--amber-600)':'var(--teal-600)' ?>"><?= date('D, M j, Y', strtotime($m['hearing_date'])) ?></span></div>
      <div class="dr"><span class="dr-lbl">⏰ Time</span><span class="dr-val"><?= $m['hearing_time']?date('g:i A',strtotime($m['hearing_time'])):'TBD' ?></span></div>
      <div class="dr"><span class="dr-lbl">📍 Venue</span><span class="dr-val"><?= e($m['venue']?:'Barangay Hall') ?></span></div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($m['complainant_name']) ?></span></div>
      <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val"><?= e($m['respondent_name']?:'Unknown') ?></span></div>
      <?php if ($cm > 0 || $rm > 0): ?>
      <div class="dr" style="margin-top:4px">
        <span class="dr-lbl">Miss History</span>
        <span class="dr-val" style="display:flex;gap:4px;flex-wrap:wrap">
          <?php if ($cm > 0): ?><span class="chip ch-amber" style="font-size:10px">Comp. missed: <?= $cm ?>x</span><?php endif; ?>
          <?php if ($rm > 0): ?><span class="chip ch-rose"  style="font-size:10px">Resp. missed: <?= $rm ?>x</span><?php endif; ?>
        </span>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-foot" style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="act-btn green" onclick="openOutcome(<?= $m['id'] ?>,'<?= e(addslashes($m['case_number'])) ?>',<?= $cm ?>,<?= $rm ?>)">📝 Record Outcome</button>
      <button class="act-btn" onclick="openNotifyModal(<?= $m['id'] ?>)">📧 Notify Parties</button>
      <button class="act-btn" onclick="viewBlotter(<?= $m['blotter_id'] ?>)">View Case</button>
      <button class="act-btn red" onclick="cancelMed(<?= $m['id'] ?>)">Cancel</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ══════════ OVERDUE ══════════ */ elseif ($tab === 'overdue'): ?>
<?php if (empty($overdue)): ?>
  <div class="empty-state"><div class="es-icon">✅</div><div class="es-title">All hearings are documented</div></div>
<?php else: ?>
<div style="font-size:13px;color:var(--ink-500);margin-bottom:14px">
  These hearings were scheduled but the date passed. Document what happened — even if no one showed up.
</div>
<div class="g2">
  <?php foreach ($overdue as $m): $cm=(int)$m['complainant_missed']; $rm=(int)$m['respondent_missed']; ?>
  <div class="card" style="border-top:3px solid var(--rose-400)">
    <div class="card-hdr" style="background:var(--rose-50)">
      <div><div class="card-title"><?= e($m['case_number']) ?></div><div class="card-sub"><?= e($m['incident_type']) ?></div></div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
        <span class="chip ch-rose"><?= (int)$m['days_overdue'] ?>d overdue</span>
        <span class="chip <?= $lm[$m['violation_level']]??'ch-slate' ?>"><?= ucfirst($m['violation_level']) ?></span>
      </div>
    </div>
    <div class="card-body" style="padding:14px 18px">
      <div class="dr"><span class="dr-lbl">Was Scheduled</span><span class="dr-val" style="font-weight:700;color:var(--rose-600)"><?= date('D, M j, Y',strtotime($m['hearing_date'])) ?></span></div>
      <div class="dr"><span class="dr-lbl">Venue</span><span class="dr-val"><?= e($m['venue']?:'Barangay Hall') ?></span></div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($m['complainant_name']) ?></span></div>
      <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val"><?= e($m['respondent_name']?:'Unknown') ?></span></div>
      <?php if ($cm>0||$rm>0): ?>
      <div class="dr"><span class="dr-lbl">Prior Misses</span>
        <span class="dr-val" style="display:flex;gap:4px;flex-wrap:wrap">
          <?php if ($cm>0): ?><span class="chip ch-amber" style="font-size:10px">Comp: <?= $cm ?>x</span><?php endif; ?>
          <?php if ($rm>0): ?><span class="chip ch-rose"  style="font-size:10px">Resp: <?= $rm ?>x</span><?php endif; ?>
        </span>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-foot" style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="btn btn-primary btn-sm" onclick="openOutcome(<?= $m['id'] ?>,'<?= e(addslashes($m['case_number'])) ?>',<?= $cm ?>,<?= $rm ?>)">⚠️ Document Now</button>
      <button class="act-btn" onclick="viewBlotter(<?= $m['blotter_id'] ?>)">View Case</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ══════════ HISTORY ══════════ */ elseif ($tab === 'past'): ?>
<?php if (empty($past)): ?>
  <div class="empty-state"><div class="es-icon">🗂️</div><div class="es-title">No history yet</div></div>
<?php else: ?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Parties</th><th>Date</th><th>Result</th><th>No-Show</th><th>Action Taken</th><th>Outcome</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($past as $m):
        $ca = $m['complainant_attended']; $ra = $m['respondent_attended'];
        $att_comp = $ca===null?'—':($ca?'<span class="chip ch-emerald" style="font-size:10px">✓</span>':'<span class="chip ch-rose" style="font-size:10px">✗</span>');
        $att_resp = $ra===null?'—':($ra?'<span class="chip ch-emerald" style="font-size:10px">✓</span>':'<span class="chip ch-rose" style="font-size:10px">✗</span>');
        $action_chip = '';
        if ($m['action_type']) {
          $albl = ['cfa_issued'=>'CFA Issued','dismissed'=>'Dismissed','warning_sent'=>'Warning Sent','rescheduled_1st'=>'Reschedule (1st miss)','rescheduled_2nd'=>'Reschedule (2nd miss)'];
          $acls = ['cfa_issued'=>'ch-violet','dismissed'=>'ch-rose','warning_sent'=>'ch-amber','rescheduled_1st'=>'ch-navy','rescheduled_2nd'=>'ch-rose'];
          $action_chip = '<span class="chip '.($acls[$m['action_type']]??'ch-slate').'" style="font-size:10px">'.($albl[$m['action_type']]??$m['action_type']).'</span>';
        }
      ?>
      <tr>
        <td class="td-mono"><?= e($m['case_number']) ?></td>
        <td>
          <div style="font-size:12px"><?= e($m['complainant_name']) ?></div>
          <div style="font-size:11px;color:var(--ink-400)">vs. <?= e($m['respondent_name']?:'?') ?></div>
        </td>
        <td style="font-size:12px;white-space:nowrap">
          <?= date('M j, Y', strtotime($m['hearing_date'])) ?>
          <?php if ($m['reschedule_date']): ?>
            <div style="font-size:11px;color:var(--teal-600)">→ <?= date('M j, Y',strtotime($m['reschedule_date'])) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="chip <?= $sc[$m['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$m['status'])) ?></span></td>
        <td>
          <div style="font-size:11px">C: <?= $att_comp ?> R: <?= $att_resp ?></div>
          <?php if ($m['no_show_by'] && $m['no_show_by']!=='none'): ?>
            <div style="font-size:10px;color:var(--rose-600);margin-top:2px">Absent: <?= ucfirst($m['no_show_by']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= $action_chip ?: '<span style="color:var(--ink-300)">—</span>' ?></td>
        <td style="font-size:12px;color:var(--ink-500);max-width:160px;white-space:normal"><?= e(mb_strimwidth($m['outcome']??'—',0,60,'…')) ?></td>
        <td>
          <div style="display:flex;flex-direction:column;gap:4px">
            <button class="act-btn" onclick="viewBlotter(<?= $m['blotter_id'] ?>)">View Case</button>
            <button class="act-btn" style="font-size:10px" onclick="openAdjust(<?= $m['blotter_id'] ?>,'<?= e(addslashes($m['case_number'])) ?>',<?= (int)$m['complainant_missed'] ?>,<?= (int)$m['respondent_missed'] ?>)">Adjust Misses</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php /* ══════════ SETTLEMENTS (Kasunduang Pag-aayos) ══════════ */ elseif ($tab === 'settlements'): ?>
<?php if (empty($settlements)): ?>
  <div class="empty-state"><div class="es-icon">📜</div><div class="es-title">No settlements on record</div><div class="es-sub">Amicable settlements appear here once a mediation is marked Completed</div></div>
<?php else: ?>
<div style="font-size:13px;color:var(--ink-500);margin-bottom:14px">
  Under Sec. 416, RA 7160, a settlement becomes final and enforceable like a court judgment 10 days after signing — unless a party formally repudiates it (fraud, violence, intimidation, or mistake of fact).
</div>
<div class="g2">
  <?php foreach ($settlements as $s):
    $ss = ['active'=>'ch-amber','final'=>'ch-emerald','repudiated'=>'ch-rose'];
    $days_left = (int)$s['days_left'];
  ?>
  <div class="card" style="<?= $s['status']==='active'?'border-top:3px solid var(--amber-400)':($s['status']==='repudiated'?'border-top:3px solid var(--rose-400)':'border-top:3px solid var(--emerald-400)') ?>">
    <div class="card-hdr" style="background:<?= $s['status']==='active'?'var(--amber-50)':($s['status']==='repudiated'?'var(--rose-50)':'var(--emerald-50)') ?>">
      <div>
        <div class="card-title"><?= e($s['case_number']) ?></div>
        <div class="card-sub"><?= e($s['complainant_name']) ?> vs. <?= e($s['respondent_name']?:'Unknown') ?></div>
      </div>
      <span class="chip <?= $ss[$s['status']]??'ch-slate' ?>"><?= ucfirst($s['status']) ?></span>
    </div>
    <div class="card-body" style="padding:14px 18px">
      <div class="dr"><span class="dr-lbl">Signed</span><span class="dr-val"><?= date('M j, Y', strtotime($s['settled_date'])) ?></span></div>
      <?php if ($s['status']==='active'): ?>
        <div class="dr"><span class="dr-lbl">Repudiation Deadline</span><span class="dr-val" style="font-weight:700;color:<?= $days_left<=2?'var(--rose-600)':'var(--amber-600)' ?>"><?= date('M j, Y', strtotime($s['repudiation_deadline'])) ?> (<?= $days_left>=0?"$days_left day(s) left":'overdue' ?>)</span></div>
      <?php elseif ($s['status']==='final'): ?>
        <div class="dr"><span class="dr-lbl">Finalized</span><span class="dr-val" style="color:var(--emerald-600)">✓ <?= $s['finalized_at']?date('M j, Y', strtotime($s['finalized_at'])):'' ?> — enforceable as a court judgment</span></div>
      <?php else: ?>
        <div class="dr"><span class="dr-lbl">Repudiated By</span><span class="dr-val" style="color:var(--rose-600)"><?= ucfirst($s['repudiated_by']) ?>, <?= date('M j, Y', strtotime($s['repudiated_at'])) ?></span></div>
        <div class="dr"><span class="dr-lbl">Ground</span><span class="dr-val" style="font-size:12px"><?= e(mb_strimwidth($s['repudiation_reason'],0,80,'…')) ?></span></div>
      <?php endif; ?>
      <div class="dr" style="align-items:flex-start"><span class="dr-lbl">Terms</span><span class="dr-val" style="font-size:12px;white-space:normal"><?= e(mb_strimwidth($s['terms'],0,120,'…')) ?></span></div>
    </div>
    <div class="card-foot" style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="act-btn" onclick="viewBlotter(<?= $s['blotter_id'] ?>)">View Case</button>
      <?php if ($s['status']==='active'): ?>
        <button class="act-btn red" onclick="openRepudiate(<?= $s['id'] ?>,'<?= e(addslashes($s['case_number'])) ?>')">Record Repudiation</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; // end tabs ?>

</div>

<!-- ════════════ MODALS ════════════ -->

<!-- New Hearing -->
<div class="modal-overlay" id="modal-new-med">
  <div class="modal">
    <div class="modal-hdr"><span class="modal-title">Schedule Mediation Hearing</span><button class="modal-x" onclick="closeModal('modal-new-med')">×</button></div>
    <div class="modal-body">
      <div class="fg"><label>Case <span class="req">*</span></label>
        <select id="nm-case">
          <option value="">— Select Case —</option>
          <?php foreach ($active_cases as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['case_number']) ?> — <?= e($c['complainant_name']) ?><?= $c['respondent_name']&&$c['respondent_name']!=='Unknown'?' vs. '.e($c['respondent_name']):'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fr2">
        <div class="fg"><label>Date <span class="req">*</span></label><input type="date" id="nm-date" min="<?= date('Y-m-d') ?>"></div>
        <div class="fg"><label>Time <span class="req">*</span></label><input type="time" id="nm-time" value="09:00"></div>
      </div>
      <div class="fg"><label>Venue</label><input type="text" id="nm-venue" value="Barangay Hall"></div>
      <div class="fg" style="margin-bottom:0"><label>Notes to Both Parties</label><textarea id="nm-notes" rows="2" placeholder="Optional instructions..."></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-new-med')">Cancel</button>
      <button class="btn btn-primary" onclick="submitNewMed()">Schedule & Notify Both Parties</button>
    </div>
  </div>
</div>

<!-- Record Outcome -->
<div class="modal-overlay" id="modal-outcome">
  <div class="modal modal-lg">
    <div class="modal-hdr"><span class="modal-title">Record Hearing Outcome</span><button class="modal-x" onclick="closeModal('modal-outcome')">×</button></div>
    <div class="modal-body">
      <input type="hidden" id="oc-id">
      <input type="hidden" id="oc-comp-missed">
      <input type="hidden" id="oc-resp-missed">

      <div class="fg"><label>Case</label><input type="text" id="oc-case" readonly style="background:var(--surface);font-weight:600"></div>

      <div id="oc-prior-warn" style="display:none;padding:10px 12px;border-radius:var(--r-md);background:var(--amber-50);border:1px solid var(--amber-200);margin-bottom:14px;font-size:12px;color:var(--amber-700)"></div>

      <!-- STEP 1: Attendance -->
      <div style="font-size:12px;font-weight:700;color:var(--ink-400);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px">Step 1 — Attendance</div>
      <div class="fr2" style="margin-bottom:18px">
        <div>
          <label>Complainant</label>
          <div style="display:flex;gap:8px;margin-top:6px">
            <button type="button" class="att-btn active" id="comp-yes" onclick="setAtt('comp','1')">✓ Present</button>
            <button type="button" class="att-btn"        id="comp-no"  onclick="setAtt('comp','0')">✗ Absent</button>
          </div>
          <input type="hidden" id="oc-comp" value="1">
        </div>
        <div>
          <label>Respondent</label>
          <div style="display:flex;gap:8px;margin-top:6px">
            <button type="button" class="att-btn active" id="resp-yes" onclick="setAtt('resp','1')">✓ Present</button>
            <button type="button" class="att-btn"        id="resp-no"  onclick="setAtt('resp','0')">✗ Absent</button>
          </div>
          <input type="hidden" id="oc-resp" value="1">
        </div>
      </div>

      <!-- STEP 2: Result -->
      <div style="font-size:12px;font-weight:700;color:var(--ink-400);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px">Step 2 — Result</div>
      <div class="fg">
        <select id="oc-status" onchange="onResultChange(this.value)">
          <option value="completed">✅ Completed — Agreement Reached</option>
          <option value="missed">🚫 No Show — Absence Recorded</option>
          <option value="rescheduled">📅 Rescheduled (Barangay decision — no penalty)</option>
          <option value="cancelled">❌ Cancelled</option>
        </select>
        <div id="oc-consequence" style="margin-top:8px;padding:10px 12px;border-radius:var(--r-md);border:1px solid;font-size:12px;display:none"></div>
      </div>

      <!-- Reschedule date (only when missed/rescheduled) -->
      <div id="reschedule-row" style="display:none">
        <div class="fr2">
          <div class="fg"><label>New Hearing Date <span id="redate-req" class="req">*</span></label><input type="date" id="oc-redate" min="<?= date('Y-m-d') ?>"></div>
          <div class="fg"><label>New Hearing Time</label><input type="time" id="oc-retime" value="09:00"></div>
        </div>
        <div id="reschedule-note" style="font-size:11px;color:var(--ink-400);margin-bottom:12px"></div>
      </div>

      <div id="oc-terms-wrap" class="fg" style="display:none">
        <label>Settlement Terms (Kasunduang Pag-aayos) <span class="req">*</span></label>
        <textarea id="oc-terms" rows="3" placeholder="State the agreed terms both parties signed, e.g. payment amount and schedule, apology, boundary agreement, etc."></textarea>
        <div style="font-size:11px;color:var(--ink-400);margin-top:4px">This becomes final and enforceable like a court judgment 10 days from today unless repudiated (Sec. 416, RA 7160).</div>
      </div>
      <div class="fg"><label>Notes / Summary</label><textarea id="oc-outcome" rows="3" placeholder="What happened, what was agreed, or reason for outcome…"></textarea></div>
      <div class="fg" style="margin-bottom:0"><label>Next Steps</label><input type="text" id="oc-next" placeholder="e.g. Both parties to sign agreement on May 10"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-outcome')">Cancel</button>
      <button class="btn btn-primary" id="oc-submit-btn" onclick="submitOutcome()">Save & Notify Parties</button>
    </div>
  </div>
</div>

<!-- Adjust Missed Count -->
<div class="modal-overlay" id="modal-adjust">
  <div class="modal">
    <div class="modal-hdr"><span class="modal-title">Adjust Missed Session Count</span><button class="modal-x" onclick="closeModal('modal-adjust')">×</button></div>
    <div class="modal-body">
      <input type="hidden" id="adj-blotter-id">
      <div class="fg"><label>Case</label><input type="text" id="adj-case" readonly style="background:var(--surface)"></div>
      <div style="font-size:12px;color:var(--ink-500);margin-bottom:14px;padding:10px;background:var(--amber-50);border-radius:var(--r-sm);border-left:3px solid var(--amber-400)">
        ⚠️ Use this only for documented emergencies or valid reasons (medical, death in family, etc.). All adjustments are logged.
      </div>
      <div class="fr2">
        <div class="fg"><label>Complainant Missed Count</label><input type="number" id="adj-comp" min="0" max="10" value="0"></div>
        <div class="fg"><label>Respondent Missed Count</label><input type="number" id="adj-resp" min="0" max="10" value="0"></div>
      </div>
      <div class="fg" style="margin-bottom:0"><label>Reason for Adjustment <span class="req">*</span></label><textarea id="adj-reason" rows="3" placeholder="State the reason for this manual correction (e.g. Medical emergency on March 15 — hospital records presented)…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-adjust')">Cancel</button>
      <button class="btn btn-primary" onclick="submitAdjust()">Save Adjustment</button>
    </div>
  </div>
</div>

<!-- Record Settlement Repudiation -->
<div class="modal-overlay" id="modal-repudiate">
  <div class="modal">
    <div class="modal-hdr"><span class="modal-title">Record Settlement Repudiation</span><button class="modal-x" onclick="closeModal('modal-repudiate')">×</button></div>
    <div class="modal-body">
      <input type="hidden" id="rep-settlement-id">
      <div class="fg"><label>Case</label><input type="text" id="rep-case" readonly style="background:var(--surface);font-weight:600"></div>
      <div style="font-size:12px;color:var(--ink-500);margin-bottom:14px;padding:10px;background:var(--rose-50);border-radius:var(--r-sm);border-left:3px solid var(--rose-400)">
        ⚠️ Valid grounds only (Sec. 418, RA 7160): fraud, violence, intimidation, or mistake of fact. A ₱ penalty is issued against the repudiating party and the case reopens.
      </div>
      <div class="fg"><label>Repudiating Party <span class="req">*</span></label>
        <select id="rep-party">
          <option value="">— Select —</option>
          <option value="complainant">Complainant</option>
          <option value="respondent">Respondent</option>
        </select>
      </div>
      <div class="fg" style="margin-bottom:0"><label>Ground for Repudiation <span class="req">*</span></label><textarea id="rep-reason" rows="3" placeholder="Describe the fraud, violence, intimidation, or mistake of fact being alleged…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-repudiate')">Cancel</button>
      <button class="btn btn-primary" onclick="submitRepudiate()">Record Repudiation</button>
    </div>
  </div>
</div>

<!-- Notify Parties — management modal -->
<div class="modal-overlay" id="modal-notify">
  <div class="modal notify-modal">
    <div class="modal-hdr">
      <div>
        <span class="modal-title">Notify Parties</span>
        <div class="notify-case-sub" id="ntf-case-sub">Loading…</div>
      </div>
      <button class="modal-x" onclick="closeModal('modal-notify')">×</button>
    </div>
    <div class="modal-body" id="ntf-body">
      <div class="notify-loading" id="ntf-loading">
        <div class="spinner" style="margin:0 auto 12px;width:28px;height:28px;border-width:2px;border-color:rgba(20,145,155,.2);border-top-color:var(--teal-600)"></div>
        Loading case &amp; hearing details…
      </div>

      <div id="ntf-content" style="display:none">

        <div class="notify-hearing-strip" id="ntf-hearing-strip"></div>

        <div class="notify-recipients">

          <div class="notify-card" id="ntf-comp-card">
            <div class="notify-card-hdr-row">
              <label class="notify-card-hdr">
                <input type="checkbox" id="ntf-comp-check" checked onchange="ntfUpdateSendBtn()">
                <span class="notify-role-pill rp-comp">Complainant</span>
                <span class="notify-card-name" id="ntf-comp-name"></span>
              </label>
              <div class="notify-card-meta">
                <span id="ntf-comp-contact"></span>
                <span class="notify-channel-badge" id="ntf-comp-channel"></span>
              </div>
            </div>
            <div class="notify-card-stats" id="ntf-comp-stats"></div>
            <textarea id="ntf-comp-message" rows="7" placeholder="Message to the complainant…"></textarea>
          </div>

          <div class="notify-card" id="ntf-resp-card">
            <div class="notify-card-hdr-row">
              <label class="notify-card-hdr">
                <input type="checkbox" id="ntf-resp-check" checked onchange="ntfUpdateSendBtn()">
                <span class="notify-role-pill rp-resp">Respondent</span>
                <span class="notify-card-name" id="ntf-resp-name"></span>
              </label>
              <div class="notify-card-meta">
                <span id="ntf-resp-contact"></span>
                <span class="notify-channel-badge" id="ntf-resp-channel"></span>
              </div>
            </div>
            <div class="notify-card-stats" id="ntf-resp-stats"></div>
            <textarea id="ntf-resp-message" rows="7" placeholder="Message to the respondent…"></textarea>
          </div>

        </div>

        <button type="button" class="notify-history-toggle" id="ntf-history-toggle" onclick="ntfToggleHistory()">
          <span id="ntf-history-arrow">▾</span> View send history <span id="ntf-history-count" class="chip ch-slate" style="font-size:10px"></span>
        </button>
        <div class="notify-history" id="ntf-history" style="display:none"></div>
        <div class="notify-history-legend" id="ntf-history-legend" style="display:none">
          <span class="chip ch-emerald" style="font-size:9px">Emailed</span> delivered by email ·
          <span class="chip ch-teal" style="font-size:9px">Read</span> they opened it in their account ·
          <span class="chip ch-amber" style="font-size:9px">In-app only</span> no email sent, not yet opened
        </div>

      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-notify')">Cancel</button>
      <button class="btn btn-primary" id="ntf-send-btn" onclick="submitNotify()" disabled>Send Notification</button>
    </div>
  </div>
</div>

<style>
.med-page {
  display:flex;
  flex-direction:column;
  gap:12px;
}
.med-page .page-hdr {
  margin-bottom:0;
}
.med-page .page-hdr h2 {
  margin-bottom:2px;
}
.med-page .tab-bar {
  margin-bottom:2px;
  overflow-x:auto;
  scrollbar-width:thin;
}
.med-page .tab-item {
  padding:9px 12px;
  white-space:nowrap;
}
.med-page .alert {
  margin-bottom:0;
  padding:10px 12px;
}
.med-page .g2 {
  display:grid;
  grid-template-columns:1fr;
  gap:10px;
}
.med-page .g2 > .card {
  overflow:hidden;
}
.med-page .card-hdr {
  padding:10px 12px;
  gap:10px;
}
.med-page .card-title {
  font-size:13px;
  line-height:1.25;
}
.med-page .card-sub {
  font-size:11px;
  line-height:1.35;
  margin-top:2px;
  overflow-wrap:anywhere;
}
.med-page .card-body {
  padding:10px 12px !important;
}
.med-page .card-foot {
  padding:9px 12px;
  align-items:center;
}
.med-page .dr {
  display:grid;
  grid-template-columns:minmax(86px, .52fr) minmax(0, 1fr);
  align-items:start;
  gap:8px;
  min-width:0;
  padding:4px 0;
}
.med-page .dr-lbl {
  font-size:10px;
  line-height:1.25;
  white-space:normal;
}
.med-page .dr-val {
  font-size:12px;
  line-height:1.35;
  min-width:0;
  overflow-wrap:anywhere;
}
.med-page .chip {
  max-width:100%;
}
.med-page .empty-state {
  padding:38px 20px;
}
.med-page .tbl-wrap {
  max-height:calc(100vh - 265px);
  overflow:auto;
}
.med-page table th {
  position:sticky;
  top:0;
  z-index:2;
  background:var(--surface);
}
.med-page table th,
.med-page table td {
  padding:8px 10px;
}
.med-page table td {
  vertical-align:top;
}
#modal-outcome .modal {
  max-height:92vh;
  display:flex;
  flex-direction:column;
}
#modal-outcome .modal-body {
  overflow:auto;
}
#modal-outcome .modal-body {
  padding:14px 18px;
}
#modal-outcome .fg {
  margin-bottom:10px;
}
#modal-outcome textarea {
  min-height:74px;
}
.att-btn { padding:7px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--ink-100);background:var(--white);color:var(--ink-400);font-family:inherit;transition:all .12s; }
.att-btn.active { background:var(--teal-600);color:var(--white);border-color:var(--teal-600); }
.att-btn:not(.active):hover { border-color:var(--teal-400);color:var(--teal-600);background:var(--teal-50); }
@media (min-width: 960px) {
  .med-page .g2 > .card {
    display:grid;
    grid-template-columns:minmax(210px, .85fr) minmax(360px, 1.55fr) minmax(160px, .55fr);
  }
  .med-page .g2 > .card > .card-hdr {
    border-right:1px solid var(--ink-100);
    border-bottom:0;
    align-items:flex-start;
  }
  .med-page .g2 > .card > .card-body {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    column-gap:18px;
    row-gap:0;
    align-content:center;
  }
  .med-page .g2 > .card > .card-foot {
    border-left:1px solid var(--ink-100);
    border-top:0;
    flex-direction:column;
    justify-content:center;
    align-items:stretch;
  }
  .med-page .g2 > .card > .card-foot .btn,
  .med-page .g2 > .card > .card-foot .act-btn {
    width:100%;
    text-align:center;
  }
}
@media (max-width: 760px) {
  .med-page .page-hdr {
    gap:10px;
  }
  .med-page .page-hdr > div:last-child {
    width:100%;
    justify-content:flex-start;
    flex-wrap:wrap;
  }
  .med-page .dr {
    grid-template-columns:1fr;
    gap:2px;
  }
  #modal-outcome .modal-body {
    padding:12px 14px;
  }
}

/* ── Notify Parties modal ─────────────────────────────────────── */
.notify-modal {
  width:760px;
  max-width:95vw;
  max-height:92vh;
  display:flex;
  flex-direction:column;
}
.notify-modal .modal-body {
  overflow-y:auto;
}
.notify-case-sub {
  font-size:12px;
  color:var(--ink-400);
  margin-top:2px;
  font-family:var(--font-mono);
}
.notify-loading {
  text-align:center;
  padding:48px 20px;
  color:var(--ink-400);
  font-size:13px;
}
.notify-hearing-strip {
  display:flex;
  align-items:center;
  gap:18px;
  flex-wrap:wrap;
  background:var(--teal-50);
  border:1px solid var(--teal-100);
  border-radius:var(--r-md);
  padding:12px 16px;
  margin-bottom:16px;
  font-size:12px;
}
.notify-hearing-strip strong {
  color:var(--teal-700);
  font-size:13px;
}
.notify-recipients {
  display:grid;
  grid-template-columns:1fr;
  gap:14px;
  margin-bottom:14px;
}
.notify-card-hdr-row {
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
}
.notify-card-hdr-row .notify-card-meta {
  margin-left:auto;
}
.notify-card {
  border:1px solid var(--ink-100);
  border-radius:var(--r-md);
  padding:12px 14px;
  display:flex;
  flex-direction:column;
  gap:8px;
  transition:opacity .12s, border-color .12s;
}
.notify-card.disabled {
  opacity:.5;
}
.notify-card-hdr {
  display:flex;
  align-items:center;
  gap:8px;
  cursor:pointer;
  font-weight:700;
}
.notify-card-hdr input[type="checkbox"] {
  width:15px;
  height:15px;
  accent-color:var(--teal-600);
  cursor:pointer;
  flex-shrink:0;
}
.notify-role-pill {
  font-size:10px;
  font-weight:700;
  padding:2px 8px;
  border-radius:20px;
  text-transform:uppercase;
  letter-spacing:.03em;
  flex-shrink:0;
}
.rp-comp { background:var(--teal-50); color:var(--teal-700); border:1px solid var(--teal-100); }
.rp-resp { background:var(--amber-50); color:var(--amber-600); border:1px solid var(--amber-200); }
.notify-card-name {
  font-size:13px;
  color:var(--ink-900);
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}
.notify-card-meta {
  display:flex;
  align-items:center;
  gap:10px;
  font-size:11px;
  color:var(--ink-400);
  font-family:var(--font-mono);
  flex-shrink:0;
}
.notify-channel-badge {
  font-size:10px;
  font-weight:700;
  padding:2px 8px;
  border-radius:20px;
  white-space:nowrap;
  font-family:var(--font-body);
}
.ncb-email  { background:var(--emerald-50); color:var(--emerald-600); border:1px solid var(--emerald-100); }
.ncb-noemail{ background:var(--ink-50);     color:var(--ink-400);     border:1px solid var(--ink-100); }
.notify-card-stats {
  font-size:11px;
  color:var(--ink-500);
  background:var(--surface);
  border-radius:var(--r-sm);
  padding:6px 9px;
  line-height:1.5;
}
.notify-card textarea {
  width:100%;
  font-family:inherit;
  font-size:13px;
  line-height:1.65;
  padding:10px 12px;
  border:1px solid var(--ink-100);
  border-radius:var(--r-sm);
  resize:vertical;
  min-height:130px;
  color:var(--ink-800);
}
.notify-card textarea:focus {
  outline:none;
  border-color:var(--teal-400);
}
.notify-history-toggle {
  display:flex;
  align-items:center;
  gap:6px;
  background:none;
  border:none;
  cursor:pointer;
  font-size:12px;
  font-weight:600;
  color:var(--teal-600);
  padding:6px 0;
  font-family:inherit;
}
.notify-history-legend {
  font-size:10.5px;
  color:var(--ink-400);
  padding:6px 2px 8px;
  display:flex;
  align-items:center;
  gap:5px;
  flex-wrap:wrap;
}
.notify-history {
  border:1px solid var(--ink-100);
  border-radius:var(--r-md);
  overflow:hidden;
  margin-top:6px;
}
.notify-history-row {
  display:flex;
  align-items:center;
  gap:10px;
  padding:8px 12px;
  border-bottom:1px solid var(--surface-2);
  font-size:11.5px;
}
.notify-history-row:last-child { border-bottom:none; }
.notify-history-empty {
  padding:20px;
  text-align:center;
  color:var(--ink-300);
  font-size:12px;
}
@media(max-width:640px) {
  .notify-card-hdr-row { flex-direction:column; align-items:flex-start; }
  .notify-card-hdr-row .notify-card-meta { margin-left:0; }
}
</style>

<script>
// ── Consequence map (KP Law rules) ──
const CONSEQUENCES = {
  comp: {
    1: { color:'#B45309', bg:'#FFFBEB', border:'#FDE68A', text:'1st miss for complainant: Hearing will be rescheduled. A warning notice is sent. If absent again, the case may be dismissed.' },
    2: { color:'#BE123C', bg:'#FFF1F2', border:'#FECDD3', text:'2nd miss for complainant: Case will be DISMISSED. Complainant will be barred from filing the same case in court (Sec. 412 LGC).' },
  },
  resp: {
    1: { color:'#B45309', bg:'#FFFBEB', border:'#FDE68A', text:'1st miss for respondent: Hearing will be rescheduled. A final warning is sent. A second absence will result in a CFA issued to the complainant.' },
    2: { color:'#6D28D9', bg:'#F5F3FF', border:'#DDD6FE', text:'2nd miss for respondent: A Certification to File Action (CFA) will be issued to the complainant. They may now bring this case to court.' },
  },
  both: { color:'#BE123C', bg:'#FFF1F2', border:'#FECDD3', text:'Both parties absent: Case will be DISMISSED/ABANDONED. To pursue, a new complaint must be filed.' },
};

function setAtt(party, val) {
  document.getElementById('oc-'+party).value = val;
  document.getElementById(party+'-yes').classList.toggle('active', val==='1');
  document.getElementById(party+'-no' ).classList.toggle('active', val==='0');
  autoSuggestResult();
}

function autoSuggestResult() {
  const comp = document.getElementById('oc-comp').value;
  const resp = document.getElementById('oc-resp').value;
  const sel  = document.getElementById('oc-status');
  if (comp==='0'||resp==='0') { sel.value='missed'; } else { sel.value='completed'; }
  onResultChange(sel.value);
}

function onResultChange(val) {
  const comp      = document.getElementById('oc-comp').value;
  const resp      = document.getElementById('oc-resp').value;
  const cm        = parseInt(document.getElementById('oc-comp-missed').value||'0');
  const rm        = parseInt(document.getElementById('oc-resp-missed').value||'0');
  const consq     = document.getElementById('oc-consequence');
  const rescRow   = document.getElementById('reschedule-row');
  const rescNote  = document.getElementById('reschedule-note');
  const dateReq   = document.getElementById('oc-redate');
  const redateReq = document.getElementById('redate-req');

  consq.style.display='none'; rescRow.style.display='none';

  if (val==='missed') {
    let rule=null, key='';
    const compAbsent = comp==='0'; const respAbsent = resp==='0';
    if (compAbsent && respAbsent) { rule=CONSEQUENCES.both; key='both'; }
    else if (compAbsent) { const miss=cm+1; rule=CONSEQUENCES.comp[Math.min(miss,2)]; key='comp_'+miss; }
    else if (respAbsent) { const miss=rm+1; rule=CONSEQUENCES.resp[Math.min(miss,2)]; key='resp_'+miss; }

    if (rule) {
      consq.style.display=''; consq.style.background=rule.bg; consq.style.borderColor=rule.border; consq.style.color=rule.color;
      consq.innerHTML='<strong>Consequence:</strong> '+rule.text;
    }

    // Show reschedule row for 1st misses (need new date)
    const is1stMiss = (compAbsent&&!respAbsent&&cm===0) || (!compAbsent&&respAbsent&&rm===0);
    if (is1stMiss) {
      rescRow.style.display='';
      rescNote.textContent='Enter the new hearing date for the rescheduled session.';
      dateReq.required=true; redateReq.style.display='';
    } else {
      dateReq.required=false; redateReq.style.display='none';
    }
  }

  if (val==='rescheduled') {
    rescRow.style.display='';
    rescNote.textContent='Rescheduled by barangay decision — no missed session counted for either party.';
    dateReq.required=true; redateReq.style.display='';
  }

  if (val==='completed') {
    consq.style.display=''; consq.style.background='var(--emerald-50)'; consq.style.borderColor='var(--emerald-100)'; consq.style.color='var(--emerald-600)';
    consq.innerHTML='✅ Both parties present and agreement reached. Blotter will be marked <strong>Resolved</strong> and a 10-day repudiation window (Sec. 416) will open.';
  }
  if (val==='cancelled') {
    consq.style.display=''; consq.style.background='var(--surface)'; consq.style.borderColor='var(--ink-100)'; consq.style.color='var(--ink-400)';
    consq.innerHTML='Hearing cancelled by barangay. Blotter returns to <strong>Active</strong>. No missed count added.';
  }

  document.getElementById('oc-terms-wrap').style.display = (val==='completed') ? '' : 'none';
}

function openOutcome(id, caseNo, compMissed, respMissed) {
  document.getElementById('oc-id').value          = id;
  document.getElementById('oc-case').value        = caseNo;
  document.getElementById('oc-comp-missed').value = compMissed;
  document.getElementById('oc-resp-missed').value = respMissed;
  document.getElementById('oc-outcome').value     = '';
  document.getElementById('oc-terms').value       = '';
  document.getElementById('oc-next').value        = '';
  document.getElementById('oc-redate').value      = '';
  document.getElementById('oc-retime').value      = '09:00';
  setAtt('comp','1'); setAtt('resp','1');
  document.getElementById('oc-status').value = 'completed';

  const warn = document.getElementById('oc-prior-warn');
  if (compMissed>0||respMissed>0) {
    warn.style.display='';
    const parts=[];
    if (compMissed>0) parts.push(`Complainant has missed ${compMissed} session(s)`);
    if (respMissed>0) parts.push(`Respondent has missed ${respMissed} session(s)`);
    warn.innerHTML='⚠️ Prior misses on record: '+parts.join(', ')+'. Next no-show may trigger dismissal or CFA.';
  } else { warn.style.display='none'; }

  onResultChange('completed');
  openModal('modal-outcome');
}

function submitOutcome() {
  const status = document.getElementById('oc-status').value;
  const redate = document.getElementById('oc-redate').value;
  if ((status==='rescheduled'||document.getElementById('oc-redate').required) && !redate)
    return showToast('Please provide the new hearing date.','error');

  const terms = document.getElementById('oc-terms').value.trim();
  if (status==='completed' && !terms)
    return showToast('Settlement terms are required to record a completed mediation.','error');

  const data = {
    action:'record_outcome', id:document.getElementById('oc-id').value,
    status, complainant_attended:document.getElementById('oc-comp').value,
    respondent_attended:document.getElementById('oc-resp').value,
    outcome:document.getElementById('oc-outcome').value.trim(),
    settlement_terms:terms,
    next_steps:document.getElementById('oc-next').value.trim(),
    reschedule_date:redate, reschedule_time:document.getElementById('oc-retime').value,
  };
  loading(true);
  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json()).then(d=>{loading(false);closeModal('modal-outcome');showToast(d.message,d.success?'success':'error');if(d.success)setTimeout(()=>location.reload(),800);})
    .catch(()=>{loading(false);showToast('Request failed.','error');});
}

function cancelMed(id){
  if(!confirm('Cancel this hearing? Blotter will return to Active. No missed count will be added.'))return;
  loading(true);
  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'cancel',id})})
    .then(r=>r.json()).then(d=>{loading(false);showToast(d.message,d.success?'success':'error');if(d.success)setTimeout(()=>location.reload(),700);});
}

function submitNewMed(){
  const data={action:'schedule_mediation',blotter_id:document.getElementById('nm-case').value,date:document.getElementById('nm-date').value,time:document.getElementById('nm-time').value,venue:document.getElementById('nm-venue').value.trim(),notes:document.getElementById('nm-notes').value.trim()};
  if(!data.blotter_id||!data.date||!data.time)return showToast('Case, date and time are required.','error');
  loading(true);
  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json()).then(d=>{loading(false);closeModal('modal-new-med');showToast(d.message,d.success?'success':'error');if(d.success)setTimeout(()=>location.reload(),700);})
    .catch(()=>{loading(false);showToast('Request failed.','error');});
}

function openAdjust(blotterId, caseNo, comp, resp) {
  document.getElementById('adj-blotter-id').value = blotterId;
  document.getElementById('adj-case').value        = caseNo;
  document.getElementById('adj-comp').value        = comp;
  document.getElementById('adj-resp').value        = resp;
  document.getElementById('adj-reason').value      = '';
  openModal('modal-adjust');
}

function submitAdjust(){
  const reason=document.getElementById('adj-reason').value.trim();
  if(!reason)return showToast('Reason is required for any adjustment.','error');
  const data={action:'adjust_missed',blotter_id:document.getElementById('adj-blotter-id').value,comp_missed:document.getElementById('adj-comp').value,resp_missed:document.getElementById('adj-resp').value,reason};
  loading(true);
  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json()).then(d=>{loading(false);closeModal('modal-adjust');showToast(d.message,d.success?'success':'error');if(d.success)setTimeout(()=>location.reload(),700);});
}

function openRepudiate(settlementId, caseNo) {
  document.getElementById('rep-settlement-id').value = settlementId;
  document.getElementById('rep-case').value           = caseNo;
  document.getElementById('rep-party').value          = '';
  document.getElementById('rep-reason').value         = '';
  openModal('modal-repudiate');
}

function submitRepudiate(){
  const party  = document.getElementById('rep-party').value;
  const reason = document.getElementById('rep-reason').value.trim();
  if (!party)  return showToast('Select which party is repudiating.','error');
  if (!reason) return showToast('A ground for repudiation is required.','error');
  if (!confirm('Record this repudiation? The case will reopen and a penalty will be issued.')) return;
  const data = {action:'repudiate_settlement', settlement_id:document.getElementById('rep-settlement-id').value, party, reason};
  loading(true);
  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json()).then(d=>{loading(false);closeModal('modal-repudiate');showToast(d.message,d.success?'success':'error');if(d.success)setTimeout(()=>location.reload(),900);})
    .catch(()=>{loading(false);showToast('Request failed.','error');});
}

// ── Notify Parties — management modal ──────────────────────────
let ntfMedId = null;
let ntfData  = null;

// party_notifications.status meanings:
//   sent    — an email was actually delivered to this party
//   read    — the party opened their VOICE account and viewed it (in Notices & Sanctions)
//   pending — recorded in their account only; no email was sent (no email on file,
//             not a linked account, or the send failed) and they haven't opened it yet
const NTF_STATUS_MAP = {
  sent:    { cls: 'ch-emerald', label: 'Emailed',      title: 'An email was successfully delivered to this party.' },
  read:    { cls: 'ch-teal',    label: 'Read',          title: 'The party opened their VOICE account and viewed this notice.' },
  pending: { cls: 'ch-amber',   label: 'In-app only',   title: 'No email was sent (no email on file or not a linked account) and the party has not opened it in their account yet.' },
};

function openNotifyModal(medId){
  ntfMedId = medId;
  ntfData  = null;
  document.getElementById('ntf-case-sub').textContent = '';
  document.getElementById('ntf-loading').style.display = '';
  document.getElementById('ntf-content').style.display = 'none';
  document.getElementById('ntf-history').style.display = 'none';
  document.getElementById('ntf-history-legend').style.display = 'none';
  document.getElementById('ntf-history-arrow').textContent = '▾';
  document.getElementById('ntf-history-count').textContent = '';
  document.getElementById('ntf-send-btn').disabled = true;
  openModal('modal-notify');

  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'notify_hearing_info',id:medId})})
    .then(r=>r.json())
    .then(d=>{
      if (!d.success) { showToast(d.message,'error'); closeModal('modal-notify'); return; }
      ntfData = d;
      ntfRender(d);
    })
    .catch(()=>{ showToast('Could not load notification details.','error'); closeModal('modal-notify'); });
}

function ntfFmtDate(s){
  if (!s) return null;
  return new Date(s.replace(' ','T')).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});
}

function ntfRenderCard(party, info){
  const card = document.getElementById('ntf-' + party + '-card');
  if (!info) { card.style.display = 'none'; document.getElementById('ntf-' + party + '-check').checked = false; return; }
  card.style.display = '';
  document.getElementById('ntf-' + party + '-name').textContent = info.name || '—';
  document.getElementById('ntf-' + party + '-contact').textContent = info.contact ? ('📞 ' + info.contact) : (info.linked ? 'No contact number on file' : 'Walk-in — not linked to an account');
  document.getElementById('ntf-' + party + '-message').value = info.message;

  const badge = document.getElementById('ntf-' + party + '-channel');
  if (info.email) { badge.className = 'notify-channel-badge ncb-email'; badge.textContent = '📧 Will email: ' + info.email; }
  else if (info.linked) { badge.className = 'notify-channel-badge ncb-noemail'; badge.textContent = '👤 Linked account, no email — in-app only'; }
  else { badge.className = 'notify-channel-badge ncb-noemail'; badge.textContent = '📵 Not linked — in-app record only'; }

  const stats = document.getElementById('ntf-' + party + '-stats');
  const last = ntfFmtDate(info.last_sent);
  stats.textContent = info.attempts > 0
    ? `Notified ${info.attempts} time${info.attempts===1?'':'s'} before (${info.emailed} by email) · Last: ${last}`
    : 'Never notified about this hearing yet';
}

function ntfRender(d){
  document.getElementById('ntf-case-sub').textContent = d.case_number;
  document.getElementById('ntf-hearing-strip').innerHTML =
    `<span>📅 <strong>${d.hearing.date}</strong></span><span>⏰ ${d.hearing.time}</span><span>📍 ${d.hearing.venue}</span>`;

  ntfRenderCard('comp', d.complainant);
  ntfRenderCard('resp', d.respondent);

  const histCount = document.getElementById('ntf-history-count');
  histCount.textContent = d.history.length;
  document.getElementById('ntf-history').innerHTML = d.history.length ? d.history.map(h => {
    const roleLbl = h.recipient_type === 'complainant' ? 'Complainant' : 'Respondent';
    const emailed = (h.channel||'').includes('email');
    const s = NTF_STATUS_MAP[h.status] || NTF_STATUS_MAP.pending;
    const statusChip = `<span class="chip ${s.cls}" style="font-size:9px" title="${s.title}">${s.label}</span>`;
    return `<div class="notify-history-row">
      <span style="flex:1">${roleLbl} · ${h.notification_type.replace(/_/g,' ')}${emailed?' · 📧 emailed':''}</span>
      ${statusChip}
      <span style="color:var(--ink-300);white-space:nowrap">${ntfFmtDate(h.created_at)}</span>
    </div>`;
  }).join('') : '<div class="notify-history-empty">No previous notifications for this hearing.</div>';

  document.getElementById('ntf-loading').style.display = 'none';
  document.getElementById('ntf-content').style.display = '';
  ntfUpdateSendBtn();
}

function ntfUpdateSendBtn(){
  const compOn = document.getElementById('ntf-comp-check').checked && document.getElementById('ntf-comp-card').style.display !== 'none';
  const respOn = document.getElementById('ntf-resp-check').checked && document.getElementById('ntf-resp-card').style.display !== 'none';
  document.getElementById('ntf-comp-card').classList.toggle('disabled', !compOn);
  document.getElementById('ntf-resp-card').classList.toggle('disabled', !respOn);
  const count = (compOn?1:0) + (respOn?1:0);
  const btn = document.getElementById('ntf-send-btn');
  btn.disabled = count === 0;
  btn.textContent = count === 0 ? 'Select at least one recipient' : `Send Notification${count>1?'s':''} (${count})`;
}

function ntfToggleHistory(){
  const el = document.getElementById('ntf-history');
  const open = el.style.display !== 'none';
  el.style.display = open ? 'none' : '';
  document.getElementById('ntf-history-legend').style.display = open ? 'none' : '';
  document.getElementById('ntf-history-arrow').textContent = open ? '▾' : '▴';
  document.getElementById('ntf-history-count').textContent = ntfData ? ntfData.history.length : 0;
}

function submitNotify(){
  if (!ntfMedId) return;
  const recipients = [];
  if (document.getElementById('ntf-comp-check').checked && document.getElementById('ntf-comp-card').style.display !== 'none') recipients.push('complainant');
  if (document.getElementById('ntf-resp-check').checked && document.getElementById('ntf-resp-card').style.display !== 'none') recipients.push('respondent');
  if (!recipients.length) return showToast('Select at least one recipient.','error');

  const data = {
    action: 'notify_hearing',
    id: ntfMedId,
    recipients,
    comp_message: document.getElementById('ntf-comp-message').value.trim(),
    resp_message: document.getElementById('ntf-resp-message').value.trim(),
  };
  const btn = document.getElementById('ntf-send-btn');
  btn.disabled = true; btn.textContent = 'Sending…';
  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json()).then(d=>{
      showToast(d.message, d.success?'success':'error');
      if (d.success) { closeModal('modal-notify'); }
      else { btn.disabled = false; ntfUpdateSendBtn(); }
    })
    .catch(()=>{ showToast('Request failed.','error'); btn.disabled = false; ntfUpdateSendBtn(); });
}
</script>
