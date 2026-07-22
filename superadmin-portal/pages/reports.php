<?php
$bid = (int)$user['barangay_id'];

$f_months = max(6, min(24, (int)($_GET['months'] ?? 12)));
$f_level  = $_GET['level'] ?? '';
$f_type   = trim($_GET['type'] ?? '');

$level_filter_sql = '';
$level_params     = [];
if ($f_level !== '') {
    $level_filter_sql = 'AND violation_level = ?';
    $level_params[]   = $f_level;
}
$type_filter_sql = '';
$type_params     = [];
if ($f_type !== '') {
    $type_filter_sql = 'AND incident_type = ?';
    $type_params[]   = $f_type;
}

$monthly_trend = [];
try {
    $params = array_merge([$bid], $level_params, $type_params);
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m')          AS ym,
            DATE_FORMAT(created_at, '%b %Y')          AS label,
            COUNT(*)                                  AS total,
            SUM(status IN ('resolved','closed'))       AS resolved,
            SUM(status IN ('active','mediation_set','escalated','pending_review')) AS active_cases,
            SUM(violation_level = 'critical')          AS critical,
            SUM(violation_level = 'serious')           AS serious,
            SUM(violation_level = 'moderate')          AS moderate,
            SUM(violation_level = 'minor')             AS minor_cases
        FROM blotters
        WHERE barangay_id = ?
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
          $level_filter_sql
          $type_filter_sql
        GROUP BY ym, label
        ORDER BY ym ASC
    ");
    $params = array_merge([$bid, $f_months], $level_params, $type_params);
    $stmt->execute($params);
    $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$n = count($monthly_trend);

foreach ($monthly_trend as $i => &$row) {
    $window3_start = max(0, $i - 2);
    $window6_start = max(0, $i - 5);
    $slice3 = array_slice($monthly_trend, $window3_start, $i - $window3_start + 1);
    $slice6 = array_slice($monthly_trend, $window6_start, $i - $window6_start + 1);
    $row['rolling_3m'] = round(array_sum(array_column($slice3, 'total')) / count($slice3), 2);
    $row['rolling_6m'] = round(array_sum(array_column($slice6, 'total')) / count($slice6), 2);
    $row['resolution_rate'] = $row['total'] > 0 ? round($row['resolved'] / $row['total'] * 100, 1) : 0.0;
    $row['backlog_rate']    = $row['total'] > 0 ? round($row['active_cases'] / $row['total'] * 100, 1) : 0.0;
    $prev = $i > 0 ? (float)$monthly_trend[$i - 1]['total'] : 0;
    $row['mom_growth'] = $prev > 0 ? round(((float)$row['total'] - $prev) / $prev * 100, 1) : 0.0;
}
unset($row);

$type_growth = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            incident_type,
            SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN 1 ELSE 0 END)                                                                                 AS cnt_now,
            SUM(CASE WHEN created_at >= DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-01')
                      AND created_at <  DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN 1 ELSE 0 END)                                                                                 AS cnt_prev
        FROM blotters
        WHERE barangay_id = ?
          AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-01')
          $level_filter_sql
          $type_filter_sql
        GROUP BY incident_type
        ORDER BY cnt_now DESC
        LIMIT 10
    ");
    $stmt->execute(array_merge([$bid], $level_params, $type_params));
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $r) {
        $now  = (int)$r['cnt_now'];
        $prev = (int)$r['cnt_prev'];
        $r['growth_pct'] = $prev > 0 ? round(($now - $prev) / $prev * 100, 1) : ($now > 0 ? 100.0 : 0.0);
        $type_growth[] = $r;
    }
} catch (PDOException $e) {}

$all_types = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT incident_type FROM blotters WHERE barangay_id=? AND incident_type!='' ORDER BY incident_type");
    $stmt->execute([$bid]);
    $all_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

$kpi = ['total'=>0,'active'=>0,'resolved'=>0,'critical'=>0,'resolution_rate'=>0.0,'backlog_rate'=>0.0,'escalation_rate'=>0.0,'avg_days_to_resolve'=>0.0,'pending_penalties'=>0,'penalty_total'=>0.0];
try {
    $kparams = array_merge([$bid], $level_params, $type_params);
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*)                                                               AS total,
            SUM(status IN ('active','mediation_set','escalated','pending_review')) AS active_cases,
            SUM(status IN ('resolved','closed'))                                   AS resolved,
            SUM(violation_level = 'critical')                                      AS critical,
            ROUND(SUM(status IN ('resolved','closed')) / NULLIF(COUNT(*),0) * 100, 1)                                                   AS resolution_rate,
            ROUND(SUM(status IN ('active','mediation_set','escalated','pending_review')) / NULLIF(COUNT(*),0) * 100, 1)                 AS backlog_rate,
            ROUND(SUM(status = 'escalated') / NULLIF(COUNT(*),0) * 100, 1)                                                              AS escalation_rate,
            ROUND(AVG(CASE WHEN status IN ('resolved','closed') THEN DATEDIFF(updated_at, created_at) END), 1)                          AS avg_days_to_resolve
        FROM blotters
        WHERE barangay_id = ?
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
          $level_filter_sql
          $type_filter_sql
    ");
    $stmt->execute(array_merge([$bid, $f_months], $level_params, $type_params));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $kpi['total']               = (int)($row['total'] ?? 0);
        $kpi['active']              = (int)($row['active_cases'] ?? 0);
        $kpi['resolved']            = (int)($row['resolved'] ?? 0);
        $kpi['critical']            = (int)($row['critical'] ?? 0);
        $kpi['resolution_rate']     = (float)($row['resolution_rate'] ?? 0);
        $kpi['backlog_rate']        = (float)($row['backlog_rate'] ?? 0);
        $kpi['escalation_rate']     = (float)($row['escalation_rate'] ?? 0);
        $kpi['avg_days_to_resolve'] = (float)($row['avg_days_to_resolve'] ?? 0);
    }
} catch (PDOException $e) {}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(p.amount),0) AS total FROM penalties p JOIN blotters b ON b.id=p.blotter_id WHERE b.barangay_id=? AND p.status='pending'");
    $stmt->execute([$bid]);
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);
    $kpi['pending_penalties'] = (int)($pr['cnt'] ?? 0);
    $kpi['penalty_total']     = (float)($pr['total'] ?? 0);
} catch (PDOException $e) {}

