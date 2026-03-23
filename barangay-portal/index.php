<?php
// barangay-portal/index.php
require_once '../connection/auth.php';
guardRole('barangay');
$user = currentUser();  // keys: id, name, role, barangay_id

$allowed = ['dashboard','blotter-management','violator-monitor','mediation','sanctions-book','records-archive','settings'];
$page    = (isset($_GET['page']) && in_array($_GET['page'], $allowed)) ? $_GET['page'] : 'dashboard';

$titles = [
    'dashboard'          => 'Dashboard',
    'blotter-management' => 'Blotter Management',
    'violator-monitor'   => 'Violator Monitor',
    'mediation'          => 'Mediation',
    'sanctions-book'     => 'Sanctions Book',
    'records-archive'    => 'Records Archive',
    'settings'           => 'Settings',
];

$bid = (int)$user['barangay_id'];

// Fetch barangay row
$bgy = ['name' => 'Barangay', 'municipality' => '', 'province' => '', 'captain_name' => '', 'contact_no' => ''];
try {
    $s = $pdo->prepare("SELECT * FROM barangays WHERE id = ? LIMIT 1");
    $s->execute([$bid]);
    $bgy = $s->fetch() ?: $bgy;
} catch (PDOException $e) {}

// Sidebar badge: pending blotters
$pending_count = 0;
try {
    $pending_count = (int)$pdo->query(
        "SELECT COUNT(*) FROM blotters WHERE barangay_id = $bid AND status = 'pending_review'"
    )->fetchColumn();
} catch (PDOException $e) {}

// Sidebar badge: upcoming mediations
$med_count = 0;
try {
    $med_count = (int)$pdo->query(
        "SELECT COUNT(*) FROM mediation_schedules ms
         JOIN blotters b ON b.id = ms.blotter_id
         WHERE b.barangay_id = $bid AND ms.status = 'scheduled' AND ms.hearing_date >= CURDATE()"
    )->fetchColumn();
} catch (PDOException $e) {}

