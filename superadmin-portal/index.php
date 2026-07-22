<?php
/**
 * superadmin-portal/index.php
 * Main shell — sidebar + topbar + page routing
 */
require_once '../connection/auth.php';
guardRole('superadmin');

$user = currentUser();

// Page routing
$allowed_pages = ['dashboard','users','barangays','reports','settings'];
$page = isset($_GET['page']) && in_array($_GET['page'], $allowed_pages)
      ? $_GET['page']
      : 'dashboard';

$page_titles = [
    'dashboard' => 'Dashboard',
    'users'     => 'User Management',
    'barangays' => 'Barangay Management',
    'reports'   => 'Reports & Analytics',
    'settings'  => 'Settings',
];
$current_title = $page_titles[$page];
// Notification bell data (insights + discrete notifications) is now served
// live by ajax/notifications_action.php — see the topbar bell below.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($current_title) ?> — VOICE Superadmin</title>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Playfair+Display:wght@700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<div class="app">

  <!-- ══════════ SIDEBAR ══════════ -->
  <aside class="sidebar">
    <a href="../index.php" class="sidebar-brand sidebar-brand-link" title="Back to landing page">
      <div class="brand-label"><div class="brand-pulse"></div>Superadmin Portal</div>
      <div class="brand-name">VOICE</div>
      <div class="brand-sub">Municipality-wide Oversight</div>
    </a>

    <div class="muni-chip">
      <div class="muni-icon">SL</div>
      <div>
        <div class="muni-name">Siniloan</div>
        <div class="muni-role">Municipality Administrator</div>
      </div>
    </div>

    <nav>
      <a class="nav-item <?= $page==='dashboard'?'active':'' ?>" href="?page=dashboard">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="1.5" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="1.5" y="9" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="9" width="5.5" height="5.5" rx="1.2"/></svg>
        <span class="nav-label">Dashboard</span>
      </a>

      <div class="nav-div"></div>
      <div class="nav-group">Management</div>

      <a class="nav-item <?= $page==='barangays'?'active':'' ?>" href="?page=barangays">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 14V7l6-5 6 5v7"/><path d="M6 14v-4h4v4"/></svg>
        <span class="nav-label">Barangays</span>
        <?php
          try {
            $cnt = $pdo->query("SELECT COUNT(*) FROM barangays WHERE is_active=1")->fetchColumn();
            if ($cnt) echo '<span class="nav-badge nb-indigo">'.(int)$cnt.'</span>';
          } catch(Exception $e){}
        ?>
      </a>

      <a class="nav-item <?= $page==='users'?'active':'' ?>" href="?page=users">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="5" r="2.5"/><path d="M1 13c0-2.8 2.2-5 5-5"/><circle cx="11.5" cy="6" r="2"/><path d="M9 14c0-2.2 1.1-4 2.5-4s2.5 1.8 2.5 4"/></svg>
        <span class="nav-label">User Management</span>
        <?php
          try {
            $pnd = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='community'")->fetchColumn();
            if ($pnd) echo '<span class="nav-badge nb-amber">'.(int)$pnd.'</span>';
          } catch(Exception $e){}
        ?>
      </a>

      <div class="nav-div"></div>
      <div class="nav-group">Analytics</div>

      <a class="nav-item <?= $page==='reports'?'active':'' ?>" href="?page=reports">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="2" width="12" height="12" rx="1.5"/><path d="M5 10V8M8 10V6M11 10V4"/></svg>
        <span class="nav-label">Reports & Analytics</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <a class="nav-item <?= $page==='settings'?'active':'' ?>" href="?page=settings" style="margin-bottom:6px">
        <svg class="nav-ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="2.5"/><path d="M8 1.5v1.3M8 13.2v1.3M1.5 8h1.3M13.2 8h1.3M3.3 3.3l.9.9M11.8 11.8l.9.9M11.8 3.3l-.9.9M4.2 11.8l-.9.9"/></svg>
        <span class="nav-label">Settings</span>
      </a>
      <div class="user-row">
        <div class="user-av"><?= strtoupper(substr($user['name'] ?? 'SA', 0, 2)) ?></div>
        <div>
          <div class="user-n"><?= htmlspecialchars($user['name'] ?? 'Superadmin') ?></div>
          <div class="user-r">System Administrator</div>
        </div>
        <a href="../connection/logout.php" class="logout-btn" title="Logout">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5.5 7.5h7M10 5l2.5 2.5L10 10"/><path d="M9 2.5H3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h6"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- ══════════ MAIN ══════════ -->
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <button class="hamburger" type="button" onclick="toggleSidebar()" aria-label="Toggle menu"><span></span></button>
        <span class="topbar-title"><?= htmlspecialchars($current_title) ?></span>
        <span class="topbar-crumb">Siniloan · Superadmin</span>
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
        <a href="?page=settings" class="topbar-user-chip">
          <div class="topbar-user-av"><?= strtoupper(substr($user['name'] ?? 'SA', 0, 2)) ?></div>
          <span class="topbar-user-name"><?= htmlspecialchars($user['name'] ?? 'Superadmin') ?></span>
        </a>
      </div>
    </div>

    <div class="content">
      <?php include "pages/{$page}.php"; ?>
    </div>
  </div>

