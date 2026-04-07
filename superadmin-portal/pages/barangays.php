<?php
// pages/barangays.php

$barangays = [];
try {
    $barangays = $pdo->query("
        SELECT b.*,
               COUNT(DISTINCT u.id) as officer_count,
               COUNT(DISTINCT uc.id) as community_count,
               COUNT(DISTINCT bl.id) as total_blotters,
               SUM(bl.status IN ('resolved','closed')) as resolved_blotters,
               SUM(bl.status NOT IN ('resolved','closed','transferred')) as active_blotters
        FROM barangays b
        LEFT JOIN users u  ON u.barangay_id = b.id AND u.role = 'barangay'
        LEFT JOIN users uc ON uc.barangay_id = b.id AND uc.role = 'community'
        LEFT JOIN blotters bl ON bl.barangay_id = b.id
        GROUP BY b.id
        ORDER BY b.name ASC
    ")->fetchAll();
} catch (PDOException $e) {}
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Barangay Management</h2>
    <p>Oversee and manage all registered barangays in the municipality</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-add-bgy')">
    <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6.5 2v9M2 6.5h9"/></svg>
    Add Barangay
  </button>
</div>

<!-- Barangay Grid -->
<?php if (empty($barangays)): ?>
  <div style="text-align:center;padding:60px;color:var(--ink-300)">No barangays registered yet. Add one to get started.</div>
<?php else: ?>
<div class="g3 mb22">
  <?php foreach ($barangays as $b):
    $rate = $b['total_blotters'] > 0 ? round($b['resolved_blotters']/$b['total_blotters']*100) : 0;
    $rate_color = $rate >= 70 ? 'var(--emerald-400)' : ($rate >= 40 ? 'var(--amber-400)' : 'var(--rose-400)');
    $initials = '';
    foreach (explode(' ', $b['name']) as $w) if (strlen($w) > 2) $initials .= strtoupper($w[0]);
  ?>
  <div class="bgy-card" onclick="openBgyDetail(<?= $b['id'] ?>)">
    <div class="bgy-card-header">
      <div class="bgy-av"><?= htmlspecialchars(substr($initials,0,3)) ?></div>
      <div>
        <div class="bgy-name"><?= htmlspecialchars($b['name']) ?></div>
        <div class="bgy-muni"><?= htmlspecialchars($b['municipality']) ?> <?= $b['province'] ? '· '.htmlspecialchars($b['province']) : '' ?></div>
      </div>
      <div style="margin-left:auto">
        <span class="chip <?= $b['is_active'] ? 'chip-emerald' : 'chip-slate' ?>" style="font-size:10px"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span>
      </div>
    </div>

    <!-- Resolution bar -->
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--ink-400);margin-bottom:4px">
        <span>Resolution rate</span>
        <span style="font-weight:600;color:<?= $rate_color ?>"><?= $rate ?>%</span>
      </div>
      <div style="height:6px;background:var(--surface-2);border-radius:10px;overflow:hidden">
        <div style="width:<?= $rate ?>%;height:100%;background:<?= $rate_color ?>;border-radius:10px"></div>
      </div>
    </div>

    <div class="bgy-stats">
      <div class="bgy-stat">
        <div class="bgy-stat-val"><?= (int)$b['total_blotters'] ?></div>
        <div class="bgy-stat-lbl">Total Blotters</div>
      </div>
      <div class="bgy-stat">
        <div class="bgy-stat-val" style="color:var(--amber-600)"><?= (int)$b['active_blotters'] ?></div>
        <div class="bgy-stat-lbl">Active Cases</div>
      </div>
      <div class="bgy-stat">
        <div class="bgy-stat-val"><?= (int)$b['officer_count'] ?></div>
        <div class="bgy-stat-lbl">Officers</div>
      </div>
      <div class="bgy-stat">
        <div class="bgy-stat-val"><?= (int)$b['community_count'] ?></div>
        <div class="bgy-stat-lbl">Community</div>
      </div>
    </div>

    <div style="display:flex;gap:6px;margin-top:12px;padding-top:10px;border-top:1px solid var(--surface-2)">
      <button class="act-btn btn-xs" onclick="event.stopPropagation();openEditBgy(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['name'])) ?>', '<?= htmlspecialchars(addslashes($b['municipality'])) ?>', '<?= htmlspecialchars(addslashes($b['province'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($b['psgc_code'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($b['contact_no'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($b['captain_name'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($b['email'] ?? '')) ?>', <?= $b['is_active'] ?>)">Edit</button>
      <button class="act-btn btn-xs" onclick="event.stopPropagation();window.location='?page=users&barangay=<?= $b['id'] ?>'">View Users</button>
      <?php if ($b['is_active']): ?>
        <button class="act-btn danger btn-xs" onclick="event.stopPropagation();toggleBgy(<?= $b['id'] ?>, 0)">Deactivate</button>
      <?php else: ?>
        <button class="act-btn btn-xs" onclick="event.stopPropagation();toggleBgy(<?= $b['id'] ?>, 1)">Activate</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ MODAL: Add Barangay ══ -->
