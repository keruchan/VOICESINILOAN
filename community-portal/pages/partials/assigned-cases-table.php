<?php
// partials/assigned-cases-table.php — renders the "All Cases Against Me" table + pager.
// Expects: $pdo, $uid, $bid, $uname already set by the caller (page or ajax endpoint).
$name_cond = '1=0';
if ($uname) {
    $parts = array_filter(preg_split('/[\s,]+/', $uname), fn($p) => strlen($p) > 2);
    $likes = [];
    foreach ($parts as $part) $likes[] = "b.respondent_name LIKE '%" . addslashes($part) . "%'";
    if ($likes) $name_cond = '(' . implode(' AND ', $likes) . ')';
}

$f_search = trim($_GET['search'] ?? '');
$f_level  = $_GET['level']  ?? '';
$f_status = $_GET['status'] ?? '';
$pg  = max(1, (int)($_GET['pg'] ?? 1));
$per = 10; $off = ($pg - 1) * $per;

$where = [
    "b.barangay_id = $bid",
    "(b.respondent_user_id = $uid OR (b.respondent_user_id IS NULL AND $name_cond))",
];
$params = [];
if ($f_level)  { $where[] = 'b.violation_level = ?'; $params[] = $f_level; }
if ($f_status) { $where[] = 'b.status = ?';          $params[] = $f_status; }
if ($f_search !== '') {
    $where[] = '(b.case_number LIKE ? OR b.incident_type LIKE ? OR b.complainant_name LIKE ?)';
    $like = "%$f_search%"; $params = array_merge($params, [$like, $like, $like]);
}
$ws = 'WHERE ' . implode(' AND ', $where);

$all_cases = []; $total = 0;
try {
    $c = $pdo->prepare("SELECT COUNT(*) FROM blotters b $ws"); $c->execute($params); $total = (int)$c->fetchColumn();
    $s = $pdo->prepare("
        SELECT b.*,
               (SELECT COUNT(*) FROM mediation_schedules ms WHERE ms.blotter_id=b.id AND ms.status='scheduled' AND ms.hearing_date>=CURDATE()) AS upcoming_med,
               CASE WHEN b.respondent_user_id = $uid THEN 'linked' ELSE 'name_match' END AS match_source
        FROM blotters b
        $ws
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $s->execute(array_merge($params, [$per, $off]));
    $all_cases = $s->fetchAll();
} catch (PDOException $e) {}
$total_pages = max(1, (int)ceil($total / $per));

if (!function_exists('acq')) {
    function acq(array $o = []): string {
        $b = array_filter(['page'=>'assigned-cases','search'=>$_GET['search']??'','level'=>$_GET['level']??'','status'=>$_GET['status']??''], fn($v)=>$v!=='');
        return '?' . http_build_query(array_merge($b, $o));
    }
}

$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-green','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate','dismissed'=>'ch-slate','cfa_issued'=>'ch-violet'];
?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Type</th><th>Level</th><th>Status</th><th>Complainant</th><th>Filed</th><th>Source</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($all_cases)): ?>
        <tr><td colspan="8"><div class="empty-state"><div class="es-icon">🔍</div><div class="es-title">No cases match</div><div class="es-sub">Adjust your search or filters.</div></div></td></tr>
      <?php else: foreach ($all_cases as $b): ?>
        <tr>
          <td class="td-mono"><?= e($b['case_number']) ?></td>
          <td class="td-main"><?= e($b['incident_type']) ?></td>
          <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
          <td>
            <span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span>
            <?php if ($b['upcoming_med'] > 0): ?><div style="font-size:10px;color:var(--amber-600);margin-top:3px"><?= (int)$b['upcoming_med'] ?> hearing(s) upcoming</div><?php endif; ?>
            <?php if ($b['status'] === 'cfa_issued'): ?><div style="font-size:10px;color:var(--violet-600);margin-top:3px">⚠️ CFA issued</div><?php endif; ?>
          </td>
          <td><?= e($b['complainant_name']) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
          <td>
            <?php if ($b['match_source'] === 'linked'): ?>
              <span class="chip ch-teal" style="font-size:10px" title="Directly linked to your account">Linked</span>
            <?php else: ?>
              <span class="chip ch-slate" style="font-size:10px" title="Matched by name — not yet linked to your account">By Name</span>
            <?php endif; ?>
          </td>
          <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= $total?min($off+1,$total):0 ?>-<?= min($off+$per,$total) ?> of <?= $total ?></span>
      <div class="pager-btns">
        <?php if ($pg>1): ?><a href="<?= acq(['pg'=>$pg-1]) ?>" data-lf class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?><a href="<?= acq(['pg'=>$i]) ?>" data-lf class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a><?php endfor; ?>
        <?php if ($pg<$total_pages): ?><a href="<?= acq(['pg'=>$pg+1]) ?>" data-lf class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