$barangay_rank = [];
try {
    $stmt = $pdo->query("
        SELECT
            bgy.name AS barangay_name,
            COUNT(bl.id)                                                                                                         AS total,
            SUM(bl.status IN ('resolved','closed'))                                                                              AS resolved,
            SUM(bl.status IN ('active','mediation_set','escalated','pending_review'))                                            AS backlog,
            SUM(bl.violation_level = 'critical')                                                                                 AS critical_count,
            ROUND(SUM(bl.status IN ('resolved','closed')) / NULLIF(COUNT(bl.id),0) * 100, 1)                                    AS resolution_rate,
            ROUND(SUM(bl.status IN ('active','mediation_set','escalated','pending_review')) / NULLIF(COUNT(bl.id),0) * 100, 1)  AS backlog_rate,
            ROUND(SUM(bl.violation_level = 'critical') / NULLIF(COUNT(bl.id),0) * 100, 1)                                      AS critical_rate,
            LEAST(100, ROUND((
                (SUM(bl.violation_level = 'critical') / NULLIF(COUNT(bl.id),0)) * 40
              + ((1 - SUM(bl.status IN ('resolved','closed')) / NULLIF(COUNT(bl.id),0)) * 30)
              + (SUM(bl.status IN ('active','mediation_set','escalated','pending_review')) / NULLIF(COUNT(bl.id),0) * 30)
            ) * 100, 1))                                                                                                         AS risk_score
        FROM barangays bgy
        LEFT JOIN blotters bl ON bl.barangay_id = bgy.id
        WHERE bgy.is_active = 1
        GROUP BY bgy.id, bgy.name
        HAVING COUNT(bl.id) > 0
        ORDER BY risk_score DESC
        LIMIT 15
    ");
    $barangay_rank = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$slope     = 0.0;
$intercept = 0.0;
$pred_full = [];
$anomalies = [];
$future_labels = [];

if ($n >= 4) {
    $xs    = range(1, $n);
    $ys    = array_map(fn($r) => (float)$r['total'], $monthly_trend);
    $sx    = array_sum($xs);
    $sy    = array_sum($ys);
    $sxy   = 0.0; $sxx = 0.0;
    for ($i = 0; $i < $n; $i++) { $sxy += $xs[$i] * $ys[$i]; $sxx += $xs[$i] ** 2; }
    $denom     = ($n * $sxx - $sx ** 2);
    $slope     = $denom != 0 ? round(($n * $sxy - $sx * $sy) / $denom, 4) : 0.0;
    $intercept = round(($sy - $slope * $sx) / $n, 4);

    $pred_full = [];
    for ($i = 1; $i <= $n + 6; $i++) {
        $pred_full[] = round(max(0.0, $slope * $i + $intercept), 1);
    }

    $mean_y   = $sy / $n;
    $variance = array_sum(array_map(fn($y) => ($y - $mean_y) ** 2, $ys)) / $n;
    $std_y    = $variance > 0 ? sqrt($variance) : 0;
    if ($std_y > 0) {
        foreach ($ys as $i => $y) {
            $z = ($y - ($slope * ($i + 1) + $intercept)) / $std_y;
            if (abs($z) > 1.8) $anomalies[$i] = round($z, 2);
        }
    }

    for ($m = 1; $m <= 6; $m++) {
        $future_labels[] = date('M Y', strtotime("+{$m} month"));
    }
}

$insights = [];

if ($n >= 4 && !empty($pred_full)) {
    $last_actual    = (float)end($monthly_trend)['total'];
    $next_predicted = (float)($pred_full[$n] ?? 0);
    if ($next_predicted > $last_actual * 1.2) {
        $insights[] = ['level'=>'danger','icon'=>'📈','title'=>'Volume Surge Predicted Next Month',
            'body'   => sprintf('Forecast: <strong>%s cases</strong> vs current %s (+%s%%). Pre-position mediation officers.', round($next_predicted), (int)$last_actual, round(($next_predicted - $last_actual) / max($last_actual, 1) * 100)),
            'action' => 'Schedule additional mediation slots 2–3 weeks ahead.'];
    }
}
if ($kpi['resolution_rate'] < 55 && $kpi['total'] > 0) {
    $insights[] = ['level'=>'danger','icon'=>'⚠️','title'=>'Critical Resolution Rate',
        'body'   => sprintf('Resolution rate is <strong>%s%%</strong> — below the 55%% minimum. <strong>%s</strong> active cases stalled.', $kpi['resolution_rate'], $kpi['active']),
        'action' => 'Case review blitz: assign each stalled case with a 5-day resolution target.'];
} elseif ($kpi['resolution_rate'] < 70 && $kpi['total'] > 0) {
    $insights[] = ['level'=>'warn','icon'=>'🔄','title'=>'Resolution Rate Below 70% Benchmark',
        'body'   => sprintf('At <strong>%s%%</strong>, resolution lags the KP benchmark. %s cases unresolved.', $kpi['resolution_rate'], $kpi['active']),
        'action' => 'Run a structured mediation blitz targeting cases older than 30 days.'];
}
if ($kpi['backlog_rate'] > 40 && $kpi['total'] > 0) {
    $insights[] = ['level'=>'warn','icon'=>'📋','title'=>'Case Backlog Exceeds 40%',
        'body'   => sprintf('<strong>%s%%</strong> of all cases (%s) are active or stalled.', $kpi['backlog_rate'], $kpi['active']),
        'action' => 'Batch-schedule mediations for oldest unresolved cases.'];
}
if ($kpi['total'] > 0 && $kpi['critical'] / max($kpi['total'], 1) > 0.15) {
    $insights[] = ['level'=>'danger','icon'=>'🚨','title'=>'Critical Case Rate Above 15%',
        'body'   => sprintf('<strong>%s critical cases</strong> (%s%% of total). Municipal support may be required.', $kpi['critical'], round($kpi['critical'] / $kpi['total'] * 100, 1)),
        'action' => 'Escalate unresolved critical cases to DILG liaison.'];
}
if ($kpi['escalation_rate'] > 20 && $kpi['total'] > 0) {
    $insights[] = ['level'=>'warn','icon'=>'⬆️','title'=>'Escalation Rate Elevated',
        'body'   => sprintf('<strong>%s%%</strong> escalation rate signals early-stage intervention failures.', $kpi['escalation_rate']),
        'action' => 'Implement 48-hour case triage.'];
}
if ($kpi['avg_days_to_resolve'] > 30 && $kpi['avg_days_to_resolve'] > 0) {
    $insights[] = ['level'=>'warn','icon'=>'⏱️','title'=>'Resolution Time Exceeds 30-Day KP Guideline',
        'body'   => sprintf('Average close time: <strong>%s days</strong>. KP guideline target is ≤30 days.', $kpi['avg_days_to_resolve']),
        'action' => 'Set SLA alerts at 25 days. Auto-notify assigned officer before breach.'];
}
foreach ($anomalies as $idx => $z) {
    if ($z > 0 && isset($monthly_trend[$idx])) {
        $insights[] = ['level'=>'info','icon'=>'📊','title'=>'Spike Detected: '.$monthly_trend[$idx]['label'],
            'body'   => sprintf('Volume was <strong>%s cases</strong> — %.2f std deviations above trend.', $monthly_trend[$idx]['total'], $z),
            'action' => 'Review incident types and external events in that period.'];
    }
}
if (empty($insights)) {
    $insights[] = ['level'=>'success','icon'=>'✅','title'=>'Performance Within Normal Range',
        'body'   => 'All metrics are within acceptable thresholds.',
        'action' => 'Monitor monthly for emerging trends.'];
}

$chart_labels   = array_column($monthly_trend, 'label');
$chart_total    = array_map('intval',   array_column($monthly_trend, 'total'));
$chart_resolved = array_map('intval',   array_column($monthly_trend, 'resolved'));
$chart_rolling3 = array_map('floatval', array_column($monthly_trend, 'rolling_3m'));
$chart_rolling6 = array_map('floatval', array_column($monthly_trend, 'rolling_6m'));
$chart_res_rate = array_map('floatval', array_column($monthly_trend, 'resolution_rate'));
$chart_backlog  = array_map('floatval', array_column($monthly_trend, 'backlog_rate'));
$chart_mom      = array_map('floatval', array_column($monthly_trend, 'mom_growth'));
$chart_critical = array_map('intval',   array_column($monthly_trend, 'critical'));
$chart_serious  = array_map('intval',   array_column($monthly_trend, 'serious'));
$chart_moderate = array_map('intval',   array_column($monthly_trend, 'moderate'));
$chart_minor    = array_map('intval',   array_column($monthly_trend, 'minor_cases'));

$all_labels      = array_merge($chart_labels, $future_labels);
$null_pad        = array_fill(0, count($future_labels), null);
$actuals_padded  = array_merge($chart_total, $null_pad);
$rolling3_padded = array_merge($chart_rolling3, $null_pad);
$rolling6_padded = array_merge($chart_rolling6, $null_pad);

$anomaly_points = [];
foreach ($chart_total as $i => $v) { $anomaly_points[] = isset($anomalies[$i]) ? $v : null; }
foreach ($future_labels as $_) { $anomaly_points[] = null; }

$pred_padded = array_fill(0, $n, null);
foreach ($pred_full as $v) { $pred_padded[] = $v; }

$bgy_labels     = array_column($barangay_rank, 'barangay_name');
$bgy_risk       = array_map('floatval', array_column($barangay_rank, 'risk_score'));
$type_labels    = array_column($type_growth, 'incident_type');
$type_now       = array_map('intval',   array_column($type_growth, 'cnt_now'));
$type_prev      = array_map('intval',   array_column($type_growth, 'cnt_prev'));
$max_type_now   = !empty($type_now) ? max($type_now) : 1;

function aj($v) { return json_encode($v, JSON_NUMERIC_CHECK); }
?>

<style>
.an-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}
.an-row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.an-kpi{background:var(--surface);border:1px solid var(--ink-100);border-radius:var(--r-lg);padding:16px 18px}
.an-kpi-val{font-size:28px;font-weight:700;color:var(--ink-900);line-height:1;margin-bottom:3px}
.an-kpi-lbl{font-size:10px;color:var(--ink-400);font-weight:600;text-transform:uppercase;letter-spacing:.06em}
.an-kpi-sub{font-size:11px;color:var(--ink-500);margin-top:5px}
.an-card{background:var(--surface);border:1px solid var(--ink-100);border-radius:var(--r-lg);padding:18px 20px}
.an-card-wide{background:var(--surface);border:1px solid var(--ink-100);border-radius:var(--r-lg);padding:18px 20px;margin-bottom:14px}
.an-ct{font-size:13px;font-weight:700;color:var(--ink-900);margin-bottom:2px}
.an-cs{font-size:11px;color:var(--ink-400);margin-bottom:14px}
.sec-hdr{font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;text-transform:uppercase;margin:20px 0 10px}
.an-insight{display:flex;gap:12px;padding:13px 15px;border-radius:var(--r-md);margin-bottom:8px;border:1px solid transparent}
.an-insight:last-child{margin-bottom:0}
.an-ii{font-size:20px;flex-shrink:0;margin-top:1px}
.an-ib{flex:1;min-width:0}
.an-it{font-size:13px;font-weight:700;margin-bottom:3px}
.an-im{font-size:12px;line-height:1.6;color:var(--ink-700);margin-bottom:6px}
.an-ia{font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;display:inline-block}
.ai-danger{background:var(--rose-50);border-color:var(--rose-200)}.ai-danger .an-it{color:var(--rose-700)}.ai-danger .an-ia{background:var(--rose-100);color:var(--rose-700)}
.ai-warn{background:var(--amber-50);border-color:var(--amber-200)}.ai-warn .an-it{color:var(--amber-700)}.ai-warn .an-ia{background:var(--amber-100);color:var(--amber-700)}
.ai-info{background:var(--teal-50);border-color:var(--teal-100)}.ai-info .an-it{color:var(--teal-700)}.ai-info .an-ia{background:var(--teal-100);color:var(--teal-700)}
.ai-success{background:var(--emerald-50);border-color:var(--emerald-100)}.ai-success .an-it{color:var(--emerald-700)}.ai-success .an-ia{background:var(--emerald-100);color:var(--emerald-700)}
.an-tbl{width:100%;border-collapse:collapse;font-size:12px}
.an-tbl th{font-size:10px;font-weight:600;color:var(--ink-400);text-transform:uppercase;letter-spacing:.06em;padding:6px 10px;border-bottom:1px solid var(--ink-100);text-align:left;white-space:nowrap}
.an-tbl td{padding:8px 10px;border-bottom:1px solid var(--ink-50);color:var(--ink-700);vertical-align:middle}
.an-tbl tr:last-child td{border-bottom:none}
.risk-bar{height:5px;border-radius:3px;background:var(--ink-100);overflow:hidden;width:60px}
.risk-fill{height:100%;border-radius:3px}
.badge-up{background:var(--rose-50);color:var(--rose-600);font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;white-space:nowrap}
.badge-dn{background:var(--emerald-50);color:var(--emerald-600);font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;white-space:nowrap}
.badge-nt{background:var(--ink-50);color:var(--ink-500);font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;white-space:nowrap}
.bar-mini{height:5px;border-radius:3px;background:var(--teal-400)}
.an-filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;padding:12px 16px;background:var(--surface);border:1px solid var(--ink-100);border-radius:var(--r-lg)}
@media(max-width:1100px){.an-kpi-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){.an-kpi-grid{grid-template-columns:1fr 1fr}.an-row-2{grid-template-columns:1fr}}
</style>

