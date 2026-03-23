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

// ── Notification data (Option A — live from existing tables) ──
$notifs = [];
$notif_count = 0;
try {
    // 1. Pending community users awaiting approval
    $pending_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='community'")->fetchColumn();
    if ($pending_users > 0) {
        $notifs[] = [
            'type'    => 'users',
            'color'   => '#F59E0B',
            'icon'    => 'user',
            'title'   => "$pending_users community user(s) pending approval",
            'sub'     => 'New registrations awaiting activation',
            'link'    => '?page=users&filter=pending',
            'time'    => 'Now',
        ];
    }

    // 2. Pending barangay officer accounts
    $pending_officers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='barangay'")->fetchColumn();
    if ($pending_officers > 0) {
        $notifs[] = [
            'type'    => 'officers',
            'color'   => '#A78BFA',
            'icon'    => 'officer',
            'title'   => "$pending_officers barangay officer(s) pending activation",
            'sub'     => 'Officer accounts need to be activated',
            'link'    => '?page=users&filter=pending',
            'time'    => 'Now',
        ];
    }

    // 3. Escalated blotters (escalated to municipality level)
    $escalated = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE status='escalated'")->fetchColumn();
    if ($escalated > 0) {
        $notifs[] = [
            'type'    => 'escalated',
            'color'   => '#FB7185',
            'icon'    => 'alert',
            'title'   => "$escalated blotter(s) escalated to municipality",
            'sub'     => 'Requires superadmin attention',
            'link'    => '?page=reports',
            'time'    => 'Now',
        ];
    }

    // 4. Inactive barangays (registered but inactive)
    $inactive_bgy = (int)$pdo->query("SELECT COUNT(*) FROM barangays WHERE is_active=0")->fetchColumn();
    if ($inactive_bgy > 0) {
        $notifs[] = [
            'type'    => 'barangay',
            'color'   => '#94A3B8',
            'icon'    => 'bgy',
            'title'   => "$inactive_bgy barangay(s) currently inactive",
            'sub'     => 'Review and activate if needed',
            'link'    => '?page=barangays',
            'time'    => 'Now',
        ];
    }

    // 5. Barangays with no officer assigned
    $no_officer = (int)$pdo->query("
        SELECT COUNT(*) FROM barangays b
        WHERE b.is_active=1
          AND NOT EXISTS (SELECT 1 FROM users u WHERE u.barangay_id=b.id AND u.role='barangay' AND u.is_active=1)
    ")->fetchColumn();
    if ($no_officer > 0) {
        $notifs[] = [
            'type'    => 'no_officer',
            'color'   => '#F59E0B',
            'icon'    => 'warning',
            'title'   => "$no_officer active barangay(s) have no assigned officer",
            'sub'     => 'No active barangay officer account found',
            'link'    => '?page=barangays',
            'time'    => 'Now',
        ];
    }

    // 6. Recent blotters filed in last 24 hours (across all barangays)
    $recent_filed = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE created_at >= NOW() - INTERVAL 24 HOUR")->fetchColumn();
    if ($recent_filed > 0) {
        $notifs[] = [
            'type'    => 'recent',
            'color'   => '#2EBAC6',
            'icon'    => 'doc',
            'title'   => "$recent_filed new blotter(s) filed in the last 24 hours",
            'sub'     => 'Across all barangays',
            'link'    => '?page=reports',
            'time'    => 'Last 24h',
        ];
    }

    $notif_count = count($notifs);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($current_title) ?> — VOICE2 Superadmin</title>
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
        <span class="topbar-title"><?= htmlspecialchars($current_title) ?></span>
        <span class="topbar-crumb">Siniloan · Superadmin</span>
      </div>
      <div class="topbar-actions">

        <!-- ── Notification Bell ── -->
        <div class="notif-wrap" id="notif-wrap">
          <button class="icon-btn notif-btn" id="notif-btn" onclick="toggleNotif(event)" title="Notifications">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 2a5 5 0 0 1 5 5v2l1.5 2.5h-13L3 9V7a5 5 0 0 1 5-5z"/><path d="M6.5 13.5a1.5 1.5 0 0 0 3 0"/></svg>
            <?php if ($notif_count > 0): ?>
              <span class="notif-badge"><?= $notif_count ?></span>
            <?php endif; ?>
          </button>

          <!-- Dropdown panel -->
          <div class="notif-panel" id="notif-panel">
            <div class="notif-panel-hdr">
              <div>
                <div class="notif-panel-title">Notifications</div>
                <div class="notif-panel-sub"><?= $notif_count > 0 ? "$notif_count items need attention" : "All clear" ?></div>
              </div>
              <?php if ($notif_count > 0): ?>
                <span class="notif-count-chip"><?= $notif_count ?> new</span>
              <?php endif; ?>
            </div>

            <div class="notif-list">
              <?php if (empty($notifs)): ?>
                <div class="notif-empty">
                  <div class="notif-empty-icon">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M14 4a8 8 0 0 1 8 8v3.5l2 3.5H4l2-3.5V12a8 8 0 0 1 8-8z"/><path d="M11 22a3 3 0 0 0 6 0"/></svg>
                  </div>
                  <div class="notif-empty-title">All caught up!</div>
                  <div class="notif-empty-sub">No pending items require your attention right now.</div>
                </div>
              <?php else: foreach ($notifs as $n): ?>
                <a class="notif-item" href="<?= $n['link'] ?>" onclick="closeNotif()">
                  <div class="notif-item-ic" style="background:<?= $n['color'] ?>22;color:<?= $n['color'] ?>">
                    <?php
                    $icons = [
                      'user'    => '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="7" cy="5" r="2.5"/><path d="M1.5 13c0-3 2.5-5 5.5-5s5.5 2.2 5.5 5"/></svg>',
                      'officer' => '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="5" cy="5" r="2.2"/><path d="M1 13c0-2.5 1.8-4.5 4-4.5"/><circle cx="10" cy="6" r="1.8"/><path d="M7.5 13c0-2 1.1-3.5 2.5-3.5s2.5 1.5 2.5 3.5"/></svg>',
                      'alert'   => '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="7" cy="7" r="5.5"/><path d="M7 4.5v3"/><circle cx="7" cy="9.5" r=".5" fill="currentColor"/></svg>',
                      'bgy'     => '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M2 13V6.5L7 2l5 4.5V13"/><path d="M5 13v-3.5h4V13"/></svg>',
                      'warning' => '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M7 1.5L13 12.5H1L7 1.5z"/><path d="M7 6v3"/><circle cx="7" cy="10.5" r=".5" fill="currentColor"/></svg>',
                      'doc'     => '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="2" y="1.5" width="10" height="11" rx="1.5"/><path d="M4.5 5h5M4.5 7.5h5M4.5 10h3"/></svg>',
                    ];
                    echo $icons[$n['icon']] ?? $icons['doc'];
                    ?>
                  </div>
                  <div class="notif-item-body">
                    <div class="notif-item-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="notif-item-sub"><?= htmlspecialchars($n['sub']) ?></div>
                  </div>
                  <div class="notif-item-time"><?= $n['time'] ?></div>
                </a>
              <?php endforeach; endif; ?>
            </div>

            <div class="notif-panel-foot">
              <a href="?page=users&filter=pending" onclick="closeNotif()" style="font-size:12px;color:var(--indigo-400);font-weight:600;text-decoration:none">View pending users →</a>
              <button onclick="location.reload()" style="font-size:11px;color:var(--ink-300);background:none;border:none;cursor:pointer">Refresh</button>
            </div>
          </div>
        </div>

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

/* ── Notification dropdown ── */
function toggleNotif(e) {
  e.stopPropagation();
  const panel = document.getElementById('notif-panel');
  panel.classList.toggle('open');
}
function closeNotif() {
  document.getElementById('notif-panel')?.classList.remove('open');
}
// Close when clicking outside
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('notif-wrap');
  if (wrap && !wrap.contains(e.target)) closeNotif();
});
// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeNotif();
});
</script>

</body>
</html>
