<?php
// pages/mediation.php — KP Law compliant
$bid = (int)$user['barangay_id'];
$tab = $_GET['tab'] ?? 'upcoming';

$upcoming = $overdue = $past = $active_cases = $notifications = [];

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

    // Pending notifications (for the badge/panel)
    $notifications = $pdo->query("
        SELECT pn.*, b.case_number
        FROM party_notifications pn
        JOIN blotters b ON b.id = pn.blotter_id
        WHERE pn.barangay_id = $bid AND pn.status = 'pending'
        ORDER BY pn.created_at DESC
        LIMIT 50
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

$notif_count = count($notifications);
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Mediation Management</h2>
    <p><?= e($bgy['name']) ?> · Katarungang Pambarangay Process</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if ($notif_count > 0): ?>
      <button class="btn btn-outline btn-sm" onclick="openModal('modal-notifications')" style="position:relative">
        🔔 Notifications
        <span style="position:absolute;top:-5px;right:-5px;min-width:17px;height:17px;background:var(--rose-400);color:#fff;font-size:9px;font-weight:700;border-radius:20px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid var(--white)"><?= $notif_count ?></span>
      </button>
    <?php endif; ?>
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
<?php endif; // end tabs ?>


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

<!-- Pending Notifications Panel -->
<div class="modal-overlay" id="modal-notifications">
  <div class="modal modal-lg">
    <div class="modal-hdr"><span class="modal-title">Pending Notifications (<?= $notif_count ?>)</span><button class="modal-x" onclick="closeModal('modal-notifications')">×</button></div>
    <div class="modal-body" style="padding:0">
      <?php if (empty($notifications)): ?>
        <div class="empty-state"><div class="es-icon">✅</div><div class="es-title">All notifications sent</div></div>
      <?php else: ?>
      <div style="padding:12px 18px;background:var(--surface);border-bottom:1px solid var(--surface-2);font-size:12px;color:var(--ink-500)">
        These notifications are queued. Mark as Sent after delivering via SMS, phone call, or printed notice.
      </div>
      <?php
        $type_icons = ['hearing_scheduled'=>'📅','hearing_rescheduled'=>'🔄','no_show_warning'=>'⚠️','case_dismissed'=>'🚫','cfa_issued'=>'📜','mediation_completed'=>'✅','mediation_cancelled'=>'❌','case_escalated'=>'⬆','general'=>'📢','hearing_reminder'=>'⏰'];
        foreach ($notifications as $n):
          $ico = $type_icons[$n['notification_type']] ?? '📢';
          $party_chip = $n['recipient_type']==='complainant' ? '<span class="chip ch-teal" style="font-size:10px">Complainant</span>' : '<span class="chip ch-amber" style="font-size:10px">Respondent</span>';
      ?>
        <div style="padding:14px 18px;border-bottom:1px solid var(--surface-2)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px">
            <div style="display:flex;align-items:flex-start;gap:10px">
              <span style="font-size:20px;line-height:1;margin-top:2px"><?= $ico ?></span>
              <div>
                <div style="font-size:13px;font-weight:700;color:var(--ink-900)"><?= e($n['subject']) ?></div>
                <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                  <?= $party_chip ?>
                  <span style="font-size:11px;font-weight:600;color:var(--ink-700)"><?= e($n['recipient_name']) ?></span>
                  <?php if ($n['recipient_contact']): ?><span style="font-size:11px;color:var(--teal-600);font-family:var(--font-mono)"><?= e($n['recipient_contact']) ?></span><?php endif; ?>
                  <span style="font-size:10px;color:var(--ink-300)"><?= e($n['case_number']) ?></span>
                </div>
              </div>
            </div>
            <button class="btn btn-success btn-sm" style="flex-shrink:0" onclick="markSent(<?= $n['id'] ?>,this)">Mark Sent</button>
          </div>
          <div style="font-size:12px;color:var(--ink-600);line-height:1.6;padding:8px 10px;background:var(--surface);border-radius:var(--r-sm)"><?= nl2br(e($n['message'])) ?></div>
          <div style="font-size:10px;color:var(--ink-300);margin-top:6px">Queued: <?= date('M j, Y g:i A', strtotime($n['created_at'])) ?> · Channel: <?= e($n['channel']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.att-btn { padding:7px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--ink-100);background:var(--white);color:var(--ink-400);font-family:inherit;transition:all .12s; }
.att-btn.active { background:var(--teal-600);color:var(--white);border-color:var(--teal-600); }
.att-btn:not(.active):hover { border-color:var(--teal-400);color:var(--teal-600);background:var(--teal-50); }
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
    consq.innerHTML='✅ Both parties present and agreement reached. Blotter will be marked <strong>Resolved</strong>.';
  }
  if (val==='cancelled') {
    consq.style.display=''; consq.style.background='var(--surface)'; consq.style.borderColor='var(--ink-100)'; consq.style.color='var(--ink-400)';
    consq.innerHTML='Hearing cancelled by barangay. Blotter returns to <strong>Active</strong>. No missed count added.';
  }
}

function openOutcome(id, caseNo, compMissed, respMissed) {
  document.getElementById('oc-id').value          = id;
  document.getElementById('oc-case').value        = caseNo;
  document.getElementById('oc-comp-missed').value = compMissed;
  document.getElementById('oc-resp-missed').value = respMissed;
  document.getElementById('oc-outcome').value     = '';
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

  const data = {
    action:'record_outcome', id:document.getElementById('oc-id').value,
    status, complainant_attended:document.getElementById('oc-comp').value,
    respondent_attended:document.getElementById('oc-resp').value,
    outcome:document.getElementById('oc-outcome').value.trim(),
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

function markSent(id, btn){
  btn.disabled=true; btn.textContent='Saving…';
  fetch('ajax/mediation_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_notif_sent',notif_id:id})})
    .then(r=>r.json()).then(d=>{
      if(d.success){ btn.closest('div[style]').style.opacity='.4'; btn.textContent='Sent'; showToast('Marked as sent.','success'); }
      else { btn.disabled=false; btn.textContent='Mark Sent'; showToast(d.message,'error'); }
    });
}
</script>
