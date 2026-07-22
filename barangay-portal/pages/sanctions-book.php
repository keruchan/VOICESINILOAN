<?php
// pages/sanctions-book.php — aligned to Katarungang Pambarangay Law (RA 7160)
$bid = (int)$user['barangay_id'];

$cat_labels = ['kp_law'=>'KP Law (R.A. 7160)','local_ordinance'=>'Local Ordinance','barangay_program'=>'Barangay Program'];
$cat_chips  = ['kp_law'=>'ch-navy','local_ordinance'=>'ch-teal','barangay_program'=>'ch-slate'];
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Sanctions Book</h2><p>Legal reference for violations and penalties — Katarungang Pambarangay Law &amp; local ordinances</p></div>
  <div style="display:flex;gap:8px">
    <a href="?page=case-finder" class="btn btn-outline btn-sm">🔎 Smart Case Finder</a>
    <button class="btn btn-primary" onclick="resetForm();openModal('modal-sanction')">+ Add Entry</button>
  </div>
</div>

<form id="sf-form" class="filter-bar" onsubmit="return false">
  <input type="hidden" name="page" value="sanctions-book">
  <div class="inp-icon" style="flex:1;max-width:280px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Violation type, sanction, or legal basis…">
  </div>
  <select name="level">
    <option value="">All Levels</option>
    <?php foreach (['minor','moderate','serious','critical'] as $l): ?>
      <option value="<?= $l ?>"><?= ucfirst($l) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category">
    <option value="">All Legal Sources</option>
    <?php foreach ($cat_labels as $v => $l): ?>
      <option value="<?= $v ?>"><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="button" id="sf-clear" class="btn btn-ghost btn-sm">✕ Clear</button>
</form>

<div id="sf-results">
  <?php require __DIR__ . '/partials/sanctions-table.php'; ?>
</div>

<!-- View Details Modal — "what exactly is on KP Law" -->
<div class="modal-overlay" id="modal-view-sanction">
  <div class="modal modal-lg">
    <div class="modal-hdr"><span class="modal-title" id="vs-title">Sanction Details</span><button class="modal-x" onclick="closeModal('modal-view-sanction')">×</button></div>
    <div class="modal-body">
      <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <span class="chip" id="vs-level-chip"></span>
        <span class="chip" id="vs-cat-chip"></span>
      </div>

      <div class="dr"><span class="dr-lbl">Violation Type</span><span class="dr-val" id="vs-vtype" style="font-weight:600"></span></div>
      <div class="dr"><span class="dr-lbl">Sanction</span><span class="dr-val" id="vs-sname"></span></div>
      <div class="dr"><span class="dr-lbl">Fine</span><span class="dr-val" id="vs-fine" style="font-weight:600;color:var(--rose-600)"></span></div>
      <div class="dr"><span class="dr-lbl">Community Service</span><span class="dr-val" id="vs-hours"></span></div>
      <div class="dr"><span class="dr-lbl">Reference No.</span><span class="dr-val" id="vs-ordref" style="font-family:var(--font-mono)"></span></div>

      <div id="vs-legal-wrap" style="margin-top:16px;padding:14px 16px;border-radius:var(--r-md);background:var(--navy-50,#eef3fb);border:1px solid var(--navy-100,#d7e3f5)">
        <div style="font-size:11px;font-weight:700;color:var(--navy-700,#1F4068);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">
          Legal Basis — <span id="vs-basis" style="font-family:var(--font-mono);text-transform:none;letter-spacing:normal"></span>
        </div>
        <p id="vs-explanation" style="font-size:13px;line-height:1.7;color:var(--ink-700);margin:0"></p>
      </div>

      <div id="vs-desc-wrap" style="margin-top:14px">
        <div style="font-size:11px;font-weight:700;color:var(--ink-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Notes</div>
        <p id="vs-desc" style="font-size:12px;color:var(--ink-500);margin:0"></p>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-view-sanction')">Close</button>
      <button class="btn btn-primary" id="vs-edit-btn">Edit This Entry</button>
    </div>
  </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal-overlay" id="modal-sanction">
  <div class="modal modal-lg">
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

      <div class="fg" style="margin-top:8px;padding-top:12px;border-top:1px solid var(--surface-2)">
        <label>Legal Source <span class="req">*</span></label>
        <select id="s-cat" onchange="onCatChange()">
          <option value="kp_law">KP Law — Katarungang Pambarangay (R.A. 7160)</option>
          <option value="local_ordinance">Local Ordinance (Sangguniang Barangay)</option>
          <option value="barangay_program">Barangay Program (non-statutory)</option>
        </select>
      </div>
      <div class="fr2">
        <div class="fg"><label>Ordinance / Reference No.</label><input type="text" id="s-ord" placeholder="e.g. Sec. 4, BO No. 2019-01"></div>
        <div class="fg"><label>Legal Basis (section/citation)</label><input type="text" id="s-basis" placeholder="e.g. Sec. 412(b)(4), R.A. 7160"></div>
      </div>
      <div class="fg" id="s-expl-wrap"><label>Legal Explanation <span class="req">*</span></label><textarea id="s-expl" rows="3" placeholder="Explain in plain language what the cited law/ordinance says and why it applies to this violation…"></textarea></div>
      <div class="fg"><label>Notes</label><textarea id="s-desc" rows="2"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-sanction')">Cancel</button>
      <button class="btn btn-primary" onclick="saveSanction()">Save Entry</button>
    </div>
  </div>
