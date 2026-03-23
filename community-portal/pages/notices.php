<?php // community-portal/pages/notices.php
$uid = (int)$user['id'];

$notices = [];
try {
    $notices = $pdo->query("
        SELECT n.*, b.case_number
        FROM notices n
        LEFT JOIN blotters b ON b.id = n.blotter_id
        WHERE n.recipient_user_id = $uid
        ORDER BY n.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

// Mark all as acknowledged
try { $pdo->prepare("UPDATE notices SET acknowledged_at=NOW() WHERE recipient_user_id=? AND acknowledged_at IS NULL")->execute([$uid]); } catch(PDOException $e){}

$type_config = [
    'summons'   => ['ch-rose',   '⚖️',  'Summons'],
    'warning'   => ['ch-amber',  '⚡',  'Warning'],
    'penalty'   => ['ch-rose',   '💰',  'Penalty Notice'],
    'escalation'=> ['ch-violet', '🚨',  'Escalation'],
    'general'   => ['ch-slate',  '📄',  'General Notice'],
];
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>Notices & Sanctions</h2><p>Formal notices issued to you by your barangay</p></div>
</div>

<?php if (empty($notices)): ?>
<div class="empty-state"><div class="es-icon">🔔</div><div class="es-title">No notices</div><div class="es-sub">Formal notices from your barangay will appear here</div></div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:10px">
<?php foreach ($notices as $n):
  [$chip, $icon, $type_lbl] = $type_config[$n['notice_type']] ?? ['ch-slate','📄','Notice'];
  $is_new = $n['acknowledged_at'] === null;
?>
<div class="card" style="<?= $is_new ? 'border-left:3px solid var(--amber-400)' : '' ?>">
  <div class="card-body" style="padding:16px 18px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px">
      <div style="display:flex;align-items:flex-start;gap:12px">
        <div style="font-size:24px;line-height:1;margin-top:2px"><?= $icon ?></div>
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--ink-900)"><?= e($n['subject'] ?: $type_lbl) ?></div>
          <?php if ($n['case_number']): ?><div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400);margin-top:2px"><?= e($n['case_number']) ?></div><?php endif; ?>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <span class="chip <?= $chip ?>"><?= $type_lbl ?></span>
        <?php if ($is_new): ?><div style="margin-top:4px"><span class="chip ch-amber">New</span></div><?php endif; ?>
        <div style="font-size:11px;color:var(--ink-400);margin-top:4px"><?= date('M j, Y', strtotime($n['created_at'])) ?></div>
      </div>
    </div>
    <?php if ($n['body']): ?>
    <div style="font-size:13px;color:var(--ink-600);line-height:1.7;padding:12px;background:var(--surface);border-radius:var(--r-sm);margin-top:8px">
      <?= nl2br(e($n['body'])) ?>
    </div>
    <?php endif; ?>
    <?php if ($n['sent_at']): ?>
    <div style="font-size:11px;color:var(--ink-400);margin-top:8px">Sent: <?= date('M j, Y g:i A', strtotime($n['sent_at'])) ?> via <?= e($n['sent_via']) ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
