<?php
// pages/blotter-management.php
$bid = (int)$user['barangay_id'];

$f_status = $_GET['status'] ?? '';
$f_level  = $_GET['level']  ?? '';
$f_type   = $_GET['type']   ?? '';
$f_search = $_GET['search'] ?? '';
$pg = max(1, (int)($_GET['pg'] ?? 1));
$per_page = 15;
$offset   = ($pg - 1) * $per_page;

// Build WHERE
$where = ["barangay_id = $bid"]; $params = [];
if ($f_status) { $where[] = 'status = ?';          $params[] = $f_status; }
if ($f_level)  { $where[] = 'violation_level = ?'; $params[] = $f_level; }
if ($f_type)   { $where[] = 'incident_type = ?';   $params[] = $f_type; }
if ($f_search) {
    $where[] = '(case_number LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ?)';
    $like = "%{$f_search}%";
    $params = array_merge($params, [$like, $like, $like]);
}
$ws = 'WHERE ' . implode(' AND ', $where);

$blotters = []; $total = 0;
try {
    $c = $pdo->prepare("SELECT COUNT(*) FROM blotters $ws");
    $c->execute($params); $total = (int)$c->fetchColumn();

    $s = $pdo->prepare("SELECT * FROM blotters $ws ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $s->execute(array_merge($params, [$per_page, $offset]));
    $blotters = $s->fetchAll();
} catch (PDOException $e) {}

$total_pages = max(1, (int)ceil($total / $per_page));

// Tab counts
$tab_counts = [];
try {
    $rows = $pdo->query("SELECT status, COUNT(*) c FROM blotters WHERE barangay_id=$bid GROUP BY status")->fetchAll();
    foreach ($rows as $r) $tab_counts[$r['status']] = (int)$r['c'];
} catch (PDOException $e) {}
$tab_counts['all'] = array_sum($tab_counts);

// Helper: build query string preserving filters
function bq(array $o = []): string {
    $base = array_filter(['page'=>'blotter-management','status'=>$_GET['status']??'','level'=>$_GET['level']??'','type'=>$_GET['type']??'','search'=>$_GET['search']??''], fn($v) => $v !== '');
    return '?' . http_build_query(array_merge($base, $o));
}

$inc_types = ['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Trespassing','Theft / Estafa','Drug-Related','Traffic Incident','Public Disturbance','Other'];
$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-emerald','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'];
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Blotter Management</h2>
    <p>All cases for <?= e($bgy['name']) ?></p>
  </div>
  <div class="page-hdr-actions">
    <button class="btn btn-outline btn-sm" onclick="exportCSV()">⬇ Export CSV</button>
    <button class="btn btn-primary" onclick="openModal('modal-new-blotter')">+ New Blotter</button>
  </div>
</div>

<!-- Status tabs -->
<div class="tab-bar" style="margin-bottom:0;border-bottom:none">
  <?php
  $tabs = [''=> 'All', 'pending_review'=>'Pending', 'active'=>'Active', 'mediation_set'=>'Mediation Set', 'resolved'=>'Resolved', 'closed'=>'Closed', 'escalated'=>'Escalated'];
  foreach ($tabs as $val => $lbl):
    $cnt = $val === '' ? ($tab_counts['all'] ?? 0) : ($tab_counts[$val] ?? 0);
  ?>
  <a class="tab-item <?= $f_status === $val ? 'active' : '' ?>" href="<?= bq(['status'=>$val,'pg'=>1]) ?>">
    <?= $lbl ?><?php if ($cnt): ?> <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= $cnt ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<div style="height:1px;background:var(--ink-100);margin-bottom:14px"></div>

<!-- Filter bar -->
<form method="GET" class="filter-bar">
  <input type="hidden" name="page" value="blotter-management">
  <?php if ($f_status): ?><input type="hidden" name="status" value="<?= e($f_status) ?>"><?php endif; ?>
  <div class="inp-icon" style="flex:1;max-width:260px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Case no., name…" value="<?= e($f_search) ?>">
  </div>
  <select name="level" onchange="this.form.submit()">
    <option value="">All Levels</option>
    <?php foreach (['minor','moderate','serious','critical'] as $l): ?>
      <option value="<?= $l ?>" <?= $f_level === $l ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type" onchange="this.form.submit()">
    <option value="">All Types</option>
    <?php foreach ($inc_types as $t): ?>
      <option value="<?= $t ?>" <?= $f_type === $t ? 'selected' : '' ?>><?= $t ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline btn-sm">Search</button>
  <a href="?page=blotter-management" class="btn btn-ghost btn-sm">Clear</a>
</form>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Case No.</th><th>Complainant</th><th>Respondent</th>
          <th>Type</th><th>Level</th><th>Status</th><th>Prescribed Action</th>
          <th>Filed</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($blotters)): ?>
        <tr><td colspan="9"><div class="empty-state"><div class="es-icon">📋</div><div class="es-title">No blotters found</div><div class="es-sub">Adjust your filters or file a new blotter</div></div></td></tr>
      <?php else: foreach ($blotters as $b): ?>
        <tr>
          <td class="td-mono"><?= e($b['case_number']) ?></td>
          <td class="td-main"><?= e($b['complainant_name']) ?></td>
          <td><?= e($b['respondent_name'] ?: '—') ?></td>
          <td style="font-size:12px"><?= e($b['incident_type']) ?></td>
          <td><span class="chip <?= $lm[$b['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
          <td><span class="chip <?= $sm[$b['status']] ?? 'ch-slate' ?>"><?= ucwords(str_replace('_', ' ', $b['status'])) ?></span></td>
          <td style="font-size:12px;color:var(--ink-500)"><?= e(ucwords(str_replace('_', ' ', $b['prescribed_action'] ?? ''))) ?: '—' ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button>
              <?php if ($b['status'] === 'pending_review'): ?>
                <button class="act-btn green" onclick="quickApprove(<?= $b['id'] ?>)">Approve</button>
              <?php endif; ?>
              <?php if (!in_array($b['status'], ['resolved','closed','transferred'])): ?>
                <button class="act-btn" onclick="openScheduleMed(<?= $b['id'] ?>, '<?= e(addslashes($b['case_number'])) ?>')">Mediation</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= min($offset + 1, $total) ?>–<?= min($offset + $per_page, $total) ?> of <?= $total ?> records</span>
      <div class="pager-btns">
        <?php if ($pg > 1): ?><a href="<?= bq(['pg'=>$pg-1]) ?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i = max(1, $pg-2); $i <= min($total_pages, $pg+2); $i++): ?>
          <a href="<?= bq(['pg'=>$i]) ?>" class="btn <?= $i === $pg ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pg < $total_pages): ?><a href="<?= bq(['pg'=>$pg+1]) ?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Schedule Mediation Modal -->
