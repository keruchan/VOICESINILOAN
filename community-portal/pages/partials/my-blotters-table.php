<?php
// partials/my-blotters-table.php — renders the My Blotters table + pager.
// Expects: $pdo, $uid already set by the caller (page or ajax endpoint).
$f_status = $_GET['status'] ?? '';
$f_search = $_GET['search'] ?? '';
$pg = max(1, (int)($_GET['pg'] ?? 1));
$per = 15; $off = ($pg - 1) * $per;

$where = ["complainant_user_id = $uid"]; $params = [];
if ($f_status) { $where[] = 'status = ?'; $params[] = $f_status; }
if ($f_search) {
    $where[] = '(case_number LIKE ? OR incident_type LIKE ? OR respondent_name LIKE ?)';
    $like = "%$f_search%"; $params = array_merge($params, [$like,$like,$like]);
}
$ws = 'WHERE ' . implode(' AND ', $where);

$blotters = []; $total = 0;
try {
    $c = $pdo->prepare("SELECT COUNT(*) FROM blotters $ws"); $c->execute($params); $total = (int)$c->fetchColumn();
    $s = $pdo->prepare("SELECT * FROM blotters $ws ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $s->execute(array_merge($params, [$per, $off]));
    $blotters = $s->fetchAll();
} catch (PDOException $e) {}
$total_pages = max(1, (int)ceil($total / $per));

if (!function_exists('mbq')) {
    function mbq(array $o = []): string {
        $b = array_filter(['page'=>'my-blotters','status'=>$_GET['status']??'','search'=>$_GET['search']??''], fn($v)=>$v!=='');
        return '?' . http_build_query(array_merge($b, $o));
    }
}
$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-green','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'];
?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Incident Type</th><th>Respondent</th><th>Level</th><th>Status</th><th>Prescribed Action</th><th>Filed</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($blotters)): ?>
        <tr><td colspan="8"><div class="empty-state"><div class="es-icon">📋</div><div class="es-title">No reports found</div><div class="es-sub"><a href="?page=file-report" style="color:var(--green-600)">File your first report →</a></div></div></td></tr>
      <?php else: foreach ($blotters as $b): ?>
        <tr>
          <td class="td-mono"><?= e($b['case_number']) ?></td>
          <td class="td-main"><?= e($b['incident_type']) ?></td>
          <td><?= e($b['respondent_name'] ?: '—') ?></td>
          <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level'] ?: '—') ?></span></td>
          <td><span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
          <td style="font-size:12px;color:var(--ink-500)"><?= e(ucwords(str_replace('_',' ',$b['prescribed_action']??''))) ?: '—' ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
          <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View Case</button></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= min($off+1,$total) ?>–<?= min($off+$per,$total) ?> of <?= $total ?></span>
      <div class="pager-btns">
        <?php if ($pg>1): ?><a href="<?= mbq(['pg'=>$pg-1]) ?>" data-lf class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?><a href="<?= mbq(['pg'=>$i]) ?>" data-lf class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a><?php endfor; ?>
        <?php if ($pg<$total_pages): ?><a href="<?= mbq(['pg'=>$pg+1]) ?>" data-lf class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
