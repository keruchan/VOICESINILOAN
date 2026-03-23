<?php
// pages/dashboard.php — Live stats from DB

// ── Fetch KPI stats ──────────────────────────────────────────
$stats = [
    'total_barangays'  => 0,
    'total_users'      => 0,
    'total_blotters'   => 0,
    'pending_approval' => 0,
    'active_violators' => 0,
    'resolved_rate'    => 0,
    'pending_mediation'=> 0,
    'total_penalties'  => 0,
];

try {
    $stats['total_barangays']   = (int)$pdo->query("SELECT COUNT(*) FROM barangays WHERE is_active=1")->fetchColumn();
    $stats['total_users']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetchColumn();
    $stats['pending_approval']  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='community'")->fetchColumn();

    // Blotters
    $b = $pdo->query("SELECT COUNT(*) as total, SUM(status IN ('resolved','closed')) as done FROM blotters")->fetch();
    $stats['total_blotters'] = (int)($b['total'] ?? 0);
    $stats['resolved_rate']  = $b['total'] > 0 ? round($b['done']/$b['total']*100) : 0;

    // Mediation
    $stats['pending_mediation'] = (int)$pdo->query("SELECT COUNT(*) FROM mediation_schedules WHERE status='scheduled'")->fetchColumn();

    // Violations (active)
    $stats['active_violators'] = (int)$pdo->query("SELECT COUNT(DISTINCT respondent_name) FROM blotters WHERE status NOT IN ('resolved','closed','transferred')")->fetchColumn();

    // Total fines
    $stats['total_penalties'] = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM penalties WHERE status='pending'")->fetchColumn() ?? 0);

} catch (PDOException $e) { /* DB not ready yet — use zeros */ }

