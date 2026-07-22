<?php
// partials/records-table.php — renders the Records Archive table + pager.
// Expects: $pdo, $bid already set by the caller (page or ajax endpoint).
$f_year = (int)($_GET['year'] ?? 0);
$f_res  = $_GET['resolution'] ?? '';
$f_type = $_GET['type']       ?? '';
$f_search = trim($_GET['search'] ?? '');
$pg  = max(1, (int)($_GET['pg'] ?? 1));
$per = 20; $off = ($pg - 1) * $per;

$where = ["b.barangay_id = $bid", "b.status IN ('resolved','closed','transferred')"]; $params = [];
if ($f_year) { $where[] = 'YEAR(b.updated_at) = ?'; $params[] = $f_year; }
if ($f_res)  { $where[] = 'b.status = ?';            $params[] = $f_res; }
if ($f_type) { $where[] = 'b.incident_type = ?';     $params[] = $f_type; }
if ($f_search !== '') {
    $where[] = '(b.case_number LIKE ? OR b.complainant_name LIKE ? OR b.respondent_name LIKE ? OR b.incident_type LIKE ? OR ms.outcome LIKE ?)';
    $like = "%{$f_search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
$ws = 'WHERE ' . implode(' AND ', $where);

$records = []; $total = 0;
try {
    $join_sql = "LEFT JOIN mediation_schedules ms ON ms.blotter_id = b.id AND ms.status = 'completed'";
    $c = $pdo->prepare("SELECT COUNT(DISTINCT b.id) FROM blotters b $join_sql $ws"); $c->execute($params); $total = (int)$c->fetchColumn();
    $s = $pdo->prepare("
        SELECT b.*, ms.outcome AS med_outcome
        FROM blotters b
        $join_sql
        $ws ORDER BY b.updated_at DESC LIMIT ? OFFSET ?
    ");
    $s->execute(array_merge($params, [$per, $off]));
    $records = $s->fetchAll();
} catch (PDOException $e) {}
$total_pages = max(1, (int)ceil($total / $per));

if (!function_exists('rq')) {
    function rq(array $o = []): string {
        $base = array_filter(['page'=>'records-archive','year'=>(string)($_GET['year']??''),'resolution'=>$_GET['resolution']??'','type'=>$_GET['type']??'','search'=>$_GET['search']??''], fn($v) => $v !== '');
        return '?' . http_build_query(array_merge($base, $o));
    }
}
$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['resolved'=>'ch-emerald','closed'=>'ch-slate','transferred'=>'ch-navy'];
?>
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
          <td><button class="act-btn" onclick="viewBlotter(<?= $r['id'] ?>)">View Case</button></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= min($off+1,$total) ?>–<?= min($off+$per,$total) ?> of <?= $total ?></span>
      <div class="pager-btns">
        <?php if ($pg>1): ?><a href="<?= rq(['pg'=>$pg-1]) ?>" data-lf class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?><a href="<?= rq(['pg'=>$i]) ?>" data-lf class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a><?php endfor; ?>
        <?php if ($pg<$total_pages): ?><a href="<?= rq(['pg'=>$pg+1]) ?>" data-lf class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
