<?php
// pages/assigned-cases.php
// Uses respondent_user_id (direct link) AND respondent_name match (walk-in records)
$uid   = (int)$user['id'];
$bid   = (int)$user['barangay_id'];
$uname = $user['name'] ?? '';

$name_cond = '1=0';
if ($uname) {
    $parts = array_filter(preg_split('/[\s,]+/', $uname), fn($p) => strlen($p) > 2);
    $likes = [];
    foreach ($parts as $part) $likes[] = "b.respondent_name LIKE '%" . addslashes($part) . "%'";
    if ($likes) $name_cond = '(' . implode(' AND ', $likes) . ')';
}

// Just for pre-populating the filter form's initial values — the actual
// filtered query lives in partials/assigned-cases-table.php
$f_search = trim($_GET['search'] ?? '');
$f_level  = $_GET['level']  ?? '';
$f_status = $_GET['status'] ?? '';

// All matching case IDs (unfiltered) — needed for penalties/hearings/KPI summaries;
// the filtered, paginated table itself is rendered by partials/assigned-cases-table.php
$all_against_ids = [];
try {
    $all_against_ids = $pdo->query("SELECT b.id FROM blotters b WHERE b.barangay_id=$bid AND (b.respondent_user_id = $uid OR (b.respondent_user_id IS NULL AND $name_cond))")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

if (!function_exists('acq')) {
    function acq(array $o = []): string {
        $b = array_filter(['page'=>'assigned-cases','search'=>$_GET['search']??'','level'=>$_GET['level']??'','status'=>$_GET['status']??''], fn($v)=>$v!=='');
        return '?' . http_build_query(array_merge($b, $o));
    }
}

// Penalties against me (missed_party = respondent or both)
$my_penalties = [];
$penalty_total_pending = 0.0;
if (!empty($all_against_ids)) {
    try {
        $in = implode(',', array_map('intval', $all_against_ids));
        $my_penalties = $pdo->query("
            SELECT p.*, b.case_number
            FROM penalties p
            JOIN blotters b ON b.id = p.blotter_id
            WHERE p.blotter_id IN ($in)
              AND p.missed_party IN ('respondent','both')
            ORDER BY p.created_at DESC
        ")->fetchAll();
        foreach ($my_penalties as $p) if ($p['status'] === 'pending') $penalty_total_pending += (float)$p['amount'];
    } catch (PDOException $e) {}
}

// Upcoming mediations where I am respondent
$my_hearings = [];
if (!empty($all_against_ids)) {
    try {
        $in = implode(',', array_map('intval', $all_against_ids));
        $my_hearings = $pdo->query("
            SELECT ms.*, b.case_number, b.complainant_name
            FROM mediation_schedules ms
            JOIN blotters b ON b.id = ms.blotter_id
            WHERE ms.blotter_id IN ($in)
              AND ms.status = 'scheduled'
              AND CONCAT(ms.hearing_date, ' ', COALESCE(ms.hearing_time, '23:59:59')) > NOW()
            ORDER BY ms.hearing_date ASC, ms.hearing_time ASC
        ")->fetchAll();
    } catch (PDOException $e) {}
}

// Active-case count for KPI (excludes resolved/closed/dismissed/transferred)
$active_count = 0;
if (!empty($all_against_ids)) {
    try {
        $in = implode(',', array_map('intval', $all_against_ids));
        $active_count = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE id IN ($in) AND status NOT IN ('resolved','closed','dismissed','transferred')")->fetchColumn();
    } catch (PDOException $e) {}
}

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-green','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate','dismissed'=>'ch-slate','cfa_issued'=>'ch-violet'];

$grand_total = count($all_against_ids);
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Cases Against Me</h2>
    <p>Blotters where you are named as the respondent / violator</p>
  </div>
</div>

<?php if ($grand_total === 0): ?>
  <div class="empty-state"><div class="es-icon">✅</div><div class="es-title">No cases on record</div><div class="es-sub">You have no active violations or cases filed against you.</div></div>
<?php else: ?>

<div class="kpi-grid">
  <div class="kpi-card kc-rose">
    <div class="kpi-top"><div class="kpi-icon ki-rose"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="7" r="3.5"/><path d="M3 17c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg></div></div>
    <div class="kpi-val"><?= $grand_total ?></div><div class="kpi-lbl">Total Cases Against Me</div>
  </div>
  <div class="kpi-card kc-amber">
    <div class="kpi-top"><div class="kpi-icon ki-amber"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="9" r="7"/><path d="M9 5.5v3.5l2.5 2"/></svg></div></div>
    <div class="kpi-val"><?= $active_count ?></div><div class="kpi-lbl">Still Active / Open</div>
  </div>
  <div class="kpi-card kc-teal">
    <div class="kpi-top"><div class="kpi-icon ki-teal"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="2" y="3" width="14" height="13" rx="2"/><path d="M2 7.5h14M6 3V1.5M12 3V1.5"/></svg></div></div>
    <div class="kpi-val"><?= count($my_hearings) ?></div><div class="kpi-lbl">Upcoming Hearings</div>
  </div>
  <div class="kpi-card kc-rose">
    <div class="kpi-top"><div class="kpi-icon ki-rose"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M9 2v14M5 5.5h6.5a2 2 0 1 1 0 4H6.5a2 2 0 1 0 0 4H13"/></svg></div></div>
    <div class="kpi-val">₱<?= number_format($penalty_total_pending) ?></div><div class="kpi-lbl">Pending Penalties</div>
  </div>
</div>

<!-- ── Upcoming hearings I must attend ── -->
<?php if (!empty($my_hearings)): ?>
<div class="alert alert-amber mb16" style="align-items:center">
  <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="var(--amber-600)" stroke-width="1.5" stroke-linecap="round" style="flex-shrink:0"><rect x="2" y="3" width="14" height="13" rx="2"/><path d="M2 7.5h14M6 3V1.5M12 3V1.5"/></svg>
  <div class="alert-text" style="flex:1">
    <strong>You have <?= count($my_hearings) ?> upcoming mediation hearing(s) you must attend</strong>
    <span>Missing a hearing may result in legal consequences.</span>
  </div>
  <a href="?page=mediation" class="btn btn-outline btn-sm" style="flex-shrink:0;border-color:var(--amber-400);color:var(--amber-600);white-space:nowrap">View Schedule →</a>
</div>
<div class="card mb22">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Complainant</th><th>Date</th><th>Time</th><th>Venue</th></tr></thead>
      <tbody>
      <?php foreach ($my_hearings as $h): ?>
        <tr>
          <td class="td-mono"><?= e($h['case_number']) ?></td>
          <td><?= e($h['complainant_name']) ?></td>
          <td style="font-weight:700;color:var(--amber-600)"><?= date('D, M j, Y', strtotime($h['hearing_date'])) ?></td>
          <td><?= $h['hearing_time'] ? date('g:i A', strtotime($h['hearing_time'])) : 'TBD' ?></td>
          <td><?= e($h['venue'] ?: 'Barangay Hall') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Penalties ── -->
<?php if (!empty($my_penalties)): ?>
<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-400);margin-bottom:10px">Penalties Against Me</div>
<div class="card mb22">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Reason</th><th>Amount</th><th>Community Service</th><th>Due Date</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($my_penalties as $p):
        $is_overdue = ($p['status']==='pending' && !empty($p['due_date']) && $p['due_date'] < date('Y-m-d'));
        $pc = $is_overdue ? 'ch-rose' : (['pending'=>'ch-amber','paid'=>'ch-green','overdue'=>'ch-rose','waived'=>'ch-slate'][$p['status']]??'ch-slate');
        $plabel = $is_overdue ? 'Overdue' : ucfirst($p['status']);
      ?>
        <tr>
          <td class="td-mono"><?= e($p['case_number']) ?></td>
          <td class="td-main"><?= e($p['reason']) ?></td>
          <td style="font-weight:700;color:var(--rose-600)">₱<?= number_format((float)$p['amount']) ?></td>
          <td style="font-size:12px"><?= $p['community_hours']?$p['community_hours'].' hrs':'—' ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= $p['due_date']?date('M j, Y',strtotime($p['due_date'])):'—' ?></td>
          <td><span class="chip <?= $pc ?>"><?= $plabel ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Unified case list ── -->
<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-400);margin-bottom:10px">All Cases Against Me</div>

<form id="ac-form" class="filter-bar" onsubmit="return false">
  <input type="hidden" name="page" value="assigned-cases">
  <div class="inp-icon" style="flex:1;max-width:280px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Case no., type, complainant…" value="<?= e($f_search) ?>">
  </div>
  <select name="level">
    <option value="">All Levels</option>
    <?php foreach (['minor','moderate','serious','critical'] as $l): ?>
      <option value="<?= $l ?>" <?= $f_level===$l?'selected':'' ?>><?= ucfirst($l) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">All Statuses</option>
    <?php foreach (['pending_review'=>'Pending','active'=>'Active','mediation_set'=>'Mediation Set','resolved'=>'Resolved','closed'=>'Closed','escalated'=>'Escalated','dismissed'=>'Dismissed','cfa_issued'=>'CFA Issued','transferred'=>'Transferred'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= $f_status===$v?'selected':'' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <button type="button" id="ac-clear" class="btn btn-ghost btn-sm">✕ Clear</button>
</form>

<div id="ac-results">
  <?php require __DIR__ . '/partials/assigned-cases-table.php'; ?>
</div>

<?php endif; // end grand_total === 0 ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  liveFilter({
    form: '#ac-form',
    result: '#ac-results',
    endpoint: 'ajax/assigned_cases_search.php',
    clearBtn: '#ac-clear',
  });
});
</script>
