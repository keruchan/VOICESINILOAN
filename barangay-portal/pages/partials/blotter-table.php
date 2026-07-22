<?php
// partials/blotter-table.php — renders the Blotter Management table + pager.
// Expects: $pdo, $bid already set by the caller (page or ajax endpoint).
$f_status = $_GET['status'] ?? '';
$f_level  = $_GET['level']  ?? '';
$f_type   = $_GET['type']   ?? '';
$f_search = $_GET['search'] ?? '';
$pg       = max(1, (int)($_GET['pg'] ?? 1));
$per_page = 15;
$offset   = ($pg - 1) * $per_page;

$where = ["barangay_id = $bid"]; $params = [];
if ($f_status) { $where[] = 'status = ?';          $params[] = $f_status; }
if ($f_level)  { $where[] = 'violation_level = ?'; $params[] = $f_level; }
if ($f_type)   { $where[] = 'incident_type = ?';   $params[] = $f_type; }
if ($f_search) {
    $where[] = '(case_number LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ?)';
    $like = "%{$f_search}%";
    $params = array_merge($params, [$like, $like, $like]);
}
$ws = 'WHERE ' . implode(' AND ', $where);

$blotters = []; $total = 0;
try {
    $c = $pdo->prepare("SELECT COUNT(*) FROM blotters $ws");
    $c->execute($params); $total = (int)$c->fetchColumn();
    $s = $pdo->prepare("SELECT * FROM blotters $ws ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $s->execute(array_merge($params, [$per_page, $offset]));
    $blotters = $s->fetchAll();
} catch (PDOException $e) {}

$total_pages = max(1, (int)ceil($total / $per_page));

if (!function_exists('bq')) {
    function bq(array $o = []): string {
        $base = array_filter(['page'=>'blotter-management','status'=>$_GET['status']??'','level'=>$_GET['level']??'','type'=>$_GET['type']??'','search'=>$_GET['search']??''], fn($v)=>$v!=='');
        return '?' . http_build_query(array_merge($base, $o));
    }
}

$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-emerald','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'];
?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Case No.</th><th>Complainant</th><th>Respondent</th><th>Type</th><th>Level</th><th>Status</th><th>Prescribed Action</th><th>Filed</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if (empty($blotters)): ?>
        <tr><td colspan="9"><div class="empty-state"><div class="es-icon">📋</div><div class="es-title">No blotters found</div><div class="es-sub">Adjust your filters or file a new blotter</div></div></td></tr>
      <?php else: foreach ($blotters as $b):
        $has_respondent = !empty(trim($b['respondent_name'] ?? ''));
        $is_terminal    = in_array($b['status'], ['resolved','closed','transferred']);
      ?>
        <tr>
          <td class="td-mono"><?= e($b['case_number']) ?></td>
          <td class="td-main"><?= e($b['complainant_name']) ?></td>
          <td><?= $has_respondent ? e($b['respondent_name']) : '<span style="color:var(--ink-300);font-style:italic;font-size:11px">No respondent</span>' ?></td>
          <td style="font-size:12px"><?= e($b['incident_type']) ?></td>
          <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
          <td><span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
          <td style="font-size:12px;color:var(--ink-500)"><?= e(ucwords(str_replace('_',' ',$b['prescribed_action']??''))) ?: '—' ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View Case</button>
              <?php if ($b['status']==='pending_review'): ?>
                <button class="act-btn green" onclick="quickApprove(<?= $b['id'] ?>)">Approve</button>
              <?php endif; ?>
              <?php if (!$is_terminal && $has_respondent): ?>
                <button class="act-btn" onclick="openScheduleMed(<?= $b['id'] ?>,'<?= e(addslashes($b['case_number'])) ?>')">Mediation</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> of <?= $total ?> records</span>
      <div class="pager-btns">
        <?php if ($pg>1): ?><a href="<?= bq(['pg'=>$pg-1]) ?>" data-lf class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?>
          <a href="<?= bq(['pg'=>$i]) ?>" data-lf class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pg<$total_pages): ?><a href="<?= bq(['pg'=>$pg+1]) ?>" data-lf class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
