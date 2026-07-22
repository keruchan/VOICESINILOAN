<?php
// pages/records-archive.php
$bid = (int)$user['barangay_id'];
$f_year = (int)($_GET['year'] ?? 0);
$f_res  = $_GET['resolution'] ?? '';
$f_type = $_GET['type']       ?? '';
$f_search = $_GET['search']   ?? '';
?>
<div class="page-hdr">
  <div class="page-hdr-left"><h2>Records Archive</h2><p>Resolved, closed, and transferred cases</p></div>
  <button type="button" class="btn btn-outline btn-sm" onclick="exportArchiveCSV()">⬇ Export CSV</button>
</div>

<form id="ra-form" class="filter-bar" onsubmit="return false">
  <input type="hidden" name="page" value="records-archive">
  <div class="inp-icon" style="flex:1;max-width:280px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Case no., name, type..." value="<?= e($f_search) ?>">
  </div>
  <select name="year" style="width:auto;min-width:100px">
    <option value="0">All Years</option>
    <?php for ($y = date('Y'); $y >= 2020; $y--): ?><option value="<?= $y ?>" <?= $f_year===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
  </select>
  <select name="resolution">
    <option value="">All Resolutions</option>
    <option value="resolved"    <?= $f_res==='resolved'   ?'selected':'' ?>>Resolved</option>
    <option value="closed"      <?= $f_res==='closed'     ?'selected':'' ?>>Closed</option>
    <option value="transferred" <?= $f_res==='transferred'?'selected':'' ?>>Transferred</option>
  </select>
  <select name="type">
    <option value="">All Types</option>
    <?php foreach (['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Other'] as $t): ?>
      <option value="<?= $t ?>" <?= $f_type===$t?'selected':'' ?>><?= $t ?></option>
    <?php endforeach; ?>
  </select>
  <button type="button" id="ra-clear" class="btn btn-ghost btn-sm">✕ Clear</button>
</form>

<div id="ra-results">
  <?php require __DIR__ . '/partials/records-table.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  liveFilter({
    form: '#ra-form',
    result: '#ra-results',
    endpoint: 'ajax/records_search.php',
    clearBtn: '#ra-clear',
  });
});
function exportArchiveCSV() {
  const form = document.getElementById('ra-form');
  const params = new URLSearchParams(new FormData(form));
  params.set('barangay_id', BARANGAY_ID);
  params.set('status', 'archived');
  showExportPreview('ajax/export_blotters.php?' + params.toString(), 'Archived Blotter Export');
}
</script>
