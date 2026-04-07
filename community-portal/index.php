<?php
// community-portal/index.php
require_once '../connection/auth.php';
guardRole('community');
$user = currentUser(); // keys: id, name, role, barangay_id

$allowed = ['dashboard','my-blotters','file-report','assigned-cases','mediation','notices','history','profile'];
$page    = (isset($_GET['page']) && in_array($_GET['page'], $allowed)) ? $_GET['page'] : 'dashboard';

$titles = [
    'dashboard'      => 'Dashboard',
    'my-blotters'    => 'My Blotters',
    'file-report'    => 'File a Report',
    'assigned-cases' => 'Cases Against Me',
    'mediation'      => 'Mediation Schedule',
    'notices'        => 'Notices & Sanctions',
    'history'        => 'Case History',
    'profile'        => 'My Profile',
];

$uid = (int)$user['id'];
$bid = (int)$user['barangay_id'];

// Barangay name
$bgy_name = 'Your Barangay';
try {
    $s = $pdo->prepare("SELECT name FROM barangays WHERE id = ? LIMIT 1");
    $s->execute([$bid]);
    $bgy_name = $s->fetchColumn() ?: $bgy_name;
} catch (PDOException $e) {}

// Sidebar badge counts
$badge_blotters = $badge_cases = $badge_notices = $badge_med = 0;
try {
    $uname_esc = addslashes($user['name'] ?? '');
    // My active blotters (filed by me, not resolved/closed/dismissed)
    $badge_blotters = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid AND status NOT IN ('resolved','closed','transferred','dismissed')")->fetchColumn();
    // Cases where I am named respondent — respondent_user_id (direct) OR name match (walk-in)
    $badge_cases = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE barangay_id=$bid AND (respondent_user_id=$uid OR (respondent_user_id IS NULL AND respondent_name LIKE '%$uname_esc%')) AND status NOT IN ('resolved','closed','dismissed')")->fetchColumn();
    // Unread notifications — party_notifications table (notices table is empty/unused)
    $badge_notices = (int)$pdo->query("SELECT COUNT(*) FROM party_notifications WHERE recipient_user_id=$uid AND read_at IS NULL AND status IN ('pending','sent')")->fetchColumn();
    // Upcoming mediations — both as complainant AND as respondent
    $badge_med = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.barangay_id=$bid AND ms.status='scheduled' AND ms.hearing_date>=CURDATE() AND (b.complainant_user_id=$uid OR b.respondent_user_id=$uid OR b.respondent_name LIKE '%$uname_esc%')")->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($titles[$page]) ?> — VOICE2 Community</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sb-brand">
      <div class="sb-pill"><div class="sb-dot"></div><span>Community Portal</span></div>
      <div class="sb-name">VOICE2</div>
      <div class="sb-sub">Barangay Blotter System</div>
    </div>

    <div class="user-chip">
      <div class="user-av"><?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?></div>
      <div>
        <div class="user-nm"><?= e($user['name'] ?? 'Resident') ?></div>
        <div class="user-bgy"><?= e($bgy_name) ?></div>
      </div>
    </div>

    <nav>
      <a class="nav-a <?= $page==='dashboard'?'active':'' ?>" href="?page=dashboard">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="1.5" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="1.5" y="9" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="9" width="5.5" height="5.5" rx="1.2"/></svg>
        <span class="nav-lbl">Dashboard</span>
      </a>

      <div class="nav-hr"></div>
      <div class="nav-sec">Blotters</div>

      <a class="nav-a <?= $page==='my-blotters'?'active':'' ?>" href="?page=my-blotters">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="1.5" width="12" height="13" rx="1.5"/><path d="M5 5.5h6M5 8h6M5 10.5h4"/></svg>
        <span class="nav-lbl">My Blotters</span>
        <?php if ($badge_blotters > 0): ?><span class="nav-badge nb-amber"><?= $badge_blotters ?></span><?php endif; ?>
      </a>

      <a class="nav-a <?= $page==='file-report'?'active':'' ?>" href="?page=file-report">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 3v10M3 8h10"/><circle cx="8" cy="8" r="6"/></svg>
        <span class="nav-lbl">File a Report</span>
      </a>

      <div class="nav-hr"></div>
      <div class="nav-sec">My Cases</div>

      <a class="nav-a <?= $page==='assigned-cases'?'active':'' ?>" href="?page=assigned-cases">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="5.5" r="2.5"/><path d="M2 14c0-3 2.5-5 5-5s5 2.2 5 5"/><path d="M11.5 8l1.5 1.5L16 7" stroke-width="1.5"/></svg>
        <span class="nav-lbl">Cases Against Me</span>
        <?php if ($badge_cases > 0): ?><span class="nav-badge nb-rose"><?= $badge_cases ?></span><?php endif; ?>
      </a>

      <a class="nav-a <?= $page==='mediation'?'active':'' ?>" href="?page=mediation">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6.5h12M6 3V1.5M10 3V1.5"/></svg>
        <span class="nav-lbl">Mediation Schedule</span>
        <?php if ($badge_med > 0): ?><span class="nav-badge nb-amber"><?= $badge_med ?></span><?php endif; ?>
      </a>

      <a class="nav-a <?= $page==='notices'?'active':'' ?>" href="?page=notices">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6v4L2 12h12l-1.5-2V6C12.5 3.5 10.5 1.5 8 1.5z"/><path d="M6.5 12a1.5 1.5 0 0 0 3 0"/></svg>
        <span class="nav-lbl">Notices & Sanctions</span>
        <?php if ($badge_notices > 0): ?><span class="nav-badge nb-rose"><?= $badge_notices ?></span><?php endif; ?>
      </a>

      <a class="nav-a <?= $page==='history'?'active':'' ?>" href="?page=history">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 8a6 6 0 1 0 .9-3.2"/><path d="M2 2.5V5.5h3"/><path d="M8 5v3.5l2 2"/></svg>
        <span class="nav-lbl">Case History</span>
      </a>
    </nav>

    <div class="sb-foot">
      <a class="nav-a <?= $page==='profile'?'active':'' ?>" href="?page=profile" style="margin-bottom:4px">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.5"/><path d="M2 14.5c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
        <span class="nav-lbl">My Profile</span>
      </a>
      <a class="logout-row" href="../connection/logout.php">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M6 13.5H3a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h3"/><path d="M10.5 11l3-3-3-3M13.5 8H6"/></svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <span class="topbar-title"><?= e($titles[$page]) ?></span>
        <span class="topbar-badge"><?= e($bgy_name) ?></span>
      </div>
      <div class="topbar-actions">
        <a href="?page=file-report" class="btn btn-primary btn-sm">+ File Report</a>
      </div>
    </div>
    <div class="content">
      <?php include "pages/{$page}.php"; ?>
    </div>
  </div>
</div>

<!-- Global: Blotter Detail Panel -->
<div class="panel-overlay" id="panel-overlay">
  <div class="slide-panel">
    <div class="panel-hdr">
      <div>
        <div class="panel-title" id="panel-case-no">Case Details</div>
        <div id="panel-case-sub" style="font-size:12px;color:var(--ink-500);margin-top:2px"></div>
      </div>
      <button class="panel-x" onclick="closePanel()">×</button>
    </div>
    <div class="panel-body" id="panel-body">
      <div style="text-align:center;padding:40px;color:var(--ink-400)">Loading…</div>
    </div>
  </div>
</div>

<div id="loading-overlay"><div class="spinner"></div></div>
<div id="toast"></div>

<script>
/* Chip maps */
const LM = { minor:'ch-green', moderate:'ch-amber', serious:'ch-rose', critical:'ch-violet' };
const SM = { pending_review:'ch-amber', active:'ch-teal', mediation_set:'ch-navy', resolved:'ch-green', closed:'ch-slate', escalated:'ch-rose', transferred:'ch-slate' };
function ucw(s){ return s ? s.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) : '—'; }
function levelChip(v){ return `<span class="chip ${LM[v]||'ch-slate'}">${ucw(v)}</span>`; }
function statusChip(v){ return `<span class="chip ${SM[v]||'ch-slate'}">${ucw(v)}</span>`; }