$bgy_init = strtoupper(implode('', array_slice(
    array_map(fn($w) => $w[0],
        array_filter(explode(' ', $bgy['name']), fn($w) => strlen($w) > 2)
    ), 0, 3
)));
if (!$bgy_init) $bgy_init = 'BG';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($titles[$page]) ?> — VOICE Barangay</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,700;1,9..144,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<div class="app">

  <!-- ── SIDEBAR ── -->
  <aside class="sidebar">
    <div class="sb-brand">
      <div class="sb-pill"><div class="sb-dot"></div><span>Barangay Portal</span></div>
      <div class="sb-name">VOICE</div>
      <div class="sb-sub">Blotter Management System</div>
    </div>

    <div class="bgy-chip">
      <div class="bgy-av"><?= $bgy_init ?></div>
      <div>
        <div class="bgy-nm"><?= e($bgy['name']) ?></div>
        <div class="bgy-loc"><?= e($bgy['municipality']) ?><?= $bgy['province'] ? ', ' . e($bgy['province']) : '' ?></div>
      </div>
    </div>

    <nav>
      <a class="nav-a <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="1.5" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="1.5" y="9" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="9" width="5.5" height="5.5" rx="1.2"/></svg>
        <span class="nav-label">Dashboard</span>
      </a>

      <div class="nav-hr"></div>
      <div class="nav-sec">Operations</div>

      <a class="nav-a <?= $page === 'blotter-management' ? 'active' : '' ?>" href="?page=blotter-management">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="1.5" width="12" height="13" rx="1.5"/><path d="M5 5.5h6M5 8h6M5 10.5h4"/></svg>
        <span class="nav-label">Blotter Management</span>
        <?php if ($pending_count > 0): ?><span class="nav-badge nb-rose"><?= $pending_count ?></span><?php endif; ?>
      </a>

      <a class="nav-a <?= $page === 'violator-monitor' ? 'active' : '' ?>" href="?page=violator-monitor">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="5.5" r="2.5"/><path d="M2 14c0-3 2.5-5 5-5s5 2.2 5 5"/><circle cx="13" cy="4" r="1.5"/><path d="M11.5 8.5c.8.5 1.5 1.4 1.5 3"/></svg>
        <span class="nav-label">Violator Monitor</span>
      </a>

      <a class="nav-a <?= $page === 'mediation' ? 'active' : '' ?>" href="?page=mediation">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6.5h12M6 3V1.5M10 3V1.5"/></svg>
        <span class="nav-label">Mediation</span>
        <?php if ($med_count > 0): ?><span class="nav-badge nb-amber"><?= $med_count ?></span><?php endif; ?>
      </a>

      <div class="nav-hr"></div>
      <div class="nav-sec">Reference</div>

      <a class="nav-a <?= $page === 'sanctions-book' ? 'active' : '' ?>" href="?page=sanctions-book">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 2h10a1 1 0 0 1 1 1v11l-2-1-2 1-2-1-2 1-2-1V3a1 1 0 0 1 1-1z"/><path d="M5.5 6h5M5.5 8.5h5M5.5 11h3"/></svg>
        <span class="nav-label">Sanctions Book</span>
      </a>

      <a class="nav-a <?= $page === 'records-archive' ? 'active' : '' ?>" href="?page=records-archive">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 4h12v2H2zM3.5 6v7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V6"/><path d="M6.5 9h3"/></svg>
        <span class="nav-label">Records Archive</span>
      </a>
    </nav>

    <div class="sb-foot">
      <a class="nav-a <?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings" style="margin-bottom:6px">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="2.5"/><path d="M8 1.5v1.3M8 13.2v1.3M1.5 8h1.3M13.2 8h1.3M3.3 3.3l.9.9M11.8 11.8l.9.9M11.8 3.3l-.9.9M4.2 11.8l-.9.9"/></svg>
        <span class="nav-label">Settings</span>
      </a>
      <div class="user-row">
        <div class="user-av"><?= strtoupper(substr($user['name'] ?? 'BG', 0, 2)) ?></div>
        <div>
          <div class="user-nm"><?= e($user['name'] ?? 'Officer') ?></div>
          <div class="user-rl">Barangay Officer</div>
        </div>
        <a href="../connection/logout.php" class="logout-btn" title="Logout">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5.5 7.5h7M10 5l2.5 2.5L10 10"/><path d="M9 2.5H3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h6"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- ── MAIN AREA ── -->
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <span class="topbar-title"><?= e($titles[$page]) ?></span>
        <span class="topbar-badge"><?= e($bgy['name']) ?></span>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-new-blotter')">+ New Blotter</button>
        <a href="../connection/logout.php" class="btn btn-outline btn-sm">Logout</a>
      </div>
    </div>

    <div class="content">
      <?php include "pages/{$page}.php"; ?>
    </div>
  </div>
</div>