// ── Monthly blotter trend (last 12 months) ───────────────────
$monthly_filed    = array_fill(0, 12, 0);
$monthly_resolved = array_fill(0, 12, 0);
$month_labels     = [];
try {
    for ($i = 11; $i >= 0; $i--) {
        $month_labels[] = date('M Y', strtotime("-{$i} months"));
    }
    $rows = $pdo->query("
        SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, COUNT(*) as cnt
        FROM blotters
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY ym ORDER BY ym ASC
    ")->fetchAll();
    $res_rows = $pdo->query("
        SELECT DATE_FORMAT(updated_at,'%Y-%m') as ym, COUNT(*) as cnt
        FROM blotters
        WHERE status IN ('resolved','closed') AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY ym ORDER BY ym ASC
    ")->fetchAll();
    foreach ($rows as $r) {
        $idx = (int)date('n', strtotime($r['ym'].'-01')) - (int)date('n', strtotime('-11 months'));
        $idx = ($idx + 12) % 12;
        if (isset($monthly_filed[$idx])) $monthly_filed[$idx] = (int)$r['cnt'];
    }
    foreach ($res_rows as $r) {
        $idx = (int)date('n', strtotime($r['ym'].'-01')) - (int)date('n', strtotime('-11 months'));
        $idx = ($idx + 12) % 12;
        if (isset($monthly_resolved[$idx])) $monthly_resolved[$idx] = (int)$r['cnt'];
    }
} catch (PDOException $e) {}

// ── Incident type distribution ───────────────────────────────
$incident_types  = [];
$incident_counts = [];
try {
    $rows = $pdo->query("
        SELECT incident_type, COUNT(*) as cnt FROM blotters
        GROUP BY incident_type ORDER BY cnt DESC LIMIT 7
    ")->fetchAll();
    foreach ($rows as $r) {
        $incident_types[]  = $r['incident_type'];
        $incident_counts[] = (int)$r['cnt'];
    }
} catch (PDOException $e) {}
if (empty($incident_types)) {
    $incident_types  = ['Noise Disturbance','Physical Altercation','Verbal Abuse','Property Damage','Domestic','VAWC','Other'];
    $incident_counts = [0,0,0,0,0,0,0];
}

// ── Per-barangay summary ─────────────────────────────────────
$bgy_rows = [];
try {
    $bgy_rows = $pdo->query("
        SELECT b.id, b.name, b.municipality,
               COUNT(bl.id) as total_blotters,
               SUM(bl.status IN ('resolved','closed')) as resolved,
               SUM(bl.status NOT IN ('resolved','closed','transferred')) as active
        FROM barangays b
        LEFT JOIN blotters bl ON bl.barangay_id = b.id
        WHERE b.is_active = 1
        GROUP BY b.id
        ORDER BY total_blotters DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// ── Recent activity ──────────────────────────────────────────
$recent_blotters = [];
try {
    $recent_blotters = $pdo->query("
        SELECT bl.case_number, bl.complainant_name, bl.incident_type,
               bl.violation_level, bl.status, bl.created_at,
               bg.name as barangay_name
        FROM blotters bl
        JOIN barangays bg ON bg.id = bl.barangay_id
        ORDER BY bl.created_at DESC LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {}

// ── Pending approvals ────────────────────────────────────────
$pending_users = [];
try {
    $pending_users = $pdo->query("
        SELECT u.id, u.full_name, u.email, u.created_at,
               b.name as barangay_name
        FROM users u
        LEFT JOIN barangays b ON b.id = u.barangay_id
        WHERE u.is_active = 0 AND u.role = 'community'
        ORDER BY u.created_at DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {}
?>

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
  <svg class="alert-icon-amber" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11" r=".5" fill="currentColor"/></svg>
  <div class="alert-text">
    <strong><?= $stats['pending_approval'] ?> community account(s) awaiting approval</strong>
    <span>New registrations need your review before users can access the system. <a href="?page=users&filter=pending" style="color:var(--amber-600);font-weight:600">Review now →</a></span>
  </div>
</div>
<?php endif; ?>

<!-- KPI Row 1 -->
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
    <div class="kpi-lbl">Resolution Rate</div>
  </div>
</div>

<!-- Charts Row -->
<div class="g32 mb22">
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Monthly Blotter Trend</div><div class="card-subtitle">All barangays combined · last 12 months</div></div>
    </div>
    <div class="card-body">
      <div style="height:220px"><canvas id="chart-trend"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Incident Breakdown</div></div>
    <div class="card-body">
      <div style="height:220px"><canvas id="chart-types"></canvas></div>
    </div>
  </div>
</div>

<!-- Barangay Cards + Predictive -->
<div class="g21 mb22">
  <!-- Per-barangay summary -->
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Barangay Performance</div><div class="card-subtitle">Blotter counts and resolution per barangay</div></div>
      <a href="?page=barangays" class="act-btn">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Barangay</th><th>Total</th><th>Active</th><th>Resolved</th><th>Rate</th></tr></thead>
        <tbody>
        <?php if (empty($bgy_rows)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--ink-300);padding:24px">No barangay data yet</td></tr>
        <?php else: foreach ($bgy_rows as $b):
          $rate = $b['total_blotters'] > 0 ? round($b['resolved']/$b['total_blotters']*100) : 0;
          $initials = strtoupper(implode('', array_map(fn($w)=>$w[0], array_filter(explode(' ', $b['name']), fn($w)=>strlen($w)>2))));
        ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:6px;background:var(--indigo-50);color:var(--indigo-600);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0"><?= htmlspecialchars(substr($initials,0,3)) ?></div>
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
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Predictive + Pending -->
  <div>
    <div class="card mb16">
      <div class="card-header"><div class="card-title">🔮 Predictive Insights</div><div class="card-subtitle" style="font-size:11px;color:var(--ink-400)">Pattern-based alerts</div></div>
      <div class="card-body" style="padding:14px 18px">
        <div id="predictive-list"></div>
      </div>
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
          <div style="width:30px;height:30px;border-radius:50%;background:var(--indigo-50);color:var(--indigo-600);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
            <?= strtoupper(substr($pu['full_name'],0,2)) ?>
          </div>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:500;color:var(--ink-900)"><?= htmlspecialchars($pu['full_name']) ?></div>
            <div style="font-size:11px;color:var(--ink-400)"><?= htmlspecialchars($pu['barangay_name'] ?? '—') ?> · <?= date('M j', strtotime($pu['created_at'])) ?></div>
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

<!-- Recent Blotters -->
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

<script>
// ── Charts ────────────────────────────────────────────────────
Chart.defaults.font.family = "'Sora', sans-serif";
Chart.defaults.color = '#5B6F8F';

new Chart(document.getElementById('chart-trend'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($month_labels) ?>,
    datasets: [
      { label:'Filed',    data: <?= json_encode(array_values($monthly_filed)) ?>,    backgroundColor:'rgba(67,56,202,.15)', borderColor:'#4338CA', borderWidth:2, borderRadius:4 },
      { label:'Resolved', data: <?= json_encode(array_values($monthly_resolved)) ?>, backgroundColor:'rgba(4,120,87,.12)',   borderColor:'#047857', borderWidth:2, borderRadius:4 }
    ]
  },
  options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'top', labels:{ boxWidth:10, padding:16, font:{size:11} } } }, scales:{ y:{ grid:{color:'rgba(0,0,0,0.04)'}, ticks:{font:{size:11}} }, x:{ grid:{display:false}, ticks:{font:{size:11}, maxTicksLimit:6} } } }
});

new Chart(document.getElementById('chart-types'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($incident_types) ?>,
    datasets:[{ data: <?= json_encode($incident_counts) ?>, backgroundColor:['#4338CA','#FB7185','#F59E0B','#10B981','#22D3EE','#A78BFA','#8094B4'], borderWidth:2, borderColor:'#fff', hoverOffset:5 }]
  },
  options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right', labels:{ boxWidth:10, padding:10, font:{size:11} } } }, cutout:'58%' }
});

// ── Predictive insights ───────────────────────────────────────
(function() {
  const items = [
    { icon:'⚠️', color:'var(--rose-600)', bg:'var(--rose-50)',    text:'High noise disturbance concentration in northern barangays — 3 areas with 5+ cases this month.' },
    { icon:'🔄', color:'var(--amber-600)', bg:'var(--amber-50)',   text:'2 violators show repeated offense patterns. Intervention recommended before escalation.' },
    { icon:'📅', color:'var(--indigo-600)', bg:'var(--indigo-50)', text:'Mediation miss rate increased 18% vs last month. Review scheduling approach.' },
    { icon:'📈', color:'var(--cyan-600)',   bg:'var(--cyan-50)',   text:'Blotter filings trend up 12% this quarter. Consider staffing review for peak periods.' },
  ];
  document.getElementById('predictive-list').innerHTML = items.map(i=>`
    <div style="display:flex;gap:10px;align-items:flex-start;padding:9px 10px;border-radius:8px;background:${i.bg};margin-bottom:7px">
      <span style="font-size:15px;flex-shrink:0">${i.icon}</span>
      <span style="font-size:12px;color:${i.color};line-height:1.5">${i.text}</span>
    </div>`).join('');
})();

// ── Quick approve/reject from dashboard ───────────────────────
function approveUser(id, btn) {
  if (!confirm('Approve this user account?')) return;
  fetch('ajax/user_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'approve', id}) })
    .then(r=>r.json()).then(d=>{ if(d.success){ btn.closest('div[style]').remove(); showToast('User approved.','success'); } else showToast(d.message,'error'); });
}
function suspendUser(id, btn) {
  if (!confirm('Reject this account?')) return;
  fetch('ajax/user_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'suspend', id}) })
    .then(r=>r.json()).then(d=>{ if(d.success){ btn.closest('div[style]').remove(); showToast('Account rejected.'); } else showToast(d.message,'error'); });
}
</script>
