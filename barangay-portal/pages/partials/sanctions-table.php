<?php
// partials/sanctions-table.php — renders the Sanctions Book table + card.
// Expects: $pdo, $bid already set by the caller (page or ajax endpoint).
$f_level  = $_GET['level']  ?? '';
$f_cat    = $_GET['category'] ?? '';
$f_search = $_GET['search'] ?? '';

$where = ["barangay_id = $bid"]; $params = [];
if ($f_level)  { $where[] = 'violation_level = ?'; $params[] = $f_level; }
if ($f_cat)    { $where[] = 'legal_category = ?';  $params[] = $f_cat; }
if ($f_search) { $where[] = '(violation_type LIKE ? OR sanction_name LIKE ? OR legal_basis LIKE ?)'; $like="%$f_search%"; $params=array_merge($params,[$like,$like,$like]); }
$ws = 'WHERE ' . implode(' AND ', $where);

$sanctions = [];
try {
    $s = $pdo->prepare("SELECT * FROM sanctions_book $ws ORDER BY violation_level DESC, violation_type ASC");
    $s->execute($params);
    $sanctions = $s->fetchAll();
} catch (PDOException $e) {}

$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$cat_labels = ['kp_law'=>'KP Law','local_ordinance'=>'Local Ordinance','barangay_program'=>'Barangay Program'];
$cat_chips  = ['kp_law'=>'ch-navy','local_ordinance'=>'ch-teal','barangay_program'=>'ch-slate'];
?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Violation Type</th><th>Level</th><th>Legal Basis</th><th>Sanction</th><th>Fine (₱)</th><th>Comm. Svc (hrs)</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($sanctions)): ?>
        <tr><td colspan="7"><div class="empty-state"><div class="es-icon">📜</div><div class="es-title">No sanctions found</div><div class="es-sub">Adjust your search or filters, or add an entry using the button above</div></div></td></tr>
      <?php else: foreach ($sanctions as $s): ?>
        <tr class="sanction-row" style="cursor:pointer" onclick="viewSanction(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
          <td class="td-main"><?= e($s['violation_type']) ?></td>
          <td><span class="chip <?= $lm[$s['violation_level']] ?? 'ch-slate' ?>"><?= ucfirst($s['violation_level']) ?></span></td>
          <td>
            <span class="chip <?= $cat_chips[$s['legal_category']]??'ch-slate' ?>" style="font-size:10px"><?= $cat_labels[$s['legal_category']]??ucfirst($s['legal_category']) ?></span>
            <?php if ($s['legal_basis']): ?><div style="font-size:11px;color:var(--ink-400);margin-top:3px;font-family:var(--font-mono)"><?= e($s['legal_basis']) ?></div><?php endif; ?>
          </td>
          <td><?= e($s['sanction_name']) ?></td>
          <td style="font-weight:600;color:var(--rose-600)">₱<?= number_format((float)($s['fine_amount'] ?? 0)) ?></td>
          <td style="font-size:12px"><?= $s['community_hours'] ? $s['community_hours'] . ' hrs' : '—' ?></td>
          <td onclick="event.stopPropagation()">
            <div style="display:flex;gap:4px">
              <button class="act-btn" onclick='viewSanction(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>View</button>
              <button class="act-btn" onclick='loadEdit(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>Edit</button>
              <button class="act-btn red" onclick="delSanction(<?= $s['id'] ?>)">Del</button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
