<?php
// community-portal/index.php
require_once '../connection/auth.php';
guardRole('community');
$user = currentUser(); // keys: id, name, role, barangay_id

$allowed = ['dashboard','my-blotters','file-report','assigned-cases','mediation','sanctions','history','profile'];
$requested_page = $_GET['page'] ?? 'dashboard';
if ($requested_page === 'notices') $requested_page = 'sanctions';
$page = in_array($requested_page, $allowed) ? $requested_page : 'dashboard';

$titles = [
    'dashboard'      => 'Dashboard',
    'my-blotters'    => 'My Blotters',
    'file-report'    => 'File a Report',
    'assigned-cases' => 'Cases Against Me',
    'mediation'      => 'Mediation Schedule',
    'sanctions'      => 'Sanctions & Penalties',
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
$badge_blotters = $badge_cases = $badge_med = $badge_sanctions = 0;
try {
    $uname = trim($user['name'] ?? '');
    $name_parts = array_filter(preg_split('/[\s,]+/', $uname), fn($p) => strlen($p) > 2);
    $name_likes = [];
    foreach ($name_parts as $part) {
        $name_likes[] = 'b.respondent_name LIKE ' . $pdo->quote('%' . $part . '%');
    }
    $name_match_sql = $name_likes ? '(' . implode(' AND ', $name_likes) . ')' : '1=0';
    $respondent_scope_sql = "(b.respondent_user_id=$uid OR (b.respondent_user_id IS NULL AND $name_match_sql))";

    // All blotters filed by me
    $badge_blotters = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid")->fetchColumn();
    // Cases where I am named respondent — respondent_user_id (direct) OR name match (walk-in)
    $badge_cases = (int)$pdo->query("SELECT COUNT(*) FROM blotters b WHERE b.barangay_id=$bid AND $respondent_scope_sql AND b.status NOT IN ('resolved','closed','dismissed')")->fetchColumn();
    // Upcoming mediations — both as complainant AND as respondent
    $badge_med = (int)$pdo->query("SELECT COUNT(DISTINCT ms.id) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.barangay_id=$bid AND ms.status='scheduled' AND ms.hearing_date>=CURDATE() AND (b.complainant_user_id=$uid OR $respondent_scope_sql)")->fetchColumn();

    // Sanctions shown in Sanctions & Penalties: formal penalties + event-derived sanctions.
    $badge_penalties = (int)$pdo->query("
        SELECT COUNT(DISTINCT p.id)
        FROM penalties p
        JOIN blotters b ON b.id = p.blotter_id
        WHERE b.barangay_id = $bid
          AND (
            (p.missed_party IN ('respondent','both') AND $respondent_scope_sql)
            OR (p.missed_party IN ('complainant','both') AND b.complainant_user_id = $uid)
          )
    ")->fetchColumn();
    $badge_event_sanctions = (int)$pdo->query("
        SELECT COUNT(*) FROM (
            SELECT CONCAT('missed_resp:', ms.id) AS sanction_key
            FROM mediation_schedules ms
            JOIN blotters b ON b.id = ms.blotter_id
            WHERE b.barangay_id = $bid
              AND ms.missed_session = 1
              AND ms.no_show_by IN ('respondent','both')
              AND $respondent_scope_sql
            UNION
            SELECT CONCAT('missed_comp:', ms.id) AS sanction_key
            FROM mediation_schedules ms
            JOIN blotters b ON b.id = ms.blotter_id
            WHERE b.barangay_id = $bid
              AND b.complainant_user_id = $uid
              AND ms.missed_session = 1
              AND ms.no_show_by IN ('complainant','both')
            UNION
            SELECT CONCAT('referred_resp:', b.id) AS sanction_key
            FROM blotters b
            WHERE b.barangay_id = $bid
              AND $respondent_scope_sql
              AND (
                b.prescribed_action IN ('refer_police','refer_vawc','escalate_municipality')
                OR b.status IN ('escalated','cfa_issued','transferred')
              )
            UNION
            SELECT CONCAT('referred_comp:', b.id) AS sanction_key
            FROM blotters b
            WHERE b.barangay_id = $bid
              AND b.complainant_user_id = $uid
              AND (
                b.prescribed_action IN ('refer_police','refer_vawc','escalate_municipality')
                OR b.status IN ('escalated','cfa_issued','transferred')
              )
            UNION
            SELECT CONCAT('dismissed_comp:', b.id) AS sanction_key
            FROM blotters b
            WHERE b.barangay_id = $bid
              AND b.complainant_user_id = $uid
              AND b.status = 'dismissed'
            UNION
            SELECT CONCAT('dismissed_resp:', b.id) AS sanction_key
            FROM blotters b
            WHERE b.barangay_id = $bid
              AND $respondent_scope_sql
              AND b.status = 'dismissed'
        ) sanctions
    ")->fetchColumn();
    $badge_sanctions = $badge_penalties + $badge_event_sanctions;
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($titles[$page]) ?> — VOICE Community</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,700;1,9..144,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <a href="../index.php" class="sb-brand sb-brand-link" title="Back to landing page">
      <div class="sb-pill"><div class="sb-dot"></div><span>Community Portal</span></div>
      <div class="sb-name">VOICE</div>
      <div class="sb-sub">Barangay Blotter System</div>
    </a>

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

      <a class="nav-a <?= $page==='sanctions'?'active':'' ?>" href="?page=sanctions">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 1.5 13.5 4v4.2c0 3.1-2.2 5.4-5.5 6.3-3.3-.9-5.5-3.2-5.5-6.3V4L8 1.5z"/><path d="M5.5 7.5h5M6.5 10h3"/></svg>
        <span class="nav-lbl">Sanctions &amp; Penalties</span>
        <?php if ($badge_sanctions > 0): ?><span class="nav-badge nb-amber" title="Sanctions"><?= $badge_sanctions ?></span><?php endif; ?>
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
        <button class="hamburger" type="button" onclick="toggleSidebar()" aria-label="Toggle menu"><span></span></button>
        <span class="topbar-title"><?= e($titles[$page]) ?></span>
        <span class="topbar-badge"><?= e($bgy_name) ?></span>
      </div>
      <div class="topbar-actions">
        <div class="notif-bell-wrap">
          <button class="notif-bell-btn" type="button" onclick="toggleNotifDropdown()" aria-label="Notifications">
            <svg width="19" height="19" viewBox="0 0 19 19" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9.5 3c-2.5 0-4.2 2-4.2 4.5v2.3c0 .7-.3 1.4-.8 1.9l-.7.8c-.5.5-.1 1.4.6 1.4h10.2c.7 0 1.1-.9.6-1.4l-.7-.8c-.5-.5-.8-1.2-.8-1.9V7.5C13.7 5 12 3 9.5 3z"/>
              <path d="M7.8 15.5a1.7 1.7 0 0 0 3.4 0"/>
            </svg>
            <span class="notif-bell-badge" id="notif-bell-badge" style="display:none">0</span>
          </button>
          <div class="notif-dropdown" id="notif-dropdown">
            <div class="notif-dropdown-hdr">
              <span>Notifications</span>
              <button type="button" onclick="markAllNotifRead()">Mark all read</button>
            </div>
            <div class="notif-dropdown-list" id="notif-dropdown-list">
              <div class="notif-dd-empty">Loading…</div>
            </div>
          </div>
        </div>
        <a href="?page=profile" class="topbar-user-chip">
          <div class="topbar-user-av"><?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?></div>
          <span class="topbar-user-name"><?= e($user['name'] ?? 'Resident') ?></span>
        </a>
      </div>
    </div>
    <div class="content">
      <?php
        $include_page = $page === 'sanctions' ? 'notices' : $page;
        include "pages/{$include_page}.php";
      ?>
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

/* ── liveFilter: instant AJAX search/filter for list pages ──────────────────
   Wires a filter <form> + result container so typing/selecting refreshes the
   results in place (debounced text input, immediate on select change) instead
   of a full page reload via a Filter/Search submit button. */
function liveFilter(opts) {
  const form = document.querySelector(opts.form);
  const result = document.querySelector(opts.result);
  if (!form || !result) return null;
  let timer;
  const debounceMs = opts.debounceMs || 300;
  const pageParam = opts.pageParam || 'pg';

  function buildParams(overrides) {
    const params = new URLSearchParams(new FormData(form));
    if (overrides) Object.keys(overrides).forEach(k => {
      if (overrides[k] === null || overrides[k] === '') params.delete(k);
      else params.set(k, overrides[k]);
    });
    return params;
  }
  function applyToForm(overrides) {
    if (!overrides) return;
    Object.keys(overrides).forEach(k => {
      const el = form.elements.namedItem(k);
      if (el) el.value = overrides[k];
    });
  }
  function refresh(overrides) {
    applyToForm(overrides);
    const params = buildParams(overrides);
    result.style.opacity = '0.45';
    fetch(opts.endpoint + '?' + params.toString())
      .then(r => r.text())
      .then(html => {
        result.innerHTML = html;
        result.style.opacity = '';
        const url = new URL(window.location.href);
        url.search = params.toString();
        history.replaceState(null, '', url);
        if (opts.afterRender) opts.afterRender();
      })
      .catch(() => { result.style.opacity = ''; showToast('Search failed. Try again.', 'error'); });
  }

  form.addEventListener('submit', e => e.preventDefault());
  form.querySelectorAll('input[type="search"], input[type="text"]').forEach(el => {
    el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => refresh({ [pageParam]: 1 }), debounceMs); });
  });
  form.querySelectorAll('select').forEach(el => {
    el.addEventListener('change', () => refresh({ [pageParam]: 1 }));
  });

  function handleNavClick(e) {
    const a = e.target.closest('a[data-lf]');
    if (!a) return;
    e.preventDefault();
    const u = new URL(a.href, window.location.href);
    const overrides = {};
    u.searchParams.forEach((v, k) => { overrides[k] = v; });
    if (!(pageParam in overrides)) overrides[pageParam] = 1;
    const nav = a.closest('[data-lf-group]');
    if (nav) nav.querySelectorAll('a').forEach(x => x.classList.remove('active'));
    a.classList.add('active');
    refresh(overrides);
  }
  result.addEventListener('click', handleNavClick);
  if (opts.navSelectors) opts.navSelectors.forEach(sel => {
    document.querySelectorAll(sel).forEach(el => el.addEventListener('click', handleNavClick));
  });

  if (opts.clearBtn) {
    const btn = document.querySelector(opts.clearBtn);
    if (btn) btn.addEventListener('click', e => {
      e.preventDefault();
      form.querySelectorAll('input[type="search"],input[type="text"]').forEach(el => el.value = '');
      form.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
      const resetOverrides = Object.assign({}, opts.resetOverrides || {}, { [pageParam]: 1 });
      refresh(resetOverrides);
    });
  }

  return { refresh };
}

function viewBlotter(id){
  document.getElementById('panel-case-no').textContent = 'Loading…';
  document.getElementById('panel-case-sub').textContent = '';
  document.getElementById('panel-body').innerHTML = `
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;gap:12px;color:var(--ink-400)">
      <div style="width:32px;height:32px;border:3px solid var(--ink-100);border-top-color:var(--teal-500);border-radius:50%;animation:spin .7s linear infinite"></div>
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
        <div class="case-view-top">
          ${levelChip(b.violation_level)}
          ${statusChip(b.status)}
          ${b.other_incident_type ? `<span class="chip ch-slate">${esc(b.other_incident_type)}</span>` : ''}
        </div>`;

      // ── Case info — two-column grid ──
      const loc = [b.incident_street, b.incident_barangay].filter(Boolean).join(', ') || b.incident_location || '—';
      const filedDate = b.created_at ? new Date(b.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—';
      const filedTime = b.created_at ? new Date(b.created_at).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) : '';

      const caseInfo = `
        <div class="card mb16 case-view-card">
          <div class="card-hdr"><span class="card-title">Case Information</span></div>
          <div class="card-body" style="padding:14px 18px">
            <div class="case-detail-grid case-detail-grid-tight" style="margin-bottom:0">
              <div class="dr detail-full">
                <span class="dr-lbl">Case No.</span>
                <span class="dr-val" style="font-family:var(--font-mono);font-size:13px;font-weight:700;color:var(--ink-900)">${esc(b.case_number)}</span>
              </div>
              <div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val" style="color:var(--ink-900);font-weight:600">${esc(b.complainant_name||'—')}</span></div>
              <div class="dr"><span class="dr-lbl">Contact</span><span class="dr-val">${esc(b.complainant_contact||'—')}</span></div>
              <div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val" style="color:var(--ink-900);font-weight:600">${esc(b.respondent_name||'None identified')}</span></div>
              <div class="dr"><span class="dr-lbl">Resp. Contact</span><span class="dr-val">${esc(b.respondent_contact||'—')}</span></div>
              <div class="dr detail-full"><span class="dr-lbl">Location</span><span class="dr-val">${esc(loc)}</span></div>
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
        <div class="card mb16 case-view-readonly">
          <div class="card-hdr"><span class="card-title">Narrative</span></div>
          <div class="card-body" style="padding:16px 18px">
            <p class="case-view-text">${esc(b.narrative||'No narrative recorded.')}</p>
          </div>
        </div>`;

      // ── Mediation ──
      const medSection = b.mediation ? `
        <div class="card mb16 case-view-card">
          <div class="card-hdr"><span class="card-title">Scheduled Mediation</span></div>
          <div class="card-body" style="padding:14px 18px">
            <div style="background:var(--teal-50);border:1px solid var(--teal-100);border-radius:var(--r-md);padding:12px 14px;margin-bottom:10px">
              <div style="font-size:17px;font-weight:700;color:var(--teal-700)">${new Date(b.mediation.hearing_date+'T00:00').toLocaleDateString('en-PH',{weekday:'long',month:'long',day:'numeric',year:'numeric'})}</div>
              ${b.mediation.hearing_time ? `<div style="font-size:14px;color:var(--teal-600);margin-top:4px">⏰ ${b.mediation.hearing_time}</div>` : ''}
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
            <a href="${url}" target="_blank" title="${esc(a.original_name)}" class="case-view-attachment">
              <img src="${url}" alt="${esc(a.original_name)}">
              <div class="case-view-attachment-name">${esc(a.original_name)}</div>
            </a>`;
        }).join('');
        attachSection = `
          <div class="card mb16 case-view-readonly">
            <div class="card-hdr">
              <span class="card-title">Attachments</span>
              <span style="font-size:11px;color:var(--ink-400)">${b.attachments.length} photo(s)</span>
            </div>
            <div class="card-body" style="padding:14px 18px">
              <div class="case-view-attachments">${thumbs}</div>
            </div>
          </div>`;
      }

      // ── Map (only if lat/lng exist) ──
      let mapSection = '';
      const lat = parseFloat(b.incident_lat);
      const lng = parseFloat(b.incident_lng);
      if (!isNaN(lat) && !isNaN(lng)) {
        mapSection = `
          <div class="card mb16 case-view-readonly">
            <div class="card-hdr">
              <span class="card-title">Incident Location</span>
              <a href="https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}" target="_blank" style="font-size:11px;color:var(--teal-600);font-weight:600;text-decoration:none">Open in Maps ↗</a>
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

// ══════════════════════════════════════════════════════════════
// Notification bell — unread count, recent list, read more, delete
// ══════════════════════════════════════════════════════════════
const NOTIF_ICONS = {
  hearing_scheduled:'📅', hearing_reminder:'🔔', hearing_rescheduled:'📅',
  no_show_warning:'⚠️', case_dismissed:'📋', cfa_issued:'⚖️',
  mediation_completed:'✅', mediation_cancelled:'❌', case_escalated:'🚨',
  settlement_repudiated:'📜', settlement_final:'📜', general:'📄',
};

let notifOpen = false;
let notifOffset = 0;
const NOTIF_PAGE = 8;

function toggleNotifDropdown() {
  notifOpen = !notifOpen;
  document.getElementById('notif-dropdown').classList.toggle('open', notifOpen);
  if (notifOpen) loadNotifDropdown(true);
}
document.addEventListener('click', function (e) {
  const wrap = document.querySelector('.notif-bell-wrap');
  if (notifOpen && wrap && !wrap.contains(e.target)) {
    notifOpen = false;
    document.getElementById('notif-dropdown').classList.remove('open');
  }
});

function notifTimeAgo(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr.replace(' ', 'T'));
  const diff = Math.floor((Date.now() - d.getTime()) / 1000);
  if (diff < 5) return 'Just now';
  if (diff < 60) return diff + 's ago';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
  return d.toLocaleDateString('en-PH', { month:'short', day:'numeric', year: d.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined });
}

function notifFullTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr.replace(' ', 'T'));
  return d.toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
}

function refreshNotifBadge() {
  fetch('ajax/notifications_action.php?action=count')
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      const badge = document.getElementById('notif-bell-badge');
      if (d.unread > 0) { badge.textContent = d.unread > 99 ? '99+' : d.unread; badge.style.display = ''; }
      else badge.style.display = 'none';
    })
    .catch(() => {});
}

function loadNotifDropdown(reset) {
  if (reset) notifOffset = 0;
  const list = document.getElementById('notif-dropdown-list');
  fetch('ajax/notifications_action.php?action=list&offset=' + notifOffset + '&limit=' + NOTIF_PAGE)
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      if (reset) list.innerHTML = '';
      const existingMore = document.getElementById('notif-load-more');
      if (existingMore) existingMore.remove();

      if (!d.notifications.length && reset) {
        list.innerHTML = '<div class="notif-dd-empty">No notifications yet</div>';
        return;
      }
      d.notifications.forEach(n => list.insertAdjacentHTML('beforeend', renderNotifItem(n)));
      notifOffset += d.notifications.length;
      if (d.has_more) {
        list.insertAdjacentHTML('beforeend', '<button type="button" id="notif-load-more" class="notif-load-more-btn" onclick="loadNotifDropdown(false)">Load more</button>');
      }
    })
    .catch(() => { if (reset) list.innerHTML = '<div class="notif-dd-empty">Could not load notifications.</div>'; });
}

function renderNotifItem(n) {
  const icon = NOTIF_ICONS[n.notification_type] || '??';
  const isUnread = String(n.is_unread ?? (n.status !== 'read' ? 1 : 0)) === '1';
  const msg = n.message || '';
  const isLong = msg.length > 90;
  const shortMsg = isLong ? msg.substring(0, 90) + '...' : msg;
  const viewLink = n.blotter_id
    ? `<span class="notif-item-viewcase" onclick="goToNotifCase(${n.id}, ${n.blotter_id})">View Case -></span>`
    : '';
  return `
  <div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${n.id}" data-unread="${isUnread ? '1' : '0'}">
    <div class="notif-item-icon">${icon}</div>
    <div class="notif-item-body">
      <div class="notif-item-subject" onclick="markNotifRead(${n.id})">${esc(n.subject || 'Notice')}</div>
      <div class="notif-item-msg">
        <span class="notif-msg-text" data-full="${esc(msg)}" data-short="${esc(shortMsg)}">${esc(shortMsg)}</span>
        ${isLong ? `<span class="notif-item-readmore" onclick="toggleReadMore(this, ${n.id})">Read more</span>` : ''}
      </div>
      <div class="notif-item-foot">
        <span class="notif-item-time" title="${esc(notifFullTime(n.created_at))}">${n.case_number ? esc(n.case_number) + ' - ' : ''}${notifTimeAgo(n.created_at)}</span>
        <span class="notif-item-actions">
          ${viewLink}
          <span class="notif-read-toggle" onclick="toggleNotifRead(${n.id})">${isUnread ? 'Mark read' : 'Mark unread'}</span>
        </span>
      </div>
    </div>
    <button type="button" class="notif-item-del" onclick="deleteNotif(event, ${n.id})" title="Delete notification">x</button>
  </div>`;
}

function toggleReadMore(el, id) {
  const msgSpan = el.previousElementSibling;
  const expanded = el.dataset.expanded === '1';
  msgSpan.textContent = expanded ? msgSpan.dataset.short : msgSpan.dataset.full;
  el.textContent = expanded ? 'Read more' : 'Show less';
  el.dataset.expanded = expanded ? '0' : '1';
  markNotifRead(id);
}

function setNotifReadState(id, isUnread) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el) return;
  el.classList.toggle('unread', isUnread);
  el.dataset.unread = isUnread ? '1' : '0';
  const toggle = el.querySelector('.notif-read-toggle');
  if (toggle) toggle.textContent = isUnread ? 'Mark read' : 'Mark unread';
}

function markNotifRead(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el || el.dataset.unread !== '1') return;
  setNotifReadState(id, false);
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_read', id }) })
    .then(() => refreshNotifBadge())
    .catch(() => { setNotifReadState(id, true); showToast('Could not mark as read.', 'error'); });
}

function markNotifUnread(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el || el.dataset.unread === '1') return;
  setNotifReadState(id, true);
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_unread', id }) })
    .then(() => refreshNotifBadge())
    .catch(() => { setNotifReadState(id, false); showToast('Could not mark as unread.', 'error'); });
}

function toggleNotifRead(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el) return;
  if (el.dataset.unread === '1') markNotifRead(id);
  else markNotifUnread(id);
}

function markAllNotifRead() {
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_all_read' }) })
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      document.querySelectorAll('.notif-item').forEach(el => { el.classList.remove('unread'); el.dataset.unread = '0'; const t = el.querySelector('.notif-read-toggle'); if (t) t.textContent = 'Mark unread'; });
      refreshNotifBadge();
      showToast('All notifications marked as read.', 'success');
    })
    .catch(() => {});
}

function deleteNotif(evt, id) {
  evt.stopPropagation();
  if (!confirm('Delete this notification? This cannot be undone.')) return;
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', id }) })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        const el = document.querySelector('.notif-item[data-id="' + id + '"]');
        if (el) el.remove();
        refreshNotifBadge();
        const list = document.getElementById('notif-dropdown-list');
        if (list && !list.querySelector('.notif-item')) list.innerHTML = '<div class="notif-dd-empty">No notifications yet</div>';
      } else {
        showToast(d.message, 'error');
      }
    })
    .catch(() => showToast('Request failed.', 'error'));
}

// Routes correctly: opens the related case when the notification has one.
function goToNotifCase(id, blotterId) {
  markNotifRead(id);
  notifOpen = false;
  document.getElementById('notif-dropdown').classList.remove('open');
  if (blotterId) viewBlotter(blotterId);
}

refreshNotifBadge();
setInterval(refreshNotifBadge, 60000);
</script>
<div class="sb-overlay" onclick="toggleSidebar()"></div>
<script>
function toggleSidebar(){
  document.querySelector('.sidebar').classList.toggle('open');
  document.querySelector('.sb-overlay').classList.toggle('show');
}
</script>
</body>
</html>
