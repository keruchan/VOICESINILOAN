<?php
// pages/reports.php

$filter_bgy  = (int)($_GET['barangay'] ?? 0);
$filter_year = (int)($_GET['year'] ?? date('Y'));
$filter_type = $_GET['type'] ?? '';

// All barangays for filter
$barangays = [];
try { $barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active=1 ORDER BY name")->fetchAll(); } catch(PDOException $e){}

// Summary totals
$totals = ['blotters'=>0,'resolved'=>0,'pending_med'=>0,'violations'=>0,'penalties'=>0];
try {
    $bgy_w  = $filter_bgy ? "AND b.barangay_id = $filter_bgy" : '';
    $year_w = $filter_year ? "AND YEAR(b.created_at) = $filter_year" : '';
    $r = $pdo->query("SELECT COUNT(*) as c, SUM(status IN ('resolved','closed')) as res FROM blotters b WHERE 1=1 $bgy_w $year_w")->fetch();
    $totals['blotters'] = (int)($r['c']??0);
    $totals['resolved'] = (int)($r['res']??0);
    $totals['pending_med'] = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id WHERE ms.status='scheduled' $bgy_w")->fetchColumn();
    $totals['penalties'] = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM penalties p JOIN blotters b ON b.id=p.blotter_id WHERE p.status='pending' $bgy_w")->fetchColumn()??0);
} catch(PDOException $e){}

// Per-barangay breakdown
$bgy_report = [];
try {
    $bgy_report = $pdo->query("
        SELECT bg.id, bg.name, bg.municipality,
               COUNT(bl.id) as total,
               SUM(bl.status IN ('resolved','closed')) as resolved,
               SUM(bl.status NOT IN ('resolved','closed','transferred')) as active,
               SUM(bl.violation_level='critical') as critical_count,
               SUM(bl.violation_level='serious') as serious_count,
               SUM(bl.violation_level='moderate') as moderate_count,
               SUM(bl.violation_level='minor') as minor_count
        FROM barangays bg
        LEFT JOIN blotters bl ON bl.barangay_id = bg.id
          AND YEAR(bl.created_at) = $filter_year
        WHERE bg.is_active = 1
        GROUP BY bg.id ORDER BY total DESC
    ")->fetchAll();
} catch(PDOException $e){}

// Top incident types for chart
$inc_labels = []; $inc_data = [];
try {
    $bgy_c = $filter_bgy ? "AND barangay_id=$filter_bgy" : '';
    $yr_c  = $filter_year ? "AND YEAR(created_at)=$filter_year" : '';
    $rows  = $pdo->query("SELECT incident_type, COUNT(*) as c FROM blotters WHERE 1=1 $bgy_c $yr_c GROUP BY incident_type ORDER BY c DESC LIMIT 8")->fetchAll();
    foreach ($rows as $r) { $inc_labels[] = $r['incident_type']; $inc_data[] = (int)$r['c']; }
} catch(PDOException $e){}
if (empty($inc_labels)) { $inc_labels = ['No data']; $inc_data = [0]; }

// Monthly breakdown for selected year
$monthly = array_fill(0,12,0);
$monthly_res = array_fill(0,12,0);
try {
    $bgy_c = $filter_bgy ? "AND barangay_id=$filter_bgy" : '';
    $rows  = $pdo->query("SELECT MONTH(created_at)-1 as m, COUNT(*) as c FROM blotters WHERE YEAR(created_at)=$filter_year $bgy_c GROUP BY m")->fetchAll();
    foreach ($rows as $r) $monthly[(int)$r['m']] = (int)$r['c'];
    $rows2 = $pdo->query("SELECT MONTH(updated_at)-1 as m, COUNT(*) as c FROM blotters WHERE YEAR(updated_at)=$filter_year AND status IN ('resolved','closed') $bgy_c GROUP BY m")->fetchAll();
    foreach ($rows2 as $r) $monthly_res[(int)$r['m']] = (int)$r['c'];
} catch(PDOException $e){}
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>Reports & Analytics</h2>
    <p>Cross-barangay summary reports and trend analysis</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
    <a href="ajax/export_report.php?barangay=<?= $filter_bgy ?>&year=<?= $filter_year ?>" class="btn btn-outline btn-sm">Export CSV</a>
  </div>
</div>

<!-- Report Filters -->
<div class="card mb22">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="page" value="reports">
      <select name="year" onchange="this.form.submit()" style="width:auto;min-width:110px">
        <?php for ($y=date('Y'); $y>=2020; $y--): ?>
          <option value="<?= $y ?>" <?= $y==$filter_year?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <select name="barangay" onchange="this.form.submit()" style="width:auto;min-width:200px">
        <option value="0">All Barangays</option>
        <?php foreach ($barangays as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $b['id']==$filter_bgy?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Apply</button>
      <span style="margin-left:auto;font-size:12px;color:var(--ink-400)">
        <?= $filter_bgy ? 'Filtered: '.htmlspecialchars($barangays[array_search($filter_bgy,array_column($barangays,'id'))]['name']??'') : 'All barangays' ?>
        · <?= $filter_year ?>
      </span>
    </form>
  </div>
</div>

<!-- KPI Summary -->
<div class="kpi-grid mb22">
  <div class="kpi-card kc-indigo">
    <div class="kpi-top"><div class="kpi-icon ki-indigo"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="2" width="12" height="12" rx="1.5"/><path d="M5 7h6M5 9.5h4"/></svg></div></div>
    <div class="kpi-val"><?= $totals['blotters'] ?></div>
    <div class="kpi-lbl">Total Blotters</div>
  </div>
  <div class="kpi-card kc-emerald">
    <div class="kpi-top"><div class="kpi-icon ki-emerald"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 8.5l3 3 7-7"/></svg></div></div>
    <div class="kpi-val"><?= $totals['resolved'] ?></div>
    <div class="kpi-lbl">Resolved Cases</div>
  </div>
  <div class="kpi-card kc-amber">
    <div class="kpi-top"><div class="kpi-icon ki-amber"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="2" width="12" height="12" rx="1.5"/><path d="M2 6h12M6 6v8"/></svg></div></div>
    <div class="kpi-val"><?= $totals['pending_med'] ?></div>
    <div class="kpi-lbl">Pending Mediation</div>
  </div>
  <div class="kpi-card kc-rose">
    <div class="kpi-top"><div class="kpi-icon ki-rose"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 2v8M5 7l3 3 3-3"/><path d="M3 14h10"/></svg></div></div>
    <div class="kpi-val">₱<?= number_format($totals['penalties']) ?></div>
    <div class="kpi-lbl">Pending Fines</div>
  </div>
</div>

<!-- Charts Row -->
<div class="g21 mb22">
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Monthly Blotter Trend — <?= $filter_year ?></div><div class="card-subtitle">Filed vs Resolved per month</div></div>
    </div>
    <div class="card-body"><div style="height:240px"><canvas id="rpt-monthly"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Incident Types</div></div>
    <div class="card-body"><div style="height:240px"><canvas id="rpt-types"></canvas></div></div>
  </div>
</div>

<!-- Per-barangay Table -->
<div class="card">
  <div class="card-header">
    <div><div class="card-title">Per-Barangay Breakdown</div><div class="card-subtitle"><?= $filter_year ?> · All incident levels</div></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Barangay</th>
          <th>Municipality</th>
          <th>Total</th>
          <th>Active</th>
          <th>Resolved</th>
          <th>Critical</th>
          <th>Serious</th>
          <th>Moderate</th>
          <th>Minor</th>
          <th>Rate</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($bgy_report)): ?>
        <tr><td colspan="11" style="text-align:center;padding:30px;color:var(--ink-300)">No data for this period.</td></tr>
      <?php else:
        $grand = ['total'=>0,'active'=>0,'resolved'=>0,'critical'=>0,'serious'=>0,'moderate'=>0,'minor'=>0];
        foreach ($bgy_report as $r) foreach (array_keys($grand) as $k) if (isset($r[$k])) $grand[$k] += $r[$k];
        foreach ($bgy_report as $r):
          $rate = $r['total']>0 ? round($r['resolved']/$r['total']*100) : 0;
          $col  = $rate>=70?'var(--emerald-400)':($rate>=40?'var(--amber-400)':'var(--rose-400)');
          $initials = implode('', array_map(fn($w)=>strtoupper($w[0]), array_filter(explode(' ',$r['name']),fn($w)=>strlen($w)>2)));
      ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:26px;height:26px;border-radius:5px;background:var(--indigo-50);color:var(--indigo-600);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;flex-shrink:0"><?= htmlspecialchars(substr($initials,0,3)) ?></div>
              <span class="td-main"><?= htmlspecialchars($r['name']) ?></span>
            </div>
          </td>
          <td style="color:var(--ink-400)"><?= htmlspecialchars($r['municipality']) ?></td>
          <td style="font-weight:700"><?= (int)$r['total'] ?></td>
          <td><span class="chip chip-amber" style="font-size:11px"><?= (int)$r['active'] ?></span></td>
          <td><span class="chip chip-emerald" style="font-size:11px"><?= (int)$r['resolved'] ?></span></td>
          <td style="font-weight:600;color:var(--rose-600)"><?= (int)$r['critical_count'] ?></td>
          <td style="color:var(--rose-400)"><?= (int)$r['serious_count'] ?></td>
          <td style="color:var(--amber-600)"><?= (int)$r['moderate_count'] ?></td>
          <td style="color:var(--ink-400)"><?= (int)$r['minor_count'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:5px">
              <div style="width:50px;height:5px;background:var(--surface-2);border-radius:10px;overflow:hidden"><div style="width:<?= $rate ?>%;height:100%;background:<?= $col ?>;border-radius:10px"></div></div>
              <span style="font-size:11px;font-weight:600;color:<?= $col ?>"><?= $rate ?>%</span>
            </div>
          </td>
          <td><a href="?page=reports&barangay=<?= $r['id'] ?>&year=<?= $filter_year ?>" class="act-btn btn-xs">Drill in</a></td>
        </tr>
      <?php endforeach;
        // Totals row
        $grand_rate = $grand['total']>0 ? round($grand['resolved']/$grand['total']*100) : 0;
      ?>
        <tr style="background:var(--surface);font-weight:700;border-top:2px solid var(--ink-100)">
          <td colspan="2" class="td-main">TOTALS</td>
          <td><?= $grand['total'] ?></td>
          <td><?= $grand['active'] ?></td>
          <td><?= $grand['resolved'] ?></td>
          <td style="color:var(--rose-600)"><?= $grand['critical'] ?></td>
          <td style="color:var(--rose-400)"><?= $grand['serious'] ?></td>
          <td style="color:var(--amber-600)"><?= $grand['moderate'] ?></td>
          <td style="color:var(--ink-400)"><?= $grand['minor'] ?></td>
          <td><span style="font-size:12px;font-weight:700;color:var(--ink-600)"><?= $grand_rate ?>%</span></td>
          <td></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
Chart.defaults.font.family = "'Sora', sans-serif";
Chart.defaults.color = '#5B6F8F';

new Chart(document.getElementById('rpt-monthly'), {
  type:'bar',
  data:{
    labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    datasets:[
      { label:'Filed',    data:<?= json_encode(array_values($monthly)) ?>,     backgroundColor:'rgba(67,56,202,.15)', borderColor:'#4338CA', borderWidth:2, borderRadius:4 },
      { label:'Resolved', data:<?= json_encode(array_values($monthly_res)) ?>, backgroundColor:'rgba(4,120,87,.12)',   borderColor:'#047857', borderWidth:2, borderRadius:4 }
    ]
  },
  options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:11}}}}, scales:{ y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:11}}}, x:{grid:{display:false},ticks:{font:{size:11}}} } }
});

new Chart(document.getElementById('rpt-types'), {
  type:'bar',
  data:{
    labels:<?= json_encode($inc_labels) ?>,
    datasets:[{ data:<?= json_encode($inc_data) ?>, backgroundColor:'rgba(67,56,202,.15)', borderColor:'#4338CA', borderWidth:2, borderRadius:5 }]
  },
  options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:11}}}, y:{grid:{display:false},ticks:{font:{size:11}}} } }
});
</script>