<div class="modal-overlay" id="modal-add-bgy">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Add Barangay</span><button class="modal-close" onclick="closeModal('modal-add-bgy')">×</button></div>
    <div class="modal-body">
      <div class="form-group"><label>Barangay Name <span class="req">*</span></label><input type="text" id="bgy-name" placeholder="e.g. Barangay San Roque"></div>
      <div class="form-row">
        <div class="form-group"><label>Municipality / City <span class="req">*</span></label><input type="text" id="bgy-muni" placeholder="e.g. Quezon City"></div>
        <div class="form-group"><label>Province</label><input type="text" id="bgy-prov" placeholder="e.g. Metro Manila"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>PSGC Code</label><input type="text" id="bgy-psgc" placeholder=""></div>
        <div class="form-group"><label>Contact Number</label><input type="text" id="bgy-contact" placeholder="09XXXXXXXXX"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Barangay Captain</label><input type="text" id="bgy-captain" placeholder="Full name"></div>
        <div class="form-group"><label>Email</label><input type="email" id="bgy-email" placeholder="bgy@email.com"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-add-bgy')">Cancel</button>
      <button class="btn btn-primary" onclick="submitAddBgy()">Add Barangay</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Edit Barangay ══ -->
<div class="modal-overlay" id="modal-edit-bgy">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Edit Barangay</span><button class="modal-close" onclick="closeModal('modal-edit-bgy')">×</button></div>
    <div class="modal-body">
      <input type="hidden" id="edit-bgy-id">
      <div class="form-group"><label>Barangay Name <span class="req">*</span></label><input type="text" id="edit-bgy-name" placeholder="e.g. Barangay San Roque"></div>
      <div class="form-row">
        <div class="form-group"><label>Municipality / City <span class="req">*</span></label><input type="text" id="edit-bgy-muni" placeholder="e.g. Paete"></div>
        <div class="form-group"><label>Province</label><input type="text" id="edit-bgy-prov" placeholder="e.g. Laguna"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>PSGC Code</label><input type="text" id="edit-bgy-psgc" placeholder="e.g. 043429001"></div>
        <div class="form-group"><label>Contact Number</label><input type="text" id="edit-bgy-contact" placeholder="09XXXXXXXXX"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Barangay Captain</label><input type="text" id="edit-bgy-captain" placeholder="Full name"></div>
        <div class="form-group"><label>Email</label><input type="email" id="edit-bgy-email" placeholder="bgy@email.com"></div>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="edit-bgy-active"><option value="1">Active</option><option value="0">Inactive</option></select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-edit-bgy')">Cancel</button>
      <button class="btn btn-primary" onclick="submitEditBgy()">Save Changes</button>
    </div>
  </div>
</div>

<script>
function openBgyDetail(id) {
  window.location = '?page=reports&barangay=' + id;
}
function openEditBgy(id, name, muni, province, psgc, contact, captain, email, active) {
  document.getElementById('edit-bgy-id').value      = id;
  document.getElementById('edit-bgy-name').value    = name;
  document.getElementById('edit-bgy-muni').value    = muni;
  document.getElementById('edit-bgy-prov').value    = province;
  document.getElementById('edit-bgy-psgc').value    = psgc;
  document.getElementById('edit-bgy-contact').value = contact;
  document.getElementById('edit-bgy-captain').value = captain;
  document.getElementById('edit-bgy-email').value   = email;
  document.getElementById('edit-bgy-active').value  = active;
  openModal('modal-edit-bgy');
}
function toggleBgy(id, active) {
  if (!confirm((active ? 'Activate' : 'Deactivate') + ' this barangay?')) return;
  fetch('ajax/barangay_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'toggle', id, active}) })
    .then(r=>r.json()).then(d=>{ showToast(d.message, d.success?'success':'error'); if(d.success) setTimeout(()=>location.reload(),700); });
}
function submitAddBgy() {
  const data = { action:'create', name: document.getElementById('bgy-name').value.trim(), municipality: document.getElementById('bgy-muni').value.trim(), province: document.getElementById('bgy-prov').value.trim(), psgc_code: document.getElementById('bgy-psgc').value.trim(), contact_no: document.getElementById('bgy-contact').value.trim(), captain_name: document.getElementById('bgy-captain').value.trim(), email: document.getElementById('bgy-email').value.trim() };
  if (!data.name || !data.municipality) return showToast('Name and municipality are required.','error');
  loading(true);
  fetch('ajax/barangay_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) })
    .then(r=>r.json()).then(d=>{ loading(false); closeModal('modal-add-bgy'); showToast(d.message, d.success?'success':'error'); if(d.success) setTimeout(()=>location.reload(),700); })
    .catch(()=>{ loading(false); showToast('Request failed.','error'); });
}
function submitEditBgy() {
  const data = {
    action:       'edit',
    id:           document.getElementById('edit-bgy-id').value,
    name:         document.getElementById('edit-bgy-name').value.trim(),
    municipality: document.getElementById('edit-bgy-muni').value.trim(),
    province:     document.getElementById('edit-bgy-prov').value.trim(),
    psgc_code:    document.getElementById('edit-bgy-psgc').value.trim(),
    contact_no:   document.getElementById('edit-bgy-contact').value.trim(),
    captain_name: document.getElementById('edit-bgy-captain').value.trim(),
    email:        document.getElementById('edit-bgy-email').value.trim(),
    is_active:    document.getElementById('edit-bgy-active').value,
  };
  if (!data.name || !data.municipality) return showToast('Name and municipality are required.', 'error');
  loading(true);
  fetch('ajax/barangay_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) })
    .then(r=>r.json()).then(d=>{ loading(false); closeModal('modal-edit-bgy'); showToast(d.message, d.success?'success':'error'); if(d.success) setTimeout(()=>location.reload(),700); })
    .catch(()=>{ loading(false); showToast('Request failed.','error'); });
}
</script>