<!-- ══ GLOBAL: New Blotter Modal ══ -->
<div class="modal-overlay" id="modal-new-blotter">
  <div class="modal modal-lg">
    <div class="modal-hdr">
      <span class="modal-title">File New Blotter</span>
      <button class="modal-x" onclick="closeModal('modal-new-blotter')">×</button>
    </div>
    <div class="modal-body">
      <div class="fr3">
        <div class="fg">
          <label>Incident Type <span class="req">*</span></label>
          <select id="nb-type">
            <option value="">— Select —</option>
            <?php foreach (['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Trespassing','Theft / Estafa','Drug-Related','Traffic Incident','Public Disturbance','Other'] as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>Violation Level <span class="req">*</span></label>
          <select id="nb-level">
            <option value="">— Select —</option>
            <option value="minor">Minor</option>
            <option value="moderate">Moderate</option>
            <option value="serious">Serious</option>
            <option value="critical">Critical</option>
          </select>
        </div>
        <div class="fg">
          <label>Incident Date <span class="req">*</span></label>
          <input type="date" id="nb-date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="fr2">
        <div class="fg"><label>Complainant Full Name <span class="req">*</span></label><input type="text" id="nb-comp-name" placeholder="Last, First Middle"></div>
        <div class="fg"><label>Complainant Contact</label><input type="tel" id="nb-comp-contact" placeholder="09XXXXXXXXX"></div>
      </div>
      <div class="fr2">
        <div class="fg"><label>Respondent / Violator Name</label><input type="text" id="nb-resp-name" placeholder="Leave blank if unknown"></div>
        <div class="fg"><label>Respondent Contact</label><input type="tel" id="nb-resp-contact" placeholder="09XXXXXXXXX"></div>
      </div>
      <div class="fg"><label>Incident Location</label><input type="text" id="nb-location" placeholder="Street / Purok / Landmark"></div>
      <div class="fg"><label>Narrative <span class="req">*</span></label><textarea id="nb-narrative" rows="4" placeholder="Describe the incident in detail…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-new-blotter')">Cancel</button>
      <button class="btn btn-primary" onclick="submitNewBlotter()">File Blotter</button>
    </div>
  </div>
</div>

<!-- ══ GLOBAL: Blotter Detail Panel ══ -->
<div class="panel-overlay" id="panel-overlay">
  <div class="slide-panel" id="slide-panel">
    <div class="panel-hdr">
      <div>
        <div class="panel-title" id="panel-case-no">Case Details</div>
        <div id="panel-case-sub" style="font-size:12px;color:var(--ink-400);margin-top:2px"></div>
      </div>
      <button class="panel-x" onclick="closePanel()">×</button>
    </div>
    <div class="panel-body" id="panel-body">
      <div style="text-align:center;padding:40px;color:var(--ink-300)">Loading…</div>
    </div>
  </div>
</div>

<!-- ══ GLOBAL: Loading & Toast ══ -->
<div id="loading-overlay"><div class="spinner"></div></div>
<div id="toast"></div>

<script>
/* ── globals ── */
const BARANGAY_ID = <?= $bid ?>;
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.color = '#6B84A0';

/* ── modal helpers ── */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open'); });

/* ── panel helpers ── */
function openPanel()  { document.getElementById('panel-overlay').classList.add('open'); }
function closePanel() { document.getElementById('panel-overlay').classList.remove('open'); }
document.getElementById('panel-overlay').addEventListener('click', e => {
  if (e.target.id === 'panel-overlay') closePanel();
});

/* ── loading ── */
function loading(s) { document.getElementById('loading-overlay').classList.toggle('show', s); }

/* ── toast ── */
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = type === 'error' ? '#BE123C' : type === 'success' ? '#047857' : '#0D1B2E';
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(t._t);
  t._t = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(10px)';
  }, 3200);
}

/* ── status / level chip maps ── */
const LEVEL_CH = { minor:'ch-emerald', moderate:'ch-amber', serious:'ch-rose', critical:'ch-violet' };
const STATUS_CH = { pending_review:'ch-amber', active:'ch-teal', mediation_set:'ch-navy', resolved:'ch-emerald', closed:'ch-slate', escalated:'ch-rose', transferred:'ch-slate' };
function levelChip(v)  { return `<span class="chip ${LEVEL_CH[v]||'ch-slate'}">${ucw(v)}</span>`; }
function statusChip(v) { return `<span class="chip ${STATUS_CH[v]||'ch-slate'}">${ucw(v.replace(/_/g,' '))}</span>`; }
function ucw(s) { return s ? s.replace(/\b\w/g, c => c.toUpperCase()) : '—'; }

/* ── view blotter (opens panel) ── */
function viewBlotter(id) {
  document.getElementById('panel-case-no').textContent = 'Loading…';
  document.getElementById('panel-case-sub').textContent = '';
  document.getElementById('panel-body').innerHTML = '<div style="text-align:center;padding:40px;color:var(--ink-300)">Loading…</div>';
  openPanel();
  fetch('ajax/get_blotter.php?id=' + id)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { document.getElementById('panel-body').innerHTML = '<p style="color:var(--rose-600);padding:20px">Could not load case.</p>'; return; }
      renderPanel(d.data);
    })
    .catch(() => { document.getElementById('panel-body').innerHTML = '<p style="color:var(--rose-600);padding:20px">Request failed.</p>'; });
}

