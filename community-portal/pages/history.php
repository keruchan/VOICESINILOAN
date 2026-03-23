<?php // community-portal/pages/history.php
$uid = (int)$user['id'];
$pg = max(1,(int)($_GET['pg']??1)); $per=15; $off=($pg-1)*$per;

$records=[]; $total=0;
try {
    $total=(int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=$uid AND status IN ('resolved','closed','transferred')")->fetchColumn();
    $records=$pdo->query("
        SELECT b.*, ms.outcome AS med_outcome, ms.hearing_date AS med_date
        FROM blotters b
        LEFT JOIN mediation_schedules ms ON ms.blotter_id=b.id AND ms.status='completed'
        WHERE b.complainant_user_id=$uid AND b.status IN ('resolved','closed','transferred')
        ORDER BY b.updated_at DESC
        LIMIT $per OFFSET $off
    ")->fetchAll();
} catch(PDOException $e){}
$total_pages=max(1,(int)ceil($total/$per));

function hq(array $o=[]): string { return '?'.http_build_query(array_merge(['page'=>'history'],$o)); }
$lm=['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm=['resolved'=>'ch-green','closed'=>'ch-slate','transferred'=>'ch-navy'];
?>
<div class="page-hdr">
  <div class="page-hdr-left"><h2>Case History</h2><p>Resolved and closed cases you filed</p></div>
</div>

<?php if(empty($records)): ?>
<div class="empty-state"><div class="es-icon">🗂️</div><div class="es-title">No closed cases yet</div><div class="es-sub">Cases that are resolved or closed will appear here</div></div>
<?php else: ?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Case No.</th><th>Incident</th><th>Respondent</th><th>Level</th><th>Resolution</th><th>Outcome</th><th>Closed</th><th></th></tr></thead>
      <tbody>
      <?php foreach($records as $b): ?>
        <tr>
          <td class="td-mono"><?= e($b['case_number']) ?></td>
          <td class="td-main"><?= e($b['incident_type']) ?></td>
          <td><?= e($b['respondent_name']?:'—') ?></td>
          <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
          <td><span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucfirst($b['status']) ?></span></td>
          <td style="font-size:12px;color:var(--ink-400);max-width:180px;white-space:normal"><?= e(mb_strimwidth($b['med_outcome']??'—',0,60,'…')) ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y',strtotime($b['updated_at'])) ?></td>
          <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= min($off+1,$total) ?>–<?= min($off+$per,$total) ?> of <?= $total ?></span>
      <div class="pager-btns">
        <?php if($pg>1): ?><a href="<?= hq(['pg'=>$pg-1]) ?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?><a href="<?= hq(['pg'=>$i]) ?>" class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a><?php endfor; ?>
        <?php if($pg<$total_pages): ?><a href="<?= hq(['pg'=>$pg+1]) ?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
