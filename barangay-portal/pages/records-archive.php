<?php
// pages/records-archive.php
$bid = (int)$user['barangay_id'];
$f_year = (int)($_GET['year'] ?? 0);
$f_res  = $_GET['resolution'] ?? '';
$f_type = $_GET['type']       ?? '';
$pg  = max(1, (int)($_GET['pg'] ?? 1));
$per = 20; $off = ($pg - 1) * $per;

$where = ["b.barangay_id = $bid", "b.status IN ('resolved','closed','transferred')"]; $params = [];
if ($f_year) { $where[] = 'YEAR(b.updated_at) = ?'; $params[] = $f_year; }
if ($f_res)  { $where[] = 'b.status = ?';            $params[] = $f_res; }
if ($f_type) { $where[] = 'b.incident_type = ?';     $params[] = $f_type; }
$ws = 'WHERE ' . implode(' AND ', $where);

$records = []; $total = 0;
try {
    $c = $pdo->prepare("SELECT COUNT(*) FROM blotters b $ws"); $c->execute($params); $total = (int)$c->fetchColumn();
    $s = $pdo->prepare("
        SELECT b.*, ms.outcome AS med_outcome
        FROM blotters b
        LEFT JOIN mediation_schedules ms ON ms.blotter_id = b.id AND ms.status = 'completed'
        $ws ORDER BY b.updated_at DESC LIMIT ? OFFSET ?
    ");
    $s->execute(array_merge($params, [$per, $off]));
    $records = $s->fetchAll();
} catch (PDOException $e) {}
$total_pages = max(1, (int)ceil($total / $per));

function rq(array $o = []): string {
    $base = array_filter(['page'=>'records-archive','year'=>(string)($_GET['year']??''),'resolution'=>$_GET['resolution']??'','type'=>$_GET['type']??''], fn($v) => $v !== '');
    return '?' . http_build_query(array_merge($base, $o));
}
$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['resolved'=>'ch-emerald','closed'=>'ch-slate','transferred'=>'ch-navy'];
?>
<div class="page-hdr">
  <div class="page-hdr-left"><h2>Records Archive</h2><p>Resolved, closed, and transferred cases</p></div>
  <a href="ajax/export_blotters.php?barangay_id=<?= $bid ?>&status=archived" class="btn btn-outline btn-sm">⬇ Export CSV</a>
</div>

<form method="GET" class="filter-bar">
  <input type="hidden" name="page" value="records-archive">
  <select name="year" onchange="this.form.submit()" style="width:auto;min-width:100px">
    <option value="0">All Years</option>
    <?php for ($y = date('Y'); $y >= 2020; $y--): ?><option value="<?= $y ?>" <?= $f_year===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
  </select>
  <select name="resolution" onchange="this.form.submit()">
    <option value="">All Resolutions</option>
    <option value="resolved"    <?= $f_res==='resolved'   ?'selected':'' ?>>Resolved</option>
    <option value="closed"      <?= $f_res==='closed'     ?'selected':'' ?>>Closed</option>
    <option value="transferred" <?= $f_res==='transferred'?'selected':'' ?>>Transferred</option>
  </select>
  <select name="type" onchange="this.form.submit()">
    <option value="">All Types</option>
    <?php foreach (['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Other'] as $t): ?>
      <option value="<?= $t ?>" <?= $f_type===$t?'selected':'' ?>><?= $t ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline btn-sm">Filter</button>
  <a href="?page=records-archive" class="btn btn-ghost btn-sm">Clear</a>
</form>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Complainant</th><th>Respondent</th><th>Type</th><th>Level</th><th>Resolution</th><th>Outcome</th><th>Closed Date</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($records)): ?>
        <tr><td colspan="9"><div class="empty-state"><div class="es-icon">🗄️</div><div class="es-title">No archived records</div></div></td></tr>
      <?php else: foreach ($records as $r): ?>
        <tr>
          <td class="td-mono"><?= e($r['case_number']) ?></td>
          <td class="td-main"><?= e($r['complainant_name']) ?></td>
          <td><?= e($r['respondent_name'] ?: '—') ?></td>
          <td style="font-size:12px"><?= e($r['incident_type']) ?></td>
          <td><span class="chip <?= $lm[$r['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($r['violation_level']) ?></span></td>
          <td><span class="chip <?= $sm[$r['status']] ?? 'ch-slate' ?>"><?= ucfirst($r['status']) ?></span></td>
          <td style="font-size:12px;color:var(--ink-400);max-width:160px;white-space:normal"><?= e(mb_strimwidth($r['med_outcome'] ?? '—', 0, 60, '…')) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($r['updated_at'])) ?></td>
          <td><button class="act-btn" onclick="viewBlotter(<?= $r['id'] ?>)">View</button></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= min($off+1,$total) ?>–<?= min($off+$per,$total) ?> of <?= $total ?></span>
      <div class="pager-btns">
        <?php if ($pg>1): ?><a href="<?= rq(['pg'=>$pg-1]) ?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?><a href="<?= rq(['pg'=>$i]) ?>" class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a><?php endfor; ?>
        <?php if ($pg<$total_pages): ?><a href="<?= rq(['pg'=>$pg+1]) ?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