function renderPanel(b) {
  document.getElementById('panel-case-no').textContent = b.case_number;
  document.getElementById('panel-case-sub').textContent = b.incident_type + ' · ' + b.incident_date;

  const prescribedOpts = ['document_only','mediation','refer_barangay','refer_police','refer_vawc','escalate_municipality']
    .map(v => `<option value="${v}"${b.prescribed_action===v?' selected':''}>${ucw(v.replace(/_/g,' '))}</option>`).join('');

  const statusOpts = ['pending_review','active','mediation_set','escalated','resolved','closed','transferred']
    .map(v => `<option value="${v}"${b.status===v?' selected':''}>${ucw(v.replace(/_/g,' '))}</option>`).join('');

  const timeline = (b.timeline||[]).map(t => `
    <div class="tl-item">
      <div class="tl-dot tl-dot-teal"></div>
      <div>
        <div class="tl-title">${t.action.replace(/_/g,' ')}</div>
        <div class="tl-desc">${t.description||''}</div>
        <div class="tl-time">${t.created_at}</div>
      </div>
    </div>`).join('');

  document.getElementById('panel-body').innerHTML = `
    <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
      ${levelChip(b.violation_level)} ${statusChip(b.status)}
    </div>

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Case Information</span></div>
      <div class="card-body" style="padding:12px 16px">
        <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val">${b.complainant_name||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Contact</span><span class="dr-val">${b.complainant_contact||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val">${b.respondent_name||'Unknown'}</span></div>
        <div class="dr"><span class="dr-lbl">Resp. Contact</span><span class="dr-val">${b.respondent_contact||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Location</span><span class="dr-val">${b.incident_location||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Filed</span><span class="dr-val">${b.created_at?.substring(0,10)||'—'}</span></div>
      </div>
    </div>

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Narrative</span></div>
      <div class="card-body" style="padding:12px 16px">
        <p style="font-size:13px;color:var(--ink-700);line-height:1.75">${b.narrative||'No narrative recorded.'}</p>
      </div>
    </div>

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Update Case</span></div>
      <div class="card-body" style="padding:12px 16px">
        <div class="fr2">
          <div class="fg"><label>Status</label><select id="p-status">${statusOpts}</select></div>
          <div class="fg"><label>Prescribed Action</label><select id="p-action">${prescribedOpts}</select></div>
        </div>
        <div class="fg"><label>Remarks</label><textarea id="p-remarks" rows="2" placeholder="Optional officer remarks…"></textarea></div>
        <button class="btn btn-primary btn-sm" onclick="updateStatus(${b.id})">Save Update</button>
      </div>
    </div>

    ${timeline ? `
    <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.05em;text-transform:uppercase;margin-bottom:10px">Activity Log</div>
    ${timeline}` : ''}
  `;
}

function updateStatus(id) {
  const status  = document.getElementById('p-status').value;
  const action  = document.getElementById('p-action').value;
  const remarks = document.getElementById('p-remarks').value;
  loading(true);
  fetch('ajax/blotter_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'update_status', id, status, prescribed_action: action, remarks })
  })
  .then(r => r.json())
  .then(d => {
    loading(false);
    showToast(d.message, d.success ? 'success' : 'error');
    if (d.success) setTimeout(() => location.reload(), 700);
  })
  .catch(() => { loading(false); showToast('Request failed.', 'error'); });
}

/* ── submit new blotter ── */
function submitNewBlotter() {
  const data = {
    incident_type:      document.getElementById('nb-type').value,
    violation_level:    document.getElementById('nb-level').value,
    incident_date:      document.getElementById('nb-date').value,
    complainant_name:   document.getElementById('nb-comp-name').value.trim(),
    complainant_contact:document.getElementById('nb-comp-contact').value.trim(),
    respondent_name:    document.getElementById('nb-resp-name').value.trim(),
    respondent_contact: document.getElementById('nb-resp-contact').value.trim(),
    incident_location:  document.getElementById('nb-location').value.trim(),
    narrative:          document.getElementById('nb-narrative').value.trim(),
  };
  if (!data.incident_type || !data.violation_level || !data.complainant_name || !data.narrative)
    return showToast('Fill in all required fields.', 'error');
  loading(true);
  fetch('ajax/blotter_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'create', ...data })
  })
  .then(r => r.json())
  .then(d => {
    loading(false);
    closeModal('modal-new-blotter');
    showToast(d.message, d.success ? 'success' : 'error');
    if (d.success) setTimeout(() => location.reload(), 700);
  })
  .catch(() => { loading(false); showToast('Request failed.', 'error'); });
}
</script>
</body>
</html>
