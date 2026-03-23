<?php
// pages/mediation.php — columns: hearing_date, hearing_time, venue, outcome, next_steps
$bid = (int)$user['barangay_id'];
$tab = $_GET['tab'] ?? 'upcoming';

$upcoming = []; $past = [];
try {
    $upcoming = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name,
               b.incident_type, b.violation_level
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid
          AND ms.status = 'scheduled'
          AND ms.hearing_date >= CURDATE()
        ORDER BY ms.hearing_date ASC
    ")->fetchAll();

    $past = $pdo->query("
        SELECT ms.*, b.case_number, b.complainant_name, b.respondent_name, b.incident_type
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid
          AND (ms.status != 'scheduled' OR ms.hearing_date < CURDATE())
        ORDER BY ms.hearing_date DESC
        LIMIT 30
    ")->fetchAll();

    // Active cases for new hearing dropdown
    $active_cases = $pdo->query("
        SELECT id, case_number, complainant_name
        FROM blotters
        WHERE barangay_id = $bid AND status IN ('active','pending_review')
        ORDER BY created_at DESC
    ")->fetchAll();
} catch (PDOException $e) { $active_cases = []; }

$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sc = ['completed'=>'ch-emerald','cancelled'=>'ch-rose','missed'=>'ch-amber','rescheduled'=>'ch-navy'];
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Mediation</h2><p>Schedule and track all hearings</p></div>
  <button class="btn btn-primary" onclick="openModal('modal-new-med')">+ Schedule Hearing</button>
</div>

<div class="tab-bar">
  <a class="tab-item <?= $tab==='upcoming'?'active':'' ?>" href="?page=mediation&tab=upcoming">Upcoming (<?= count($upcoming) ?>)</a>
  <a class="tab-item <?= $tab==='past'    ?'active':'' ?>" href="?page=mediation&tab=past">Past Hearings (<?= count($past) ?>)</a>
</div>

<?php if ($tab === 'upcoming'): ?>
  <?php if (empty($upcoming)): ?>
    <div class="empty-state"><div class="es-icon">📅</div><div class="es-title">No upcoming hearings</div><div class="es-sub">Schedule hearings from the button above or from Blotter Management</div></div>
  <?php else: ?>
  <div class="g2">
    <?php foreach ($upcoming as $m): ?>
    <div class="card">
      <div class="card-hdr" style="background:var(--teal-50)">
        <div>
          <div class="card-title"><?= e($m['case_number']) ?></div>
          <div class="card-sub"><?= e($m['incident_type']) ?></div>
        </div>
        <span class="chip <?= $lm[$m['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($m['violation_level']) ?></span>
      </div>
      <div class="card-body" style="padding:14px 18px">
        <div class="dr"><span class="dr-lbl">📅 Date</span>
          <span class="dr-val" style="font-weight:700;color:var(--teal-600)"><?= date('D, M j, Y', strtotime($m['hearing_date'])) ?></span></div>
        <div class="dr"><span class="dr-lbl">⏰ Time</span>
          <span class="dr-val"><?= $m['hearing_time'] ? date('g:i A', strtotime($m['hearing_time'])) : 'TBD' ?></span></div>
        <div class="dr"><span class="dr-lbl">📍 Venue</span>
          <span class="dr-val"><?= e($m['venue'] ?: 'Barangay Hall') ?></span></div>
        <div class="dr"><span class="dr-lbl">Complainant</span>
          <span class="dr-val"><?= e($m['complainant_name']) ?></span></div>
        <div class="dr"><span class="dr-lbl">Respondent</span>
          <span class="dr-val"><?= e($m['respondent_name'] ?: 'Unknown') ?></span></div>
      </div>
      <div class="card-foot" style="display:flex;gap:8px">
        <button class="act-btn green" onclick="openOutcome(<?= $m['id'] ?>,'<?= e(addslashes($m['case_number'])) ?>')">Record Outcome</button>
        <button class="act-btn red"   onclick="cancelMed(<?= $m['id'] ?>)">Cancel</button>
        <button class="act-btn"       onclick="viewBlotter(<?= $m['blotter_id'] ?>)">View Case</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php else: // past ?>
  <div class="card">
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Case No.</th><th>Type</th><th>Parties</th><th>Date</th><th>Status</th><th>Outcome</th></tr></thead>
        <tbody>
        <?php if (empty($past)): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="es-icon">🗂️</div><div class="es-title">No past hearings</div></div></td></tr>
        <?php else: foreach ($past as $m): ?>
          <tr>
            <td class="td-mono"><?= e($m['case_number']) ?></td>
            <td style="font-size:12px"><?= e($m['incident_type']) ?></td>
            <td>
              <div><?= e($m['complainant_name']) ?></div>
              <div style="font-size:11px;color:var(--ink-400)">vs. <?= e($m['respondent_name'] ?: '?') ?></div>
            </td>
            <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($m['hearing_date'])) ?></td>
            <td><span class="chip <?= $sc[$m['status']] ?? 'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$m['status'])) ?></span></td>
            <td style="font-size:12px;color:var(--ink-500);max-width:200px;white-space:normal"><?= e(mb_strimwidth($m['outcome'] ?? '—', 0, 80, '…')) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- New Hearing Modal -->
<div class="modal-overlay" id="modal-new-med">
  <div class="modal">
    <div class="modal-hdr"><span class="modal-title">Schedule New Hearing</span><button class="modal-x" onclick="closeModal('modal-new-med')">×</button></div>
    <div class="modal-body">
      <div class="fg">
        <label>Select Case <span class="req">*</span></label>
        <select id="nm-case">
          <option value="">— Select Active Case —</option>
          <?php foreach ($active_cases as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['case_number']) ?> — <?= e($c['complainant_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fr2">
        <div class="fg"><label>Date <span class="req">*</span></label><input type="date" id="nm-date" min="<?= date('Y-m-d') ?>"></div>
        <div class="fg"><label>Time <span class="req">*</span></label><input type="time" id="nm-time"></div>
      </div>
      <div class="fg"><label>Venue</label><input type="text" id="nm-venue" value="Barangay Hall" placeholder="Barangay Hall"></div>
      <div class="fg"><label>Notes</label><textarea id="nm-notes" rows="2" placeholder="Instructions for both parties…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-new-med')">Cancel</button>
      <button class="btn btn-primary" onclick="submitNewMed()">Schedule</button>
    </div>
  </div>
</div>

<!-- Record Outcome Modal -->
<div class="modal-overlay" id="modal-outcome">
  <div class="modal">
    <div class="modal-hdr"><span class="modal-title">Record Hearing Outcome</span><button class="modal-x" onclick="closeModal('modal-outcome')">×</button></div>
    <div class="modal-body">
      <input type="hidden" id="oc-id">
      <div class="fg"><label>Case</label><input type="text" id="oc-case" readonly style="background:var(--surface)"></div>
      <div class="fg">
        <label>Result <span class="req">*</span></label>
        <select id="oc-status">
          <option value="completed">Completed — Agreement Reached</option>
          <option value="missed">No Show — Respondent Absent</option>
          <option value="cancelled">Cancelled</option>
          <option value="rescheduled">Rescheduled</option>
        </select>
      </div>
      <div class="fr2">
        <div class="fg"><label>Complainant Attended</label><select id="oc-comp"><option value="1">Yes</option><option value="0">No</option></select></div>
        <div class="fg"><label>Respondent Attended</label><select id="oc-resp"><option value="1">Yes</option><option value="0">No</option></select></div>
      </div>
      <div class="fg"><label>Outcome / Summary</label><textarea id="oc-outcome" rows="3" placeholder="What was agreed or decided…"></textarea></div>
      <div class="fg"><label>Next Steps</label><input type="text" id="oc-next" placeholder="e.g. Sign agreement, Pay fine by April 1"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-outcome')">Cancel</button>
      <button class="btn btn-primary" onclick="submitOutcome()">Save Outcome</button>
    </div>
  </div>
</div>

<script>
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

function openOutcome(id, caseNo) {
  document.getElementById('oc-id').value   = id;
  document.getElementById('oc-case').value = caseNo;
  document.getElementById('oc-outcome').value = '';
  document.getElementById('oc-next').value = '';
  openModal('modal-outcome');
}

function submitOutcome() {
  const data = {
    action:               'record_outcome',
    id:                   document.getElementById('oc-id').value,
    status:               document.getElementById('oc-status').value,
    complainant_attended: document.getElementById('oc-comp').value,
    respondent_attended:  document.getElementById('oc-resp').value,
    outcome:              document.getElementById('oc-outcome').value.trim(),
    next_steps:           document.getElementById('oc-next').value.trim(),
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
  if (!confirm('Cancel this mediation hearing?')) return;
  loading(true);
  fetch('ajax/mediation_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'cancel', id }) })
    .then(r => r.json()).then(d => {
      loading(false);
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 700);
    });
}
</script>
