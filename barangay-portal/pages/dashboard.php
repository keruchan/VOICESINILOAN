<?php
// pages/dashboard.php
$bid = (int)$user['barangay_id'];

// ── KPIs ──────────────────────────────────────────────────────
$kpi = ['total'=>0,'active'=>0,'resolved'=>0,'pending'=>0,'mediations'=>0,'violators'=>0];
try {
    $row = $pdo->query("
        SELECT COUNT(*) AS total,
               SUM(status NOT IN ('resolved','closed','transferred')) AS active,
               SUM(status IN ('resolved','closed'))                   AS resolved,
               SUM(status = 'pending_review')                         AS pending
        FROM blotters WHERE barangay_id = $bid
    ")->fetch();
    $kpi['total']     = (int)($row['total']    ?? 0);
    $kpi['active']    = (int)($row['active']   ?? 0);
    $kpi['resolved']  = (int)($row['resolved'] ?? 0);
    $kpi['pending']   = (int)($row['pending']  ?? 0);

    $kpi['mediations'] = (int)$pdo->query("
        SELECT COUNT(*) FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid AND ms.status = 'scheduled' AND ms.hearing_date >= CURDATE()
    ")->fetchColumn();

    $kpi['violators'] = (int)$pdo->query("
        SELECT COUNT(DISTINCT respondent_name) FROM blotters
        WHERE barangay_id = $bid AND respondent_name != '' AND respondent_name != 'Unknown'
          AND status NOT IN ('resolved','closed','transferred')
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

// ── Incident type donut ────────────────────────────────────────
$inc_labels = ['No Data']; $inc_data = [0];
try {
    $rows = $pdo->query("
        SELECT incident_type, COUNT(*) AS c FROM blotters
        WHERE barangay_id = $bid GROUP BY incident_type ORDER BY c DESC LIMIT 8
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

// ── Recent blotters ────────────────────────────────────────────
$recent = [];
try {
    $recent = $pdo->query("
        SELECT id, case_number, complainant_name, incident_type, violation_level, status, created_at
        FROM blotters WHERE barangay_id = $bid ORDER BY created_at DESC LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {}

// ── Upcoming hearings ──────────────────────────────────────────
$hearings = [];
try {
    $hearings = $pdo->query("
        SELECT ms.id, ms.hearing_date, ms.hearing_time, ms.venue,
               b.case_number, b.complainant_name, b.respondent_name
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid AND ms.status = 'scheduled' AND ms.hearing_date >= CURDATE()
        ORDER BY ms.hearing_date ASC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {}
?>

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
<div class="alert alert-amber mb16">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="var(--amber-600)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11.5" r=".5" fill="currentColor"/></svg>
  <div class="alert-text">
    <strong><?= $kpi['pending'] ?> blotter(s) awaiting review</strong>
    <span>New community submissions need officer action. <a href="?page=blotter-management&status=pending_review" style="color:var(--amber-600);font-weight:600">Review now →</a></span>
  </div>
</div>
<?php endif; ?>

<!-- KPI Cards -->
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

<!-- Charts row -->
<div class="g32 mb22">
  <div class="card">
    <div class="card-hdr">
      <div><div class="card-title">Monthly Trend</div><div class="card-sub">Filed vs Resolved — last 12 months</div></div>
    </div>
    <div class="card-body"><div style="height:200px"><canvas id="ch-trend"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-hdr"><div class="card-title">Incident Types</div></div>
    <div class="card-body"><div style="height:200px"><canvas id="ch-types"></canvas></div></div>
  </div>
</div>

<!-- Recent blotters + sidebar -->
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
            <td style="font-size:12px"><?= e($b['incident_type']) ?></td>
            <td><span class="chip <?= ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'][$b['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
            <td><span class="chip <?= ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-emerald','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'][$b['status']] ?? 'ch-slate' ?>"><?= ucwords(str_replace('_', ' ', $b['status'])) ?></span></td>
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
              <?= date('D, M j', strtotime($h['hearing_date'])) ?><?= $h['hearing_time'] ? ' at ' . date('g:i A', strtotime($h['hearing_time'])) : '' ?>
            </div>
            <div style="font-size:11px;color:var(--ink-300)">📍 <?= e($h['venue'] ?? 'Barangay Hall') ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Severity bars -->
    <div class="card">
      <div class="card-hdr"><div class="card-title">Severity Breakdown</div></div>
      <div class="card-body" style="padding:14px 18px">
        <?php foreach ([
          ['Critical', 'var(--violet-400)', $sev['critical']],
          ['Serious',  'var(--rose-400)',   $sev['serious']],
          ['Moderate', 'var(--amber-400)',  $sev['moderate']],
          ['Minor',    'var(--emerald-400)',$sev['minor']],
        ] as [$lbl, $col, $cnt]): ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--ink-600);margin-bottom:4px">
            <span><?= $lbl ?></span><span style="font-weight:600"><?= $cnt ?></span>
          </div>
          <div style="height:7px;background:var(--surface-2);border-radius:10px;overflow:hidden">
            <div style="width:<?= round($cnt / $sev_max * 100) ?>%;height:100%;background:<?= $col ?>;border-radius:10px"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
new Chart(document.getElementById('ch-trend'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [
      { label:'Filed',    data:<?= json_encode($filed_data) ?>, backgroundColor:'rgba(13,115,119,.15)', borderColor:'#0D7377', borderWidth:2, borderRadius:4 },
      { label:'Resolved', data:<?= json_encode($res_data) ?>,   backgroundColor:'rgba(4,120,87,.12)',   borderColor:'#047857', borderWidth:2, borderRadius:4 }
    ]
  },
  options: { responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'top', labels:{ boxWidth:10, font:{size:11} } } },
    scales:{ y:{ grid:{ color:'rgba(0,0,0,.04)' }, ticks:{font:{size:11}} },
             x:{ grid:{ display:false }, ticks:{ font:{size:11}, maxTicksLimit:6 } } } }
});
new Chart(document.getElementById('ch-types'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($inc_labels) ?>,
    datasets: [{ data:<?= json_encode($inc_data) ?>,
      backgroundColor:['#0D7377','#FB7185','#F59E0B','#10B981','#A78BFA','#2EBAC6','#4A7FAC','#BE123C'],
      borderWidth:2, borderColor:'#fff' }]
  },
  options: { responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'right', labels:{ boxWidth:10, padding:10, font:{size:11} } } },
    cutout:'58%' }
});
</script>