<div id="analytics-live-region">
<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Analytics Engine</h2>
    <p>Descriptive &middot; Predictive &middot; Prescriptive &nbsp;&middot;&nbsp; <?= date('F j, Y') ?></p>
  </div>
  <div class="page-hdr-actions">
    <span style="font-size:11px;color:var(--ink-400)">
      Slope: <strong><?= $slope ?></strong> &nbsp; Intercept: <strong><?= $intercept ?></strong>
      &nbsp;&middot;&nbsp; <?= count($anomalies) ?> anomal<?= count($anomalies) !== 1 ? 'ies' : 'y' ?> detected
    </span>
  </div>
</div>

<form method="GET" class="an-filter-bar">
  <input type="hidden" name="page" value="analytics">
  <label style="font-size:11px;font-weight:600;color:var(--ink-500);white-space:nowrap">Period</label>
  <select name="months" style="width:auto;min-width:130px">
    <option value="6"  <?= $f_months===6  ?'selected':'' ?>>Last 6 Months</option>
    <option value="12" <?= $f_months===12 ?'selected':'' ?>>Last 12 Months</option>
    <option value="24" <?= $f_months===24 ?'selected':'' ?>>Last 24 Months</option>
  </select>
  <label style="font-size:11px;font-weight:600;color:var(--ink-500);white-space:nowrap">Level</label>
  <select name="level" style="width:auto;min-width:130px">
    <option value="" <?= $f_level==='' ?'selected':'' ?>>All Levels</option>
    <option value="critical" <?= $f_level==='critical'?'selected':'' ?>>Critical</option>
    <option value="serious"  <?= $f_level==='serious' ?'selected':'' ?>>Serious</option>
    <option value="moderate" <?= $f_level==='moderate'?'selected':'' ?>>Moderate</option>
    <option value="minor"    <?= $f_level==='minor'   ?'selected':'' ?>>Minor</option>
  </select>
  <label style="font-size:11px;font-weight:600;color:var(--ink-500);white-space:nowrap">Type</label>
  <select name="type" style="width:auto;min-width:170px">
    <option value="">All Incident Types</option>
    <?php foreach ($all_types as $t): ?>
    <option value="<?= e($t) ?>" <?= $f_type===$t?'selected':'' ?>><?= e($t) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($f_level !== '' || $f_type !== '' || $f_months !== 12): ?>
  <a href="?page=analytics" class="btn btn-ghost btn-sm">&#x2715; Clear</a>
  <?php endif; ?>