</div>

<script>
const SF_CAT_LABELS = <?= json_encode($cat_labels) ?>;
const SF_CAT_CHIPS  = <?= json_encode($cat_chips) ?>;
const SF_LEVEL_CH   = { minor:'ch-emerald', moderate:'ch-amber', serious:'ch-rose', critical:'ch-violet' };

function resetForm() {
  document.getElementById('s-id').value = 0;
  ['s-type','s-sname','s-fine','s-csh','s-ord','s-basis','s-expl','s-desc'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('s-level').value = 'minor';
  document.getElementById('s-cat').value = 'kp_law';
  document.getElementById('sanction-modal-title').textContent = 'Add Sanction Entry';
  onCatChange();
}

function onCatChange() {
  const wrap = document.getElementById('s-expl-wrap');
  wrap.style.display = document.getElementById('s-cat').value === 'barangay_program' ? 'none' : '';
}

function loadEdit(s) {
  document.getElementById('s-id').value    = s.id;
  document.getElementById('s-type').value  = s.violation_type;
  document.getElementById('s-level').value = s.violation_level;
  document.getElementById('s-sname').value = s.sanction_name;
  document.getElementById('s-fine').value  = s.fine_amount || 0;
  document.getElementById('s-csh').value   = s.community_hours || 0;
  document.getElementById('s-cat').value   = s.legal_category || 'local_ordinance';
  document.getElementById('s-ord').value   = s.ordinance_ref || '';
  document.getElementById('s-basis').value = s.legal_basis || '';
  document.getElementById('s-expl').value  = s.legal_explanation || '';
  document.getElementById('s-desc').value  = s.description || '';
  document.getElementById('sanction-modal-title').textContent = 'Edit Sanction Entry';
  onCatChange();
  closeModal('modal-view-sanction');
  openModal('modal-sanction');
}

function viewSanction(s) {
  document.getElementById('vs-title').textContent    = s.violation_type;
  document.getElementById('vs-vtype').textContent     = s.violation_type;
  document.getElementById('vs-sname').textContent     = s.sanction_name;
  document.getElementById('vs-fine').textContent      = '₱' + Number(s.fine_amount || 0).toLocaleString();
  document.getElementById('vs-hours').textContent      = s.community_hours ? s.community_hours + ' hrs' : '—';
  document.getElementById('vs-ordref').textContent    = s.ordinance_ref || '—';

  const levelChip = document.getElementById('vs-level-chip');
  levelChip.className = 'chip ' + (SF_LEVEL_CH[s.violation_level] || 'ch-slate');
  levelChip.textContent = s.violation_level.charAt(0).toUpperCase() + s.violation_level.slice(1);

  const catChip = document.getElementById('vs-cat-chip');
  catChip.className = 'chip ' + (SF_CAT_CHIPS[s.legal_category] || 'ch-slate');
  catChip.textContent = SF_CAT_LABELS[s.legal_category] || s.legal_category;

  const legalWrap = document.getElementById('vs-legal-wrap');
  if (s.legal_explanation) {
    legalWrap.style.display = '';
    document.getElementById('vs-basis').textContent = s.legal_basis || '—';
    document.getElementById('vs-explanation').textContent = s.legal_explanation;
  } else {
    legalWrap.style.display = 'none';
  }

  const descWrap = document.getElementById('vs-desc-wrap');
  if (s.description) { descWrap.style.display = ''; document.getElementById('vs-desc').textContent = s.description; }
  else descWrap.style.display = 'none';

  document.getElementById('vs-edit-btn').onclick = () => loadEdit(s);
  openModal('modal-view-sanction');
}

function saveSanction() {
  const data = {
    action:            'save',
    id:                parseInt(document.getElementById('s-id').value) || 0,
    violation_type:    document.getElementById('s-type').value.trim(),
    violation_level:   document.getElementById('s-level').value,
    legal_category:    document.getElementById('s-cat').value,
    sanction_name:     document.getElementById('s-sname').value.trim(),
    fine_amount:       parseFloat(document.getElementById('s-fine').value) || 0,
    community_hours:   parseInt(document.getElementById('s-csh').value) || 0,
    ordinance_ref:     document.getElementById('s-ord').value.trim(),
    legal_basis:       document.getElementById('s-basis').value.trim(),
    legal_explanation: document.getElementById('s-expl').value.trim(),
    description:       document.getElementById('s-desc').value.trim(),
  };
  if (!data.violation_type || !data.sanction_name) return showToast('Violation type and sanction name are required.', 'error');
  loading(true);
  fetch('ajax/sanction_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
    .then(r => r.json()).then(d => {
      loading(false);
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) { closeModal('modal-sanction'); sfLive.refresh(); }
    });
}

function delSanction(id) {
  if (!confirm('Delete this entry?')) return;
  loading(true);
  fetch('ajax/sanction_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'delete', id }) })
    .then(r => r.json()).then(d => {
      loading(false);
      showToast(d.message, d.success ? 'success' : 'error');
      if (d.success) sfLive.refresh();
    });
}

let sfLive;
document.addEventListener('DOMContentLoaded', function () {
  sfLive = liveFilter({
    form: '#sf-form',
    result: '#sf-results',
    endpoint: 'ajax/sanctions_search.php',
    clearBtn: '#sf-clear',
  });
});
</script>
