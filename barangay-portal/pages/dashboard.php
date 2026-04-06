<?php
// pages/dashboard.php
$bid = (int)$user['barangay_id'];

// ── KPIs ──────────────────────────────────────────────────────
$kpi = ['total'=>0,'active'=>0,'resolved'=>0,'pending'=>0,'mediations'=>0,'violators'=>0,'penalties_pending'=>0,'no_shows'=>0];
try {
    $row = $pdo->query("
        SELECT COUNT(*) AS total,
               SUM(status NOT IN ('resolved','closed','transferred')) AS active,
               SUM(status IN ('resolved','closed'))                   AS resolved,
               SUM(status = 'pending_review')                         AS pending
        FROM blotters WHERE barangay_id = $bid
    ")->fetch();
    $kpi['total']    = (int)($row['total']   ?? 0);
    $kpi['active']   = (int)($row['active']  ?? 0);
    $kpi['resolved'] = (int)($row['resolved']?? 0);
    $kpi['pending']  = (int)($row['pending'] ?? 0);

    $kpi['mediations'] = (int)$pdo->query("
        SELECT COUNT(*) FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid AND ms.status = 'scheduled' AND ms.hearing_date >= CURDATE()
    ")->fetchColumn();

    $kpi['violators'] = (int)$pdo->query("
        SELECT COUNT(DISTINCT respondent_name) FROM blotters
        WHERE barangay_id = $bid AND respondent_name NOT IN ('','Unknown')
          AND status NOT IN ('resolved','closed','transferred')
    ")->fetchColumn();

    $kpi['penalties_pending'] = (int)$pdo->query("
        SELECT COUNT(*) FROM penalties p
        JOIN blotters b ON b.id = p.blotter_id
        WHERE b.barangay_id = $bid AND p.status IN ('pending','overdue')
    ")->fetchColumn();

    $kpi['no_shows'] = (int)$pdo->query("
        SELECT COUNT(*) FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid AND ms.missed_session = 1
    ")->fetchColumn();
} catch (PDOException $e) {}

$res_rate = $kpi['total'] > 0 ? round($kpi['resolved'] / $kpi['total'] * 100) : 0;

// ── Monthly trend (last 12 months) ────────────────────────────
$months = []; $filed_data = []; $res_data = [];
for ($i = 11; $i >= 0; $i--) {
    $months[]     = date('M y', strtotime("-{$i} months"));
    $filed_data[] = 0;
    $res_data[]   = 0;
}
try {
    $rows = $pdo->query("
        SELECT DATE_FORMAT(created_at,'%b %y') AS mo, COUNT(*) AS c
        FROM blotters WHERE barangay_id=$bid AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY mo
    ")->fetchAll();
    foreach ($rows as $r) {
        $idx = array_search($r['mo'], $months);
        if ($idx !== false) $filed_data[$idx] = (int)$r['c'];
    }
    $rows2 = $pdo->query("
        SELECT DATE_FORMAT(updated_at,'%b %y') AS mo, COUNT(*) AS c
        FROM blotters WHERE barangay_id=$bid AND status IN ('resolved','closed')
          AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY mo
    ")->fetchAll();
    foreach ($rows2 as $r) {
        $idx = array_search($r['mo'], $months);
        if ($idx !== false) $res_data[$idx] = (int)$r['c'];
    }
} catch (PDOException $e) {}

// ── Forecasting: linear regression on last 6 months → next 3 ─
$forecast_months = [];
$forecast_data   = [];
$recent_6 = array_slice($filed_data, -6);
$n = count($recent_6);
$sum_x = 0; $sum_y = 0; $sum_xy = 0; $sum_xx = 0;
for ($i = 0; $i < $n; $i++) {
    $sum_x  += $i; $sum_y  += $recent_6[$i];
    $sum_xy += $i * $recent_6[$i]; $sum_xx += $i * $i;
}
$denom     = ($n * $sum_xx - $sum_x * $sum_x);
$slope     = $denom != 0 ? ($n * $sum_xy - $sum_x * $sum_y) / $denom : 0;
$intercept = $n > 0 ? ($sum_y - $slope * $sum_x) / $n : 0;

for ($i = 1; $i <= 3; $i++) {
    $forecast_months[] = date('M y', strtotime("+{$i} months"));
    $forecast_data[]   = max(0, (int)round($intercept + $slope * ($n - 1 + $i)));
}

// Chart arrays: bridge actual to forecast at index 11
$chart_labels   = array_merge($months, $forecast_months);
$chart_filed    = array_merge($filed_data, [null, null, null]);
$chart_forecast = array_merge(array_fill(0, 11, null), [end($filed_data)], $forecast_data);

$last_actual    = (int)(end($recent_6) ?: 0);
$first_forecast = $forecast_data[0] ?? 0;
$trend_pct      = $last_actual > 0 ? abs(round(($first_forecast - $last_actual) / $last_actual * 100)) : 0;
$trend_dir      = $first_forecast > $last_actual ? 'up' : ($first_forecast < $last_actual ? 'down' : 'flat');

// ── Avg resolution time ────────────────────────────────────────
$avg_res_days = 0;
try {
    $avg = $pdo->query("
        SELECT AVG(DATEDIFF(updated_at, created_at))
        FROM blotters WHERE barangay_id=$bid AND status IN ('resolved','closed') AND updated_at > created_at
    ")->fetchColumn();
    $avg_res_days = round((float)($avg ?? 0), 1);
} catch (PDOException $e) {}

// ── Incident type donut ────────────────────────────────────────
$inc_labels = ['No Data']; $inc_data = [0];
try {
    $rows = $pdo->query("
        SELECT incident_type, COUNT(*) AS c FROM blotters
        WHERE barangay_id=$bid GROUP BY incident_type ORDER BY c DESC LIMIT 8
    ")->fetchAll();
    if ($rows) {
        $inc_labels = array_column($rows, 'incident_type');
        $inc_data   = array_map(fn($r) => (int)$r['c'], $rows);
    }
} catch (PDOException $e) {}

// ── Severity breakdown ─────────────────────────────────────────
$sev = ['minor'=>0,'moderate'=>0,'serious'=>0,'critical'=>0];
try {
    $rows = $pdo->query("SELECT violation_level, COUNT(*) c FROM blotters WHERE barangay_id=$bid GROUP BY violation_level")->fetchAll();
    foreach ($rows as $r) if (isset($sev[$r['violation_level']])) $sev[$r['violation_level']] = (int)$r['c'];
} catch (PDOException $e) {}
$sev_max = max(1, ...array_values($sev));

// ── Day-of-week heatmap ────────────────────────────────────────
$dow_data   = array_fill(0, 7, 0);
$dow_labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
try {
    $rows = $pdo->query("
        SELECT DAYOFWEEK(incident_date)-1 AS dow, COUNT(*) AS c
        FROM blotters WHERE barangay_id=$bid GROUP BY dow
    ")->fetchAll();
    foreach ($rows as $r) $dow_data[(int)$r['dow']] = (int)$r['c'];
} catch (PDOException $e) {}
$dow_max = max(1, ...array_values($dow_data));

// ── Month-over-month incident type comparison ──────────────────
$type_this = []; $type_last = [];
try {
    $rows = $pdo->query("
        SELECT incident_type, COUNT(*) c FROM blotters
        WHERE barangay_id=$bid AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
        GROUP BY incident_type ORDER BY c DESC LIMIT 6
    ")->fetchAll();
    foreach ($rows as $r) $type_this[$r['incident_type']] = (int)$r['c'];

    $rows2 = $pdo->query("
        SELECT incident_type, COUNT(*) c FROM blotters
        WHERE barangay_id=$bid
          AND MONTH(created_at)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
          AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
        GROUP BY incident_type ORDER BY c DESC LIMIT 6
    ")->fetchAll();
    foreach ($rows2 as $r) $type_last[$r['incident_type']] = (int)$r['c'];
} catch (PDOException $e) {}

// ── Repeat Violators / Recidivism Risk ────────────────────────
$repeat_violators = [];
try {
    $repeat_violators = $pdo->query("
        SELECT
            respondent_name,
            COUNT(*)                                                              AS total_cases,
            SUM(status NOT IN ('resolved','closed','transferred'))                AS active_cases,
            SUM(violation_level IN ('serious','critical'))                        AS severe_cases,
            MAX(created_at)                                                       AS last_incident,
            GROUP_CONCAT(DISTINCT incident_type ORDER BY created_at DESC SEPARATOR ', ') AS incident_types,
            MAX(CASE violation_level WHEN 'critical' THEN 4 WHEN 'serious' THEN 3
                WHEN 'moderate' THEN 2 ELSE 1 END)                               AS max_sev_rank
        FROM blotters
        WHERE barangay_id=$bid AND respondent_name NOT IN ('','Unknown')
        GROUP BY respondent_name
        HAVING total_cases >= 2
        ORDER BY total_cases DESC, active_cases DESC, max_sev_rank DESC
        LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {}

function computeRisk(array $rv): int {
    $score  = min((int)$rv['total_cases'],  8) * 10;
    $score += min((int)$rv['active_cases'], 3) * 5;
    $score += ((int)$rv['severe_cases'] > 0) ? 15 : 0;
    return min(100, $score);
}

// ── No-show rate ──────────────────────────────────────────────
$no_show_rate = 0;
try {
    $total_s  = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.barangay_id=$bid")->fetchColumn();
    $missed_s = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE b.barangay_id=$bid AND ms.missed_session=1")->fetchColumn();
    $no_show_rate = $total_s > 0 ? round($missed_s / $total_s * 100) : 0;
} catch (PDOException $e) {}

// ── Prescribed action breakdown ────────────────────────────────
$action_data = [];
try {
    $rows = $pdo->query("SELECT prescribed_action, COUNT(*) c FROM blotters WHERE barangay_id=$bid GROUP BY prescribed_action ORDER BY c DESC")->fetchAll();
    foreach ($rows as $r) $action_data[$r['prescribed_action']] = (int)$r['c'];
} catch (PDOException $e) {}
$action_labels = array_map(fn($k) => ucwords(str_replace('_',' ',$k)), array_keys($action_data));
$action_vals   = array_values($action_data);

// ── Recent blotters ────────────────────────────────────────────
$recent = [];
try {
    $recent = $pdo->query("
        SELECT id, case_number, complainant_name, incident_type, violation_level, status, created_at
        FROM blotters WHERE barangay_id=$bid ORDER BY created_at DESC LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {}

// ── Upcoming hearings ──────────────────────────────────────────
$hearings = [];
try {
    $hearings = $pdo->query("
        SELECT ms.id, ms.hearing_date, ms.hearing_time, ms.venue,
               b.case_number, b.complainant_name, b.respondent_name
        FROM mediation_schedules ms
        JOIN blotters b ON b.id=ms.blotter_id
        WHERE b.barangay_id=$bid AND ms.status='scheduled' AND ms.hearing_date >= CURDATE()
        ORDER BY ms.hearing_date ASC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {}
?>

<!-- ══════════ DASHBOARD-ONLY STYLES (no conflict with styles.css) ══════════ -->
<style>
.analytics-section-hdr {
    display:flex; align-items:center; gap:10px;
    margin:26px 0 14px; font-size:11px; font-weight:700;
    letter-spacing:.08em; text-transform:uppercase; color:var(--ink-400);
}
.analytics-section-hdr span { white-space:nowrap; }
.analytics-section-hdr::after { content:''; flex:1; height:1px; background:var(--ink-100); }

/* Secondary stats row */
.kpi-stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.kpi-stat { background:var(--white); border:1px solid var(--ink-100); border-radius:var(--r-lg);
    padding:14px 16px; display:flex; align-items:center; gap:12px; }
.kpi-stat-icon { width:36px; height:36px; border-radius:var(--r-sm); display:flex;
    align-items:center; justify-content:center; flex-shrink:0; font-size:16px; }
.kpi-stat-val  { font-size:22px; font-weight:700; color:var(--ink-900); line-height:1.1; }
.kpi-stat-lbl  { font-size:11px; color:var(--ink-400); font-weight:500; margin-top:2px; }

/* Forecast card */
.card-forecast { border-top:3px solid var(--violet-400) !important; }

/* Forecast badge */
.fc-badge { display:inline-flex; align-items:center; gap:4px; font-size:11px;
    font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap; }
.fc-up   { background:var(--rose-50);    color:var(--rose-600); }
.fc-down { background:var(--emerald-50); color:var(--emerald-600); }
.fc-flat { background:var(--ink-50);     color:var(--ink-400); }

/* Forecast projected pills */
.forecast-pills { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
.fc-pill { flex:1; min-width:80px; background:var(--violet-50); border:1px solid var(--violet-100);
    border-radius:var(--r-md); padding:10px 12px; text-align:center; }
.fc-pill-mo  { font-size:11px; font-weight:600; color:var(--violet-600); }
.fc-pill-val { font-size:24px; font-weight:800; color:var(--ink-900); line-height:1.1; }
.fc-pill-lbl { font-size:10px; color:var(--ink-400); margin-top:2px; }

/* Insight callout */
.insight-box { background:var(--navy-50); border:1px solid var(--navy-200);
    border-radius:var(--r-md); padding:11px 14px; font-size:12px;
    color:var(--navy-700); line-height:1.6; margin-top:14px; }
.insight-box strong { color:var(--navy-800); }

/* Repeat violator risk */
.risk-row { display:flex; align-items:center; gap:10px; padding:9px 0;
    border-bottom:1px solid var(--surface-2); }
.risk-row:last-child { border-bottom:none; }
.risk-name { flex:1; min-width:0; font-size:12px; font-weight:600; color:var(--ink-900);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.risk-meta { font-size:10px; color:var(--ink-400); font-weight:400; display:block; margin-top:1px; }
.risk-bar-wrap { width:80px; flex-shrink:0; }
.risk-bar-bg   { height:6px; background:var(--surface-2); border-radius:10px; overflow:hidden; }
.risk-bar-fill { height:100%; border-radius:10px; }
.risk-score-lbl { font-size:11px; font-weight:700; width:30px; text-align:right; flex-shrink:0; }
.risk-hi   { background:var(--rose-400); }
.risk-mid  { background:var(--amber-400); }
.risk-lo   { background:var(--emerald-400); }
.risk-hi-text  { color:var(--rose-600); }
.risk-mid-text { color:var(--amber-600); }
.risk-lo-text  { color:var(--emerald-600); }

/* MoM comparison */
.mom-row { display:flex; align-items:center; gap:8px; padding:7px 0;
    border-bottom:1px solid var(--surface-2); font-size:12px; }
.mom-row:last-child { border-bottom:none; }
.mom-type  { flex:1; color:var(--ink-700); font-weight:500; white-space:nowrap;
    overflow:hidden; text-overflow:ellipsis; }
.mom-cnt   { font-weight:700; color:var(--ink-900); min-width:18px; text-align:right; }
.mom-delta { font-size:10px; font-weight:700; padding:1px 6px; border-radius:10px;
    white-space:nowrap; margin-left:2px; }
.md-up   { background:var(--rose-50);    color:var(--rose-600); }
.md-down { background:var(--emerald-50); color:var(--emerald-600); }
.md-new  { background:var(--amber-50);   color:var(--amber-600); }
.md-gone { background:var(--ink-50);     color:var(--ink-400); }

/* Day-of-week heatmap */
.dow-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:5px; margin-top:10px; }
.dow-cell { border-radius:var(--r-sm); padding:8px 4px; text-align:center; }
.dow-day  { font-size:10px; font-weight:600; }
.dow-num  { font-size:15px; font-weight:700; margin-top:2px; }

@media(max-width:1100px){ .kpi-stat-row{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:768px) { .kpi-stat-row{ grid-template-columns:1fr 1fr; } .forecast-pills{ flex-direction:column; } }
</style>

<!-- ══════════════════════════ PAGE HEADER ══════════════════════════ -->
<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Dashboard</h2>
    <p><?= e($bgy['name']) ?> &nbsp;·&nbsp; <?= date('F j, Y') ?></p>
  </div>
  <div class="page-hdr-actions">
    <button class="btn btn-outline btn-sm" onclick="location.reload()">↻ Refresh</button>
    <button class="btn btn-primary" onclick="openModal('modal-new-blotter')">+ File Blotter</button>
  </div>
</div>

<?php if ($kpi['pending'] > 0): ?>
<div class="alert alert-amber">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--amber-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11.5" r=".5" fill="currentColor"/></svg>
  <div class="alert-text">
    <strong><?= $kpi['pending'] ?> blotter(s) awaiting review</strong>
    <span>New community submissions need officer action. <a href="?page=blotter-management&status=pending_review" style="color:var(--amber-600);font-weight:600">Review now →</a></span>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════ PRIMARY KPI CARDS ══════════════════════════ -->
<div class="kpi-grid">
  <div class="kpi-card kc-teal">
    <div class="kpi-top">
      <div class="kpi-icon ki-teal"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M5 7h8M5 10h5"/></svg></div>
      <span class="kpi-trend tr-fl">all time</span>
    </div>
    <div class="kpi-val"><?= $kpi['total'] ?></div>
    <div class="kpi-lbl">Total Blotters</div>
  </div>
  <div class="kpi-card kc-amber">
    <div class="kpi-top">
      <div class="kpi-icon ki-amber"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="9" r="7"/><path d="M9 5.5v3.5l2.5 2"/></svg></div>
      <span class="kpi-trend tr-fl">ongoing</span>
    </div>
    <div class="kpi-val"><?= $kpi['active'] ?></div>
    <div class="kpi-lbl">Active Cases</div>
  </div>
  <div class="kpi-card kc-emerald">
    <div class="kpi-top">
      <div class="kpi-icon ki-emerald"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M4 9.5l3.5 3.5 7-7"/></svg></div>
      <span class="kpi-trend <?= $res_rate >= 70 ? 'tr-up' : 'tr-dn' ?>"><?= $res_rate ?>%</span>
    </div>
    <div class="kpi-val"><?= $kpi['resolved'] ?></div>
    <div class="kpi-lbl">Resolved</div>
  </div>
  <div class="kpi-card kc-rose">
    <div class="kpi-top">
      <div class="kpi-icon ki-rose"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="7" r="3.5"/><path d="M3 17c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg></div>
      <span class="kpi-trend tr-fl">active</span>
    </div>
    <div class="kpi-val"><?= $kpi['violators'] ?></div>
    <div class="kpi-lbl">Active Violators</div>
  </div>
</div>

<!-- ══════════════════════════ ANALYTICS SNAPSHOT ══════════════════════════ -->
<div class="analytics-section-hdr">
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M1 10l3-3 2.5 2.5L10 5l3 3"/></svg>
  <span>Analytics Snapshot</span>
</div>

<div class="kpi-stat-row">
  <div class="kpi-stat">
    <div class="kpi-stat-icon" style="background:var(--teal-50)">⏱</div>
    <div>
      <div class="kpi-stat-val"><?= $avg_res_days ?>d</div>
      <div class="kpi-stat-lbl">Avg. Resolution Time</div>
    </div>
  </div>
  <div class="kpi-stat">
    <div class="kpi-stat-icon" style="background:var(--rose-50)">🚫</div>
    <div>
      <div class="kpi-stat-val"><?= $no_show_rate ?>%</div>
      <div class="kpi-stat-lbl">Mediation No-Show Rate</div>
    </div>
  </div>
  <div class="kpi-stat">
    <div class="kpi-stat-icon" style="background:var(--amber-50)">⚖️</div>
    <div>
      <div class="kpi-stat-val"><?= $kpi['penalties_pending'] ?></div>
      <div class="kpi-stat-lbl">Pending Penalties</div>
    </div>
  </div>
  <div class="kpi-stat">
    <div class="kpi-stat-icon" style="background:var(--violet-50)">🔁</div>
    <div>
      <div class="kpi-stat-val"><?= count($repeat_violators) ?></div>
      <div class="kpi-stat-lbl">Repeat Violators on File</div>
    </div>
  </div>
</div>

<!-- ══════════════════════════ TREND + TYPES ══════════════════════════ -->
<div class="g32 mb22">
  <div class="card">
    <div class="card-hdr">
      <div>
        <div class="card-title">Monthly Trend</div>
        <div class="card-sub">Filed vs Resolved — last 12 months</div>
      </div>
    </div>
    <div class="card-body"><div style="height:200px"><canvas id="ch-trend"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-hdr"><div class="card-title">Incident Types</div></div>
    <div class="card-body"><div style="height:200px"><canvas id="ch-types"></canvas></div></div>
  </div>
</div>

<!-- ══════════════════════════ PREDICTIVE ANALYTICS ══════════════════════════ -->
<div class="analytics-section-hdr">
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><polyline points="1,13 5,7 8,10 11,4 13,6"/><polyline points="11,1 13,1 13,3" stroke-width="1.4"/></svg>
  <span>Predictive Analytics</span>
</div>

<div class="g21 mb22">

  <!-- Forecast card -->
  <div class="card card-forecast">
    <div class="card-hdr">
      <div>
        <div class="card-title">📈 Blotter Volume Forecast</div>
        <div class="card-sub">Linear regression on last 6 months · next 3 months projected</div>
      </div>
      <?php
        $fc_cls = $trend_dir === 'up' ? 'fc-up' : ($trend_dir === 'down' ? 'fc-down' : 'fc-flat');
        $fc_arr = $trend_dir === 'up' ? '▲' : ($trend_dir === 'down' ? '▼' : '→');
      ?>
      <span class="fc-badge <?= $fc_cls ?>"><?= $fc_arr ?> <?= $trend_pct ?>% vs now</span>
    </div>
    <div class="card-body">
      <div style="height:180px"><canvas id="ch-forecast"></canvas></div>
      <div class="forecast-pills">
        <?php foreach ($forecast_months as $i => $fm): ?>
        <div class="fc-pill">
          <div class="fc-pill-mo"><?= $fm ?></div>
          <div class="fc-pill-val"><?= $forecast_data[$i] ?></div>
          <div class="fc-pill-lbl">projected cases</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php
        $total_fc  = array_sum($forecast_data);
        $trend_wrd = $trend_dir === 'up' ? 'increase' : ($trend_dir === 'down' ? 'decrease' : 'remain stable');
      ?>
      <div class="insight-box">
        <strong>Insight:</strong> Based on recent filing patterns, blotter volume is projected to
        <strong><?= $trend_wrd ?></strong> over the next 3 months — an estimated
        <strong><?= $total_fc ?> total cases</strong>.
        <?php if ($trend_dir === 'up'): ?>
        Pre-emptive community interventions and staffing readiness are advised.
        <?php elseif ($trend_dir === 'down'): ?>
        Current conflict resolution measures appear to be having a positive effect.
        <?php else: ?>
        Maintain current monitoring cadence and officer allocation.
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Repeat Violator Risk -->
  <div class="card">
    <div class="card-hdr">
      <div>
        <div class="card-title">🔁 Repeat Violator Risk</div>
        <div class="card-sub">Respondents with 2+ blotters, scored by recidivism risk</div>
      </div>
      <a href="?page=blotter-management" class="act-btn">View all →</a>
    </div>
    <div class="card-body" style="padding:8px 18px">
      <?php if (empty($repeat_violators)): ?>
        <div style="text-align:center;padding:24px;color:var(--ink-300);font-size:12px">
          <div style="font-size:28px;margin-bottom:8px">✅</div>
          No repeat violators on record
        </div>
      <?php else: foreach ($repeat_violators as $rv):
          $score = computeRisk($rv);
          $rc = $score >= 65 ? 'risk-hi'  : ($score >= 35 ? 'risk-mid'  : 'risk-lo');
          $rt = $score >= 65 ? 'risk-hi-text' : ($score >= 35 ? 'risk-mid-text' : 'risk-lo-text');
          $rl = $score >= 65 ? 'High' : ($score >= 35 ? 'Med' : 'Low');
      ?>
        <div class="risk-row">
          <div style="flex:1;min-width:0">
            <div class="risk-name"><?= e($rv['respondent_name']) ?></div>
            <span class="risk-meta"><?= $rv['total_cases'] ?> cases · <?= $rv['active_cases'] ?> active · <?= e(mb_strimwidth($rv['incident_types'],0,28,'…')) ?></span>
          </div>
          <div class="risk-bar-wrap">
            <div style="font-size:9px;color:var(--ink-400);text-align:right;margin-bottom:2px"><?= $rl ?></div>
            <div class="risk-bar-bg"><div class="risk-bar-fill <?= $rc ?>" style="width:<?= $score ?>%"></div></div>
          </div>
          <div class="risk-score-lbl <?= $rt ?>"><?= $score ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php if (!empty($repeat_violators)): ?>
    <div class="card-foot" style="font-size:11px;color:var(--ink-400)">
      Risk score 0–100 · frequency (50%) + active cases (30%) + severity (20%)
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════ PATTERNS & DISTRIBUTION ══════════════════════════ -->
<div class="analytics-section-hdr">
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1" y="1" width="5" height="5" rx="1"/><rect x="8" y="1" width="5" height="5" rx="1"/><rect x="1" y="8" width="5" height="5" rx="1"/><rect x="8" y="8" width="5" height="5" rx="1"/></svg>
  <span>Patterns &amp; Distribution</span>
</div>

<div class="g3 mb22">

  <!-- Day-of-week heatmap -->
  <div class="card">
    <div class="card-hdr">
      <div>
        <div class="card-title">📅 Peak Incident Days</div>
        <div class="card-sub">Incidents by day of week</div>
      </div>
    </div>
    <div class="card-body">
      <div class="dow-grid">
        <?php foreach ($dow_labels as $di => $dl):
          $val = $dow_data[$di];
          $intensity = $dow_max > 0 ? $val / $dow_max : 0;
          $opacity = round(0.07 + $intensity * 0.85, 2);
          $bg       = "rgba(13,115,119,{$opacity})";
          $txt_col  = $intensity > 0.5 ? '#fff' : 'var(--teal-700)';
          $lbl_col  = $intensity > 0.5 ? 'rgba(255,255,255,.75)' : 'var(--ink-400)';
        ?>
        <div class="dow-cell" style="background:<?= $bg ?>">
          <div class="dow-day" style="color:<?= $lbl_col ?>"><?= $dl ?></div>
          <div class="dow-num" style="color:<?= $txt_col ?>"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php
        $peak_idx = array_search(max($dow_data), $dow_data);
        $peak_day = $dow_labels[$peak_idx ?? 0];
        $has_weekends = ($dow_data[0] + $dow_data[6]) > 0;
      ?>
      <div class="insight-box" style="margin-top:12px">
        <strong>Peak day:</strong> <?= $peak_day ?> records the highest incident filings.
        <?= $has_weekends ? 'Weekend incidents are present — consider weekend officer coverage.' : 'Incidents are concentrated on weekdays.' ?>
      </div>
    </div>
  </div>

  <!-- Month-over-month type comparison -->
  <div class="card">
    <div class="card-hdr">
      <div>
        <div class="card-title">📊 Type Comparison</div>
        <div class="card-sub">This month vs last month</div>
      </div>
    </div>
    <div class="card-body" style="padding:10px 18px">
      <?php
        $sorted_types = array_unique(array_merge(array_keys($type_this), array_keys($type_last)));
        usort($sorted_types, fn($a,$b) => ($type_this[$b]??0) - ($type_this[$a]??0));
        $display_types = array_slice($sorted_types, 0, 6);
      ?>
      <?php if (empty($display_types)): ?>
        <div style="text-align:center;padding:20px;color:var(--ink-300);font-size:12px">No data this period</div>
      <?php else: foreach ($display_types as $t):
          $cur = $type_this[$t] ?? 0;
          $prv = $type_last[$t] ?? 0;
          if ($cur === 0 && $prv > 0)        { $dc='md-gone'; $dd='Gone'; }
          elseif ($prv === 0 && $cur > 0)    { $dc='md-new';  $dd='New'; }
          elseif ($cur > $prv)               { $dc='md-up';   $dd='+'.($cur-$prv); }
          elseif ($cur < $prv)               { $dc='md-down'; $dd='-'.($prv-$cur); }
          else                               { $dc='';         $dd='='; }
      ?>
        <div class="mom-row">
          <span class="mom-type"><?= e($t) ?></span>
          <span class="mom-cnt"><?= $cur ?></span>
          <span class="mom-delta <?= $dc ?>"><?= $dd ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Prescribed actions pie -->
  <div class="card">
    <div class="card-hdr">
      <div>
        <div class="card-title">⚖️ Prescribed Actions</div>
        <div class="card-sub">How cases are being handled</div>
      </div>
    </div>
    <div class="card-body"><div style="height:180px"><canvas id="ch-actions"></canvas></div></div>
  </div>
</div>

<!-- ══════════════════════════ RECENT BLOTTERS + SIDEBAR ══════════════════════════ -->
<div class="g21">
  <div class="card">
    <div class="card-hdr">
      <div class="card-title">Recent Blotters</div>
      <a href="?page=blotter-management" class="act-btn">View all →</a>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Case No.</th><th>Complainant</th><th>Type</th><th>Level</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="es-icon">📋</div><div class="es-title">No blotters yet</div></div></td></tr>
        <?php else: foreach ($recent as $b): ?>
          <tr>
            <td class="td-mono"><?= e($b['case_number']) ?></td>
            <td class="td-main"><?= e($b['complainant_name']) ?></td>
            <td style="font-size:12px;color:var(--ink-600)"><?= e($b['incident_type']) ?></td>
            <td><span class="chip <?= ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'][$b['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
            <td><span class="chip <?= ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-emerald','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'][$b['status']] ?? 'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
            <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <!-- Upcoming hearings -->
    <div class="card mb16">
      <div class="card-hdr">
        <div class="card-title">📅 Upcoming Hearings</div>
        <a href="?page=mediation" class="act-btn">All →</a>
      </div>
      <div class="card-body" style="padding:0 18px">
        <?php if (empty($hearings)): ?>
          <div style="text-align:center;padding:20px;color:var(--ink-300);font-size:13px">No upcoming hearings</div>
        <?php else: foreach ($hearings as $h): ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--surface-2)">
            <div style="font-size:13px;font-weight:600;color:var(--ink-900)"><?= e($h['case_number']) ?></div>
            <div style="font-size:12px;color:var(--teal-600);font-weight:600;margin-top:2px">
              <?= date('D, M j', strtotime($h['hearing_date'])) ?><?= $h['hearing_time'] ? ' at '.date('g:i A', strtotime($h['hearing_time'])) : '' ?>
            </div>
            <div style="font-size:11px;color:var(--ink-400)">📍 <?= e($h['venue'] ?? 'Barangay Hall') ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Severity bars -->
    <div class="card">
      <div class="card-hdr"><div class="card-title">Severity Breakdown</div></div>
      <div class="card-body" style="padding:14px 18px">
        <?php foreach ([
          ['Critical','var(--violet-400)',$sev['critical']],
          ['Serious', 'var(--rose-400)',  $sev['serious']],
          ['Moderate','var(--amber-400)', $sev['moderate']],
          ['Minor',   'var(--emerald-400)',$sev['minor']],
        ] as [$lbl,$col,$cnt]): ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--ink-600);margin-bottom:4px">
            <span><?= $lbl ?></span><span style="font-weight:600"><?= $cnt ?></span>
          </div>
          <div style="height:7px;background:var(--surface-2);border-radius:10px;overflow:hidden">
            <div style="width:<?= round($cnt/$sev_max*100) ?>%;height:100%;background:<?= $col ?>;border-radius:10px"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════ CHART.JS INIT ══════════════════════════ -->
<script>
(function(){
// Monthly Trend
new Chart(document.getElementById('ch-trend'),{
  type:'bar',
  data:{
    labels:<?= json_encode($months) ?>,
    datasets:[
      {label:'Filed',    data:<?= json_encode($filed_data) ?>, backgroundColor:'rgba(13,115,119,.15)',borderColor:'#0D7377',borderWidth:2,borderRadius:4},
      {label:'Resolved', data:<?= json_encode($res_data) ?>,   backgroundColor:'rgba(4,120,87,.12)',  borderColor:'#047857',borderWidth:2,borderRadius:4}
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:11}}}},
    scales:{y:{grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{size:11}}},
            x:{grid:{display:false},ticks:{font:{size:11},maxTicksLimit:6}}}}
});

// Incident Types
new Chart(document.getElementById('ch-types'),{
  type:'doughnut',
  data:{
    labels:<?= json_encode($inc_labels) ?>,
    datasets:[{data:<?= json_encode($inc_data) ?>,
      backgroundColor:['#0D7377','#FB7185','#F59E0B','#10B981','#A78BFA','#2EBAC6','#4A7FAC','#BE123C'],
      borderWidth:2,borderColor:'#fff'}]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'right',labels:{boxWidth:10,padding:8,font:{size:11}}}},cutout:'58%'}
});

// Forecast
new Chart(document.getElementById('ch-forecast'),{
  type:'line',
  data:{
    labels:<?= json_encode($chart_labels) ?>,
    datasets:[
      {
        label:'Actual Filed',
        data:<?= json_encode($chart_filed) ?>,
        borderColor:'#0D7377',backgroundColor:'rgba(13,115,119,.07)',
        borderWidth:2,pointRadius:3,pointBackgroundColor:'#0D7377',
        fill:true,tension:.35,spanGaps:false
      },
      {
        label:'Forecast',
        data:<?= json_encode($chart_forecast) ?>,
        borderColor:'#7C3AED',backgroundColor:'rgba(139,92,246,.07)',
        borderWidth:2,borderDash:[5,4],pointRadius:4,
        pointBackgroundColor:'#7C3AED',pointStyle:'rectRot',
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
new Chart(document.getElementById('ch-actions'),{
  type:'doughnut',
  data:{
    labels:<?= json_encode($action_labels) ?>,
    datasets:[{data:<?= json_encode($action_vals) ?>,
      backgroundColor:['#0D7377','#10B981','#F59E0B','#A78BFA','#FB7185','#2EBAC6'],
      borderWidth:2,borderColor:'#fff'}]
  },
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:8,font:{size:10}}}},
    cutout:'55%'}
});
<?php else: ?>
document.getElementById('ch-actions').parentElement.innerHTML=
  '<div style="display:flex;align-items:center;justify-content:center;height:180px;color:var(--ink-300);font-size:13px">No action data yet</div>';
<?php endif; ?>
})();
</script>