</form>

<div class="an-kpi-grid">
  <div class="an-kpi">
    <div class="an-kpi-val"><?= number_format($kpi['total']) ?></div>
    <div class="an-kpi-lbl">Total Blotters</div>
    <div class="an-kpi-sub"><?= $kpi['active'] ?> active &middot; <?= $kpi['resolved'] ?> resolved</div>
  </div>
  <div class="an-kpi">
    <div class="an-kpi-val" style="color:<?= $kpi['resolution_rate']>=70?'var(--emerald-600)':($kpi['resolution_rate']>=55?'var(--amber-600)':'var(--rose-600)') ?>">
      <?= $kpi['resolution_rate'] ?>%
    </div>
    <div class="an-kpi-lbl">Resolution Rate</div>
    <div class="an-kpi-sub">Avg <?= $kpi['avg_days_to_resolve'] ?> days to close</div>
  </div>
  <div class="an-kpi">
    <div class="an-kpi-val" style="color:<?= $kpi['backlog_rate']>40?'var(--rose-600)':'var(--ink-900)' ?>"><?= $kpi['backlog_rate'] ?>%</div>
    <div class="an-kpi-lbl">Backlog Rate</div>
    <div class="an-kpi-sub"><?= $kpi['active'] ?> stalled cases</div>
  </div>
  <div class="an-kpi">
    <div class="an-kpi-val" style="color:var(--violet-600)"><?= $kpi['escalation_rate'] ?>%</div>
    <div class="an-kpi-lbl">Escalation Rate</div>
    <div class="an-kpi-sub"><?= $kpi['critical'] ?> critical &middot; &#8369;<?= number_format($kpi['penalty_total']) ?> pending</div>
  </div>
