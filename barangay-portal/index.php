<?php
// barangay-portal/index.php
require_once '../connection/auth.php';
guardRole('barangay');
$user = currentUser();  // keys: id, name, role, barangay_id

$allowed = ['dashboard','blotter-management','violator-monitor','mediation','user-management','sanctions-book','case-finder','records-archive','settings'];
$page    = (isset($_GET['page']) && in_array($_GET['page'], $allowed)) ? $_GET['page'] : 'dashboard';

$titles = [
    'dashboard'          => 'Dashboard',
    'blotter-management' => 'Blotter Management',
    'violator-monitor'   => 'Violator Monitor',
    'mediation'          => 'Mediation',
    'user-management'     => 'User Management',
    'sanctions-book'     => 'Sanctions Book',
    'case-finder'        => 'Smart Case Finder',
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

// FIX 3: Fetch all barangay names for the dropdown
$barangay_list = [];
try {
    $bs = $pdo->query("SELECT id, name FROM barangay_name ORDER BY name ASC");
    $barangay_list = $bs->fetchAll(PDO::FETCH_ASSOC);
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

$bgy_init = '';
foreach (preg_split('/[\s\-]+/', trim((string)$bgy['name'])) as $w) {
    if (preg_match('/[A-Za-z]/', $w, $m)) $bgy_init .= strtoupper($m[0]);
}
$bgy_init = substr($bgy_init, 0, 2);
if ($bgy_init === '') $bgy_init = 'BG';
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
    <a href="../index.php" class="sb-brand sb-brand-link" title="Back to landing page">
      <div class="sb-pill"><div class="sb-dot"></div><span>Barangay Portal</span></div>
      <div class="sb-name">VOICE</div>
      <div class="sb-sub">Blotter Management System</div>
    </a>

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

      <a class="nav-a <?= $page === 'user-management' ? 'active' : '' ?>" href="?page=user-management">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6.5h12M6 3V1.5M10 3V1.5"/></svg>
        <span class="nav-label">User Management</span>
      </a>

      <div class="nav-hr"></div>
      <div class="nav-sec">Reference</div>

      <a class="nav-a <?= $page === 'sanctions-book' ? 'active' : '' ?>" href="?page=sanctions-book">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 2h10a1 1 0 0 1 1 1v11l-2-1-2 1-2-1-2 1-2-1V3a1 1 0 0 1 1-1z"/><path d="M5.5 6h5M5.5 8.5h5M5.5 11h3"/></svg>
        <span class="nav-label">Sanctions Book</span>
      </a>

      <a class="nav-a <?= $page === 'case-finder' ? 'active' : '' ?>" href="?page=case-finder">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="7" cy="7" r="5"/><path d="M11 11l3.5 3.5"/></svg>
        <span class="nav-label">Smart Case Finder</span>
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
        <button class="hamburger" type="button" onclick="toggleSidebar()" aria-label="Toggle menu"><span></span></button>
        <span class="topbar-title"><?= e($titles[$page]) ?></span>
        <span class="topbar-badge"><?= e($bgy['name']) ?></span>
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
          <div class="topbar-user-av"><?= strtoupper(substr($user['name'] ?? 'BG', 0, 2)) ?></div>
          <span class="topbar-user-name"><?= e($user['name'] ?? 'Officer') ?></span>
        </a>
      </div>
    </div>

    <div class="content">
      <?php include "pages/{$page}.php"; ?>
    </div>
  </div>
</div>

<!-- ══ GLOBAL: New Blotter Modal ══ -->
<!-- GLOBAL: Export Preview Modal -->
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

<div class="modal-overlay" id="modal-new-blotter">
  <div class="modal modal-lg" style="max-width:720px;max-height:90vh;overflow-y:auto">
    <div class="modal-hdr" style="position:sticky;top:0;background:var(--surface);z-index:10;border-bottom:1px solid var(--ink-100)">
      <span class="modal-title">File New Blotter</span>
      <button class="modal-x" onclick="closeModal('modal-new-blotter')">&#x2715;</button>
    </div>
    <div class="modal-body" style="padding:20px 24px">

      <!-- Error banner -->
      <div id="nb-error" style="display:none;background:var(--rose-50,#fff1f2);border:1px solid var(--rose-200,#fecdd3);
           border-radius:var(--r-md);padding:10px 14px;font-size:13px;color:var(--rose-700,#be123c);
           margin-bottom:16px"></div>

      <!-- Incident Details -->
      <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.07em;
                  text-transform:uppercase;margin-bottom:10px">Incident Details</div>

      <div class="fr3" style="margin-bottom:12px">
        <div class="fg" style="margin-bottom:0">
          <label>Incident Type <span class="req">*</span></label>
          <select id="nb-type" onchange="nbAutoSeverity(this.value); nbToggleOther(this.value)">
            <option value="">--- Select ---</option>
            <?php foreach (['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Trespassing','Theft / Estafa','Drug-Related','Traffic Incident','Public Disturbance','Other'] as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- <div class="fg" style="margin-bottom:0">
          <label>Violation Level <span class="req">*</span></label>
          <select id="nb-level">
            <option value="">--- Select ---</option>
            <option value="minor">Minor</option>
            <option value="moderate">Moderate</option>
            <option value="serious">Serious</option>
            <option value="critica  l">Critical</option>
          </select>
        </div> -->
        <div class="fg" style="margin-bottom:0">
          <label>Incident Date <span class="req">*</span></label>
          <input type="date" id="nb-date" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <!-- Other type field -->
      <div class="fg" id="nb-other-wrap" style="display:none;margin-bottom:12px">
        <label>Please specify <span class="req">*</span></label>
        <input type="text" id="nb-type-other" placeholder="e.g. Illegal dumping, Stray animals" maxlength="120">
      </div>

      <!-- Auto-severity hint -->
      <div id="nb-severity-hint" style="display:none;border-radius:var(--r-md);padding:10px 14px;
           margin-bottom:14px;border:1px solid;font-size:12px;transition:all .2s">
        <span id="nb-sev-emoji" style="font-size:15px;margin-right:6px"></span>
        <strong id="nb-sev-label"></strong>
        <span id="nb-sev-desc" style="color:var(--ink-500);margin-left:6px"></span>
      </div>

      <!-- FIX 3: Barangay dropdown (was readonly input) -->
      <div class="fr2" style="margin-bottom:12px">
        <div class="fg" style="margin-bottom:0">
          <label>Barangay <span class="req">*</span></label>
          <select id="nb-barangay">
            <option value="">--- Select Barangay ---</option>
            <?php foreach ($barangay_list as $bl): ?>
              <option value="<?= e($bl['name']) ?>" <?= ($bl['name'] === $bgy['name']) ? 'selected' : '' ?>>
                <?= e($bl['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg" style="margin-bottom:0">
          <label>Street / Address <span class="req">*</span></label>
          <input type="text" id="nb-street" placeholder="e.g. 123 Rizal St., Purok 4">
        </div>
      </div>

      <div style="height:1px;background:var(--ink-100);margin:16px 0"></div>

      <!-- FIX 2: Complainant with live search -->
      <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.07em;
                  text-transform:uppercase;margin-bottom:10px">Complainant</div>

      <div class="fr2" style="margin-bottom:12px">
        <div class="fg" id="nb-comp-wrap" style="margin-bottom:0">
          <label>Full Name <span class="req">*</span></label>

          <!-- Linked badge -->
          <div id="nb-comp-badge"
               style="display:none;align-items:center;gap:6px;
                      background:var(--green-50);border:1px solid var(--green-200);
                      border-radius:var(--r-md);padding:7px 10px;margin-bottom:6px;font-size:12px">
            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"
                 stroke="var(--green-600)" stroke-width="1.8" stroke-linecap="round">
              <path d="M2 7l3.5 3.5L11 3"/>
            </svg>
            <span style="font-weight:600;color:var(--green-700)" id="nb-comp-linked-name"></span>
            <span style="color:var(--green-600);font-size:11px">- Registered user</span>
            <button type="button" onclick="nbCompUnlink()"
                    style="margin-left:auto;background:none;border:none;cursor:pointer;
                           color:var(--ink-400);font-size:16px;line-height:1;padding:0 2px">&#x2715;</button>
          </div>

          <!-- Text input -->
          <div style="position:relative">
            <input type="text" id="nb-comp-name"
                   placeholder="Search registered user or type name"
                   autocomplete="off"
                   oninput="nbCompInput(this.value)"
                   onkeydown="nbCompKeydown(event)"
                   onfocus="nbCompFocus()">
            <div id="nb-comp-spinner"
                 style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%)">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                   stroke="var(--ink-400)" stroke-width="2" stroke-linecap="round"
                   style="animation:nb-spin .75s linear infinite">
                <circle cx="7" cy="7" r="5" stroke-opacity=".25"/>
                <path d="M7 2a5 5 0 0 1 5 5"/>
              </svg>
            </div>
          </div>
        </div>

        <div class="fg" style="margin-bottom:0">
          <label>Contact Number</label>
          <input type="tel" id="nb-comp-contact" placeholder="09XXXXXXXXX">
        </div>
      </div>

      <div style="height:1px;background:var(--ink-100);margin:16px 0"></div>

      <!-- Respondent / Violator -->
      <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.07em;
                  text-transform:uppercase;margin-bottom:10px">Respondent / Violator</div>

      <div class="fr2" style="margin-bottom:4px">
        <!-- FIX 1: Live-search — now calls correct ajax endpoint -->
        <div class="fg" id="nb-resp-wrap" style="margin-bottom:0">
          <label>Full Name</label>

          <!-- Linked badge -->
          <div id="nb-resp-badge"
               style="display:none;align-items:center;gap:6px;
                      background:var(--green-50);border:1px solid var(--green-200);
                      border-radius:var(--r-md);padding:7px 10px;margin-bottom:6px;font-size:12px">
            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"
                 stroke="var(--green-600)" stroke-width="1.8" stroke-linecap="round">
              <path d="M2 7l3.5 3.5L11 3"/>
            </svg>
            <span style="font-weight:600;color:var(--green-700)" id="nb-resp-linked-name"></span>
            <span style="color:var(--green-600);font-size:11px">- Registered user</span>
            <button type="button" onclick="nbUnlink()"
                    style="margin-left:auto;background:none;border:none;cursor:pointer;
                           color:var(--ink-400);font-size:16px;line-height:1;padding:0 2px">&#x2715;</button>
          </div>

          <!-- Text input -->
          <div style="position:relative">
            <input type="text" id="nb-resp-name"
                   placeholder="Type to search, or leave blank if unknown"
                   autocomplete="off"
                   oninput="nbRespInput(this.value)"
                   onkeydown="nbRespKeydown(event)"
                   onfocus="nbRespFocus()">
            <div id="nb-resp-spinner"
                 style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%)">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                   stroke="var(--ink-400)" stroke-width="2" stroke-linecap="round"
                   style="animation:nb-spin .75s linear infinite">
                <circle cx="7" cy="7" r="5" stroke-opacity=".25"/>
                <path d="M7 2a5 5 0 0 1 5 5"/>
              </svg>
            </div>
          </div>
          <div style="font-size:11px;color:var(--ink-400);margin-top:4px">
            Leave blank if unknown - officer can update later
          </div>
        </div>

        <div class="fg" style="margin-bottom:0">
          <label>Contact Number</label>
          <input type="tel" id="nb-resp-contact" placeholder="09XXXXXXXXX">
        </div>
      </div>

      <div style="height:1px;background:var(--ink-100);margin:16px 0"></div>

      <!-- Narrative -->
      <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.07em;
                  text-transform:uppercase;margin-bottom:10px">Narrative</div>

      <div class="fg" style="margin-bottom:0">
        <label>Description <span class="req">*</span></label>
        <textarea id="nb-narrative" rows="4"
                  placeholder="Describe the incident in detail - time, people involved, sequence of events..."></textarea>
      </div>

    </div><!-- /modal-body -->

    <div class="modal-foot" style="position:sticky;bottom:0;background:var(--surface);
         border-top:1px solid var(--ink-100);z-index:10">
      <button class="btn btn-outline" onclick="nbReset(); closeModal('modal-new-blotter')">Cancel</button>
      <button class="btn btn-primary" onclick="submitNewBlotter()">File Blotter</button>
    </div>
  </div>
</div>

<!-- Respondent dropdown (fixed to body to escape modal overflow clipping) -->
<div id="nb-resp-dropdown"
     style="display:none;position:fixed;z-index:99999;
            background:var(--surface,#fff);border:1px solid var(--ink-100);
            border-radius:var(--r-lg);box-shadow:0 8px 28px rgba(0,0,0,.18);
            overflow:hidden;min-width:260px"></div>

<!-- Complainant dropdown (fixed to body) -->
<div id="nb-comp-dropdown"
     style="display:none;position:fixed;z-index:99999;
            background:var(--surface,#fff);border:1px solid var(--ink-100);
            border-radius:var(--r-lg);box-shadow:0 8px 28px rgba(0,0,0,.18);
            overflow:hidden;min-width:260px"></div>

<!-- Panel respondent search dropdown (fixed to body) -->
<div id="panel-resp-dropdown"
     style="display:none;position:fixed;z-index:99999;
            background:var(--surface,#fff);border:1px solid var(--ink-100);
            border-radius:var(--r-lg);box-shadow:0 8px 28px rgba(0,0,0,.18);
            overflow:hidden;min-width:260px"></div>

<!-- ══ GLOBAL: Blotter Detail Panel ══ -->
<div class="panel-overlay" id="panel-overlay">
  <div class="slide-panel" id="slide-panel">
    <div class="panel-hdr">
      <div>
        <div class="panel-title" id="panel-case-no">Case Details</div>
        <div id="panel-case-sub" style="font-size:12px;color:var(--ink-400);margin-top:2px"></div>
      </div>
      <button class="panel-x" onclick="closePanel()">&#x2715;</button>
    </div>
    <div class="panel-body" id="panel-body">
      <div style="text-align:center;padding:40px;color:var(--ink-300)">Loading...</div>
    </div>
  </div>
</div>

<!-- ══ GLOBAL: Loading & Toast ══ -->
<div id="loading-overlay"><div class="spinner"></div></div>
<div id="toast"></div>

<script>
/* globals */
const BARANGAY_ID = <?= $bid ?>;
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.color = '#6B84A0';

/* modal helpers */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open'); });

/* panel helpers */
function openPanel()  { document.getElementById('panel-overlay').classList.add('open'); }
function closePanel() { document.getElementById('panel-overlay').classList.remove('open'); }
document.getElementById('panel-overlay').addEventListener('click', e => {
  if (e.target.id === 'panel-overlay') closePanel();
});

/* loading */
function loading(s) { document.getElementById('loading-overlay').classList.toggle('show', s); }

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
      body.innerHTML = '<div class="empty-state"><div class="es-title">Preview unavailable</div><div class="es-sub">You can still download the CSV export.</div></div>';
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

/* toast */
function showToast(msg, type) {
  type = type || '';
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = type === 'error' ? '#BE123C' : type === 'success' ? '#047857' : '#0D1B2E';
  t.style.opacity = '1';
  t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(t._t);
  t._t = setTimeout(function() {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(10px)';
  }, 3200);
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

/* chip maps */
const LEVEL_CH  = { minor:'ch-emerald', moderate:'ch-amber', serious:'ch-rose', critical:'ch-violet' };
const STATUS_CH = { pending_review:'ch-amber', active:'ch-teal', mediation_set:'ch-navy', resolved:'ch-emerald', closed:'ch-slate', escalated:'ch-rose', transferred:'ch-slate' };
function levelChip(v)  { return '<span class="chip ' + (LEVEL_CH[v]||'ch-slate') + '">' + ucw(v) + '</span>'; }
function statusChip(v) { return '<span class="chip ' + (STATUS_CH[v]||'ch-slate') + '">' + ucw(v.replace(/_/g,' ')) + '</span>'; }
function ucw(s) { return s ? s.replace(/\b\w/g, function(c) { return c.toUpperCase(); }) : '-'; }

/* view blotter */
function viewBlotter(id) {
  document.getElementById('panel-case-no').textContent = 'Loading...';
  document.getElementById('panel-case-sub').textContent = '';
  document.getElementById('panel-body').innerHTML = '<div style="text-align:center;padding:40px;color:var(--ink-300)">Loading...</div>';
  openPanel();
  fetch('ajax/get_blotter.php?id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.success) { document.getElementById('panel-body').innerHTML = '<p style="color:var(--rose-600);padding:20px">Could not load case.</p>'; return; }
      renderPanel(d.data);
    })
    .catch(function() { document.getElementById('panel-body').innerHTML = '<p style="color:var(--rose-600);padding:20px">Request failed.</p>'; });
}

function renderPanel(b) {
  document.getElementById('panel-case-no').textContent = b.case_number;
  document.getElementById('panel-case-sub').textContent = b.incident_type + ' - ' + b.incident_date;

  const prescribedOpts = ['pending','document_only','mediation','barangay_deliberation','refer_barangay','refer_police','refer_vawc','escalate_municipality']
    .map(function(v) { return '<option value="' + v + '"' + (b.prescribed_action===v?' selected':'') + '>' + ucw(v.replace(/_/g,' ')) + '</option>'; }).join('');

  const statusOpts = ['pending_review','active','mediation_set','deliberation','escalated','resolved','closed','transferred']
    .map(function(v) { return '<option value="' + v + '"' + (b.status===v?' selected':'') + '>' + ucw(v.replace(/_/g,' ')) + '</option>'; }).join('');

  const timeline = (b.timeline||[]).map(function(t) {
    return '<div class="tl-item"><div class="tl-dot tl-dot-teal"></div><div><div class="tl-title">' + ucw(t.action.replace(/_/g,' ')) + '</div><div class="tl-desc">' + (t.description||'') + '</div><div class="tl-time">' + t.created_at + '</div></div></div>';
  }).join('');

  const attachmentsHtml = (b.attachments && b.attachments.length > 0) ?
    '<div class="card mb16 case-view-readonly"><div class="card-hdr"><span class="card-title">Attachments (' + b.attachments.length + ')</span></div><div class="card-body" style="padding:12px 16px"><div class="case-view-attachments">' +
    b.attachments.map(function(att) {
      const imgPath = '../' + att.file_path;
      return '<div class="case-view-attachment" onclick="viewAttachment(\'' + imgPath + '\',\'' + att.original_name + '\')">' +
        '<img src="' + imgPath + '" alt="' + att.original_name + '" onerror="this.style.opacity=\'0.3\'">' +
        '<div class="case-view-attachment-name">' + att.original_name + '</div></div>';
    }).join('') + '</div></div></div>' : '';

  // Respondent linked badge HTML (shown if currently linked to a registered user)
  const respBadge = b.respondent_user_id
    ? '<div id="panel-resp-badge" style="display:flex;align-items:center;gap:6px;background:var(--green-50);border:1px solid var(--green-200);border-radius:var(--r-md);padding:7px 10px;margin-bottom:6px;font-size:12px">' +
      '<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="var(--green-600)" stroke-width="1.8" stroke-linecap="round"><path d="M2 7l3.5 3.5L11 3"/></svg>' +
      '<span style="font-weight:600;color:var(--green-700)" id="panel-resp-linked-name">' + pEsc(b.respondent_name||'') + '</span>' +
      '<span style="color:var(--green-600);font-size:11px">· Registered user</span>' +
      '<button type="button" onclick="panelRespUnlink()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--ink-400);font-size:16px;line-height:1;padding:0 2px">×</button>' +
      '</div>' +
      '<input type="text" id="panel-resp-name" value="' + pEsc(b.respondent_name||'') + '" style="display:none" autocomplete="off" oninput="panelRespInput(this.value)" onkeydown="panelRespKeydown(event)" onfocus="panelRespFocus()">'
    : '<div id="panel-resp-badge" style="display:none;align-items:center;gap:6px;background:var(--green-50);border:1px solid var(--green-200);border-radius:var(--r-md);padding:7px 10px;margin-bottom:6px;font-size:12px">' +
      '<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="var(--green-600)" stroke-width="1.8" stroke-linecap="round"><path d="M2 7l3.5 3.5L11 3"/></svg>' +
      '<span style="font-weight:600;color:var(--green-700)" id="panel-resp-linked-name"></span>' +
      '<span style="color:var(--green-600);font-size:11px">· Registered user</span>' +
      '<button type="button" onclick="panelRespUnlink()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--ink-400);font-size:16px;line-height:1;padding:0 2px">×</button>' +
      '</div>' +
      '<input type="text" id="panel-resp-name" placeholder="Type to search, or leave blank if unknown" value="' + pEsc(b.respondent_name||'') + '" autocomplete="off" oninput="panelRespInput(this.value)" onkeydown="panelRespKeydown(event)" onfocus="panelRespFocus()" style="position:relative">';

  // Store current blotter id + respondent_user_id for the panel save
  window._panelBlotterId     = b.id;
  window._panelRespUserId    = b.respondent_user_id || null;
  window._panelRespLinked    = !!b.respondent_user_id;

  // Store full blotter for Case Report
  window._currentBlotter = b;

  document.getElementById('panel-body').innerHTML =
    '<div class="case-view-top">' +
      levelChip(b.violation_level) + ' ' + statusChip(b.status) +
      '<button class="btn btn-outline btn-sm case-view-push" style="border-color:var(--navy-200);color:var(--navy-700)" onclick="openCaseReport()">Case Report</button>' +
    '</div>' +

    // Case Information (read-only)
    '<div class="card mb16 case-view-card">' +
      '<div class="card-hdr"><span class="card-title">Case Information</span></div>' +
      '<div class="card-body" style="padding:12px 16px">' +
        '<div class="dr"><span class="dr-lbl">Complainant</span><span class="dr-val">' + pEsc(b.complainant_name||'—') + '</span></div>' +
        '<div class="dr"><span class="dr-lbl">Contact</span><span class="dr-val">' + pEsc(b.complainant_contact||'—') + '</span></div>' +
        '<div class="dr"><span class="dr-lbl">Respondent</span><span class="dr-val">' + pEsc(b.respondent_name||'Not identified') + '</span></div>' +
        '<div class="dr"><span class="dr-lbl">Resp. Contact</span><span class="dr-val">' + pEsc(b.respondent_contact||'—') + '</span></div>' +
        '<div class="dr"><span class="dr-lbl">Location</span><span class="dr-val">' + pEsc(b.incident_location||'—') + '</span></div>' +
        '<div class="dr"><span class="dr-lbl">Incident Date</span><span class="dr-val">' + ((b.incident_date||'').substring(0,10)||'—') + '</span></div>' +
        '<div class="dr"><span class="dr-lbl">Prescribed Action</span><span class="dr-val">' + ucw((b.prescribed_action||'pending').replace(/_/g,' ')) + '</span></div>' +
        '<div class="dr"><span class="dr-lbl">Filed</span><span class="dr-val">' + ((b.created_at||'').substring(0,10)||'—') + '</span></div>' +
      '</div>' +
    '</div>' +

    // Respondent / Violator (editable)
    '<div class="card mb16 case-view-edit">' +
      '<div class="card-hdr">' +
        '<span class="card-title">⚠️ Respondent / Violator</span>' +
        '<span style="font-size:11px;color:var(--amber-600);font-weight:600">Editable</span>' +
      '</div>' +
      '<div class="card-body" style="padding:12px 16px">' +

        // Name with live search
        '<div class="fg" id="panel-resp-wrap">' +
          '<label style="font-size:12px;font-weight:600;color:var(--ink-600)">Full Name</label>' +
          '<div style="position:relative">' + respBadge +
            '<div id="panel-resp-spinner" style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%)">' +
              '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="var(--ink-400)" stroke-width="2" stroke-linecap="round" style="animation:nb-spin .75s linear infinite"><circle cx="7" cy="7" r="5" stroke-opacity=".25"/><path d="M7 2a5 5 0 0 1 5 5"/></svg>' +
            '</div>' +
          '</div>' +
          '<div style="font-size:11px;color:var(--ink-400);margin-top:4px">Search registered users · type manually if not registered · leave blank if unknown</div>' +
        '</div>' +

        // Contact
        '<div class="fg">' +
          '<label style="font-size:12px;font-weight:600;color:var(--ink-600)">Contact Number</label>' +
          '<input type="tel" id="panel-resp-contact" value="' + pEsc(b.respondent_contact||'') + '" placeholder="09XXXXXXXXX">' +
        '</div>' +

        // Location
        '<div class="fg" style="margin-bottom:14px">' +
          '<label style="font-size:12px;font-weight:600;color:var(--ink-600)">Incident Location</label>' +
          '<input type="text" id="panel-resp-location" value="' + pEsc(b.incident_location||'') + '" placeholder="Street, Purok, Landmark…">' +
        '</div>' +

        '<button class="btn btn-primary btn-sm" onclick="saveRespondent()">💾 Save Respondent Details</button>' +
        '<div id="panel-resp-feedback" style="font-size:12px;margin-top:8px;min-height:16px"></div>' +
      '</div>' +
    '</div>' +

    // ── Narrative ──
    '<div class="card mb16 case-view-readonly">' +
      '<div class="card-hdr"><span class="card-title">Narrative</span></div>' +
      '<div class="card-body" style="padding:12px 16px"><p class="case-view-text">' + pEsc(b.narrative||'No narrative recorded.') + '</p></div>' +
    '</div>' +

    attachmentsHtml +

    // ── Update Case ──
    '<div class="card mb16 case-view-edit">' +
      '<div class="card-hdr"><span class="card-title">Update Case</span></div>' +
      '<div class="card-body" style="padding:12px 16px">' +
        '<div class="fr2">' +
          '<div class="fg"><label>Status</label><select id="p-status">' + statusOpts + '</select></div>' +
          '<div class="fg"><label>Prescribed Action</label><select id="p-action">' + prescribedOpts + '</select></div>' +
        '</div>' +
        '<div class="fg"><label>Remarks</label><textarea id="p-remarks" rows="2" placeholder="Optional officer remarks..."></textarea></div>' +
        '<div style="text-align:right;margin-top:8px"><button class="btn btn-primary btn-sm" onclick="updateStatus(' + b.id + ')">Save Update</button></div>' +
      '</div>' +
    '</div>' +

    (timeline ? '<div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.05em;text-transform:uppercase;margin-bottom:10px">Activity Log</div>' + timeline : '');

  // Init panel respondent search state
  panelRespInit();
}

// ── Panel respondent search helpers ─────────────────────────────────────────
var pRespTimer    = null;
var pRespResults  = [];
var pRespFocusIdx = -1;

function panelRespInit() {
  // Move panel dropdown to body (idempotent)
  const dd = document.getElementById('panel-resp-dropdown');
  if (dd && dd.parentElement !== document.body) document.body.appendChild(dd);
}

function pRespPosition() {
  const input = document.getElementById('panel-resp-name');
  const dd    = document.getElementById('panel-resp-dropdown');
  if (!input || !dd) return;
  const rect = input.getBoundingClientRect();
  dd.style.top   = (rect.bottom + window.scrollY + 2) + 'px';
  dd.style.left  = (rect.left  + window.scrollX)     + 'px';
  dd.style.width = rect.width + 'px';
}

window.addEventListener('scroll', function() {
  if (document.getElementById('panel-resp-dropdown')?.style.display !== 'none') pRespPosition();
}, true);
window.addEventListener('resize', function() {
  if (document.getElementById('panel-resp-dropdown')?.style.display !== 'none') pRespPosition();
});

function panelRespFocus() {
  const val = document.getElementById('panel-resp-name')?.value?.trim();
  if (!window._panelRespLinked && val && val.length >= 2 && !pRespResults.length) pRespDoSearch(val);
}

function panelRespInput(val) {
  if (window._panelRespLinked) panelRespUnlink(false);
  clearTimeout(pRespTimer);
  const q = val.trim();
  if (q.length < 2) { pRespHideDropdown(); pRespSpinner(false); return; }
  pRespSpinner(true);
  pRespTimer = setTimeout(function() { pRespDoSearch(q); }, 300);
}

function pRespDoSearch(q) {
  fetch('ajax/search_users_barangay.php?q=' + encodeURIComponent(q))
    .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(function(data) {
      pRespSpinner(false);
      pRespResults  = (data.success && data.results) ? data.results : [];
      pRespFocusIdx = -1;
      pRespRenderDropdown(q);
    })
    .catch(function(err) { pRespSpinner(false); pRespHideDropdown(); });
}

function pRespRenderDropdown(query) {
  const dd = document.getElementById('panel-resp-dropdown');
  if (!dd) return;
  pRespPosition();

  if (!pRespResults.length) {
    dd.innerHTML = '<div style="padding:11px 14px;font-size:12px;color:var(--ink-400)">No registered user found for "' + pEsc(query) + '" — name will be saved as typed.</div>';
    dd.style.display = 'block';
    return;
  }

  const safe = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const re   = new RegExp('(' + safe + ')', 'gi');

  dd.innerHTML = pRespResults.map(function(u, i) {
    const hi = pEsc(u.name).replace(re, '<mark style="background:var(--amber-100,#fef3c7);color:inherit;border-radius:2px;padding:0 1px">$1</mark>');
    return '<div class="panel-resp-item" onmousedown="pRespSelect(' + i + ')" onmouseover="pRespSetFocus(' + i + ')" style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--ink-50,#f8fafc)">' +
      '<span>' + hi + '</span>' +
      '<span style="font-size:10px;font-weight:700;color:var(--green-600);background:var(--green-50);border:1px solid var(--green-200);border-radius:20px;padding:2px 8px;white-space:nowrap">Registered ✓</span></div>';
  }).join('');
  dd.style.display = 'block';
}

function panelRespKeydown(e) {
  const dd = document.getElementById('panel-resp-dropdown');
  if (!dd || dd.style.display === 'none') return;
  if (e.key === 'ArrowDown')     { e.preventDefault(); pRespSetFocus(Math.min(pRespFocusIdx + 1, pRespResults.length - 1)); }
  else if (e.key === 'ArrowUp')  { e.preventDefault(); pRespSetFocus(Math.max(pRespFocusIdx - 1, 0)); }
  else if (e.key === 'Enter' && pRespFocusIdx >= 0) { e.preventDefault(); pRespSelect(pRespFocusIdx); }
  else if (e.key === 'Escape')   { pRespHideDropdown(); }
}

function pRespSetFocus(idx) {
  pRespFocusIdx = idx;
  document.querySelectorAll('.panel-resp-item').forEach(function(el, i) {
    el.style.background = i === idx ? 'var(--green-50,#f0fdf4)' : '';
  });
}

function pRespSelect(idx) {
  const u = pRespResults[idx];
  if (!u) return;
  window._panelRespUserId = u.id;
  window._panelRespLinked = true;
  document.getElementById('panel-resp-name').value              = u.name;
  document.getElementById('panel-resp-linked-name').textContent = u.name;
  if (u.contact) document.getElementById('panel-resp-contact').value = u.contact;
  document.getElementById('panel-resp-badge').style.display     = 'flex';
  document.getElementById('panel-resp-name').style.display      = 'none';
  pRespHideDropdown();
}

function panelRespUnlink(clearText) {
  window._panelRespUserId = null;
  window._panelRespLinked = false;
  document.getElementById('panel-resp-badge').style.display = 'none';
  const inp = document.getElementById('panel-resp-name');
  inp.style.display = '';
  if (clearText !== false) inp.value = '';
  inp.focus();
}

function pRespHideDropdown() {
  const dd = document.getElementById('panel-resp-dropdown');
  if (dd) { dd.style.display = 'none'; dd.innerHTML = ''; }
  pRespResults  = [];
  pRespFocusIdx = -1;
}

function pRespSpinner(show) {
  const s = document.getElementById('panel-resp-spinner');
  if (s) s.style.display = show ? 'block' : 'none';
}

document.addEventListener('mousedown', function(e) {
  const wrap = document.getElementById('panel-resp-wrap');
  const dd   = document.getElementById('panel-resp-dropdown');
  if (!wrap?.contains(e.target) && !dd?.contains(e.target)) pRespHideDropdown();
});

function pEsc(s) {
  return String(s).replace(/[&<>"']/g, function(c) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}

// ── Save respondent details ─────────────────────────────────────────────────
function saveRespondent() {
  const id       = window._panelBlotterId;
  const uid      = window._panelRespUserId || null;
  const nameEl   = document.getElementById('panel-resp-name');
  const name     = nameEl ? nameEl.value.trim() : '';
  const contact  = (document.getElementById('panel-resp-contact')?.value || '').trim();
  const location = (document.getElementById('panel-resp-location')?.value || '').trim();
  const feedback = document.getElementById('panel-resp-feedback');

  if (!id) return;

  feedback.textContent = 'Saving…';
  feedback.style.color = 'var(--ink-400)';

  fetch('ajax/blotter_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action:              'update_respondent',
      id:                  id,
      respondent_user_id:  uid,
      respondent_name:     name,
      respondent_contact:  contact,
      incident_location:   location,
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.success) {
      feedback.textContent = '✓ ' + d.message;
      feedback.style.color = 'var(--green-600)';
      showToast(d.message, 'success');
      // Refresh panel after a short delay
      setTimeout(function() {
        fetch('ajax/get_blotter.php?id=' + id)
          .then(function(r) { return r.json(); })
          .then(function(res) { if (res.success) renderPanel(res.data); });
      }, 800);
    } else {
      feedback.textContent = '✗ ' + (d.message || 'Save failed.');
      feedback.style.color = 'var(--rose-600)';
    }
  })
  .catch(function(err) {
    feedback.textContent = '✗ Request failed: ' + err.message;
    feedback.style.color = 'var(--rose-600)';
  });
}

function viewAttachment(filePath, fileName) {
  const modal = document.createElement('div');
  modal.className = 'modal-overlay open';
  modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.8);z-index:9999;display:flex;align-items:center;justify-content:center';
  const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(filePath);
  const content = isImage
    ? '<img src="' + filePath + '" alt="' + fileName + '" style="max-width:90vw;max-height:90vh;border-radius:var(--r-lg);box-shadow:0 20px 40px rgba(0,0,0,.3)">'
    : '<div style="background:#fff;padding:40px;border-radius:var(--r-lg);text-align:center"><div style="font-size:48px;margin-bottom:20px">file</div><div style="font-size:14px;margin-bottom:20px">' + fileName + '</div><a href="' + filePath + '" download class="btn btn-primary">Download File</a></div>';
  modal.innerHTML = content + '<button onclick="this.parentElement.remove()" style="position:absolute;top:20px;right:20px;width:40px;height:40px;background:#fff;border:none;border-radius:50%;font-size:24px;cursor:pointer">x</button>';
  modal.onclick = function(e) { if (e.target === modal) modal.remove(); };
  document.body.appendChild(modal);
}

// ═══════════════════════════════
// SEVERITY MAP
// ═══════════════════════════════
const NB_SEVERITY_MAP = {
  'Noise Disturbance':'minor','Public Disturbance':'minor','Traffic Incident':'minor','Other':'minor',
  'Verbal Abuse / Threat':'moderate','Trespassing':'moderate','Property Damage':'moderate',
  'Theft / Estafa':'serious','Physical Altercation':'serious','Drug-Related':'serious','Domestic Dispute':'serious',
  'VAWC':'critical'
};
const NB_SEVERITY_INFO = {
  minor:    { emoji:'minor',    label:'Minor',    color:'#16A34A', desc:'Low risk - typically handled via verbal warning or documentation' },
  moderate: { emoji:'moderate', label:'Moderate', color:'#B45309', desc:'May require mediation or written agreement' },
  serious:  { emoji:'serious',  label:'Serious',  color:'#BE123C', desc:'Requires formal mediation and may result in sanctions' },
  critical: { emoji:'critical', label:'Critical', color:'#6D28D9', desc:'Urgent - may require police referral or immediate intervention' }
};

function nbAutoSeverity(type) {
  const hint = document.getElementById('nb-severity-hint');
  if (!type) { hint.style.display = 'none'; return; }
  const sev  = NB_SEVERITY_MAP[type] || 'minor';
  const info = NB_SEVERITY_INFO[sev];
  document.getElementById('nb-level').value           = sev;
  document.getElementById('nb-sev-emoji').textContent = info.emoji;
  document.getElementById('nb-sev-label').textContent = info.label;
  document.getElementById('nb-sev-label').style.color = info.color;
  document.getElementById('nb-sev-desc').textContent  = info.desc;
  hint.style.borderColor  = info.color + '55';
  hint.style.background   = info.color + '11';
  hint.style.display      = 'block';
}

function nbToggleOther(type) {
  const wrap  = document.getElementById('nb-other-wrap');
  const input = document.getElementById('nb-type-other');
  if (type === 'Other') {
    wrap.style.display = 'block';
    input.required     = true;
    input.focus();
  } else {
    wrap.style.display = 'none';
    input.required     = false;
    input.value        = '';
  }
}

// ═══════════════════════════════
// SUBMIT
// ═══════════════════════════════
var nbRespUserId = null;
var nbCompUserId = null;

function showNbError(msg) {
  const el = document.getElementById('nb-error');
  el.innerHTML = 'Error: ' + msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior:'smooth', block:'nearest' });
}
function hideNbError() {
  document.getElementById('nb-error').style.display = 'none';
}

function submitNewBlotter() {
  hideNbError();

  const typeVal    = document.getElementById('nb-type').value;
  const typeOther  = document.getElementById('nb-type-other').value.trim();
  const incType    = typeVal === 'Other' ? typeOther : typeVal;
  const level      = document.getElementById('nb-level').value;
  const date       = document.getElementById('nb-date').value;
  const street     = document.getElementById('nb-street').value.trim();
  const barangay   = document.getElementById('nb-barangay').value.trim();
  const compName   = document.getElementById('nb-comp-name').value.trim();
  const compContact= document.getElementById('nb-comp-contact').value.trim();
  const respName   = document.getElementById('nb-resp-name').value.trim();
  const respContact= document.getElementById('nb-resp-contact').value.trim();
  const narrative  = document.getElementById('nb-narrative').value.trim();

  if (!typeVal)                           return showNbError('Incident type is required.');
  if (typeVal === 'Other' && !typeOther)  return showNbError('Please specify the incident type.');
  if (!level)                             return showNbError('Violation level is required.');
  if (!barangay)                          return showNbError('Barangay is required.');
  if (!street)                            return showNbError('Street / Address is required.');
  if (!compName)                          return showNbError('Complainant name is required.');
  if (!narrative || narrative.length < 20) return showNbError('Narrative is required (min 20 characters).');
  if (date > '<?= date('Y-m-d') ?>')      return showNbError('Incident date cannot be in the future.');

  const locationStr = street + ', ' + barangay;

  loading(true);
  fetch('ajax/blotter_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action:               'new_blotter',
      incident_type:        incType,
      violation_level:      level,
      incident_date:        date,
      incident_location:    locationStr,
      complainant_user_id:  nbCompUserId || null,
      complainant_name:     compName,
      complainant_contact:  compContact,
      respondent_user_id:   nbRespUserId || null,
      respondent_name:      respName,
      respondent_contact:   respContact,
      narrative:            narrative
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    loading(false);
    if (!d.success) return showNbError(d.message || 'Submission failed.');
    showToast('Blotter filed: ' + d.case_number, 'success');
    nbReset();
    closeModal('modal-new-blotter');
    setTimeout(function() { location.reload(); }, 800);
  })
  .catch(function(err) { loading(false); showNbError('Request failed: ' + err.message); });
}

function nbReset() {
  ['nb-type','nb-level'].forEach(function(id) { document.getElementById(id).value = ''; });
  document.getElementById('nb-date').value     = '<?= date('Y-m-d') ?>';
  document.getElementById('nb-barangay').value = <?= json_encode($bgy['name']) ?>;
  ['nb-type-other','nb-comp-contact','nb-resp-name','nb-resp-contact','nb-street','nb-narrative']
    .forEach(function(id) { document.getElementById(id).value = ''; });
  document.getElementById('nb-other-wrap').style.display    = 'none';
  document.getElementById('nb-severity-hint').style.display = 'none';
  nbUnlink();
  nbCompUnlink();
  hideNbError();
}

// ═══════════════════════════════════════════════════════
// FIX 1: RESPONDENT search — correct endpoint
// ═══════════════════════════════════════════════════════
var nbTimer    = null;
var nbResults  = [];
var nbFocusIdx = -1;
var nbLinked   = false;

(function() {
  const dd = document.getElementById('nb-resp-dropdown');
  if (dd && dd.parentElement !== document.body) document.body.appendChild(dd);
})();

function nbPositionDropdown() {
  const input = document.getElementById('nb-resp-name');
  const dd    = document.getElementById('nb-resp-dropdown');
  if (!input || !dd) return;
  const rect = input.getBoundingClientRect();
  dd.style.top   = (rect.bottom + window.scrollY + 2) + 'px';
  dd.style.left  = (rect.left  + window.scrollX)     + 'px';
  dd.style.width = rect.width + 'px';
}

window.addEventListener('scroll', function() {
  if (document.getElementById('nb-resp-dropdown')?.style.display !== 'none') nbPositionDropdown();
}, true);
window.addEventListener('resize', function() {
  if (document.getElementById('nb-resp-dropdown')?.style.display !== 'none') nbPositionDropdown();
});

function nbRespFocus() {
  const val = document.getElementById('nb-resp-name')?.value?.trim();
  if (!nbLinked && val && val.length >= 2 && !nbResults.length) nbDoSearch(val);
}

function nbRespInput(val) {
  if (nbLinked) nbUnlink(false);
  clearTimeout(nbTimer);
  const q = val.trim();
  if (q.length < 2) { nbHideDropdown(); nbShowSpinner(false); return; }
  nbShowSpinner(true);
  nbTimer = setTimeout(function() { nbDoSearch(q); }, 300);
}

function nbDoSearch(q) {
  // FIX: use search_users_barangay.php — the community search_users.php rejects barangay role
  fetch('ajax/search_users_barangay.php?q=' + encodeURIComponent(q))
    .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(function(data) {
      nbShowSpinner(false);
      nbResults  = (data.success && data.results) ? data.results : [];
      nbFocusIdx = -1;
      nbRenderDropdown(q);
    })
    .catch(function(err) { nbShowSpinner(false); console.warn('NB resp search:', err); nbHideDropdown(); });
}

function nbRenderDropdown(query) {
  const dd = document.getElementById('nb-resp-dropdown');
  if (!dd) return;
  nbPositionDropdown();

  if (!nbResults.length) {
    dd.innerHTML = '<div style="padding:11px 14px;font-size:12px;color:var(--ink-400)">No registered user found for "' + nbEsc(query) + '" - name will be saved as typed.</div>';
    dd.style.display = 'block';
    return;
  }

  const safe = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const re   = new RegExp('(' + safe + ')', 'gi');

  dd.innerHTML = nbResults.map(function(u, i) {
    const hi = nbEsc(u.name).replace(re, '<mark style="background:var(--amber-100,#fef3c7);color:inherit;border-radius:2px;padding:0 1px">$1</mark>');
    return '<div class="nb-resp-item" data-idx="' + i + '" onmousedown="nbSelect(' + i + ')" onmouseover="nbSetFocus(' + i + ')" style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--ink-50,#f8fafc)">' +
      '<span>' + hi + '</span>' +
      '<span style="font-size:10px;font-weight:700;color:var(--green-600);background:var(--green-50);border:1px solid var(--green-200);border-radius:20px;padding:2px 8px;white-space:nowrap">Registered</span></div>';
  }).join('');
  dd.style.display = 'block';
}

function nbRespKeydown(e) {
  const dd = document.getElementById('nb-resp-dropdown');
  if (!dd || dd.style.display === 'none') return;
  if (e.key === 'ArrowDown')      { e.preventDefault(); nbSetFocus(Math.min(nbFocusIdx + 1, nbResults.length - 1)); }
  else if (e.key === 'ArrowUp')   { e.preventDefault(); nbSetFocus(Math.max(nbFocusIdx - 1, 0)); }
  else if (e.key === 'Enter' && nbFocusIdx >= 0) { e.preventDefault(); nbSelect(nbFocusIdx); }
  else if (e.key === 'Escape')    { nbHideDropdown(); }
}

function nbSetFocus(idx) {
  nbFocusIdx = idx;
  document.querySelectorAll('.nb-resp-item').forEach(function(el, i) {
    el.style.background = i === idx ? 'var(--green-50,#f0fdf4)' : '';
  });
}

function nbSelect(idx) {
  const u = nbResults[idx];
  if (!u) return;
  nbRespUserId = u.id;
  document.getElementById('nb-resp-name').value              = u.name;
  document.getElementById('nb-resp-linked-name').textContent = u.name;
  if (u.contact) document.getElementById('nb-resp-contact').value = u.contact;
  document.getElementById('nb-resp-badge').style.display     = 'flex';
  document.getElementById('nb-resp-name').style.display      = 'none';
  nbLinked = true;
  nbHideDropdown();
}

function nbUnlink(clearText) {
  nbRespUserId = null;
  document.getElementById('nb-resp-badge').style.display = 'none';
  const inp = document.getElementById('nb-resp-name');
  inp.style.display = '';
  if (clearText !== false) inp.value = '';
  inp.focus();
  nbLinked = false;
}

function nbHideDropdown() {
  const dd = document.getElementById('nb-resp-dropdown');
  if (dd) { dd.style.display = 'none'; dd.innerHTML = ''; }
  nbResults  = [];
  nbFocusIdx = -1;
}

function nbShowSpinner(show) {
  const s = document.getElementById('nb-resp-spinner');
  if (s) s.style.display = show ? 'block' : 'none';
}

document.addEventListener('mousedown', function(e) {
  const wrap = document.getElementById('nb-resp-wrap');
  const dd   = document.getElementById('nb-resp-dropdown');
  if (!wrap?.contains(e.target) && !dd?.contains(e.target)) nbHideDropdown();
});

// ═══════════════════════════════════════════════════════
// FIX 2: COMPLAINANT live search
// ═══════════════════════════════════════════════════════
var nbCompTimer    = null;
var nbCompResults  = [];
var nbCompFocusIdx = -1;
var nbCompLinked   = false;

(function() {
  const dd = document.getElementById('nb-comp-dropdown');
  if (dd && dd.parentElement !== document.body) document.body.appendChild(dd);
})();

function nbCompPositionDropdown() {
  const input = document.getElementById('nb-comp-name');
  const dd    = document.getElementById('nb-comp-dropdown');
  if (!input || !dd) return;
  const rect = input.getBoundingClientRect();
  dd.style.top   = (rect.bottom + window.scrollY + 2) + 'px';
  dd.style.left  = (rect.left  + window.scrollX)     + 'px';
  dd.style.width = rect.width + 'px';
}

window.addEventListener('scroll', function() {
  if (document.getElementById('nb-comp-dropdown')?.style.display !== 'none') nbCompPositionDropdown();
}, true);
window.addEventListener('resize', function() {
  if (document.getElementById('nb-comp-dropdown')?.style.display !== 'none') nbCompPositionDropdown();
});

function nbCompFocus() {
  const val = document.getElementById('nb-comp-name')?.value?.trim();
  if (!nbCompLinked && val && val.length >= 2 && !nbCompResults.length) nbCompDoSearch(val);
}

function nbCompInput(val) {
  if (nbCompLinked) nbCompUnlink(false);
  clearTimeout(nbCompTimer);
  const q = val.trim();
  if (q.length < 2) { nbCompHideDropdown(); nbCompShowSpinner(false); return; }
  nbCompShowSpinner(true);
  nbCompTimer = setTimeout(function() { nbCompDoSearch(q); }, 300);
}

function nbCompDoSearch(q) {
  fetch('ajax/search_users_barangay.php?q=' + encodeURIComponent(q))
    .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(function(data) {
      nbCompShowSpinner(false);
      nbCompResults  = (data.success && data.results) ? data.results : [];
      nbCompFocusIdx = -1;
      nbCompRenderDropdown(q);
    })
    .catch(function(err) { nbCompShowSpinner(false); console.warn('NB comp search:', err); nbCompHideDropdown(); });
}

function nbCompRenderDropdown(query) {
  const dd = document.getElementById('nb-comp-dropdown');
  if (!dd) return;
  nbCompPositionDropdown();

  if (!nbCompResults.length) {
    dd.innerHTML = '<div style="padding:11px 14px;font-size:12px;color:var(--ink-400)">No registered user found for "' + nbEsc(query) + '" - name will be saved as typed.</div>';
    dd.style.display = 'block';
    return;
  }

  const safe = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const re   = new RegExp('(' + safe + ')', 'gi');

  dd.innerHTML = nbCompResults.map(function(u, i) {
    const hi = nbEsc(u.name).replace(re, '<mark style="background:var(--amber-100,#fef3c7);color:inherit;border-radius:2px;padding:0 1px">$1</mark>');
    return '<div class="nb-comp-item" data-idx="' + i + '" onmousedown="nbCompSelect(' + i + ')" onmouseover="nbCompSetFocus(' + i + ')" style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--ink-50,#f8fafc)">' +
      '<span>' + hi + '</span>' +
      '<span style="font-size:10px;font-weight:700;color:var(--green-600);background:var(--green-50);border:1px solid var(--green-200);border-radius:20px;padding:2px 8px;white-space:nowrap">Registered</span></div>';
  }).join('');
  dd.style.display = 'block';
}

function nbCompKeydown(e) {
  const dd = document.getElementById('nb-comp-dropdown');
  if (!dd || dd.style.display === 'none') return;
  if (e.key === 'ArrowDown')      { e.preventDefault(); nbCompSetFocus(Math.min(nbCompFocusIdx + 1, nbCompResults.length - 1)); }
  else if (e.key === 'ArrowUp')   { e.preventDefault(); nbCompSetFocus(Math.max(nbCompFocusIdx - 1, 0)); }
  else if (e.key === 'Enter' && nbCompFocusIdx >= 0) { e.preventDefault(); nbCompSelect(nbCompFocusIdx); }
  else if (e.key === 'Escape')    { nbCompHideDropdown(); }
}

function nbCompSetFocus(idx) {
  nbCompFocusIdx = idx;
  document.querySelectorAll('.nb-comp-item').forEach(function(el, i) {
    el.style.background = i === idx ? 'var(--green-50,#f0fdf4)' : '';
  });
}

function nbCompSelect(idx) {
  const u = nbCompResults[idx];
  if (!u) return;
  nbCompUserId = u.id;
  document.getElementById('nb-comp-name').value              = u.name;
  document.getElementById('nb-comp-linked-name').textContent = u.name;
  if (u.contact) document.getElementById('nb-comp-contact').value = u.contact;
  document.getElementById('nb-comp-badge').style.display     = 'flex';
  document.getElementById('nb-comp-name').style.display      = 'none';
  nbCompLinked = true;
  nbCompHideDropdown();
}

function nbCompUnlink(clearText) {
  nbCompUserId = null;
  document.getElementById('nb-comp-badge').style.display = 'none';
  const inp = document.getElementById('nb-comp-name');
  inp.style.display = '';
  if (clearText !== false) inp.value = '';
  inp.focus();
  nbCompLinked = false;
}

function nbCompHideDropdown() {
  const dd = document.getElementById('nb-comp-dropdown');
  if (dd) { dd.style.display = 'none'; dd.innerHTML = ''; }
  nbCompResults  = [];
  nbCompFocusIdx = -1;
}

function nbCompShowSpinner(show) {
  const s = document.getElementById('nb-comp-spinner');
  if (s) s.style.display = show ? 'block' : 'none';
}

document.addEventListener('mousedown', function(e) {
  const wrap = document.getElementById('nb-comp-wrap');
  const dd   = document.getElementById('nb-comp-dropdown');
  if (!wrap?.contains(e.target) && !dd?.contains(e.target)) nbCompHideDropdown();
});

// Shared escape helper
function nbEsc(s) {
  return String(s).replace(/[&<>"']/g, function(c) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}

// ══════════════════════════════════════════════════════════════
// Notification bell — live insights (non-dismissible) + discrete
// stored notifications (read/unread, read more, delete, routing)
// ══════════════════════════════════════════════════════════════
const INSIGHT_ICONS = {
  doc: '📄', warning: '⚠️', bgy: '📅', alert: '🚨',
};

let bnotifOpen = false;
let bnotifOffset = 0;
const BNOTIF_PAGE = 8;

function toggleNotifDropdown() {
  bnotifOpen = !bnotifOpen;
  document.getElementById('notif-dropdown').classList.toggle('open', bnotifOpen);
  if (bnotifOpen) loadNotifDropdown(true);
}
document.addEventListener('click', function (e) {
  const wrap = document.querySelector('.notif-bell-wrap');
  if (bnotifOpen && wrap && !wrap.contains(e.target)) {
    bnotifOpen = false;
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
  if (reset) bnotifOffset = 0;
  const list = document.getElementById('notif-dropdown-list');
  fetch('ajax/notifications_action.php?action=list&offset=' + bnotifOffset + '&limit=' + BNOTIF_PAGE)
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;
      if (reset) list.innerHTML = '';
      const existingMore = document.getElementById('bnotif-load-more');
      if (existingMore) existingMore.remove();

      if (reset && d.insights && d.insights.length) {
        let html = '<div class="notif-section-lbl">Needs Attention</div>';
        d.insights.forEach(function (n) {
          html += '<a class="notif-item insight" href="' + n.link + '" onclick="bnotifOpen=false;document.getElementById(\'notif-dropdown\').classList.remove(\'open\')">' +
            '<div class="notif-item-icon" style="color:' + n.color + '">' + (INSIGHT_ICONS[n.icon] || '📄') + '</div>' +
            '<div class="notif-item-body"><div class="notif-item-subject">' + nbEsc(n.title) + '</div><div class="notif-item-msg">' + nbEsc(n.sub) + '</div></div>' +
            '</a>';
        });
        list.insertAdjacentHTML('beforeend', html);
      }

      if (reset && (!d.notifications.length) && (!d.insights || !d.insights.length)) {
        list.insertAdjacentHTML('beforeend', '<div class="notif-dd-empty">All caught up! No notifications right now.</div>');
      }

      if (d.notifications.length) {
        if (reset) list.insertAdjacentHTML('beforeend', '<div class="notif-section-lbl">Recent Notifications</div>');
        d.notifications.forEach(function (n) { list.insertAdjacentHTML('beforeend', renderBNotifItem(n)); });
      }

      bnotifOffset += d.notifications.length;
      if (d.has_more) {
        list.insertAdjacentHTML('beforeend', '<button type="button" id="bnotif-load-more" class="notif-load-more-btn" onclick="loadNotifDropdown(false)">Load more</button>');
      }
    })
    .catch(() => { if (reset) list.innerHTML = '<div class="notif-dd-empty">Could not load notifications.</div>'; });
}

function renderBNotifItem(n) {
  const isUnread = String(n.is_unread ?? (n.status !== 'read' ? 1 : 0)) === '1';
  const msg = n.message || '';
  const isLong = msg.length > 90;
  const shortMsg = isLong ? msg.substring(0, 90) + '...' : msg;
  const viewLink = n.link_blotter_id
    ? '<span class="notif-item-viewcase" onclick="goToBNotif(' + n.id + ',' + n.link_blotter_id + ')">View Case -></span>'
    : (n.link_page ? '<span class="notif-item-viewcase" onclick="goToBNotifPage(' + n.id + ',\'' + n.link_page + '\')">View -></span>' : '');
  return `
  <div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${n.id}" data-unread="${isUnread ? '1' : '0'}">
    <div class="notif-item-body">
      <div class="notif-item-subject" onclick="markBNotifRead(${n.id})">${nbEsc(n.subject || 'Notice')}</div>
      <div class="notif-item-msg">
        <span class="notif-msg-text" data-full="${nbEsc(msg)}" data-short="${nbEsc(shortMsg)}">${nbEsc(shortMsg)}</span>
        ${isLong ? `<span class="notif-item-readmore" onclick="toggleBReadMore(this, ${n.id})">Read more</span>` : ''}
      </div>
      <div class="notif-item-foot">
        <span class="notif-item-time" title="${nbEsc(notifFullTime(n.created_at))}">${n.case_number ? nbEsc(n.case_number) + ' - ' : ''}${notifTimeAgo(n.created_at)}</span>
        <span class="notif-item-actions">
          ${viewLink}
          <span class="notif-read-toggle" onclick="toggleBNotifRead(${n.id})">${isUnread ? 'Mark read' : 'Mark unread'}</span>
        </span>
      </div>
    </div>
    <button type="button" class="notif-item-del" onclick="deleteBNotif(event, ${n.id})" title="Delete notification">x</button>
  </div>`;
}

function toggleBReadMore(el, id) {
  const msgSpan = el.previousElementSibling;
  const expanded = el.dataset.expanded === '1';
  msgSpan.textContent = expanded ? msgSpan.dataset.short : msgSpan.dataset.full;
  el.textContent = expanded ? 'Read more' : 'Show less';
  el.dataset.expanded = expanded ? '0' : '1';
  markBNotifRead(id);
}

function setBNotifReadState(id, isUnread) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el) return;
  el.classList.toggle('unread', isUnread);
  el.dataset.unread = isUnread ? '1' : '0';
  const toggle = el.querySelector('.notif-read-toggle');
  if (toggle) toggle.textContent = isUnread ? 'Mark read' : 'Mark unread';
}

function markBNotifRead(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el || el.dataset.unread !== '1') return;
  setBNotifReadState(id, false);
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_read', id }) })
    .then(() => refreshNotifBadge()).catch(() => { setBNotifReadState(id, true); showToast('Could not mark as read.', 'error'); });
}

function markBNotifUnread(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el || el.dataset.unread === '1') return;
  setBNotifReadState(id, true);
  fetch('ajax/notifications_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'mark_unread', id }) })
    .then(() => refreshNotifBadge()).catch(() => { setBNotifReadState(id, false); showToast('Could not mark as unread.', 'error'); });
}

function toggleBNotifRead(id) {
  const el = document.querySelector('.notif-item[data-id="' + id + '"]');
  if (!el) return;
  if (el.dataset.unread === '1') markBNotifRead(id);
  else markBNotifUnread(id);
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

function deleteBNotif(evt, id) {
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

function goToBNotif(id, blotterId) {
  markBNotifRead(id);
  bnotifOpen = false;
  document.getElementById('notif-dropdown').classList.remove('open');
  viewBlotter(blotterId);
}
function goToBNotifPage(id, page) {
  markBNotifRead(id);
  window.location.href = '?page=' + page;
}

refreshNotifBadge();
setInterval(refreshNotifBadge, 60000);
</script>

<style>
@keyframes nb-spin { to { transform: rotate(360deg); } }
.nb-resp-item:last-child { border-bottom: none !important; }
.nb-resp-item:hover      { background: var(--green-50, #f0fdf4) !important; }
.nb-comp-item:last-child { border-bottom: none !important; }
.nb-comp-item:hover      { background: var(--green-50, #f0fdf4) !important; }
</style>
<div class="sb-overlay" onclick="toggleSidebar()"></div>
<script>
function toggleSidebar(){
  document.querySelector('.sidebar').classList.toggle('open');
  document.querySelector('.sb-overlay').classList.toggle('show');
}
</script>
</body>
</html>
