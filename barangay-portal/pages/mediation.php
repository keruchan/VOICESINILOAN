<?php
// pages/mediation.php
$bid = (int)$user['barangay_id'];
$tab = $_GET['tab'] ?? 'upcoming';

$upcoming  = [];
$overdue   = [];  // scheduled but date already passed and not documented
$past      = [];
$active_cases = [];

try {
    // Upcoming — scheduled, future dates
    $upcoming = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name,
               b.incident_type, b.violation_level, b.id AS blotter_id,
               (SELECT COUNT(*) FROM mediation_schedules ms2
                WHERE ms2.blotter_id = ms.blotter_id AND ms2.missed_session = 1) AS total_missed
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid
          AND ms.status = 'scheduled'
          AND ms.hearing_date >= CURDATE()
        ORDER BY ms.hearing_date ASC
    ")->fetchAll();

    // Overdue — scheduled but date passed and not documented (needs action)
    $overdue = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name,
               b.incident_type, b.violation_level, b.id AS blotter_id,
               DATEDIFF(CURDATE(), ms.hearing_date) AS days_overdue,
               (SELECT COUNT(*) FROM mediation_schedules ms2
                WHERE ms2.blotter_id = ms.blotter_id AND ms2.missed_session = 1) AS total_missed
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid
          AND ms.status = 'scheduled'
          AND ms.hearing_date < CURDATE()
        ORDER BY ms.hearing_date ASC
    ")->fetchAll();

    // Past — all documented outcomes
    $past = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name, b.incident_type,
               b.id AS blotter_id
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid
          AND ms.status != 'scheduled'
        ORDER BY ms.hearing_date DESC
        LIMIT 50
    ")->fetchAll();

    // Active cases for scheduling dropdown
    $active_cases = $pdo->query("
        SELECT id, case_number, complainant_name, respondent_name
        FROM blotters
        WHERE barangay_id = $bid AND status IN ('active','pending_review','mediation_set')
        ORDER BY created_at DESC
    ")->fetchAll();

} catch (PDOException $e) {}

$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sc = ['completed'=>'ch-emerald','cancelled'=>'ch-rose','missed'=>'ch-amber','rescheduled'=>'ch-navy'];

$total_overdue  = count($overdue);
$total_upcoming = count($upcoming);
$total_past     = count($past);

// Penalty escalation rules by missed session count
$penalty_rules = [
    1 => ['₱500 fine or 4 hours community service', 500, 4],
    2 => ['₱1,000 fine or 8 hours community service', 1000, 8],
    3 => ['₱2,000 fine + case escalation recommended', 2000, 16],
];
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Mediation Management</h2>
    <p>Schedule and track all mediation hearings for <?= e($bgy['name']) ?></p>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-new-med')">+ Schedule Hearing</button>
</div>

<!-- ── Overdue alert banner ── -->
<?php if ($total_overdue > 0): ?>
<div class="alert alert-rose mb16" style="align-items:flex-start">
  <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="var(--rose-600)" stroke-width="1.5" stroke-linecap="round" style="flex-shrink:0;margin-top:1px"><circle cx="9" cy="9" r="7"/><path d="M9 5.5v4"/><circle cx="9" cy="12.5" r=".7" fill="currentColor"/></svg>
  <div class="alert-text">
    <strong><?= $total_overdue ?> hearing(s) passed without documentation!</strong>
    <span>These sessions were scheduled but never recorded. Please document the outcome — mark as No Show, Completed, or Rescheduled. Leaving them undocumented delays penalty processing.</span>
  </div>
</div>
<?php endif; ?>

<!-- ── Tabs ── -->
<div class="tab-bar">
  <a class="tab-item <?= $tab==='upcoming'?'active':'' ?>" href="?page=mediation&tab=upcoming">
    Upcoming <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= $total_upcoming ?></span>
  </a>
  <a class="tab-item <?= $tab==='overdue'?'active':'' ?>" href="?page=mediation&tab=overdue" style="<?= $total_overdue>0 ? 'color:var(--rose-600)' : '' ?>">
    Needs Documentation
    <?php if ($total_overdue > 0): ?><span style="font-size:10px;background:var(--rose-50);color:var(--rose-600);border:1px solid var(--rose-100);padding:0 6px;border-radius:10px;margin-left:3px;font-weight:700"><?= $total_overdue ?></span><?php endif; ?>
  </a>
  <a class="tab-item <?= $tab==='past'?'active':'' ?>" href="?page=mediation&tab=past">
    History <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= $total_past ?></span>
  </a>
</div>

<!-- ════════════ UPCOMING ════════════ -->
<?php if ($tab === 'upcoming'): ?>
<?php if (empty($upcoming)): ?>
  <div class="empty-state"><div class="es-icon">📅</div><div class="es-title">No upcoming hearings</div><div class="es-sub">Schedule hearings from the button above or from Blotter Management</div></div>
<?php else: ?>
<div class="g2">
  <?php foreach ($upcoming as $m):
    $days_left = (int)floor((strtotime($m['hearing_date']) - time()) / 86400);
    $is_today  = $m['hearing_date'] === date('Y-m-d');
    $urgent    = $days_left <= 1;
  ?>
  <div class="card" style="<?= $urgent ? 'border-top:3px solid var(--amber-400)' : '' ?>">
    <div class="card-hdr" style="background:<?= $is_today ? 'var(--amber-50)' : 'var(--teal-50)' ?>">
      <div>
        <div class="card-title"><?= e($m['case_number']) ?></div>
        <div class="card-sub"><?= e($m['incident_type']) ?></div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
        <span class="chip <?= $lm[$m['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($m['violation_level']) ?></span>
        <?php if ($is_today): ?><span class="chip ch-amber" style="font-size:10px">TODAY</span>
        <?php elseif ($urgent): ?><span class="chip ch-amber" style="font-size:10px">TOMORROW</span><?php endif; ?>
      </div>
    </div>
    <div class="card-body" style="padding:14px 18px">
      <div class="dr">
        <span class="dr-lbl">📅 Date</span>
        <span class="dr-val" style="font-weight:700;color:<?= $is_today ? 'var(--amber-600)' : 'var(--teal-600)' ?>"><?= date('D, M j, Y', strtotime($m['hearing_date'])) ?></span>
      </div>
      <div class="dr"><span class="dr-lbl">⏰ Time</span><span class="dr-val"><?= $m['hearing_time'] ? date('g:i A', strtotime($m['hearing_time'])) : 'TBD' ?></span></div>
      <div class="dr"><span class="dr-lbl">📍 Venue</span><span class="dr-val"><?= e($m['venue'] ?: 'Barangay Hall') ?></span></div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($m['complainant_name']) ?></span></div>
      <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val"><?= e($m['respondent_name'] ?: 'Unknown') ?></span></div>
      <?php if ((int)$m['total_missed'] > 0): ?>
      <div class="dr">
        <span class="dr-lbl">Missed Sessions</span>
        <span class="dr-val"><span class="chip ch-rose"><?= (int)$m['total_missed'] ?> missed</span></span>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-foot" style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="act-btn green" onclick="openOutcome(<?= $m['id'] ?>,'<?= e(addslashes($m['case_number'])) ?>',<?= (int)$m['total_missed'] ?>)">
        📝 Record Outcome
      </button>
      <button class="act-btn" onclick="viewBlotter(<?= $m['blotter_id'] ?>)">View Case</button>
      <button class="act-btn red" onclick="cancelMed(<?= $m['id'] ?>)">Cancel</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════════════ OVERDUE (NEEDS DOCUMENTATION) ════════════ -->
<?php elseif ($tab === 'overdue'): ?>
<?php if (empty($overdue)): ?>
  <div class="empty-state"><div class="es-icon">✅</div><div class="es-title">All hearings are documented</div><div class="es-sub">No sessions are waiting for outcome recording</div></div>
<?php else: ?>
<div style="font-size:13px;color:var(--ink-500);margin-bottom:14px">
  These hearings were scheduled but the date has passed. You must record what happened — <strong>No Show, Completed, Rescheduled, or Cancelled</strong>.
</div>
<div class="g2">
  <?php foreach ($overdue as $m): ?>
  <div class="card" style="border-top:3px solid var(--rose-400)">
    <div class="card-hdr" style="background:var(--rose-50)">
      <div>
        <div class="card-title"><?= e($m['case_number']) ?></div>
        <div class="card-sub"><?= e($m['incident_type']) ?></div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
        <span class="chip ch-rose"><?= (int)$m['days_overdue'] ?>d overdue</span>
        <span class="chip <?= $lm[$m['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($m['violation_level']) ?></span>
      </div>
    </div>
    <div class="card-body" style="padding:14px 18px">
      <div class="dr">
        <span class="dr-lbl">📅 Was Scheduled</span>
        <span class="dr-val" style="font-weight:700;color:var(--rose-600)"><?= date('D, M j, Y', strtotime($m['hearing_date'])) ?></span>
      </div>
      <div class="dr"><span class="dr-lbl">⏰ Time</span><span class="dr-val"><?= $m['hearing_time'] ? date('g:i A', strtotime($m['hearing_time'])) : 'TBD' ?></span></div>
      <div class="dr"><span class="dr-lbl">📍 Venue</span><span class="dr-val"><?= e($m['venue'] ?: 'Barangay Hall') ?></span></div>
      <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val"><?= e($m['complainant_name']) ?></span></div>
      <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val"><?= e($m['respondent_name'] ?: 'Unknown') ?></span></div>
      <?php if ((int)$m['total_missed'] > 0): ?>
      <div class="dr">
        <span class="dr-lbl">Prior Misses</span>
        <span class="dr-val"><span class="chip ch-rose"><?= (int)$m['total_missed'] ?> missed</span></span>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-foot" style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="btn btn-primary btn-sm" onclick="openOutcome(<?= $m['id'] ?>,'<?= e(addslashes($m['case_number'])) ?>',<?= (int)$m['total_missed'] ?>)">
        ⚠️ Document Now
      </button>
      <button class="act-btn" onclick="viewBlotter(<?= $m['blotter_id'] ?>)">View Case</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════════════ HISTORY ════════════ -->
<?php elseif ($tab === 'past'): ?>
<?php if (empty($past)): ?>
  <div class="empty-state"><div class="es-icon">🗂️</div><div class="es-title">No hearing history</div></div>
<?php else: ?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Case No.</th><th>Parties</th><th>Hearing Date</th>
          <th>Result</th><th>Attendance</th><th>Penalty</th><th>Outcome Notes</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($past as $m):
        // Attendance display
        $comp_att = $m['complainant_attended'] === null ? '—' : ($m['complainant_attended'] ? '<span class="chip ch-emerald" style="font-size:10px">✓ Yes</span>' : '<span class="chip ch-rose" style="font-size:10px">✗ No</span>');
        $resp_att = $m['respondent_attended']  === null ? '—' : ($m['respondent_attended']  ? '<span class="chip ch-emerald" style="font-size:10px">✓ Yes</span>' : '<span class="chip ch-rose" style="font-size:10px">✗ No</span>');
      ?>
        <tr>
          <td class="td-mono"><?= e($m['case_number']) ?></td>
          <td>
            <div style="font-size:12px"><?= e($m['complainant_name']) ?></div>
            <div style="font-size:11px;color:var(--ink-400)">vs. <?= e($m['respondent_name'] ?: '?') ?></div>
          </td>
          <td style="font-size:12px;color:var(--ink-600);white-space:nowrap">
            <?= date('M j, Y', strtotime($m['hearing_date'])) ?>
            <?php if ($m['reschedule_date']): ?>
              <div style="font-size:11px;color:var(--teal-600)">→ <?= date('M j, Y', strtotime($m['reschedule_date'])) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="chip <?= $sc[$m['status']] ?? 'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$m['status'])) ?></span></td>
          <td>
            <div style="font-size:11px;color:var(--ink-400)">Comp: <?= $comp_att ?></div>
            <div style="font-size:11px;color:var(--ink-400);margin-top:2px">Resp: <?= $resp_att ?></div>
          </td>
          <td style="font-size:12px">
            <?php if ($m['penalty_issued']): ?>
              <span class="chip ch-rose" style="font-size:10px">Issued</span>
            <?php elseif ($m['missed_session']): ?>
              <span class="chip ch-amber" style="font-size:10px">Pending</span>
            <?php else: ?><span style="color:var(--ink-300)">—</span><?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--ink-500);max-width:180px;white-space:normal">
            <?= e(mb_strimwidth($m['outcome'] ?? '—', 0, 70, '…')) ?>
          </td>
          <td>
            <?php if ($m['missed_session'] && !$m['penalty_issued']): ?>
              <button class="act-btn" style="white-space:nowrap" onclick="issuePenalty(<?= $m['id'] ?>,'<?= e(addslashes($m['case_number'])) ?>','<?= $m['no_show_by'] ?>')">Issue Penalty</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; // end tab ?>


