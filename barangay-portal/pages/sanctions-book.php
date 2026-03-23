<?php
// pages/sanctions-book.php — real cols: violation_type, sanction_name, community_hours, ordinance_ref
$bid = (int)$user['barangay_id'];
$f_level  = $_GET['level']  ?? '';
$f_search = $_GET['search'] ?? '';

$where = ["barangay_id = $bid"]; $params = [];
if ($f_level)  { $where[] = 'violation_level = ?'; $params[] = $f_level; }
if ($f_search) { $where[] = '(violation_type LIKE ? OR sanction_name LIKE ?)'; $like="%$f_search%"; $params=array_merge($params,[$like,$like]); }
$ws = 'WHERE ' . implode(' AND ', $where);

$sanctions = [];
try {
    $s = $pdo->prepare("SELECT * FROM sanctions_book $ws ORDER BY violation_level DESC, violation_type ASC");
    $s->execute($params);
    $sanctions = $s->fetchAll();
} catch (PDOException $e) {}

$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Sanctions Book</h2><p>Ordinance reference for violations and penalties</p></div>
  <button class="btn btn-primary" onclick="resetForm();openModal('modal-sanction')">+ Add Entry</button>
</div>

<form method="GET" class="filter-bar">
  <input type="hidden" name="page" value="sanctions-book">
  <div class="inp-icon" style="flex:1;max-width:260px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Violation type or sanction name…" value="<?= e($f_search) ?>">
  </div>
  <select name="level" onchange="this.form.submit()">
    <option value="">All Levels</option>
    <?php foreach (['minor','moderate','serious','critical'] as $l): ?>
      <option value="<?= $l ?>" <?= $f_level===$l?'selected':'' ?>><?= ucfirst($l) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline btn-sm">Filter</button>
  <a href="?page=sanctions-book" class="btn btn-ghost btn-sm">Clear</a>
</form>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Violation Type</th><th>Level</th><th>Sanction</th><th>Fine (₱)</th><th>Comm. Svc (hrs)</th><th>Ordinance Ref.</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($sanctions)): ?>
        <tr><td colspan="7"><div class="empty-state"><div class="es-icon">📜</div><div class="es-title">No sanctions defined</div><div class="es-sub">Add ordinance entries using the button above</div></div></td></tr>
      <?php else: foreach ($sanctions as $s): ?>
        <tr>
          <td class="td-main"><?= e($s['violation_type']) ?></td>
          <td><span class="chip <?= $lm[$s['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($s['violation_level']) ?></span></td>
          <td><?= e($s['sanction_name']) ?></td>
          <td style="font-weight:600;color:var(--rose-600)">₱<?= number_format((float)($s['fine_amount'] ?? 0)) ?></td>
          <td style="font-size:12px"><?= $s['community_hours'] ? $s['community_hours'] . ' hrs' : '—' ?></td>
          <td style="font-family:var(--font-mono);font-size:12px;color:var(--teal-600)"><?= e($s['ordinance_ref'] ?: '—') ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="act-btn" onclick='loadEdit(<?= json_encode($s) ?>)'>Edit</button>
              <button class="act-btn red" onclick="delSanction(<?= $s['id'] ?>)">Del</button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal-overlay" id="modal-sanction">
  <div class="modal">
    <div class="modal-hdr">
      <span class="modal-title" id="sanction-modal-title">Add Sanction Entry</span>
      <button class="modal-x" onclick="closeModal('modal-sanction')">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="s-id" value="0">
      <div class="fr2">
        <div class="fg"><label>Violation Type <span class="req">*</span></label><input type="text" id="s-type" placeholder="e.g. Noise Disturbance – 1st Offense"></div>
        <div class="fg"><label>Level <span class="req">*</span></label>
          <select id="s-level"><option value="minor">Minor</option><option value="moderate">Moderate</option><option value="serious">Serious</option><option value="critical">Critical</option></select>
        </div>
      </div>
      <div class="fg"><label>Sanction Name <span class="req">*</span></label><input type="text" id="s-sname" placeholder="e.g. Verbal Warning + ₱500 Fine"></div>
      <div class="fr2">
        <div class="fg"><label>Fine Amount (₱)</label><input type="number" id="s-fine" placeholder="0" min="0" step="0.01"></div>
        <div class="fg"><label>Community Service (hrs)</label><input type="number" id="s-csh" placeholder="0" min="0"></div>
      </div>
      <div class="fg"><label>Ordinance Reference</label><input type="text" id="s-ord" placeholder="e.g. Sec. 4, BO No. 2019-01"></div>
      <div class="fg"><label>Description / Notes</label><textarea id="s-desc" rows="2"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-sanction')">Cancel</button>
      <button class="btn btn-primary" onclick="saveSanction()">Save Entry</button>
    </div>
  </div>
</div>

<script>
function resetForm() {
  document.getElementById('s-id').value = 0;
  ['s-type','s-sname','s-fine','s-csh','s-ord','s-desc'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('s-level').value = 'minor';
  document.getElementById('sanction-modal-title').textContent = 'Add Sanction Entry';
}

function loadEdit(s) {
  document.getElementById('s-id').value    = s.id;
  document.getElementById('s-type').value  = s.violation_type;
  document.getElementById('s-level').value = s.violation_level;
  document.getElementById('s-sname').value = s.sanction_name;
  document.getElementById('s-fine').value  = s.fine_amount || 0;
  document.getElementById('s-csh').value   = s.community_hours || 0;
  document.getElementById('s-ord').value   = s.ordinance_ref || '';
  document.getElementById('s-desc').value  = s.description || '';
  document.getElementById('sanction-modal-title').textContent = 'Edit Sanction Entry';
  openModal('modal-sanction');
}

function saveSanction() {
  const data = {
    action:          'save',
    id:              parseInt(document.getElementById('s-id').value) || 0,
    violation_type:  document.getElementById('s-type').value.trim(),
    violation_level: document.getElementById('s-level').value,
    sanction_name:   document.getElementById('s-sname').value.trim(),
    fine_amount:     parseFloat(document.getElementById('s-fine').value) || 0,
    community_hours: parseInt(document.getElementById('s-csh').value) || 0,
    ordinance_ref:   document.getElementById('s-ord').value.trim(),
    description:     document.getElementById('s-desc').value.trim(),
  };
  if (!data.violation_type || !data.sanction_name) return showToast('Violation type and sanction name are required.', 'error');
  loading(true);
  fetch('ajax/sanction_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
    .then(r => r.json()).then(d => {
      loading(false); closeModal('modal-sanction');
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 700);
    });
}

function delSanction(id) {
  if (!confirm('Delete this entry?')) return;
  loading(true);
  fetch('ajax/sanction_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'delete', id }) })
    .then(r => r.json()).then(d => {
      loading(false);
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) setTimeout(() => location.reload(), 700);
    });
}
</script>