function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open'); });

function openPanel()  { document.getElementById('panel-overlay').classList.add('open'); }
function closePanel() { document.getElementById('panel-overlay').classList.remove('open'); }
document.getElementById('panel-overlay').addEventListener('click', e => { if (e.target.id==='panel-overlay') closePanel(); });

function loading(s){ document.getElementById('loading-overlay').classList.toggle('show', s); }

function showToast(msg, type=''){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = type==='error'?'#BE123C':type==='success'?'#15803D':'#1E293B';
  t.style.opacity='1'; t.style.transform='translateX(-50%) translateY(0)';
  clearTimeout(t._t);
  t._t = setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateX(-50%) translateY(10px)'; }, 3200);
}

function viewBlotter(id){
  document.getElementById('panel-case-no').textContent = 'Loading…';
  document.getElementById('panel-case-sub').textContent = '';
  document.getElementById('panel-body').innerHTML = `
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;gap:12px;color:var(--ink-400)">
      <div style="width:32px;height:32px;border:3px solid var(--ink-100);border-top-color:var(--green-500);border-radius:50%;animation:spin .7s linear infinite"></div>
      <span style="font-size:13px">Loading case details…</span>
    </div>`;
  openPanel();

  fetch('ajax/get_blotter.php?id=' + id)
    .then(r => r.json())
    .then(d => {
      if (!d.success) {
        document.getElementById('panel-body').innerHTML = `
          <div style="text-align:center;padding:48px 20px;color:var(--ink-400)">
            <div style="font-size:32px;margin-bottom:12px">⚠️</div>
            <div style="font-size:14px;font-weight:600;color:var(--ink-600);margin-bottom:6px">Unable to load case</div>
            <div style="font-size:13px">${d.message||'Access denied or case not found.'}</div>
          </div>`; return;
      }
      const b = d;

      // ── Header ──
      document.getElementById('panel-case-no').textContent  = b.case_number;
      document.getElementById('panel-case-sub').textContent = (b.other_incident_type || b.incident_type || '') + (b.incident_date ? ' · ' + b.incident_date : '');

      // ── Chips ──
      const chips = `
        <div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
          ${levelChip(b.violation_level)}
          ${statusChip(b.status)}
          ${b.other_incident_type ? `<span class="chip ch-slate">${esc(b.other_incident_type)}</span>` : ''}
        </div>`;

      // ── Case info — two-column grid ──
      const loc = [b.incident_street, b.incident_barangay].filter(Boolean).join(', ') || b.incident_location || '—';
      const filedDate = b.created_at ? new Date(b.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—';
      const filedTime = b.created_at ? new Date(b.created_at).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) : '';

      const caseInfo = `
        <div class="card mb16">
          <div class="card-hdr"><span class="card-title">📋 Case Information</span></div>
          <div class="card-body" style="padding:14px 18px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
              <div class="dr" style="grid-column:1/3">
                <span class="dr-lbl">Case No.</span>
                <span class="dr-val" style="font-family:var(--font-mono);font-size:13px;font-weight:700;color:var(--ink-900)">${esc(b.case_number)}</span>
              </div>
              <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val" style="color:var(--ink-900);font-weight:600">${esc(b.complainant_name||'—')}</span></div>
              <div class="dr"><span class="dr-lbl">Contact</span><span class="dr-val">${esc(b.complainant_contact||'—')}</span></div>
              <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val" style="color:var(--ink-900);font-weight:600">${esc(b.respondent_name||'None identified')}</span></div>
              <div class="dr"><span class="dr-lbl">Resp. Contact</span><span class="dr-val">${esc(b.respondent_contact||'—')}</span></div>
              <div class="dr" style="grid-column:1/3"><span class="dr-lbl">Location</span><span class="dr-val" style="max-width:70%;text-align:right">${esc(loc)}</span></div>
              <div class="dr"><span class="dr-lbl">Prescribed Action</span><span class="dr-val">${ucw(b.prescribed_action||'pending_review')}</span></div>
              <div class="dr">
                <span class="dr-lbl">Date Filed</span>
                <span class="dr-val" style="color:var(--ink-900);font-weight:700">${filedDate}${filedTime ? '<br><span style="font-size:11px;font-weight:400;color:var(--ink-600)">'+filedTime+'</span>' : ''}</span>
              </div>
            </div>
          </div>
        </div>`;

      // ── Narrative ──
      const narrative = `
        <div class="card mb16">
          <div class="card-hdr"><span class="card-title">📝 Narrative</span></div>
          <div class="card-body" style="padding:16px 18px">
            <p style="font-size:13.5px;color:var(--ink-800,#1e293b);line-height:1.85;white-space:pre-wrap;word-break:break-word">${esc(b.narrative||'No narrative recorded.')}</p>
          </div>
        </div>`;

      // ── Mediation ──
      const medSection = b.mediation ? `
        <div class="card mb16" style="border-top:3px solid var(--green-500)">
          <div class="card-hdr"><span class="card-title">📅 Scheduled Mediation</span></div>
          <div class="card-body" style="padding:14px 18px">
            <div style="background:var(--green-50);border:1px solid var(--green-100);border-radius:var(--r-md);padding:12px 14px;margin-bottom:10px">
              <div style="font-size:17px;font-weight:700;color:var(--green-700)">${new Date(b.mediation.hearing_date+'T00:00').toLocaleDateString('en-PH',{weekday:'long',month:'long',day:'numeric',year:'numeric'})}</div>
              ${b.mediation.hearing_time ? `<div style="font-size:14px;color:var(--green-600);margin-top:4px">⏰ ${b.mediation.hearing_time}</div>` : ''}
              <div style="font-size:12px;color:var(--ink-500);margin-top:4px">📍 ${esc(b.mediation.venue||'Barangay Hall')}</div>
            </div>
          </div>
        </div>` : '';

      // ── Attachments ──
      let attachSection = '';
      if (b.attachments && b.attachments.length > 0) {
        const thumbs = b.attachments.map(a => {
          const url = '../../' + a.file_path;
          const kb  = a.file_size ? Math.round(a.file_size/1024) + ' KB' : '';
          return `
            <a href="${url}" target="_blank" title="${esc(a.original_name)}" style="display:block;position:relative;border-radius:var(--r-md);overflow:hidden;border:1px solid var(--ink-100);aspect-ratio:1;background:var(--surface-2)">
              <img src="${url}" alt="${esc(a.original_name)}" style="width:100%;height:100%;object-fit:cover;display:block">
              <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);color:#fff;font-size:10px;padding:3px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(a.original_name)}</div>
            </a>`;
        }).join('');
        attachSection = `
          <div class="card mb16">
            <div class="card-hdr">
              <span class="card-title">📎 Attachments</span>
              <span style="font-size:11px;color:var(--ink-400)">${b.attachments.length} photo(s)</span>
            </div>
            <div class="card-body" style="padding:14px 18px">
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px">${thumbs}</div>
            </div>
          </div>`;
      }

      // ── Map (only if lat/lng exist) ──
      let mapSection = '';
      const lat = parseFloat(b.incident_lat);
      const lng = parseFloat(b.incident_lng);
      if (!isNaN(lat) && !isNaN(lng)) {
        mapSection = `
          <div class="card mb16">
            <div class="card-hdr">
              <span class="card-title">📍 Incident Location</span>
              <a href="https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}" target="_blank" style="font-size:11px;color:var(--green-600);font-weight:600;text-decoration:none">Open in Maps ↗</a>
            </div>
            <div class="card-body" style="padding:0">
              <div id="view-map-${id}" style="height:220px;width:100%"></div>
            </div>
            <div style="padding:10px 18px;background:var(--surface);border-top:1px solid var(--surface-2);font-size:11px;color:var(--ink-400);font-family:var(--font-mono)">
              ${lat.toFixed(6)}, ${lng.toFixed(6)}
            </div>
          </div>`;
      }

      // ── Timeline ──
      const timeline = (b.timeline||[]).map(t => `
        <div class="tl-item">
          <div class="tl-dot tl-dot-green"></div>
          <div>
            <div class="tl-title">${ucw(t.action)}</div>
            <div class="tl-desc">${esc(t.description||'')}</div>
            <div class="tl-time">${t.created_at}</div>
          </div>
        </div>`).join('');

      // ── Render ──
      document.getElementById('panel-body').innerHTML =
        chips + caseInfo + narrative + medSection + attachSection + mapSection +
        (timeline ? `<div style="font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px">ACTIVITY LOG</div>${timeline}` : '');

      // ── Init map after render ──
      if (!isNaN(lat) && !isNaN(lng)) {
        setTimeout(() => initViewMap(id, lat, lng), 80);
      }
    })
    .catch(err => {
      document.getElementById('panel-body').innerHTML = `
        <div style="text-align:center;padding:48px 20px;color:var(--ink-400)">
          <div style="font-size:32px;margin-bottom:12px">❌</div>
          <div style="font-size:14px;font-weight:600;color:var(--ink-600);margin-bottom:6px">Request failed</div>
          <div style="font-size:12px">Check your connection and try again.</div>
        </div>`;
    });
}

// ── Leaflet map for view panel ──
const _viewMaps = {};
function initViewMap(id, lat, lng) {
  const el = document.getElementById('view-map-' + id);
  if (!el || _viewMaps[id]) return;
  if (typeof L === 'undefined') {
    // Load Leaflet on demand
    const css = document.createElement('link');
    css.rel='stylesheet'; css.href='https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
    document.head.appendChild(css);
    const js = document.createElement('script');
    js.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
    js.onload = () => buildViewMap(id, lat, lng, el);
    document.head.appendChild(js);
  } else {
    buildViewMap(id, lat, lng, el);
  }
}
function buildViewMap(id, lat, lng, el) {
  if (_viewMaps[id]) return;
  const m = L.map(el, { zoomControl:true, scrollWheelZoom:false }).setView([lat,lng], 16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom:19
  }).addTo(m);
  L.marker([lat,lng]).addTo(m).bindPopup('Incident location').openPopup();
  _viewMaps[id] = m;
}

// ── Helper: HTML-escape ──
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>