</div>

<div class="sec-hdr">&#9888; Prescriptive Insights &amp; Recommendations</div>
<div class="an-card-wide">
  <?php foreach ($insights as $ins): ?>
  <div class="an-insight ai-<?= $ins['level'] ?>">
    <div class="an-ii"><?= $ins['icon'] ?></div>
    <div class="an-ib">
      <div class="an-it"><?= $ins['title'] ?></div>
      <div class="an-im"><?= $ins['body'] ?></div>
      <span class="an-ia">&#8594; <?= e($ins['action']) ?></span>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="sec-hdr">&#128200; Volume Trend &amp; Forecast</div>
<div class="an-card-wide">
  <div class="an-ct">Monthly Blotter Volume &mdash; Actuals + 6-Month Linear Forecast</div>
  <div class="an-cs">Dashed = regression forecast &middot; Triangles = anomaly spikes &middot; Rolling averages overlaid</div>
  <?php if (empty($monthly_trend)): ?>
  <div style="text-align:center;padding:60px;color:var(--ink-300);font-size:13px">No data for selected filters.</div>
  <?php else: ?>
  <div style="position:relative;height:300px"><canvas id="ch-trend"></canvas></div>
  <?php endif; ?>
</div>

<div class="an-row-2">
  <div class="an-card">
    <div class="an-ct">Resolution Rate Trend</div>
    <div class="an-cs">Monthly % of cases closed or resolved</div>
    <?php if (empty($monthly_trend)): ?>
    <div style="text-align:center;padding:40px;color:var(--ink-300);font-size:13px">No data.</div>
    <?php else: ?>
    <div style="position:relative;height:220px"><canvas id="ch-resolution"></canvas></div>
    <?php endif; ?>
  </div>
  <div class="an-card">
    <div class="an-ct">Backlog Rate Trend</div>
    <div class="an-cs">Active + stalled as % of monthly total</div>
    <?php if (empty($monthly_trend)): ?>
    <div style="text-align:center;padding:40px;color:var(--ink-300);font-size:13px">No data.</div>
    <?php else: ?>
    <div style="position:relative;height:220px"><canvas id="ch-backlog"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<div class="sec-hdr">&#128202; Violation Level Distribution</div>
