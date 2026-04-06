<?php
// pages/dashboard.php — Superadmin · Live stats + Analytics + Forecasting

// ── KPI stats ─────────────────────────────────────────────────
$stats = [
    'total_barangays'   => 0,
    'total_users'       => 0,
    'total_blotters'    => 0,
    'pending_approval'  => 0,
    'active_violators'  => 0,
    'resolved_rate'     => 0,
    'pending_mediation' => 0,
    'total_penalties'   => 0,
    'active_cases'      => 0,
    'no_shows'          => 0,
    'avg_res_days'      => 0,
    'overdue_penalties' => 0,
];

try {
    $stats['total_barangays']  = (int)$pdo->query("SELECT COUNT(*) FROM barangays WHERE is_active=1")->fetchColumn();
    $stats['total_users']      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetchColumn();
    $stats['pending_approval'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='community'")->fetchColumn();

    $b = $pdo->query("SELECT COUNT(*) as total, SUM(status IN ('resolved','closed')) as done, SUM(status NOT IN ('resolved','closed','transferred')) as active FROM blotters")->fetch();
    $stats['total_blotters'] = (int)($b['total']  ?? 0);
    $stats['active_cases']   = (int)($b['active'] ?? 0);
    $stats['resolved_rate']  = $b['total'] > 0 ? round($b['done'] / $b['total'] * 100) : 0;

    $stats['pending_mediation'] = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules WHERE status='scheduled'")->fetchColumn();
    $stats['active_violators']  = (int)$pdo->query("SELECT COUNT(DISTINCT respondent_name) FROM blotters WHERE status NOT IN ('resolved','closed','transferred') AND respondent_name NOT IN ('','Unknown')")->fetchColumn();
    $stats['total_penalties']   = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM penalties WHERE status='pending'")->fetchColumn() ?? 0);
    $stats['overdue_penalties'] = (int)$pdo->query("SELECT COUNT(*) FROM penalties WHERE status='overdue'")->fetchColumn();
    $stats['no_shows']          = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules WHERE missed_session=1")->fetchColumn();

    $avg = $pdo->query("SELECT AVG(DATEDIFF(updated_at,created_at)) FROM blotters WHERE status IN ('resolved','closed') AND updated_at > created_at")->fetchColumn();
    $stats['avg_res_days'] = round((float)($avg ?? 0), 1);

} catch (PDOException $e) {}

// ── Monthly trend (last 12 months) ────────────────────────────
$monthly_filed    = array_fill(0, 12, 0);
$monthly_resolved = array_fill(0, 12, 0);
$month_labels     = [];
try {
    for ($i = 11; $i >= 0; $i--) $month_labels[] = date('M Y', strtotime("-{$i} months"));

    $rows = $pdo->query("
        SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, COUNT(*) as cnt
        FROM blotters WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY ym ORDER BY ym ASC
    ")->fetchAll();
    foreach ($rows as $r) {
        $idx = (int)date('n', strtotime($r['ym'].'-01')) - (int)date('n', strtotime('-11 months'));
        $idx = ($idx + 12) % 12;
        if (isset($monthly_filed[$idx])) $monthly_filed[$idx] = (int)$r['cnt'];
    }
    $res_rows = $pdo->query("
        SELECT DATE_FORMAT(updated_at,'%Y-%m') as ym, COUNT(*) as cnt
        FROM blotters WHERE status IN ('resolved','closed') AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY ym ORDER BY ym ASC
    ")->fetchAll();
    foreach ($res_rows as $r) {
        $idx = (int)date('n', strtotime($r['ym'].'-01')) - (int)date('n', strtotime('-11 months'));
        $idx = ($idx + 12) % 12;
        if (isset($monthly_resolved[$idx])) $monthly_resolved[$idx] = (int)$r['cnt'];
    }
} catch (PDOException $e) {}

// ── Forecasting: linear regression on last 6 months → next 3 ─
$fc_months = [];
$fc_data   = [];
$recent_6  = array_slice($monthly_filed, -6);
$n = count($recent_6);
$sx=0; $sy=0; $sxy=0; $sxx=0;
for ($i=0; $i<$n; $i++) { $sx+=$i; $sy+=$recent_6[$i]; $sxy+=$i*$recent_6[$i]; $sxx+=$i*$i; }
$den       = ($n*$sxx - $sx*$sx);
$slope     = $den!=0 ? ($n*$sxy - $sx*$sy)/$den : 0;
$intercept = $n>0 ? ($sy - $slope*$sx)/$n : 0;
for ($i=1; $i<=3; $i++) {
    $fc_months[] = date('M Y', strtotime("+{$i} months"));
    $fc_data[]   = max(0, (int)round($intercept + $slope*($n-1+$i)));
}
// Chart arrays bridging actual → forecast
$fc_chart_labels  = array_merge($month_labels, $fc_months);
$fc_chart_actual  = array_merge($monthly_filed, [null,null,null]);
$fc_chart_fc      = array_merge(array_fill(0,11,null), [end($monthly_filed)], $fc_data);

$last_actual = (int)(end($recent_6) ?: 0);
$next_fc     = $fc_data[0] ?? 0;
$fc_pct      = $last_actual > 0 ? abs(round(($next_fc - $last_actual)/$last_actual*100)) : 0;
$fc_dir      = $next_fc > $last_actual ? 'up' : ($next_fc < $last_actual ? 'down' : 'flat');

// ── Incident type distribution ────────────────────────────────
$incident_types  = [];
$incident_counts = [];
try {
    $rows = $pdo->query("
        SELECT incident_type, COUNT(*) as cnt FROM blotters
        GROUP BY incident_type ORDER BY cnt DESC LIMIT 8
    ")->fetchAll();
    foreach ($rows as $r) { $incident_types[] = $r['incident_type']; $incident_counts[] = (int)$r['cnt']; }
} catch (PDOException $e) {}
if (empty($incident_types)) { $incident_types = ['No data']; $incident_counts = [0]; }

// ── Per-barangay summary (with trend vs last month) ───────────
$bgy_rows = [];
try {
    $bgy_rows = $pdo->query("
        SELECT b.id, b.name, b.municipality,
               COUNT(bl.id)                                                   AS total_blotters,
               SUM(bl.status IN ('resolved','closed'))                        AS resolved,
               SUM(bl.status NOT IN ('resolved','closed','transferred'))      AS active,
               SUM(bl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))        AS this_month,
               SUM(bl.created_at BETWEEN DATE_SUB(NOW(),INTERVAL 60 DAY) AND DATE_SUB(NOW(),INTERVAL 30 DAY)) AS last_month
        FROM barangays b
        LEFT JOIN blotters bl ON bl.barangay_id = b.id
        WHERE b.is_active = 1
        GROUP BY b.id
        ORDER BY total_blotters DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// ── Repeat violators (system-wide) ───────────────────────────
$repeat_violators = [];
try {
    $repeat_violators = $pdo->query("
        SELECT respondent_name,
               COUNT(*)                                                    AS total_cases,
               SUM(status NOT IN ('resolved','closed','transferred'))      AS active_cases,
               SUM(violation_level IN ('serious','critical'))              AS severe_cases,
               MAX(created_at)                                             AS last_incident,
               GROUP_CONCAT(DISTINCT barangay_id)                         AS barangay_ids,
               GROUP_CONCAT(DISTINCT incident_type ORDER BY created_at DESC SEPARATOR ', ') AS incident_types
        FROM blotters
        WHERE respondent_name NOT IN ('','Unknown')
        GROUP BY respondent_name
        HAVING total_cases >= 2
        ORDER BY total_cases DESC, active_cases DESC
        LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {}

function saRisk(array $rv): int {
    $s  = min((int)$rv['total_cases'],  8) * 10;
    $s += min((int)$rv['active_cases'], 3) * 5;
    $s += ((int)$rv['severe_cases'] > 0) ? 15 : 0;
    // Cross-barangay bonus
    $bids = array_filter(explode(',', $rv['barangay_ids']??''));
    if (count($bids) > 1) $s += 10;
    return min(100, $s);
}

// ── Severity distribution (system-wide) ──────────────────────
$sev_data = ['minor'=>0,'moderate'=>0,'serious'=>0,'critical'=>0];
try {
    $rows = $pdo->query("SELECT violation_level, COUNT(*) c FROM blotters GROUP BY violation_level")->fetchAll();
    foreach ($rows as $r) if (isset($sev_data[$r['violation_level']])) $sev_data[$r['violation_level']] = (int)$r['c'];
} catch (PDOException $e) {}

// ── Prescribed actions (system-wide) ─────────────────────────
$action_data = [];
try {
    $rows = $pdo->query("SELECT prescribed_action, COUNT(*) c FROM blotters GROUP BY prescribed_action ORDER BY c DESC")->fetchAll();
    foreach ($rows as $r) $action_data[$r['prescribed_action']] = (int)$r['c'];
} catch (PDOException $e) {}
$action_labels = array_map(fn($k)=>ucwords(str_replace('_',' ',$k)), array_keys($action_data));
$action_vals   = array_values($action_data);

// ── Day-of-week heatmap ───────────────────────────────────────
$dow_data   = array_fill(0,7,0);
$dow_labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
try {
    $rows = $pdo->query("SELECT DAYOFWEEK(incident_date)-1 AS dow, COUNT(*) AS c FROM blotters GROUP BY dow")->fetchAll();
    foreach ($rows as $r) $dow_data[(int)$r['dow']] = (int)$r['c'];
} catch (PDOException $e) {}
$dow_max = max(1, ...array_values($dow_data));

// ── Per-barangay monthly (for bar comparison chart) ───────────
$bgy_chart_names  = [];
$bgy_chart_active = [];
$bgy_chart_res    = [];
foreach (array_slice($bgy_rows, 0, 6) as $b) {
    $bgy_chart_names[]  = $b['name'];
    $bgy_chart_active[] = (int)$b['active'];
    $bgy_chart_res[]    = (int)$b['resolved'];
}

// ── Recent blotters & pending approvals ──────────────────────
$recent_blotters = [];
try {
    $recent_blotters = $pdo->query("
        SELECT bl.case_number, bl.complainant_name, bl.incident_type,
               bl.violation_level, bl.status, bl.created_at, bg.name as barangay_name
        FROM blotters bl
        JOIN barangays bg ON bg.id = bl.barangay_id
        ORDER BY bl.created_at DESC LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {}

$pending_users = [];
try {
    $pending_users = $pdo->query("
        SELECT u.id, u.full_name, u.email, u.created_at, b.name as barangay_name
        FROM users u
        LEFT JOIN barangays b ON b.id = u.barangay_id
        WHERE u.is_active=0 AND u.role='community'
        ORDER BY u.created_at DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {}

// ── Month-over-month incident types ──────────────────────────
$type_this = []; $type_last = [];
try {
    $rows = $pdo->query("SELECT incident_type, COUNT(*) c FROM blotters WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) GROUP BY incident_type ORDER BY c DESC LIMIT 6")->fetchAll();
    foreach ($rows as $r) $type_this[$r['incident_type']] = (int)$r['c'];
    $rows2 = $pdo->query("SELECT incident_type, COUNT(*) c FROM blotters WHERE MONTH(created_at)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) GROUP BY incident_type ORDER BY c DESC LIMIT 6")->fetchAll();
    foreach ($rows2 as $r) $type_last[$r['incident_type']] = (int)$r['c'];
} catch (PDOException $e) {}
$all_types = array_unique(array_merge(array_keys($type_this), array_keys($type_last)));
usort($all_types, fn($a,$b)=>($type_this[$b]??0)-($type_this[$a]??0));
?>

<!-- ════════════════════════════════════════════
     SUPERADMIN DASHBOARD-ONLY STYLES
════════════════════════════════════════════ -->
<style>
.sa-section-hdr {
    display:flex; align-items:center; gap:10px; margin:26px 0 14px;
    font-size:11px; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:var(--ink-400);
}
.sa-section-hdr span { white-space:nowrap; }
.sa-section-hdr::after { content:''; flex:1; height:1px; background:var(--ink-100); }

/* Secondary stat row */
.sa-stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.sa-stat { background:var(--white); border:1px solid var(--ink-100); border-radius:var(--r-lg);
    padding:14px 16px; display:flex; align-items:center; gap:12px; }
.sa-stat-icon { width:36px; height:36px; border-radius:var(--r-sm); display:flex;
    align-items:center; justify-content:center; flex-shrink:0; font-size:16px; }
.sa-stat-val  { font-size:22px; font-weight:700; color:var(--ink-900); line-height:1.1; }
.sa-stat-lbl  { font-size:11px; color:var(--ink-400); font-weight:500; margin-top:2px; }

/* Forecast */
.card-forecast-sa { border-top:3px solid var(--indigo-400,#818CF8) !important; }
.fc-badge-sa { display:inline-flex; align-items:center; gap:4px; font-size:11px;
    font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap; }
.fc-up-sa   { background:var(--rose-50);    color:var(--rose-600); }
.fc-down-sa { background:var(--emerald-50); color:var(--emerald-600); }
.fc-flat-sa { background:var(--ink-50);     color:var(--ink-400); }

.fc-pills-sa { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
.fc-pill-sa  { flex:1; min-width:80px; background:#EEF2FF; border:1px solid #C7D2FE;
    border-radius:var(--r-md); padding:10px 12px; text-align:center; }
.fc-pill-mo  { font-size:11px; font-weight:600; color:#4338CA; }
.fc-pill-val { font-size:24px; font-weight:800; color:var(--ink-900); line-height:1.1; }
.fc-pill-lbl { font-size:10px; color:var(--ink-400); margin-top:2px; }

/* Insight box */
.sa-insight { background:var(--navy-50,#EDF5FB); border:1px solid var(--navy-200,#AECFE8);
    border-radius:var(--r-md); padding:11px 14px; font-size:12px;
    color:var(--navy-700,#1A3252); line-height:1.6; margin-top:14px; }
.sa-insight strong { color:var(--navy-800,#122338); }

/* Risk rows */
.risk-row { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--surface-2); }
.risk-row:last-child { border-bottom:none; }
.risk-name { flex:1; min-width:0; font-size:12px; font-weight:600; color:var(--ink-900);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.risk-meta { font-size:10px; color:var(--ink-400); display:block; margin-top:1px; }
.risk-bar-wrap { width:80px; flex-shrink:0; }
.risk-bar-bg   { height:6px; background:var(--surface-2); border-radius:10px; overflow:hidden; }
.risk-bar-fill { height:100%; border-radius:10px; }
.risk-score    { font-size:11px; font-weight:700; width:30px; text-align:right; flex-shrink:0; }
.rhi  { background:var(--rose-400); }  .rhi-t { color:var(--rose-600); }
.rmid { background:var(--amber-400); } .rmid-t{ color:var(--amber-600); }
.rlo  { background:var(--emerald-400); }.rlo-t { color:var(--emerald-600); }

/* DoW heatmap */
.dow-grid-sa { display:grid; grid-template-columns:repeat(7,1fr); gap:5px; margin-top:10px; }
.dow-cell-sa { border-radius:var(--r-sm); padding:8px 4px; text-align:center; }
.dow-day-sa  { font-size:10px; font-weight:600; }
.dow-num-sa  { font-size:15px; font-weight:700; margin-top:2px; }

/* MoM */
.mom-row { display:flex; align-items:center; gap:8px; padding:7px 0;
    border-bottom:1px solid var(--surface-2); font-size:12px; }
.mom-row:last-child { border-bottom:none; }
.mom-type  { flex:1; color:var(--ink-700); font-weight:500; white-space:nowrap;
    overflow:hidden; text-overflow:ellipsis; }
.mom-cnt   { font-weight:700; color:var(--ink-900); min-width:18px; text-align:right; }
.mom-delta { font-size:10px; font-weight:700; padding:1px 6px;
    border-radius:10px; white-space:nowrap; margin-left:2px; }
.md-up   { background:var(--rose-50);    color:var(--rose-600); }
.md-down { background:var(--emerald-50); color:var(--emerald-600); }
.md-new  { background:var(--amber-50);   color:var(--amber-600); }
.md-gone { background:var(--ink-50);     color:var(--ink-400); }

/* Barangay trend delta */
.bgy-delta { font-size:10px; font-weight:700; padding:1px 5px; border-radius:10px; margin-left:4px; }
.bd-up   { background:var(--rose-50);    color:var(--rose-600); }
.bd-down { background:var(--emerald-50); color:var(--emerald-600); }
.bd-flat { background:var(--ink-50);     color:var(--ink-400); }

@media(max-width:1100px){ .sa-stat-row{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:768px) { .sa-stat-row{ grid-template-columns:1fr 1fr; } .fc-pills-sa{ flex-direction:column; } }
</style>

<!-- ══════════ PAGE HEADER ══════════ -->
<div class="page-header">
  <div class="page-header-left">
    <h2>Overview Dashboard</h2>
    <p>Municipality-wide blotter system snapshot · <?= date('F j, Y') ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-outline btn-sm" onclick="location.reload()">↻ Refresh</button>
    <a href="?page=reports" class="btn btn-primary btn-sm">View Full Reports</a>
  </div>
</div>

<?php if ($stats['pending_approval'] > 0): ?>
<div class="alert alert-amber mb22">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--amber-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11" r=".5" fill="currentColor"/></svg>
  <div class="alert-text">
    <strong><?= $stats['pending_approval'] ?> community account(s) awaiting approval</strong>
    <span>New registrations need your review. <a href="?page=users&filter=pending" style="color:var(--amber-600);font-weight:600">Review now →</a></span>
  </div>
</div>
<?php endif; ?>

<!-- ══════════ PRIMARY KPI CARDS ══════════ -->
<div class="kpi-grid mb22">
  <div class="kpi-card kc-indigo">
    <div class="kpi-top">
      <div class="kpi-icon ki-indigo"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M2 16V8l7-6 7 6v8"/><path d="M7 16v-5h4v5"/></svg></div>
      <span class="kpi-trend trend-flat">—</span>
    </div>
    <div class="kpi-val"><?= $stats['total_barangays'] ?></div>
    <div class="kpi-lbl">Active Barangays</div>
  </div>
  <div class="kpi-card kc-cyan">
    <div class="kpi-top">
      <div class="kpi-icon ki-cyan"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="6.5" r="3"/><path d="M3 16c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg></div>
      <span class="kpi-trend trend-up">↑ users</span>
    </div>
    <div class="kpi-val"><?= $stats['total_users'] ?></div>
    <div class="kpi-lbl">Registered Users</div>
  </div>
  <div class="kpi-card kc-amber">
    <div class="kpi-top">
      <div class="kpi-icon ki-amber"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M5 7h8M5 10h6"/></svg></div>
      <span class="kpi-trend trend-up">all time</span>
    </div>
    <div class="kpi-val"><?= $stats['total_blotters'] ?></div>
    <div class="kpi-lbl">Total Blotters Filed</div>
  </div>
  <div class="kpi-card kc-emerald">
    <div class="kpi-top">
      <div class="kpi-icon ki-emerald"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M4 9.5l3.5 3.5 7-7"/></svg></div>
      <span class="kpi-trend <?= $stats['resolved_rate']>=70?'trend-up':'trend-down' ?>"><?= $stats['resolved_rate'] ?>%</span>
    </div>
    <div class="kpi-val"><?= $stats['resolved_rate'] ?>%</div>
    <div class="kpi-lbl">System Resolution Rate</div>
  </div>
</div>

<!-- ══════════ ANALYTICS SNAPSHOT ══════════ -->
<div class="sa-section-hdr">
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M1 10l3-3 2.5 2.5L10 5l3 3"/></svg>
  <span>Analytics Snapshot</span>
</div>

<div class="sa-stat-row">
  <div class="sa-stat">
    <div class="sa-stat-icon" style="background:#EEF2FF">⏱</div>
    <div>
      <div class="sa-stat-val"><?= $stats['avg_res_days'] ?>d</div>
      <div class="sa-stat-lbl">Avg. Resolution Time</div>
    </div>
  </div>
  <div class="sa-stat">
    <div class="sa-stat-icon" style="background:var(--rose-50)">🚫</div>
    <div>
      <div class="sa-stat-val"><?= $stats['no_shows'] ?></div>
      <div class="sa-stat-lbl">Total Mediation No-Shows</div>
    </div>
  </div>
  <div class="sa-stat">
    <div class="sa-stat-icon" style="background:var(--amber-50)">⚖️</div>
    <div>
      <div class="sa-stat-val">₱<?= number_format($stats['total_penalties'], 0) ?></div>
      <div class="sa-stat-lbl">Pending Penalty Amount</div>
    </div>
  </div>
  <div class="sa-stat">
    <div class="sa-stat-icon" style="background:var(--violet-50)">🔁</div>
    <div>
      <div class="sa-stat-val"><?= count($repeat_violators) ?></div>
      <div class="sa-stat-lbl">System-wide Repeat Violators</div>
    </div>
  </div>
</div>

<!-- ══════════ TREND + INCIDENT TYPES ══════════ -->
<div class="g32 mb22">
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Monthly Blotter Trend</div><div class="card-subtitle">All barangays combined · last 12 months</div></div>
    </div>
    <div class="card-body"><div style="height:220px"><canvas id="chart-trend"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Incident Breakdown</div></div>
    <div class="card-body"><div style="height:220px"><canvas id="chart-types"></canvas></div></div>
  </div>
</div>

<!-- ══════════ PREDICTIVE ANALYTICS ══════════ -->
<div class="sa-section-hdr">
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><polyline points="1,13 5,7 8,10 11,4 13,6"/><polyline points="11,1 13,1 13,3" stroke-width="1.4"/></svg>
  <span>Predictive Analytics</span>
</div>

<div class="g21 mb22">

  <!-- Forecast card -->
  <div class="card card-forecast-sa">
    <div class="card-header">
      <div>
        <div class="card-title">📈 System-wide Blotter Forecast</div>
        <div class="card-subtitle">Linear regression · last 6 months → next 3 months</div>
      </div>
      <?php
        $fc_cls = $fc_dir==='up' ? 'fc-up-sa' : ($fc_dir==='down' ? 'fc-down-sa' : 'fc-flat-sa');
        $fc_arr = $fc_dir==='up' ? '▲' : ($fc_dir==='down' ? '▼' : '→');
      ?>
      <span class="fc-badge-sa <?= $fc_cls ?>"><?= $fc_arr ?> <?= $fc_pct ?>% vs now</span>
    </div>
    <div class="card-body">
      <div style="height:200px"><canvas id="chart-forecast"></canvas></div>
      <div class="fc-pills-sa">
        <?php foreach ($fc_months as $i => $fm): ?>
        <div class="fc-pill-sa">
          <div class="fc-pill-mo"><?= $fm ?></div>
          <div class="fc-pill-val"><?= $fc_data[$i] ?></div>
          <div class="fc-pill-lbl">projected cases</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php
        $total_fc  = array_sum($fc_data);
        $trend_wrd = $fc_dir==='up' ? 'increase' : ($fc_dir==='down' ? 'decrease' : 'remain stable');
      ?>
      <div class="sa-insight">
        <strong>System Insight:</strong> Municipality-wide blotter volume is projected to
        <strong><?= $trend_wrd ?></strong> over the next 3 months — an estimated
        <strong><?= $total_fc ?> total cases</strong> across all barangays.
        <?php if ($fc_dir==='up'): ?>
        Recommend coordinating with barangay captains for pre-emptive community interventions.
        <?php elseif ($fc_dir==='down'): ?>
        Current system-wide conflict resolution programs are showing positive impact.
        <?php else: ?>
        Maintain current inter-barangay coordination and staffing levels.
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Repeat Violators system-wide -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">🔁 Repeat Violator Risk</div>
        <div class="card-subtitle">Respondents with 2+ blotters across all barangays</div>
      </div>
      <a href="?page=reports" class="act-btn">View all</a>
    </div>
    <div class="card-body" style="padding:8px 18px">
      <?php if (empty($repeat_violators)): ?>
        <div style="text-align:center;padding:24px;color:var(--ink-300);font-size:12px">
          <div style="font-size:28px;margin-bottom:8px">✅</div>
          No repeat violators on record
        </div>
      <?php else: foreach ($repeat_violators as $rv):
          $score = saRisk($rv);
          $rc = $score>=65 ? 'rhi'  : ($score>=35 ? 'rmid'  : 'rlo');
          $rt = $score>=65 ? 'rhi-t': ($score>=35 ? 'rmid-t': 'rlo-t');
          $rl = $score>=65 ? 'High' : ($score>=35 ? 'Med'   : 'Low');
          $bids = array_filter(explode(',', $rv['barangay_ids']??''));
          $cross = count($bids) > 1 ? '<span style="font-size:9px;background:#EEF2FF;color:#4338CA;padding:1px 5px;border-radius:8px;font-weight:700;margin-left:4px">cross-bgy</span>' : '';
      ?>
        <div class="risk-row">
          <div style="flex:1;min-width:0">
            <div class="risk-name"><?= htmlspecialchars($rv['respondent_name']) ?><?= $cross ?></div>
            <span class="risk-meta"><?= $rv['total_cases'] ?> cases · <?= $rv['active_cases'] ?> active · <?= htmlspecialchars(mb_strimwidth($rv['incident_types'],0,28,'…')) ?></span>
          </div>
          <div class="risk-bar-wrap">
            <div style="font-size:9px;color:var(--ink-400);text-align:right;margin-bottom:2px"><?= $rl ?></div>
            <div class="risk-bar-bg"><div class="risk-bar-fill <?= $rc ?>" style="width:<?= $score ?>%"></div></div>
          </div>
          <div class="risk-score <?= $rt ?>"><?= $score ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php if (!empty($repeat_violators)): ?>
    <div class="card-foot" style="font-size:11px;color:var(--ink-400)">
      Risk 0–100 · frequency (50%) + active cases (30%) + severity (20%) · cross-barangay +10
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════ PATTERNS & DISTRIBUTION ══════════ -->
<div class="sa-section-hdr">
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1" y="1" width="5" height="5" rx="1"/><rect x="8" y="1" width="5" height="5" rx="1"/><rect x="1" y="8" width="5" height="5" rx="1"/><rect x="8" y="8" width="5" height="5" rx="1"/></svg>
  <span>Patterns &amp; Distribution</span>
</div>

<div class="g3 mb22">

  <!-- DoW heatmap -->
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">📅 Peak Incident Days</div><div class="card-subtitle">All barangays · by day of week</div></div>
    </div>
    <div class="card-body">
      <div class="dow-grid-sa">
        <?php foreach ($dow_labels as $di => $dl):
          $val       = $dow_data[$di];
          $intensity = $dow_max > 0 ? $val/$dow_max : 0;
          $opacity   = round(0.07 + $intensity*0.85, 2);
          $bg        = "rgba(67,56,202,{$opacity})";
          $txt_col   = $intensity > 0.5 ? '#fff' : '#3730A3';
          $lbl_col   = $intensity > 0.5 ? 'rgba(255,255,255,.75)' : 'var(--ink-400)';
        ?>
        <div class="dow-cell-sa" style="background:<?= $bg ?>">
          <div class="dow-day-sa" style="color:<?= $lbl_col ?>"><?= $dl ?></div>
          <div class="dow-num-sa" style="color:<?= $txt_col ?>"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php
        $pk_idx  = array_search(max($dow_data), $dow_data);
        $pk_day  = $dow_labels[$pk_idx ?? 0];
        $weekend = ($dow_data[0]+$dow_data[6]) > 0;
      ?>
      <div class="sa-insight" style="margin-top:12px">
        <strong>Peak day:</strong> <?= $pk_day ?> records the most incidents municipality-wide.
        <?= $weekend ? 'Weekend incidents noted — evaluate barangay officer weekend schedules.' : 'Most incidents fall on weekdays.' ?>
      </div>
    </div>
  </div>

  <!-- MoM type comparison -->
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">📊 Incident Type Trends</div><div class="card-subtitle">This month vs last month</div></div>
    </div>
    <div class="card-body" style="padding:10px 18px">
      <?php if (empty($all_types)): ?>
        <div style="text-align:center;padding:20px;color:var(--ink-300);font-size:12px">No data this period</div>
      <?php else: foreach (array_slice($all_types,0,6) as $t):
          $cur = $type_this[$t] ?? 0;
          $prv = $type_last[$t] ?? 0;
          if ($cur===0 && $prv>0)     { $dc='md-gone'; $dd='Gone'; }
          elseif ($prv===0 && $cur>0) { $dc='md-new';  $dd='New'; }
          elseif ($cur>$prv)          { $dc='md-up';   $dd='+'.($cur-$prv); }
          elseif ($cur<$prv)          { $dc='md-down'; $dd='-'.($prv-$cur); }
          else                        { $dc='';         $dd='='; }
      ?>
        <div class="mom-row">
          <span class="mom-type"><?= htmlspecialchars($t) ?></span>
          <span class="mom-cnt"><?= $cur ?></span>
          <span class="mom-delta <?= $dc ?>"><?= $dd ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Prescribed actions pie -->
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">⚖️ Prescribed Actions</div><div class="card-subtitle">System-wide case handling</div></div>
    </div>
    <div class="card-body"><div style="height:190px"><canvas id="chart-actions"></canvas></div></div>
  </div>
</div>

<!-- ══════════ BARANGAY PERFORMANCE + SIDEBAR ══════════ -->
<div class="g21 mb22">

  <!-- Per-barangay table (enhanced with trend) -->
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Barangay Performance</div><div class="card-subtitle">Blotter counts, resolution rate, and 30-day trend</div></div>
      <a href="?page=barangays" class="act-btn">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Barangay</th><th>Total</th><th>Active</th><th>Resolved</th><th>Rate</th><th>30d Trend</th></tr></thead>
        <tbody>
        <?php if (empty($bgy_rows)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--ink-300);padding:24px">No barangay data yet</td></tr>
        <?php else: foreach ($bgy_rows as $b):
          $rate = $b['total_blotters'] > 0 ? round($b['resolved']/$b['total_blotters']*100) : 0;
          $initials = strtoupper(implode('', array_map(fn($w)=>$w[0], array_filter(explode(' ', $b['name']), fn($w)=>strlen($w)>2))));
          $this_mo = (int)$b['this_month'];
          $last_mo = (int)$b['last_month'];
          if ($this_mo > $last_mo)      { $dc='bd-up';   $dd='▲'.($this_mo-$last_mo); }
          elseif ($this_mo < $last_mo)  { $dc='bd-down'; $dd='▼'.($last_mo-$this_mo); }
          else                          { $dc='bd-flat';  $dd='—'; }
        ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:6px;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0"><?= htmlspecialchars(substr($initials,0,3)) ?></div>
                <div>
                  <div class="td-main" style="font-size:13px"><?= htmlspecialchars($b['name']) ?></div>
                  <div style="font-size:11px;color:var(--ink-400)"><?= htmlspecialchars($b['municipality']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-weight:600"><?= (int)$b['total_blotters'] ?></td>
            <td><span class="chip chip-amber"><?= (int)$b['active'] ?></span></td>
            <td><span class="chip chip-emerald"><?= (int)$b['resolved'] ?></span></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <div style="width:48px;height:5px;background:var(--surface-2);border-radius:10px;overflow:hidden">
                  <div style="width:<?= $rate ?>%;height:100%;background:<?= $rate>=70?'var(--emerald-400)':($rate>=40?'var(--amber-400)':'var(--rose-400)') ?>;border-radius:10px"></div>
                </div>
                <span style="font-size:11px;font-weight:600;color:var(--ink-600)"><?= $rate ?>%</span>
              </div>
            </td>
            <td><span class="bgy-delta <?= $dc ?>"><?= $dd ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sidebar: Barangay comparison chart + Pending approvals -->
  <div>
    <div class="card mb16">
      <div class="card-header">
        <div><div class="card-title">📊 Barangay Comparison</div><div class="card-subtitle">Active vs resolved per barangay</div></div>
      </div>
      <div class="card-body"><div style="height:200px"><canvas id="chart-bgy-compare"></canvas></div></div>
    </div>

    <?php if (!empty($pending_users)): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Pending Approvals</div>
        <a href="?page=users&filter=pending" class="act-btn">View all</a>
      </div>
      <div class="card-body" style="padding:0 18px">
        <?php foreach ($pending_users as $pu): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--surface-2)">
          <div style="width:30px;height:30px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
            <?= strtoupper(substr($pu['full_name'],0,2)) ?>
          </div>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:500;color:var(--ink-900)"><?= htmlspecialchars($pu['full_name']) ?></div>
            <div style="font-size:11px;color:var(--ink-400)"><?= htmlspecialchars($pu['barangay_name']??'—') ?> · <?= date('M j', strtotime($pu['created_at'])) ?></div>
          </div>
          <div style="display:flex;gap:4px">
            <button class="btn btn-success btn-xs" onclick="approveUser(<?= (int)$pu['id'] ?>, this)">Approve</button>
            <button class="act-btn danger btn-xs" onclick="suspendUser(<?= (int)$pu['id'] ?>, this)">Reject</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════ RECENT BLOTTERS ══════════ -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Blotters (All Barangays)</div>
    <a href="?page=reports" class="act-btn">Full report</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Barangay</th><th>Complainant</th><th>Incident</th><th>Level</th><th>Status</th><th>Filed</th></tr></thead>
      <tbody>
      <?php if (empty($recent_blotters)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--ink-300);padding:24px">No blotters filed yet.</td></tr>
      <?php else: foreach ($recent_blotters as $bl):
        $lvl_map = ['minor'=>'chip-emerald','moderate'=>'chip-amber','serious'=>'chip-rose','critical'=>'chip-violet'];
        $st_map  = ['pending_review'=>'status-pending','active'=>'status-active','resolved'=>'status-active','closed'=>'status-inactive','escalated'=>'chip-violet','mediation_set'=>'chip-cyan'];
      ?>
        <tr>
          <td class="td-mono"><?= htmlspecialchars($bl['case_number']) ?></td>
          <td><?= htmlspecialchars($bl['barangay_name']) ?></td>
          <td class="td-main"><?= htmlspecialchars($bl['complainant_name']) ?></td>
          <td><?= htmlspecialchars($bl['incident_type']) ?></td>
          <td><span class="chip <?= $lvl_map[$bl['violation_level']] ?? 'chip-slate' ?>"><?= ucfirst($bl['violation_level']) ?></span></td>
          <td><span class="chip <?= $st_map[$bl['status']] ?? 'chip-slate' ?>"><?= ucwords(str_replace('_',' ',$bl['status'])) ?></span></td>
          <td style="color:var(--ink-400);font-size:12px"><?= date('M j, Y', strtotime($bl['created_at'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══════════ CHART.JS INIT ══════════ -->
<script>
Chart.defaults.font.family = "'Sora', sans-serif";
Chart.defaults.color = '#5B6F8F';

// Monthly Trend
new Chart(document.getElementById('chart-trend'), {
  type:'bar',
  data:{
    labels: <?= json_encode($month_labels) ?>,
    datasets:[
      {label:'Filed',    data:<?= json_encode(array_values($monthly_filed)) ?>,    backgroundColor:'rgba(67,56,202,.15)', borderColor:'#4338CA', borderWidth:2, borderRadius:4},
      {label:'Resolved', data:<?= json_encode(array_values($monthly_resolved)) ?>, backgroundColor:'rgba(4,120,87,.12)',   borderColor:'#047857', borderWidth:2, borderRadius:4}
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'top',labels:{boxWidth:10,padding:16,font:{size:11}}}},
    scales:{y:{grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{size:11}}},
            x:{grid:{display:false},ticks:{font:{size:11},maxTicksLimit:6}}}}
});

// Incident Types
new Chart(document.getElementById('chart-types'), {
  type:'doughnut',
  data:{
    labels:<?= json_encode($incident_types) ?>,
    datasets:[{data:<?= json_encode($incident_counts) ?>,
      backgroundColor:['#4338CA','#FB7185','#F59E0B','#10B981','#22D3EE','#A78BFA','#8094B4','#BE123C'],
      borderWidth:2,borderColor:'#fff',hoverOffset:5}]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'right',labels:{boxWidth:10,padding:10,font:{size:11}}}},cutout:'58%'}
});

// Forecast
new Chart(document.getElementById('chart-forecast'), {
  type:'line',
  data:{
    labels:<?= json_encode($fc_chart_labels) ?>,
    datasets:[
      {
        label:'Actual Filed',
        data:<?= json_encode($fc_chart_actual) ?>,
        borderColor:'#4338CA',backgroundColor:'rgba(67,56,202,.07)',
        borderWidth:2,pointRadius:3,pointBackgroundColor:'#4338CA',
        fill:true,tension:.35,spanGaps:false
      },
      {
        label:'Forecast',
        data:<?= json_encode($fc_chart_fc) ?>,
        borderColor:'#F59E0B',backgroundColor:'rgba(245,158,11,.07)',
        borderWidth:2,borderDash:[5,4],pointRadius:4,
        pointBackgroundColor:'#F59E0B',pointStyle:'rectRot',
        fill:true,tension:.3,spanGaps:true
      }
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{
      legend:{position:'top',labels:{boxWidth:10,font:{size:11}}},
      tooltip:{callbacks:{label:ctx=>ctx.dataset.label+': '+(ctx.raw??'—')}}
    },
    scales:{
      y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{size:11}}},
      x:{grid:{display:false},ticks:{font:{size:11},maxTicksLimit:8}}
    }
  }
});

// Prescribed Actions
<?php if (!empty($action_vals)): ?>
new Chart(document.getElementById('chart-actions'), {
  type:'doughnut',
  data:{
    labels:<?= json_encode($action_labels) ?>,
    datasets:[{data:<?= json_encode($action_vals) ?>,
      backgroundColor:['#4338CA','#10B981','#F59E0B','#A78BFA','#FB7185','#22D3EE'],
      borderWidth:2,borderColor:'#fff'}]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:8,font:{size:10}}}},
    cutout:'55%'}
});
<?php else: ?>
document.getElementById('chart-actions').parentElement.innerHTML=
  '<div style="display:flex;align-items:center;justify-content:center;height:190px;color:var(--ink-300);font-size:13px">No action data yet</div>';
<?php endif; ?>

// Barangay Comparison
new Chart(document.getElementById('chart-bgy-compare'), {
  type:'bar',
  data:{
    labels:<?= json_encode($bgy_chart_names) ?>,
    datasets:[
      {label:'Active',   data:<?= json_encode($bgy_chart_active) ?>, backgroundColor:'rgba(245,158,11,.25)', borderColor:'#B45309', borderWidth:1.5, borderRadius:4},
      {label:'Resolved', data:<?= json_encode($bgy_chart_res) ?>,   backgroundColor:'rgba(4,120,87,.2)',   borderColor:'#047857', borderWidth:1.5, borderRadius:4}
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:10}}}},
    scales:{
      y:{grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{size:10}}},
      x:{grid:{display:false},ticks:{font:{size:10}}}
    }
  }
});

// Quick approve/reject
function approveUser(id, btn) {
  if (!confirm('Approve this user account?')) return;
  fetch('ajax/user_action.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'approve',id})})
    .then(r=>r.json()).then(d=>{ if(d.success){btn.closest('div[style]').remove();showToast('User approved.','success');}else showToast(d.message,'error'); });
}
function suspendUser(id, btn) {
  if (!confirm('Reject this account?')) return;
  fetch('ajax/user_action.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'suspend',id})})
    .then(r=>r.json()).then(d=>{ if(d.success){btn.closest('div[style]').remove();showToast('Account rejected.');}else showToast(d.message,'error'); });
}
</script>