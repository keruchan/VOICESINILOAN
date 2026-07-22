<?php
// pages/violator-monitor.php
$bid = (int)$user['barangay_id'];
$f_search = $_GET['search'] ?? '';
$f_risk   = $_GET['risk']   ?? '';

// KPI totals — always reflect ALL tracked violators, independent of the
// search/risk filter applied to the table below.
$kpi = ['total' => 0, 'critical' => 0, 'high' => 0, 'active_cases' => 0];
try {
    $rows = $pdo->query("
        SELECT
            COUNT(*) AS total_cases,
            SUM(status NOT IN ('resolved','closed','transferred')) AS active_cases,
            SUM(violation_level = 'critical') AS cnt_critical,
            SUM(violation_level = 'serious')  AS cnt_serious,
            SUM(violation_level = 'moderate') AS cnt_moderate,
            SUM(violation_level = 'minor')    AS cnt_minor
        FROM blotters
        WHERE barangay_id = $bid AND respondent_name != '' AND respondent_name != 'Unknown'
        GROUP BY respondent_name, respondent_contact
    ")->fetchAll();
    $kpi['total'] = count($rows);
    foreach ($rows as $r) {
        $score = min(100,
            (int)$r['cnt_critical'] * 30 + (int)$r['cnt_serious'] * 20 +
            (int)$r['cnt_moderate'] * 10 + (int)$r['cnt_minor']   *  5 +
            (int)$r['active_cases'] * 10
        );
        if ($score >= 80) $kpi['critical']++;
        elseif ($score >= 50) $kpi['high']++;
        $kpi['active_cases'] += (int)$r['active_cases'];
    }
} catch (PDOException $e) {}
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Violator Monitor</h2><p>Track repeat offenders and risk scores</p></div>
</div>

<div class="kpi-grid mb16">
  <div class="kpi-card kc-teal">
    <div class="kpi-top"><div class="kpi-icon ki-teal"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.5"/><path d="M2 14c0-3 2.5-5 6-5s6 2 6 5"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['total'] ?></div>
    <div class="kpi-lbl">Tracked Violators</div>
  </div>
  <div class="kpi-card kc-violet">
    <div class="kpi-top"><div class="kpi-icon ki-violet"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 1.5L14.5 13H1.5L8 1.5z"/><path d="M8 6.5v3"/><circle cx="8" cy="11.5" r=".6" fill="currentColor"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['critical'] ?></div>
    <div class="kpi-lbl">Critical Risk</div>
  </div>
  <div class="kpi-card kc-rose">
    <div class="kpi-top"><div class="kpi-icon ki-rose"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6.5"/><path d="M8 4.5v4l2.5 1.5"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['high'] ?></div>
    <div class="kpi-lbl">High Risk</div>
  </div>
  <div class="kpi-card kc-amber">
    <div class="kpi-top"><div class="kpi-icon ki-amber"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6.5h12"/></svg></div></div>
    <div class="kpi-val"><?= $kpi['active_cases'] ?></div>
    <div class="kpi-lbl">Active Cases (all violators)</div>
  </div>
</div>

<form id="vm-form" class="filter-bar" onsubmit="return false">
  <input type="hidden" name="page" value="violator-monitor">
  <div class="inp-icon" style="flex:1;max-width:280px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Violator name…" value="<?= e($f_search) ?>">
  </div>
  <select name="risk">
    <option value="">All Risk Levels</option>
    <option value="critical" <?= $f_risk==='critical'?'selected':'' ?>>Critical (80+)</option>
    <option value="high"     <?= $f_risk==='high'    ?'selected':'' ?>>High (50–79)</option>
    <option value="medium"   <?= $f_risk==='medium'  ?'selected':'' ?>>Medium (25–49)</option>
    <option value="low"      <?= $f_risk==='low'     ?'selected':'' ?>>Low (0–24)</option>
  </select>
  <button type="button" id="vm-clear" class="btn btn-ghost btn-sm">✕ Clear</button>
</form>

<div id="vm-results">
  <?php require __DIR__ . '/partials/violator-table.php'; ?>
</div>

<!-- Violator Cases Modal -->
<div class="modal-overlay" id="modal-vcases">
  <div class="modal vcases-modal">
    <div class="modal-hdr">
      <div>
        <span class="modal-title" id="vcases-title">Case History</span>
        <div class="vcases-sub" id="vcases-sub"></div>
      </div>
      <button class="modal-x" onclick="closeModal('modal-vcases')">×</button>
    </div>
    <div class="modal-body" id="vcases-body">
      <div class="vcases-loading" id="vcases-loading">
        <div class="spinner" style="margin:0 auto 12px;width:28px;height:28px;border-width:2px;border-color:rgba(20,145,155,.2);border-top-color:var(--teal-600)"></div>
        Loading case history…
      </div>

      <div id="vcases-content" style="display:none">
        <div class="vcases-strip" id="vcases-strip"></div>

        <div class="notices-filter-bar" style="margin-bottom:12px">
          <div class="inp-icon" style="flex:1;min-width:180px;max-width:280px">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M10 10l2.5 2.5"/></svg>
            <input type="search" id="vcases-search" placeholder="Search case no. or type…" oninput="vcFilter()">
          </div>
          <select id="vcases-filter-level" onchange="vcFilter()">
            <option value="">All Levels</option>
            <option value="minor">Minor</option>
            <option value="moderate">Moderate</option>
            <option value="serious">Serious</option>
            <option value="critical">Critical</option>
          </select>
          <select id="vcases-filter-status" onchange="vcFilter()">
            <option value="">All Statuses</option>
            <option value="pending_review">Pending</option>
            <option value="active">Active</option>
            <option value="mediation_set">Mediation Set</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
            <option value="escalated">Escalated</option>
            <option value="dismissed">Dismissed</option>
            <option value="cfa_issued">CFA Issued</option>
            <option value="transferred">Transferred</option>
          </select>
          <button class="btn btn-outline btn-sm" onclick="vcClearFilters()">✕ Clear</button>
          <span id="vcases-count" class="nf-count"></span>
        </div>

        <div id="vcases-no-results" style="display:none">
          <div class="empty-state"><div class="es-icon">🔍</div><div class="es-title">No cases match</div><div class="es-sub">Try adjusting the search or filters.</div></div>
        </div>

        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Case No.</th><th>Type</th><th>Level</th><th>Status</th><th>Filed</th><th></th></tr></thead>
            <tbody id="vcases-tbody"></tbody>
          </table>
        </div>
      </div>

      <div id="vcases-empty" style="display:none">
        <div class="empty-state"><div class="es-icon">📋</div><div class="es-title">No cases found</div></div>
      </div>
    </div>
  </div>
</div>

<style>
.vm-level-bar {
  display:flex;
  height:6px;
  border-radius:10px;
  overflow:hidden;
  background:var(--surface-2);
  margin-bottom:4px;
}
.vm-level-legend {
  display:flex;
  gap:6px;
  font-size:10px;
  font-weight:600;
}
.vcases-modal {
  width:880px;
  max-width:95vw;
  max-height:92vh;
  display:flex;
  flex-direction:column;
}
.vcases-modal .modal-body { overflow-y:auto; }
.vcases-sub { font-size:12px; color:var(--ink-400); margin-top:2px; }
.vcases-loading { text-align:center; padding:48px 20px; color:var(--ink-400); font-size:13px; }
.vcases-strip {
  display:flex;
  align-items:center;
  gap:18px;
  flex-wrap:wrap;
  background:var(--teal-50);
  border:1px solid var(--teal-100);
  border-radius:var(--r-md);
  padding:12px 16px;
  margin-bottom:16px;
  font-size:12px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  liveFilter({
    form: '#vm-form',
    result: '#vm-results',
    endpoint: 'ajax/violator_search.php',
    clearBtn: '#vm-clear',
  });
});