<div class="an-card-wide">
  <div class="an-ct">Monthly Stacked Violation Levels</div>
  <div class="an-cs">Minor &rarr; Critical breakdown over time</div>
  <?php if (empty($monthly_trend)): ?>
  <div style="text-align:center;padding:60px;color:var(--ink-300);font-size:13px">No data.</div>
  <?php else: ?>
  <div style="position:relative;height:260px"><canvas id="ch-stacked"></canvas></div>
  <?php endif; ?>
</div>

<div class="an-row-2">
  <div class="an-card">
    <div class="an-ct">Month-over-Month Growth</div>
    <div class="an-cs">Red = surge &middot; Green = decline</div>
    <?php if (empty($monthly_trend)): ?>
    <div style="text-align:center;padding:40px;color:var(--ink-300);font-size:13px">No data.</div>
    <?php else: ?>
    <div style="position:relative;height:220px"><canvas id="ch-mom"></canvas></div>
    <?php endif; ?>
  </div>
  <div class="an-card">
    <div class="an-ct">Top Incident Types</div>
    <div class="an-cs">This month vs last month</div>
    <?php if (empty($type_growth)): ?>
    <div style="text-align:center;padding:40px;color:var(--ink-300);font-size:13px">No data.</div>
    <?php else: ?>
    <div style="position:relative;height:220px"><canvas id="ch-types"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($barangay_rank)): ?>