<div class="modal-overlay" id="modal-schedule-med">
  <div class="modal">
    <div class="modal-hdr">
      <span class="modal-title">Schedule Mediation</span>
      <button class="modal-x" onclick="closeModal('modal-schedule-med')">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="sm-blotter-id">
      <div class="fg">
        <label>Case</label>
        <input type="text" id="sm-case-no" readonly style="background:var(--surface)">
      </div>
      <div class="fr2">
        <div class="fg"><label>Hearing Date <span class="req">*</span></label><input type="date" id="sm-date" min="<?= date('Y-m-d') ?>"></div>
        <div class="fg"><label>Hearing Time <span class="req">*</span></label><input type="time" id="sm-time"></div>
      </div>
      <div class="fg"><label>Venue / Location</label><input type="text" id="sm-venue" placeholder="Barangay Hall" value="Barangay Hall"></div>
      <div class="fg"><label>Notes</label><textarea id="sm-notes" rows="2" placeholder="Instructions for parties…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-schedule-med')">Cancel</button>
      <button class="btn btn-primary" onclick="submitSchedule()">Schedule Hearing</button>
    </div>
  </div>
</div>

<script>
function quickApprove(id) {
  if (!confirm('Move this blotter to Active status?')) return;
  loading(true);
  fetch('ajax/blotter_action.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action:'update_status', id, status:'active', prescribed_action:'document_only', remarks:'Approved by officer' })
  }).then(r => r.json()).then(d => {
    loading(false);
    showToast(d.message, d.success ? 'success' : 'error');
    if (d.success) setTimeout(() => location.reload(), 700);
  });
}

function openScheduleMed(id, caseNo) {
  document.getElementById('sm-blotter-id').value = id;
  document.getElementById('sm-case-no').value    = caseNo;
  openModal('modal-schedule-med');
}

function submitSchedule() {
  const data = {
    action:     'schedule_mediation',
    blotter_id: document.getElementById('sm-blotter-id').value,
    date:       document.getElementById('sm-date').value,
    time:       document.getElementById('sm-time').value,
    venue:      document.getElementById('sm-venue').value.trim(),
    notes:      document.getElementById('sm-notes').value.trim(),
  };
  if (!data.date || !data.time) return showToast('Date and time are required.', 'error');
  loading(true);
  fetch('ajax/mediation_action.php', {
    method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
  }).then(r => r.json()).then(d => {
    loading(false);
    closeModal('modal-schedule-med');
    showToast(d.message, d.success ? 'success' : 'error');
    if (d.success) setTimeout(() => location.reload(), 700);
  }).catch(() => { loading(false); showToast('Request failed.', 'error'); });
}

function exportCSV() {
  const params = new URLSearchParams({
    barangay_id: BARANGAY_ID,
    status:  '<?= e($f_status) ?>',
    level:   '<?= e($f_level) ?>',
    search:  '<?= e($f_search) ?>'
  });
  window.location = 'ajax/export_blotters.php?' + params.toString();
}
</script>