<!-- ════════════ MODALS ════════════ -->

<!-- New Hearing -->
<div class="modal-overlay" id="modal-new-med">
  <div class="modal">
    <div class="modal-hdr"><span class="modal-title">Schedule New Hearing</span><button class="modal-x" onclick="closeModal('modal-new-med')">×</button></div>
    <div class="modal-body">
      <div class="fg">
        <label>Select Case <span class="req">*</span></label>
        <select id="nm-case">
          <option value="">— Select Case —</option>
          <?php foreach ($active_cases as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['case_number']) ?> — <?= e($c['complainant_name']) ?><?= $c['respondent_name'] ? ' vs. ' . e($c['respondent_name']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fr2">
        <div class="fg"><label>Date <span class="req">*</span></label><input type="date" id="nm-date" min="<?= date('Y-m-d') ?>"></div>
        <div class="fg"><label>Time <span class="req">*</span></label><input type="time" id="nm-time"></div>
      </div>
      <div class="fg"><label>Venue</label><input type="text" id="nm-venue" value="Barangay Hall"></div>
      <div class="fg" style="margin-bottom:0"><label>Notes for Parties</label><textarea id="nm-notes" rows="2" placeholder="Instructions sent to both parties…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-new-med')">Cancel</button>
      <button class="btn btn-primary" onclick="submitNewMed()">Schedule Hearing</button>
    </div>
  </div>
</div>

<!-- Record Outcome (smart) -->
<div class="modal-overlay" id="modal-outcome">
  <div class="modal modal-lg">
    <div class="modal-hdr">
      <span class="modal-title">Record Hearing Outcome</span>
      <button class="modal-x" onclick="closeModal('modal-outcome')">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="oc-id">
      <input type="hidden" id="oc-missed-count">

      <div class="fg">
        <label>Case</label>
        <input type="text" id="oc-case" readonly style="background:var(--surface);font-weight:600">
      </div>

      <!-- Prior misses warning -->
      <div id="oc-miss-warn" style="display:none;padding:10px 12px;border-radius:var(--r-md);background:var(--rose-50);border:1px solid var(--rose-100);margin-bottom:14px;font-size:12px;color:var(--rose-700)"></div>

      <!-- Attendance first — drives auto-result -->
      <div style="font-size:12px;font-weight:700;color:var(--ink-400);letter-spacing:.05em;text-transform:uppercase;margin-bottom:10px">Attendance</div>
      <div class="fr2" style="margin-bottom:16px">
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

      <!-- Auto-suggested result -->
      <div class="fg">
        <label>Hearing Result <span class="req">*</span></label>
        <select id="oc-status" onchange="onResultChange(this.value)">
          <option value="completed">✅ Completed — Agreement Reached</option>
          <option value="missed">🚫 No Show (auto-counted as missed)</option>
          <option value="rescheduled">📅 Rescheduled (barangay decision, no penalty)</option>
          <option value="cancelled">❌ Cancelled</option>
        </select>
        <div id="oc-auto-note" style="font-size:11px;color:var(--ink-400);margin-top:5px"></div>
      </div>

      <!-- Reschedule date (shown only when rescheduled) -->
      <div id="reschedule-row" style="display:none">
        <div class="fr2">
          <div class="fg"><label>New Hearing Date <span class="req">*</span></label><input type="date" id="oc-redate" min="<?= date('Y-m-d') ?>"></div>
          <div class="fg"><label>New Hearing Time</label><input type="time" id="oc-retime"></div>
        </div>
      </div>

      <!-- No-show penalty preview (shown only when missed) -->
      <div id="penalty-preview" style="display:none;padding:12px 14px;border-radius:var(--r-md);background:var(--amber-50);border:1px solid var(--amber-200);margin-bottom:14px">
        <div style="font-size:12px;font-weight:700;color:var(--amber-600);margin-bottom:6px">⚠️ Penalty will be automatically issued</div>
        <div id="penalty-detail" style="font-size:12px;color:var(--ink-700)"></div>
        <div style="font-size:11px;color:var(--ink-500);margin-top:6px">
          Penalty is issued to the absent party. Officer can waive or adjust from the History tab.
        </div>
      </div>

      <div class="fg"><label>Outcome Notes / Summary</label><textarea id="oc-outcome" rows="3" placeholder="What happened, what was agreed, or reason for outcome…"></textarea></div>
      <div class="fg" style="margin-bottom:0"><label>Next Steps</label><input type="text" id="oc-next" placeholder="e.g. Sign agreement on April 30, Pay fine within 15 days"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-outcome')">Cancel</button>
      <button class="btn btn-primary" onclick="submitOutcome()">Save Outcome</button>
    </div>
  </div>
</div>

<!-- Issue Penalty (manual, from history) -->
<div class="modal-overlay" id="modal-penalty">
  <div class="modal">
    <div class="modal-hdr"><span class="modal-title">Issue No-Show Penalty</span><button class="modal-x" onclick="closeModal('modal-penalty')">×</button></div>
    <div class="modal-body">
      <input type="hidden" id="pen-med-id">
      <div class="fg"><label>Case</label><input type="text" id="pen-case" readonly style="background:var(--surface)"></div>
      <div class="fg">
        <label>Absent Party</label>
        <select id="pen-party">
          <option value="respondent">Respondent</option>
          <option value="complainant">Complainant</option>
          <option value="both">Both Parties</option>
        </select>
      </div>
      <div class="fr2">
        <div class="fg"><label>Fine Amount (₱)</label><input type="number" id="pen-amount" min="0" step="50" value="500"></div>
        <div class="fg"><label>Community Service (hrs)</label><input type="number" id="pen-csh" min="0" value="4"></div>
      </div>
      <div class="fg"><label>Due Date</label><input type="date" id="pen-due" value="<?= date('Y-m-d', strtotime('+15 days')) ?>"></div>
      <div class="fg" style="margin-bottom:0"><label>Reason</label><input type="text" id="pen-reason" value="Failure to appear at scheduled mediation hearing"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-penalty')">Cancel</button>
      <button class="btn btn-danger" onclick="submitPenalty()">Issue Penalty</button>
    </div>
  </div>
</div>

<style>
.att-btn {
  padding:6px 14px; border-radius:var(--r-sm); font-size:12px; font-weight:600;
  cursor:pointer; border:1px solid var(--ink-100); background:var(--white);
  color:var(--ink-400); font-family:inherit; transition:all .12s;
}
.att-btn.active { background:var(--teal-600); color:var(--white); border-color:var(--teal-600); }
.att-btn:not(.active):hover { border-color:var(--teal-400); color:var(--teal-600); background:var(--teal-50); }
</style>

<script>
// ── Penalty rules per missed session count ──
const PENALTY_RULES = <?= json_encode($penalty_rules) ?>;

function setAtt(party, val) {
  document.getElementById('oc-' + party).value = val;
  document.getElementById(party + '-yes').classList.toggle('active', val === '1');
  document.getElementById(party + '-no' ).classList.toggle('active', val === '0');
  autoSuggestResult();
}

function autoSuggestResult() {
  const comp = document.getElementById('oc-comp').value;
  const resp = document.getElementById('oc-resp').value;
  const sel  = document.getElementById('oc-status');
  let suggested = 'completed';
  let note = '';

  if (comp === '0' && resp === '0') {
    suggested = 'missed';
    note = '⚠️ Both parties absent — auto-set to No Show.';
  } else if (comp === '0') {
    suggested = 'missed';
    note = '⚠️ Complainant absent — auto-set to No Show.';
  } else if (resp === '0') {
    suggested = 'missed';
    note = '⚠️ Respondent absent — auto-set to No Show.';
  } else {
    note = '✓ Both parties present.';
  }

  sel.value = suggested;
  document.getElementById('oc-auto-note').textContent = note;
  onResultChange(suggested);
}

function onResultChange(val) {
  const reschedRow    = document.getElementById('reschedule-row');
  const penPreview    = document.getElementById('penalty-preview');
  const penDetail     = document.getElementById('penalty-detail');
  const missedCount   = parseInt(document.getElementById('oc-missed-count').value || '0');
  const comp          = document.getElementById('oc-comp').value;
  const resp          = document.getElementById('oc-resp').value;

  reschedRow.style.display = (val === 'rescheduled') ? '' : 'none';
  if (val === 'rescheduled') {
    document.getElementById('oc-auto-note').textContent = 'ℹ️ Rescheduled = no penalty. A new session will be created.';
  }

  if (val === 'missed') {
    penPreview.style.display = '';
    const nextMiss = missedCount + 1;
    const rule     = PENALTY_RULES[Math.min(nextMiss, 3)];
    let who = '';
    if (comp === '0' && resp === '0') who = 'Both parties';
    else if (comp === '0') who = 'Complainant';
    else if (resp === '0') who = 'Respondent';
    else who = 'Absent party';
    penDetail.innerHTML = `
      <strong>Missed session #${nextMiss}</strong><br>
      Penalty for <strong>${who}</strong>: ${rule ? rule[0] : 'Subject to officer discretion'}<br>
      ${nextMiss >= 3 ? '<strong style="color:var(--rose-600)">3rd miss: Case escalation strongly recommended.</strong>' : ''}
    `;
  } else {
    penPreview.style.display = 'none';
  }
}

function openOutcome(id, caseNo, missedCount) {
  document.getElementById('oc-id').value          = id;
  document.getElementById('oc-case').value        = caseNo;
  document.getElementById('oc-missed-count').value= missedCount;
  document.getElementById('oc-outcome').value     = '';
  document.getElementById('oc-next').value        = '';
  document.getElementById('oc-redate').value      = '';
  document.getElementById('oc-retime').value      = '';
  // Reset attendance to present
  setAtt('comp','1'); setAtt('resp','1');
  document.getElementById('oc-status').value = 'completed';
  document.getElementById('oc-auto-note').textContent = '';
  document.getElementById('reschedule-row').style.display = 'none';
  document.getElementById('penalty-preview').style.display = 'none';
  // Prior miss warning
  const warn = document.getElementById('oc-miss-warn');
  if (missedCount > 0) {
    warn.style.display = '';
    warn.textContent = `⚠️ This case already has ${missedCount} missed session(s) on record. Another no-show will trigger escalated penalties.`;
  } else {
    warn.style.display = 'none';
  }
  openModal('modal-outcome');
}

function submitOutcome() {
  const status   = document.getElementById('oc-status').value;
  const redate   = document.getElementById('oc-redate').value;
  const retime   = document.getElementById('oc-retime').value;
  if (status === 'rescheduled' && !redate) return showToast('Please specify the new hearing date.', 'error');

  const data = {
    action:               'record_outcome',
    id:                   document.getElementById('oc-id').value,
    status,
    complainant_attended: document.getElementById('oc-comp').value,
    respondent_attended:  document.getElementById('oc-resp').value,
    outcome:              document.getElementById('oc-outcome').value.trim(),
    next_steps:           document.getElementById('oc-next').value.trim(),
    reschedule_date:      redate,
    reschedule_time:      retime,
  };
  loading(true);
  fetch('ajax/mediation_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
    .then(r => r.json()).then(d => {
      loading(false); closeModal('modal-outcome');
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 700);
    }).catch(() => { loading(false); showToast('Request failed.', 'error'); });
}

function cancelMed(id) {
  if (!confirm('Cancel this mediation hearing? This will not count as a missed session.')) return;
  loading(true);
  fetch('ajax/mediation_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'cancel', id }) })
    .then(r => r.json()).then(d => {
      loading(false); showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 700);
    });
}

function submitNewMed() {
  const data = {
    action:     'schedule_mediation',
    blotter_id: document.getElementById('nm-case').value,
    date:       document.getElementById('nm-date').value,
    time:       document.getElementById('nm-time').value,
    venue:      document.getElementById('nm-venue').value.trim(),
    notes:      document.getElementById('nm-notes').value.trim(),
  };
  if (!data.blotter_id || !data.date || !data.time) return showToast('Case, date and time are required.', 'error');
  loading(true);
  fetch('ajax/mediation_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
    .then(r => r.json()).then(d => {
      loading(false); closeModal('modal-new-med');
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 700);
    }).catch(() => { loading(false); showToast('Request failed.', 'error'); });
}

function issuePenalty(medId, caseNo, noShowBy) {
  document.getElementById('pen-med-id').value = medId;
  document.getElementById('pen-case').value   = caseNo;
  const partyMap = { respondent:'respondent', complainant:'complainant', both:'both' };
  document.getElementById('pen-party').value  = partyMap[noShowBy] || 'respondent';
  openModal('modal-penalty');
}

function submitPenalty() {
  const data = {
    action:     'issue_penalty',
    med_id:     document.getElementById('pen-med-id').value,
    party:      document.getElementById('pen-party').value,
    amount:     document.getElementById('pen-amount').value,
    csh:        document.getElementById('pen-csh').value,
    due_date:   document.getElementById('pen-due').value,
    reason:     document.getElementById('pen-reason').value.trim(),
  };
  loading(true);
  fetch('ajax/mediation_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
    .then(r => r.json()).then(d => {
      loading(false); closeModal('modal-penalty');
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 700);
    }).catch(() => { loading(false); showToast('Request failed.', 'error'); });
}
</script>