</div><!-- /app -->

<!-- Export Preview Modal -->
<div class="modal-overlay" id="modal-export-preview">
  <div class="modal modal-lg" style="max-width:920px;width:95vw;max-height:92vh;display:flex;flex-direction:column;padding:0;overflow:hidden">
    <div class="modal-hdr" style="flex-shrink:0">
      <span class="modal-title" id="export-preview-title">Export Preview</span>
      <button class="modal-x" onclick="closeModal('modal-export-preview')">&#x2715;</button>
    </div>
    <div class="modal-body" id="export-preview-body" style="overflow:auto;padding:18px 20px;max-height:68vh"></div>
    <div class="modal-foot" style="flex-shrink:0;justify-content:space-between">
      <button class="btn btn-ghost" onclick="closeModal('modal-export-preview')">Close</button>
      <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
        <button class="btn btn-outline" type="button" onclick="printExportPreview()">Print / Save PDF</button>
        <a class="btn btn-primary" id="export-preview-download" href="#">Download CSV</a>
      </div>
    </div>
  </div>
</div>

<!-- Loading overlay -->
<div id="loading-overlay"><div class="spinner"></div></div>

<!-- Toast -->
<div id="toast"></div>

<script>
function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  if (type==='error') t.style.background='#BE123C';
  else if (type==='success') t.style.background='#047857';
  else t.style.background='#0D1117';
  t.style.opacity='1'; t.style.transform='translateX(-50%) translateY(0)';
  clearTimeout(t._t);
  t._t = setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(-50%) translateY(10px)';t.style.background='';},3200);
}
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('open'); });
function loading(show) { document.getElementById('loading-overlay').classList.toggle('show',show); }

