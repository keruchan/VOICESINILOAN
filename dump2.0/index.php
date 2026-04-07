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
    <div class="sidebar-brand">
      <div class="brand-label"><div class="brand-pulse"></div>Superadmin Portal</div>
      <div class="brand-name">VOICE<br>Command</div>
      <div class="brand-sub">Municipality-wide Oversight</div>
    </div>

    <div class="muni-chip">
      <div class="muni-icon">SL</div>
      <div>
        <div class="muni-name">Paete</div>
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
        <span class="topbar-title"><?= htmlspecialchars($current_title) ?></span>
        <span class="topbar-crumb">Paete · Superadmin</span>
      </div>
      <div class="topbar-actions">
        <button class="icon-btn" title="Notifications">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 2a5 5 0 0 1 5 5v2l1.5 2.5h-13L3 9V7a5 5 0 0 1 5-5z"/><path d="M6.5 13.5a1.5 1.5 0 0 0 3 0"/></svg>
        </button>
        <a href="../connection/logout.php" class="btn btn-outline btn-sm">Logout</a>
      </div>
    </div>

    <div class="content">
      <?php include "pages/{$page}.php"; ?>
    </div>
  </div>

</div><!-- /app -->

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
</script>

</body>
</html>