<div class="sec-hdr">&#127968; Barangay Performance Ranking</div>
<div class="an-row-2">
  <div class="an-card">
    <div class="an-ct">Risk Score by Barangay</div>
    <div class="an-cs">Higher = more intervention needed (0–100)</div>
    <div style="position:relative;height:<?= max(200, count($barangay_rank) * 38 + 60) ?>px"><canvas id="ch-bgy-risk"></canvas></div>
  </div>
  <div class="an-card" style="overflow-x:auto">
    <div class="an-ct">Performance Table</div>
    <div class="an-cs">Ranked by composite risk score</div>
    <table class="an-tbl">
      <thead><tr><th>#</th><th>Barangay</th><th>Total</th><th>Resolved%</th><th>Backlog%</th><th>Critical</th><th>Risk</th></tr></thead>
      <tbody>
        <?php foreach ($barangay_rank as $ri => $br):
          $rc = $br['risk_score']>=60?'var(--rose-500)':($br['risk_score']>=35?'var(--amber-500)':'var(--emerald-500)');
        ?>
        <tr>
          <td style="color:var(--ink-400)"><?= $ri+1 ?></td>
          <td style="font-weight:600"><?= e($br['barangay_name']) ?></td>
          <td><?= $br['total'] ?></td>
          <td><?= $br['resolution_rate'] ?>%</td>
          <td><?= $br['backlog_rate'] ?>%</td>
          <td style="color:var(--rose-600);font-weight:700"><?= $br['critical_count'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:7px">
              <div class="risk-bar"><div class="risk-fill" style="width:<?= min(100,$br['risk_score']) ?>%;background:<?= $rc ?>"></div></div>
              <span style="font-size:11px;font-weight:700;color:<?= $rc ?>"><?= $br['risk_score'] ?></span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="sec-hdr">&#128269; Incident Type Growth Analysis</div>
<div class="an-card-wide" style="overflow-x:auto">
  <?php if (empty($type_growth)): ?>
  <div style="text-align:center;padding:40px;color:var(--ink-300);font-size:13px">No incident type data for the current period.</div>
  <?php else: ?>
  <table class="an-tbl">
    <thead><tr><th>Incident Type</th><th>This Month</th><th>Last Month</th><th>MoM Growth</th><th>Volume Bar</th></tr></thead>
    <tbody>
      <?php foreach ($type_growth as $tg):
        $gpct = (float)$tg['growth_pct'];
        $gcls = $gpct > 20 ? 'badge-up' : ($gpct < -10 ? 'badge-dn' : 'badge-nt');
        $garr = $gpct > 20 ? '&#9650;' : ($gpct < -10 ? '&#9660;' : '&ndash;');
        $bar_w = $max_type_now > 0 ? round($tg['cnt_now'] / $max_type_now * 100) : 0;
      ?>
      <tr>
        <td style="font-weight:600;color:var(--ink-900)"><?= e($tg['incident_type']) ?></td>
        <td><?= (int)$tg['cnt_now'] ?></td>
        <td style="color:var(--ink-400)"><?= (int)$tg['cnt_prev'] ?></td>
        <td><span class="<?= $gcls ?>"><?= $garr ?> <?= abs($gpct) ?>%</span></td>
        <td style="min-width:80px"><div style="background:var(--ink-100);border-radius:3px;height:5px;overflow:hidden"><div class="bar-mini" style="width:<?= $bar_w ?>%"></div></div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if (!empty($monthly_trend) || !empty($type_growth) || !empty($barangay_rank)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function(){
'use strict';
var BLUE='#378ADD',BLUE_A='rgba(55,138,221,0.13)',GREEN='#059669',GREEN_A='rgba(5,150,105,0.12)',
    AMBER='#D97706',ROSE='#E11D48',ROSE_A='rgba(225,29,72,0.11)',VIOLET='#7C3AED',
    GRID_C='rgba(0,0,0,0.045)',TF={size:10,family:"'Plus Jakarta Sans',sans-serif"};

function xAx(r){ return {grid:{display:false},ticks:{font:TF,maxRotation:r||0,autoSkip:true,maxTicksLimit:12}}; }
function yAx(pct){ return {grid:{color:GRID_C},ticks:{font:TF,callback:pct?function(v){return v+'%';}:undefined}}; }

<?php if (!empty($monthly_trend)): ?>
var allLabels    = <?= aj($all_labels) ?>;
var predPadded   = <?= aj($pred_padded) ?>;
var actPadded    = <?= aj($actuals_padded) ?>;
var anomalyPts   = <?= aj($anomaly_points) ?>;
var r3Padded     = <?= aj($rolling3_padded) ?>;
var r6Padded     = <?= aj($rolling6_padded) ?>;
var labels       = <?= aj($chart_labels) ?>;
var resRate      = <?= aj($chart_res_rate) ?>;
var backlogRate  = <?= aj($chart_backlog) ?>;
var momData      = <?= aj($chart_mom) ?>;
var critical     = <?= aj($chart_critical) ?>;
var serious      = <?= aj($chart_serious) ?>;
var moderate     = <?= aj($chart_moderate) ?>;
var minor        = <?= aj($chart_minor) ?>;

new Chart(document.getElementById('ch-trend'),{
  type:'line',
  data:{labels:allLabels,datasets:[
    {label:'Actual',   data:actPadded,  borderColor:BLUE, backgroundColor:BLUE_A,  borderWidth:2.5,fill:true, tension:0.3,pointRadius:3,pointHoverRadius:5,spanGaps:false},
    {label:'Forecast', data:predPadded, borderColor:BLUE, backgroundColor:'transparent',borderWidth:2,borderDash:[6,4],tension:0.3,pointRadius:2,fill:false,spanGaps:true},
    {label:'3M Avg',   data:r3Padded,   borderColor:AMBER,borderWidth:1.5,borderDash:[4,3],pointRadius:0,fill:false,tension:0.3,spanGaps:false},
    {label:'6M Avg',   data:r6Padded,   borderColor:GREEN,borderWidth:1.5,borderDash:[3,3],pointRadius:0,fill:false,tension:0.3,spanGaps:false},
    {label:'Anomaly',  data:anomalyPts, borderColor:ROSE, backgroundColor:ROSE,borderWidth:0,pointRadius:9,pointStyle:'triangle',fill:false,showLine:false},
  ]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:true,position:'top',labels:{font:TF,boxWidth:11,padding:14}},tooltip:{mode:'index',intersect:false}},
    scales:{x:xAx(45),y:yAx(false)}},
});

new Chart(document.getElementById('ch-resolution'),{
  type:'line',
  data:{labels:labels,datasets:[{label:'Resolution %',data:resRate,borderColor:GREEN,backgroundColor:GREEN_A,fill:true,tension:0.35,borderWidth:2.5,pointRadius:3}]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{mode:'index',intersect:false}},
    scales:{x:xAx(45),y:{min:0,max:100,grid:{color:GRID_C},ticks:{font:TF,callback:function(v){return v+'%';}}}}},
});

new Chart(document.getElementById('ch-backlog'),{
  type:'line',
  data:{labels:labels,datasets:[{label:'Backlog %',data:backlogRate,borderColor:ROSE,backgroundColor:ROSE_A,fill:true,tension:0.35,borderWidth:2.5,pointRadius:3}]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{mode:'index',intersect:false}},
    scales:{x:xAx(45),y:yAx(true)}},
});

