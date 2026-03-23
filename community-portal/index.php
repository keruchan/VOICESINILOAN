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
    // My active blotters (filed by me, not resolved)
    $badge_blotters = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid AND status NOT IN ('resolved','closed','transferred')")->fetchColumn();
    // Cases where I am tagged as violator
    $badge_cases = (int)$pdo->query("SELECT COUNT(*) FROM violations WHERE user_id=$uid")->fetchColumn();
    // Unread notices (acknowledged_at IS NULL = not yet acknowledged)
    $badge_notices = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE recipient_user_id=$uid AND acknowledged_at IS NULL")->fetchColumn();
    // Upcoming mediations for my blotters
    $badge_med = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.complainant_user_id=$uid AND ms.status='scheduled' AND ms.hearing_date>=CURDATE()")->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($titles[$page]) ?> — VOICE Community</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sb-brand">
      <div class="sb-pill"><div class="sb-dot"></div><span>Community Portal</span></div>
      <div class="sb-name">VOICE</div>
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
  document.getElementById('panel-body').innerHTML = '<div style="text-align:center;padding:40px;color:var(--ink-400)">Loading…</div>';
  openPanel();
  fetch('ajax/get_blotter.php?id=' + id)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { document.getElementById('panel-body').innerHTML = '<p style="color:var(--rose-600);padding:20px">Unable to load case.</p>'; return; }
      const b = d.data;
      document.getElementById('panel-case-no').textContent = b.case_number;
      document.getElementById('panel-case-sub').textContent = b.incident_type + ' · ' + b.incident_date;

      const tl = (b.timeline||[]).map(t => `
        <div class="tl-item">
          <div class="tl-dot tl-dot-slate"></div>
          <div>
            <div class="tl-title">${ucw(t.action)}</div>
            <div class="tl-desc">${t.description||''}</div>
            <div class="tl-time">${t.created_at}</div>
          </div>
        </div>`).join('');

      const medSection = b.mediation ? `
        <div class="card mb16" style="border-top:3px solid var(--green-500)">
          <div class="card-hdr"><span class="card-title">📅 Scheduled Mediation</span></div>
          <div class="card-body" style="padding:12px 16px">
            <div class="dr"><span class="dr-lbl">Date</span><span class="dr-val" style="color:var(--green-700);font-weight:700">${new Date(b.mediation.hearing_date+'T00:00').toLocaleDateString('en-PH',{weekday:'short',month:'long',day:'numeric',year:'numeric'})}</span></div>
            <div class="dr"><span class="dr-lbl">Time</span><span class="dr-val">${b.mediation.hearing_time||'TBD'}</span></div>
            <div class="dr"><span class="dr-lbl">Venue</span><span class="dr-val">${b.mediation.venue||'Barangay Hall'}</span></div>
          </div>
        </div>` : '';

      document.getElementById('panel-body').innerHTML = `
        <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
          ${levelChip(b.violation_level)} ${statusChip(b.status)}
        </div>
        <div class="card mb16">
          <div class="card-hdr"><span class="card-title">Case Information</span></div>
          <div class="card-body" style="padding:12px 16px">
            <div class="dr"><span class="dr-lbl">Case No.</span><span class="dr-val" style="font-family:var(--font-mono)">${b.case_number}</span></div>
            <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val">${b.complainant_name}</span></div>
            <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val">${b.respondent_name||'Unknown'}</span></div>
            <div class="dr"><span class="dr-lbl">Location</span><span class="dr-val">${b.incident_location||'—'}</span></div>
            <div class="dr"><span class="dr-lbl">Prescribed Action</span><span class="dr-val">${ucw(b.prescribed_action||'Pending')}</span></div>
            <div class="dr"><span class="dr-lbl">Date Filed</span><span class="dr-val">${b.created_at?.substring(0,10)||'—'}</span></div>
          </div>
        </div>
        <div class="card mb16">
          <div class="card-hdr"><span class="card-title">Narrative</span></div>
          <div class="card-body" style="padding:12px 16px">
            <p style="font-size:13px;color:var(--ink-700);line-height:1.75">${b.narrative||'No narrative recorded.'}</p>
          </div>
        </div>
        ${medSection}
        ${tl ? `<div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.05em;text-transform:uppercase;margin-bottom:10px">ACTIVITY LOG</div>${tl}` : ''}
      `;
    })
    .catch(() => { document.getElementById('panel-body').innerHTML = '<p style="color:var(--rose-600);padding:20px">Request failed.</p>'; });
}
</script>
</body>
</html>