let exportPreviewHtml = '';
function epEsc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function showExportPreview(url, fallbackTitle) {
  const modal = document.getElementById('modal-export-preview');
  const title = document.getElementById('export-preview-title');
  const body = document.getElementById('export-preview-body');
  const dl = document.getElementById('export-preview-download');
  if (!modal || !body || !dl) {
    window.location = url;
    return;
  }
  const previewUrl = new URL(url, window.location.href);
  previewUrl.searchParams.set('preview', '1');
  title.textContent = fallbackTitle || 'Export Preview';
  body.innerHTML = '<div style="text-align:center;padding:36px;color:var(--ink-400)">Preparing preview...</div>';
  exportPreviewHtml = '';
  dl.removeAttribute('href');
  openModal('modal-export-preview');
  fetch(previewUrl.toString(), { headers: { 'Accept': 'application/json' } })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      title.textContent = data.title || fallbackTitle || 'Export Preview';
      dl.href = data.download_url || url;
      const columns = data.columns || [];
      const rows = data.rows || [];
      const head = columns.map(c => '<th style="padding:8px 10px;text-align:left;border-bottom:1px solid var(--ink-100);font-size:11px;white-space:nowrap">' + epEsc(c) + '</th>').join('');
      const bodyRows = rows.length
        ? rows.map(row => '<tr>' + row.map(cell => '<td style="padding:8px 10px;border-bottom:1px solid var(--ink-50);font-size:12px;vertical-align:top;white-space:nowrap">' + epEsc(cell || '-') + '</td>').join('') + '</tr>').join('')
        : '<tr><td colspan="' + Math.max(columns.length, 1) + '" style="padding:28px;text-align:center;color:var(--ink-300)">No records to export.</td></tr>';
      exportPreviewHtml =
        '<h2 style="font-family:Arial,sans-serif;margin:0 0 10px">' + epEsc(data.title || fallbackTitle || 'Export Preview') + '</h2>' +
        '<p style="font-family:Arial,sans-serif;margin:0 0 14px;color:#555">Showing ' + epEsc(data.preview_count || rows.length) + ' of ' + epEsc(data.total ?? rows.length) + ' record(s).</p>' +
        '<table style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:12px"><thead><tr>' + head + '</tr></thead><tbody>' + bodyRows + '</tbody></table>';
      body.innerHTML =
        '<div style="font-size:13px;color:var(--ink-500);margin-bottom:12px">Previewing ' + epEsc(data.preview_count || rows.length) + ' of ' + epEsc(data.total ?? rows.length) + ' record(s). Review the sample below before downloading.</div>' +
        '<div class="tbl-wrap" style="border:1px solid var(--ink-100);border-radius:var(--r-md);max-height:48vh;overflow:auto"><table><thead><tr>' + head + '</tr></thead><tbody>' + bodyRows + '</tbody></table></div>';
    })
    .catch(() => {
      exportPreviewHtml = '';
      body.innerHTML = '<div style="text-align:center;padding:36px;color:var(--ink-400)">Preview unavailable. You can still download the CSV export.</div>';
      dl.href = url;
    });
}
function printExportPreview() {
  if (!exportPreviewHtml) return showToast('Preview is still loading.', 'error');
  const w = window.open('', '_blank', 'width=1000,height=800');
  if (!w) return showToast('Allow pop-ups to print or save PDF.', 'error');
  w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Export Preview</title><style>@page{margin:16mm}body{font-family:Arial,sans-serif;color:#111}table{border-collapse:collapse;width:100%;font-size:11px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;vertical-align:top}th{background:#f3f4f6}</style></head><body>' + exportPreviewHtml + '</body></html>');
  w.document.close();
  w.focus();
  setTimeout(() => w.print(), 400);
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

// ══════════════════════════════════════════════════════════════
// Notification bell — live insights (non-dismissible) + discrete
// stored notifications (read/unread, read more, delete, routing)
// ══════════════════════════════════════════════════════════════
const INSIGHT_ICONS = {
  user:'👤', officer:'🧑‍💼', alert:'🚨', bgy:'🏘️', warning:'⚠️', doc:'📄',
};

let sanotifOpen = false;
let sanotifOffset = 0;
const SANOTIF_PAGE = 8;

function saEsc(s) {
  return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function toggleNotifDropdown() {
  sanotifOpen = !sanotifOpen;
  document.getElementById('notif-dropdown').classList.toggle('open', sanotifOpen);
  if (sanotifOpen) loadNotifDropdown(true);
}
document.addEventListener('click', function (e) {
  const wrap = document.querySelector('.notif-bell-wrap');
  if (sanotifOpen && wrap && !wrap.contains(e.target)) {
    sanotifOpen = false;
    document.getElementById('notif-dropdown').classList.remove('open');
  }
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && sanotifOpen) {
    sanotifOpen = false;
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
  return d.toLocaleDateString('en-PH', { month:'short', day:'numeric' });
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
    }).catch(() => {});
}

function loadNotifDropdown(reset) {
  if (reset) sanotifOffset = 0;
  const list = document.getElementById('notif-dropdown-list');
  fetch('ajax/notifications_action.php?action=list&offset=' + sanotifOffset + '&limit=' + SANOTIF_PAGE)
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      if (reset) list.innerHTML = '';
      const existingMore = document.getElementById('sanotif-load-more');
      if (existingMore) existingMore.remove();

      if (reset && d.insights && d.insights.length) {
        let html = '<div class="notif-section-lbl">Needs Attention</div>';
        d.insights.forEach(function (n) {
          html += '<a class="notif-item insight" href="' + n.link + '" onclick="sanotifOpen=false;document.getElementById(\'notif-dropdown\').classList.remove(\'open\')">' +
            '<div class="notif-item-icon" style="color:' + n.color + '">' + (INSIGHT_ICONS[n.icon] || '📄') + '</div>' +
            '<div class="notif-item-body"><div class="notif-item-subject">' + saEsc(n.title) + '</div><div class="notif-item-msg">' + saEsc(n.sub) + '</div></div>' +
            '</a>';
        });
        list.insertAdjacentHTML('beforeend', html);
      }

      if (reset && (!d.notifications.length) && (!d.insights || !d.insights.length)) {
        list.insertAdjacentHTML('beforeend', '<div class="notif-dd-empty">All caught up! No notifications right now.</div>');
      }

      if (d.notifications.length) {
        if (reset) list.insertAdjacentHTML('beforeend', '<div class="notif-section-lbl">Recent Notifications</div>');
        d.notifications.forEach(function (n) { list.insertAdjacentHTML('beforeend', renderSANotifItem(n)); });
      }

      sanotifOffset += d.notifications.length;
      if (d.has_more) {
        list.insertAdjacentHTML('beforeend', '<button type="button" id="sanotif-load-more" class="notif-load-more-btn" onclick="loadNotifDropdown(false)">Load more</button>');
      }
    })
    .catch(() => { if (reset) list.innerHTML = '<div class="notif-dd-empty">Could not load notifications.</div>'; });
}

