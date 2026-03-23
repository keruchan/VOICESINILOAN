<?php
// pages/violator-monitor.php
$bid = (int)$user['barangay_id'];
$f_search = $_GET['search'] ?? '';
$f_risk   = $_GET['risk']   ?? '';

// Aggregate per respondent
$where_extra = $f_search ? "AND respondent_name LIKE " . $pdo->quote("%{$f_search}%") : '';
$violators = [];
try {
    $violators = $pdo->query("
        SELECT
            respondent_name,
            respondent_contact,
            COUNT(*) AS total_cases,
            SUM(status NOT IN ('resolved','closed','transferred')) AS active_cases,
            SUM(violation_level = 'critical') AS cnt_critical,
            SUM(violation_level = 'serious')  AS cnt_serious,
            SUM(violation_level = 'moderate') AS cnt_moderate,
            SUM(violation_level = 'minor')    AS cnt_minor,
            MAX(created_at) AS last_case_date
        FROM blotters
        WHERE barangay_id = $bid
          AND respondent_name != '' AND respondent_name != 'Unknown'
          $where_extra
        GROUP BY respondent_name, respondent_contact
        ORDER BY cnt_critical DESC, cnt_serious DESC, active_cases DESC, total_cases DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// Compute risk score per row
$scored = [];
foreach ($violators as $v) {
    $score = min(100,
        (int)$v['cnt_critical']  * 30 +
        (int)$v['cnt_serious']   * 20 +
        (int)$v['cnt_moderate']  * 10 +
        (int)$v['cnt_minor']     *  5 +
        (int)$v['active_cases']  * 10
    );
    $v['risk_score'] = $score;
    $v['risk_label'] = $score >= 80 ? 'Critical' : ($score >= 50 ? 'High' : ($score >= 25 ? 'Medium' : 'Low'));
    $scored[] = $v;
}
// Filter by risk level
if ($f_risk) {
    $scored = array_filter($scored, fn($v) => strtolower($v['risk_label']) === $f_risk);
}
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Violator Monitor</h2><p>Track repeat offenders and risk scores</p></div>
</div>

<form method="GET" class="filter-bar">
  <input type="hidden" name="page" value="violator-monitor">
  <div class="inp-icon" style="flex:1;max-width:280px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Violator name…" value="<?= e($f_search) ?>">
  </div>
  <select name="risk" onchange="this.form.submit()">
    <option value="">All Risk Levels</option>
    <option value="critical" <?= $f_risk==='critical'?'selected':'' ?>>Critical (80+)</option>
    <option value="high"     <?= $f_risk==='high'    ?'selected':'' ?>>High (50–79)</option>
    <option value="medium"   <?= $f_risk==='medium'  ?'selected':'' ?>>Medium (25–49)</option>
    <option value="low"      <?= $f_risk==='low'     ?'selected':'' ?>>Low (0–24)</option>
  </select>
  <button type="submit" class="btn btn-outline btn-sm">Filter</button>
  <a href="?page=violator-monitor" class="btn btn-ghost btn-sm">Clear</a>
</form>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Violator</th><th>Contact</th><th>Risk Score</th><th>Total</th><th>Active</th><th>Critical</th><th>Serious</th><th>Last Case</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (empty($scored)): ?>
        <tr><td colspan="9"><div class="empty-state"><div class="es-icon">🛡️</div><div class="es-title">No violators tracked</div><div class="es-sub">Violators appear once blotters name a respondent</div></div></td></tr>
      <?php else: foreach ($scored as $v):
        $score = $v['risk_score'];
        $rc = $score >= 80 ? 'ch-violet' : ($score >= 50 ? 'ch-rose' : ($score >= 25 ? 'ch-amber' : 'ch-emerald'));
        $bar_col = $score >= 80 ? 'var(--violet-400)' : ($score >= 50 ? 'var(--rose-400)' : ($score >= 25 ? 'var(--amber-400)' : 'var(--emerald-400))'));
      ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px">
              <div style="width:30px;height:30px;border-radius:50%;background:var(--teal-50);color:var(--teal-600);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($v['respondent_name'], 0, 2)) ?>
              </div>
              <span class="td-main"><?= e($v['respondent_name']) ?></span>
            </div>
          </td>
          <td style="font-size:12px"><?= e($v['respondent_contact'] ?: '—') ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:52px;height:6px;background:var(--surface-2);border-radius:10px;overflow:hidden">
                <div style="width:<?= $score ?>%;height:100%;background:<?= $bar_col ?>;border-radius:10px"></div>
              </div>
              <span class="chip <?= $rc ?>"><?= $v['risk_label'] ?> · <?= $score ?></span>
            </div>
          </td>
          <td style="font-weight:700"><?= (int)$v['total_cases'] ?></td>
          <td><span class="chip ch-amber"><?= (int)$v['active_cases'] ?></span></td>
          <td style="color:var(--violet-600);font-weight:700"><?= (int)$v['cnt_critical'] ?></td>
          <td style="color:var(--rose-600)"><?= (int)$v['cnt_serious'] ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($v['last_case_date'])) ?></td>
          <td><button class="act-btn" onclick="showCases('<?= e(addslashes($v['respondent_name'])) ?>')">Cases</button></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Violator Cases Modal -->
<div class="modal-overlay" id="modal-vcases">
  <div class="modal modal-lg">
    <div class="modal-hdr">
      <span class="modal-title" id="vcases-title">Case History</span>
      <button class="modal-x" onclick="closeModal('modal-vcases')">×</button>
    </div>
    <div class="modal-body" id="vcases-body">
      <div style="text-align:center;padding:30px;color:var(--ink-300)">Loading…</div>
    </div>
  </div>
</div>

<script>
function showCases(name) {
  document.getElementById('vcases-title').textContent = name + ' — All Cases';
  document.getElementById('vcases-body').innerHTML = '<div style="text-align:center;padding:30px;color:var(--ink-300)">Loading…</div>';
  openModal('modal-vcases');
  fetch('ajax/get_violator_cases.php?name=' + encodeURIComponent(name))
    .then(r => r.json())
    .then(d => {
      if (!d.success || !d.cases.length) {
        document.getElementById('vcases-body').innerHTML = '<div class="empty-state"><div class="es-title">No cases found</div></div>';
        return;
      }
      const rows = d.cases.map(c => `
        <tr>
          <td class="td-mono">${c.case_number}</td>
          <td>${c.incident_type}</td>
          <td>${levelChip(c.violation_level)}</td>
          <td>${statusChip(c.status)}</td>
          <td style="font-size:12px;color:var(--ink-400)">${c.created_at.substring(0,10)}</td>
          <td><button class="act-btn" onclick="closeModal('modal-vcases');viewBlotter(${c.id})">View</button></td>
        </tr>`).join('');
      document.getElementById('vcases-body').innerHTML = `
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Case No.</th><th>Type</th><th>Level</th><th>Status</th><th>Filed</th><th></th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;
    });
}
</script>
