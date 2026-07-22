<?php
// partials/users-table.php — renders the User Management table + pager.
// Expects: $pdo already set by the caller (page or ajax endpoint).
$filter_role     = $_GET['role']     ?? '';
$filter_status   = $_GET['filter']   ?? '';
$filter_barangay = $_GET['barangay'] ?? '';
$filter_search   = $_GET['search']   ?? '';
$page_num        = max(1, (int)($_GET['p'] ?? 1));
$per_page        = 15;
$offset          = ($page_num - 1) * $per_page;

$where  = ["u.role != 'superadmin'"];
$params = [];

if ($filter_role) { $where[] = 'u.role = ?'; $params[] = $filter_role; }
if ($filter_status === 'pending') { $where[] = 'u.is_active = 0'; }
elseif ($filter_status === 'active') { $where[] = 'u.is_active = 1'; }
elseif ($filter_status === 'suspended') { $where[] = 'u.is_active = 2'; }
if ($filter_barangay) { $where[] = 'u.barangay_id = ?'; $params[] = $filter_barangay; }
if ($filter_search) {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)';
    $like = '%'.$filter_search.'%';
    $params = array_merge($params, [$like, $like, $like]);
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$users = []; $total_rows = 0;
try {
    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
    $cnt_stmt->execute($params);
    $total_rows = (int)$cnt_stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.contact_number, u.role,
               u.is_active, u.last_login, u.created_at,
               b.name as barangay_name,
               (SELECT COUNT(*) FROM blotters bl WHERE bl.complainant_user_id = u.id) as blotters_filed
        FROM users u
        LEFT JOIN barangays b ON b.id = u.barangay_id
        $where_sql
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $users = $stmt->fetchAll();
} catch (PDOException $e) {}

$total_pages = max(1, ceil($total_rows / $per_page));

if (!function_exists('qstr')) {
    function qstr(array $overrides = []): string {
        $base = $_GET;
        unset($base['p']);
        return '?' . http_build_query(array_merge($base, $overrides));
    }
}
?>
<div class="card">
  <div class="table-wrap">
    <table id="users-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Barangay</th>
          <th>Status</th>
          <th>Blotters</th>
          <th>Last Login</th>
          <th>Registered</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($users)): ?>
        <tr><td colspan="10" style="text-align:center;color:var(--ink-300);padding:36px">No users found matching your filters.</td></tr>
      <?php else: foreach ($users as $u):
        $status_label = match((int)$u['is_active']) { 0=>'Pending', 1=>'Active', 2=>'Suspended', default=>'Unknown' };
        $status_chip  = match((int)$u['is_active']) { 0=>'status-pending', 1=>'status-active', 2=>'status-suspended', default=>'chip-slate' };
        $role_chip    = 'role-'.$u['role'];
      ?>
        <tr id="user-row-<?= $u['id'] ?>">
          <td><input type="checkbox" class="row-check" value="<?= $u['id'] ?>"></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:30px;height:30px;border-radius:50%;background:var(--indigo-50);color:var(--indigo-600);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($u['full_name'],0,2)) ?>
              </div>
              <div>
                <div class="td-main"><?= htmlspecialchars($u['full_name']) ?></div>
                <div style="font-size:11px;color:var(--ink-400)"><?= htmlspecialchars($u['contact_number'] ?? '—') ?></div>
              </div>
            </div>
          </td>
          <td style="color:var(--ink-500)"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="chip <?= $role_chip ?>"><?= ucfirst($u['role']) ?></span></td>
          <td><?= htmlspecialchars($u['barangay_name'] ?? '—') ?></td>
          <td><span class="chip <?= $status_chip ?>"><?= $status_label ?></span></td>
          <td style="text-align:center;font-weight:600"><?= (int)$u['blotters_filed'] ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= $u['last_login'] ? date('M j, Y', strtotime($u['last_login'])) : 'Never' ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:nowrap">
              <button class="act-btn btn-xs" onclick="openUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= htmlspecialchars(addslashes($u['email'])) ?>', '<?= $u['role'] ?>', '<?= $u['barangay_name'] ?>', <?= (int)$u['is_active'] ?>)" title="Edit">Edit</button>
              <?php if ($u['is_active'] == 0): ?>
                <button class="btn btn-success btn-xs" onclick="userAction(<?= $u['id'] ?>, 'approve')">Approve</button>
              <?php elseif ($u['is_active'] == 1): ?>
                <button class="act-btn danger btn-xs" onclick="userAction(<?= $u['id'] ?>, 'suspend')">Suspend</button>
              <?php else: ?>
                <button class="act-btn btn-xs" onclick="userAction(<?= $u['id'] ?>, 'activate')">Activate</button>
              <?php endif; ?>
              <button class="act-btn danger btn-xs" onclick="userAction(<?= $u['id'] ?>, 'delete')">Delete</button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Bulk actions + pagination -->
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:12px;color:var(--ink-400)">
        Showing <?= min($offset+1,$total_rows) ?>–<?= min($offset+$per_page,$total_rows) ?> of <?= $total_rows ?> users
      </span>
      <select id="bulk-action" class="btn-sm" style="padding:4px 8px;font-size:12px;width:auto">
        <option value="">Bulk action…</option>
        <option value="approve">Approve selected</option>
        <option value="suspend">Suspend selected</option>
        <option value="delete">Delete selected</option>
      </select>
      <button class="btn btn-outline btn-sm" onclick="executeBulk()">Apply</button>
    </div>
    <div style="display:flex;gap:5px;align-items:center">
      <?php if ($page_num > 1): ?>
        <a href="<?= qstr(['p'=>$page_num-1]) ?>" data-lf class="btn btn-outline btn-sm">← Prev</a>
      <?php endif; ?>
      <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?>
        <a href="<?= qstr(['p'=>$i]) ?>" data-lf class="btn <?= $i===$page_num?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page_num < $total_pages): ?>
        <a href="<?= qstr(['p'=>$page_num+1]) ?>" data-lf class="btn btn-outline btn-sm">Next →</a>
      <?php endif; ?>
    </div>
  </div>
</div>