let vcCases = [];

function showCases(name, contact, riskLabel, riskScore, totalCases) {
  document.getElementById('vcases-title').textContent = name;
  document.getElementById('vcases-sub').textContent = 'Full case history for this violator';
  document.getElementById('vcases-loading').style.display = '';
  document.getElementById('vcases-content').style.display = 'none';
  document.getElementById('vcases-empty').style.display = 'none';
  document.getElementById('vcases-search').value = '';
  document.getElementById('vcases-filter-level').value = '';
  document.getElementById('vcases-filter-status').value = '';
  openModal('modal-vcases');

  const riskChipCls = riskScore >= 80 ? 'ch-violet' : riskScore >= 50 ? 'ch-rose' : riskScore >= 25 ? 'ch-amber' : 'ch-emerald';
  document.getElementById('vcases-strip').innerHTML =
    `<span>👤 <strong>${nbEsc(name)}</strong></span>` +
    (contact ? `<span>📞 ${nbEsc(contact)}</span>` : '') +
    `<span class="chip ${riskChipCls}">${nbEsc(riskLabel)} · ${riskScore}</span>` +
    `<span>${totalCases} case${totalCases==1?'':'s'} total</span>`;

  fetch('ajax/get_violator_cases.php?name=' + encodeURIComponent(name))
    .then(r => r.json())
    .then(d => {
      document.getElementById('vcases-loading').style.display = 'none';
      if (!d.success || !d.cases.length) {
        document.getElementById('vcases-empty').style.display = '';
        return;
      }
      vcCases = d.cases;
      document.getElementById('vcases-content').style.display = '';
      vcFilter();
    })
    .catch(() => {
      document.getElementById('vcases-loading').style.display = 'none';
      document.getElementById('vcases-empty').style.display = '';
    });
}

function vcFilter() {
  const search = document.getElementById('vcases-search').value.trim().toLowerCase();
  const level  = document.getElementById('vcases-filter-level').value;
  const status = document.getElementById('vcases-filter-status').value;

  const matched = vcCases.filter(c => {
    const matchSearch = !search || c.case_number.toLowerCase().includes(search) || c.incident_type.toLowerCase().includes(search);
    const matchLevel  = !level  || c.violation_level === level;
    const matchStatus = !status || c.status === status;
    return matchSearch && matchLevel && matchStatus;
  });

  document.getElementById('vcases-tbody').innerHTML = matched.map(c => `
    <tr>
      <td class="td-mono">${nbEsc(c.case_number)}</td>
      <td>${nbEsc(c.incident_type)}</td>
      <td>${levelChip(c.violation_level)}</td>
      <td>${statusChip(c.status)}</td>
      <td style="font-size:12px;color:var(--ink-400)">${nbEsc(c.created_at.substring(0,10))}</td>
      <td><button class="act-btn" onclick="closeModal('modal-vcases');viewBlotter(${c.id})">View Case</button></td>
    </tr>`).join('');

  document.getElementById('vcases-no-results').style.display = (vcCases.length > 0 && matched.length === 0) ? '' : 'none';

  const countEl = document.getElementById('vcases-count');
  const hasFilter = search || level || status;
  countEl.textContent = hasFilter ? `Showing ${matched.length} of ${vcCases.length}` : '';
}

function vcClearFilters() {
  document.getElementById('vcases-search').value = '';
  document.getElementById('vcases-filter-level').value = '';
  document.getElementById('vcases-filter-status').value = '';
  vcFilter();
}
</script>