new Chart(document.getElementById('ch-stacked'),{
  type:'bar',
  data:{labels:labels,datasets:[
    {label:'Critical',data:critical,backgroundColor:VIOLET,stack:'v'},
    {label:'Serious', data:serious, backgroundColor:ROSE,  stack:'v'},
    {label:'Moderate',data:moderate,backgroundColor:AMBER, stack:'v'},
    {label:'Minor',   data:minor,   backgroundColor:GREEN, stack:'v'},
  ]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:true,position:'top',labels:{font:TF,boxWidth:10,padding:12}},tooltip:{mode:'index',intersect:false}},
    scales:{x:{stacked:true,grid:{display:false},ticks:{font:TF,maxRotation:45,maxTicksLimit:12}},y:{stacked:true,grid:{color:GRID_C},ticks:{font:TF}}}},
});

new Chart(document.getElementById('ch-mom'),{
  type:'bar',
  data:{labels:labels,datasets:[{label:'MoM %',data:momData,backgroundColor:momData.map(function(v){return v>=0?ROSE:GREEN;}),borderRadius:3}]},
  options:{responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return c.parsed.y+'%';}}}},
    scales:{x:{grid:{display:false},ticks:{font:TF,maxRotation:45,maxTicksLimit:12}},y:{grid:{color:GRID_C},ticks:{font:TF,callback:function(v){return v+'%';}}}}},
});
<?php endif; ?>

<?php if (!empty($type_growth)): ?>
var typeLabels=<?= aj($type_labels) ?>,typeNow=<?= aj($type_now) ?>,typePrev=<?= aj($type_prev) ?>;
new Chart(document.getElementById('ch-types'),{
  type:'bar',
  data:{labels:typeLabels,datasets:[
    {label:'This Month',data:typeNow, backgroundColor:BLUE,             borderRadius:3},
    {label:'Last Month',data:typePrev,backgroundColor:'rgba(0,0,0,0.07)',borderRadius:3},
  ]},
  options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:true,position:'top',labels:{font:TF,boxWidth:10,padding:10}},tooltip:{mode:'index',intersect:false}},
    scales:{x:{grid:{color:GRID_C},ticks:{font:TF}},y:{grid:{display:false},ticks:{font:TF}}}},
});
<?php endif; ?>

<?php if (!empty($barangay_rank)): ?>
var bgyLabels=<?= aj($bgy_labels) ?>,bgyRisk=<?= aj($bgy_risk) ?>;
new Chart(document.getElementById('ch-bgy-risk'),{
  type:'bar',
  data:{labels:bgyLabels,datasets:[{label:'Risk Score',data:bgyRisk,backgroundColor:bgyRisk.map(function(v){return v>=60?ROSE:v>=35?AMBER:GREEN;}),borderRadius:3}]},
  options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return 'Risk: '+c.parsed.x;}}}},
    scales:{x:{min:0,max:100,grid:{color:GRID_C},ticks:{font:TF}},y:{grid:{display:false},ticks:{font:TF}}}},
});
<?php endif; ?>

})();
</script>
<?php endif; ?>
</div>

<script>
(function(){
  var analyticsAbort = null;

  function runRegionScripts(region) {
    var scripts = Array.prototype.slice.call(region.querySelectorAll('script'));
    return scripts.reduce(function(chain, oldScript) {
      return chain.then(function() {
        return new Promise(function(resolve) {
          if (oldScript.src && oldScript.src.indexOf('Chart.js') !== -1 && window.Chart) {
            resolve();
            return;
          }
          var script = document.createElement('script');
          Array.prototype.slice.call(oldScript.attributes).forEach(function(attr) {
            script.setAttribute(attr.name, attr.value);
          });
          if (oldScript.src) {
            script.onload = resolve;
            script.onerror = resolve;
          } else {
            script.textContent = oldScript.textContent;
          }
          document.body.appendChild(script);
          if (!oldScript.src) resolve();
        });
      });
    }, Promise.resolve());
  }

  function loadAnalytics(url, pushState) {
    var region = document.getElementById('analytics-live-region');
    if (!region) {
      window.location.href = url;
      return;
    }
    if (analyticsAbort) analyticsAbort.abort();
    analyticsAbort = new AbortController();
    region.style.opacity = '0.45';
    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      signal: analyticsAbort.signal
    })
      .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var next = doc.getElementById('analytics-live-region');
        if (!next) {
          window.location.href = url;
          return;
        }
        region.replaceWith(next);
        if (pushState) history.pushState(null, '', url);
        runRegionScripts(next);
      })
      .catch(function(err) {
        region.style.opacity = '';
        if (err.name === 'AbortError') return;
        showToast('Analytics filters failed. Please try again.', 'error');
      });
  }

  document.addEventListener('change', function(e) {
    var form = e.target.closest('#analytics-live-region .an-filter-bar');
    if (!form) return;
    var params = new URLSearchParams(new FormData(form));
    loadAnalytics('?' + params.toString(), true);
  });

  document.addEventListener('click', function(e) {
    var clear = e.target.closest('#analytics-live-region .an-filter-bar a[href*="page=analytics"]');
    if (!clear) return;
    e.preventDefault();
    loadAnalytics(clear.href, true);
  });

  window.addEventListener('popstate', function() {
    if ((location.search || '').indexOf('page=analytics') !== -1) {
      loadAnalytics(location.href, false);
    }
  });
})();
</script>