function renderSANotifItem(n) {
  const isUnread = String(n.is_unread ?? (n.status !== 'read' ? 1 : 0)) === '1';
  const msg = n.message || '';
  const isLong = msg.length > 90;
  const shortMsg = isLong ? msg.substring(0, 90) + '...' : msg;
  const viewLink = n.link_page ? '<span class="notif-item-viewcase" onclick="goToSANotifPage(' + n.id + ',\'' + n.link_page + '\')">View -></span>' : '';
  return `
  <div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${n.id}" data-unread="${isUnread ? '1' : '0'}">
    <div class="notif-item-body">
      <div class="notif-item-subject" onclick="markSANotifRead(${n.id})">${saEsc(n.subject || 'Notice')}</div>
      <div class="notif-item-msg">
        <span class="notif-msg-text" data-full="${saEsc(msg)}" data-short="${saEsc(shortMsg)}">${saEsc(shortMsg)}</span>
        ${isLong ? `<span class="notif-item-readmore" onclick="toggleSAReadMore(this, ${n.id})">Read more</span>` : ''}
      </div>
      <div class="notif-item-foot">
        <span class="notif-item-time" title="${saEsc(notifFullTime(n.created_at))}">${n.case_number ? saEsc(n.case_number) + ' - ' : ''}${notifTimeAgo(n.created_at)}</span>
        <span class="notif-item-actions">
          ${viewLink}
          <span class="notif-read-toggle" onclick="toggleSANotifRead(${n.id})">${isUnread ? 'Mark read' : 'Mark unread'}</span>
        </span>
      </div>
    </div>
    <button type="button" class="notif-item-del" onclick="deleteSANotif(event, ${n.id})" title="Delete notification">x</button>
  </div>`;
}

function toggleSAReadMore(el, id) {
  const msgSpan = el.previousElementSibling;
  const expanded = el.dataset.expanded === '1';
  msgSpan.textContent = expanded ? msgSpan.dataset.short : msgSpan.dataset.full;
  el.textContent = expanded ? 'Read more' : 'Show less';
  el.dataset.expanded = expanded ? '0' : '1';
  markSANotifRead(id);
}

function setSANotifReadState(id, isUnread) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el) return;
  el.classList.toggle('unread', isUnread);
  el.dataset.unread = isUnread ? '1' : '0';
  const toggle = el.querySelector('.notif-read-toggle');
  if (toggle) toggle.textContent = isUnread ? 'Mark read' : 'Mark unread';
}

function markSANotifRead(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el || el.dataset.unread !== '1') return;
  setSANotifReadState(id, false);
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_read', id }) })
    .then(() => refreshNotifBadge()).catch(() => { setSANotifReadState(id, true); showToast('Could not mark as read.', 'error'); });
}

function markSANotifUnread(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el || el.dataset.unread === '1') return;
  setSANotifReadState(id, true);
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_unread', id }) })
    .then(() => refreshNotifBadge()).catch(() => { setSANotifReadState(id, false); showToast('Could not mark as unread.', 'error'); });
}

function toggleSANotifRead(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el) return;
  if (el.dataset.unread === '1') markSANotifRead(id);
  else markSANotifUnread(id);
}

function markAllNotifRead() {
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_all_read' }) })
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      document.querySelectorAll('.notif-item:not(.insight)').forEach(el => { el.classList.remove('unread'); el.dataset.unread = '0'; const t = el.querySelector('.notif-read-toggle'); if (t) t.textContent = 'Mark unread'; });
      refreshNotifBadge();
      showToast('All notifications marked as read.', 'success');
    }).catch(() => {});
}

function deleteSANotif(evt, id) {
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
        if (list && !list.querySelector('.notif-item:not(.insight)') && !list.querySelector('.notif-item.insight')) {
          list.innerHTML = '<div class="notif-dd-empty">All caught up! No notifications right now.</div>';
        }
      } else showToast(d.message, 'error');
    }).catch(() => showToast('Request failed.', 'error'));
}

function goToSANotifPage(id, page) {
  markSANotifRead(id);
  window.location.href = '?page=' + page;
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
