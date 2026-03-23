<?php
// pages/users.php — Full user management

// ── Filters ──────────────────────────────────────────────────
$filter_role     = $_GET['role']     ?? '';
$filter_status   = $_GET['filter']   ?? '';
$filter_barangay = $_GET['barangay'] ?? '';
$filter_search   = $_GET['search']   ?? '';
$page_num        = max(1, (int)($_GET['p'] ?? 1));
$per_page        = 15;
$offset          = ($page_num - 1) * $per_page;

// ── Build WHERE ───────────────────────────────────────────────
$where  = ["u.role != 'superadmin'"];
$params = [];

if ($filter_role) {
    $where[] = 'u.role = ?';
    $params[] = $filter_role;
}
if ($filter_status === 'pending') {
    $where[] = 'u.is_active = 0';
} elseif ($filter_status === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($filter_status === 'suspended') {
    $where[] = 'u.is_active = 2';
}
if ($filter_barangay) {
    $where[] = 'u.barangay_id = ?';
    $params[] = $filter_barangay;
}
if ($filter_search) {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)';
    $like = '%'.$filter_search.'%';
    $params = array_merge($params, [$like, $like, $like]);
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// ── Fetch users ───────────────────────────────────────────────
$users      = [];
$total_rows = 0;
$barangays  = [];
try {
    // Count
    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
    $cnt_stmt->execute($params);
    $total_rows = (int)$cnt_stmt->fetchColumn();

    // Rows
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

    // For filter dropdown
    $barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active=1 ORDER BY name")->fetchAll();

} catch (PDOException $e) { /* tables may not exist */ }

$total_pages = max(1, ceil($total_rows / $per_page));

// ── Summary counts ────────────────────────────────────────────
$counts = ['total'=>0, 'barangay'=>0, 'community'=>0, 'pending'=>0, 'suspended'=>0];
try {
    $r = $pdo->query("SELECT role, is_active, COUNT(*) as c FROM users WHERE role != 'superadmin' GROUP BY role, is_active")->fetchAll();
    foreach ($r as $row) {
        $counts['total'] += $row['c'];
        if ($row['role'] === 'barangay')  $counts['barangay']  += $row['c'];
        if ($row['role'] === 'community') $counts['community'] += $row['c'];
        if ($row['is_active'] == 0)       $counts['pending']   += $row['c'];
        if ($row['is_active'] == 2)       $counts['suspended'] += $row['c'];
    }
} catch (PDOException $e) {}

// Build current query string helper
function qstr(array $overrides = []): string {
    $base = $_GET;
    unset($base['p']);
    return '?' . http_build_query(array_merge($base, $overrides));
}
?>

<div class="page-header">
  <div class="page-header-left">
    <h2>User Management</h2>
    <p>Manage all barangay officers and community users across the municipality</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-outline btn-sm" onclick="exportUsers()">
      <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M11 8v3H2V2h3M8 2h3v3M5 8l6-6"/></svg>
      Export CSV
    </button>
    <button class="btn btn-primary" onclick="openModal('modal-add-user')">
      <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6.5 2v9M2 6.5h9"/></svg>
      Add User
    </button>
  </div>
</div>

<!-- Summary KPIs -->
<div class="kpi-grid mb22" style="grid-template-columns:repeat(5,1fr)">
  <div class="kpi-card kc-indigo">
    <div class="kpi-top"><div class="kpi-icon ki-indigo"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.5"/><path d="M2 14c0-3 2.7-5 6-5s6 2 6 5"/></svg></div></div>
    <div class="kpi-val"><?= $counts['total'] ?></div>
    <div class="kpi-lbl">Total Users</div>
  </div>
  <div class="kpi-card kc-cyan">
    <div class="kpi-top"><div class="kpi-icon ki-cyan"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 14V7l6-5 6 5v7"/></svg></div></div>
    <div class="kpi-val"><?= $counts['barangay'] ?></div>
    <div class="kpi-lbl">Barangay Officers</div>
  </div>
  <div class="kpi-card kc-emerald">
    <div class="kpi-top"><div class="kpi-icon ki-emerald"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.5"/><path d="M2 14c0-3 2.7-5 6-5s6 2 6 5"/></svg></div></div>
    <div class="kpi-val"><?= $counts['community'] ?></div>
    <div class="kpi-lbl">Community Users</div>
  </div>
  <div class="kpi-card kc-amber">
    <div class="kpi-top"><div class="kpi-icon ki-amber"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5"/><circle cx="8" cy="11" r=".5" fill="currentColor"/></svg></div></div>
    <div class="kpi-val"><?= $counts['pending'] ?></div>
    <div class="kpi-lbl">Pending Approval</div>
  </div>
  <div class="kpi-card kc-rose">
    <div class="kpi-top"><div class="kpi-icon ki-rose"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M10 6L6 10M6 6l4 4"/></svg></div></div>
    <div class="kpi-val"><?= $counts['suspended'] ?></div>
    <div class="kpi-lbl">Suspended</div>
  </div>
</div>

<!-- Filters -->
<div class="filter-bar">
  <form method="GET" style="display:contents" id="filter-form">
    <input type="hidden" name="page" value="users">
    <div class="input-icon" style="flex:1;max-width:280px">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
      <input type="search" name="search" placeholder="Name, email, contact…" value="<?= htmlspecialchars($filter_search) ?>">
    </div>
    <select name="role" onchange="this.form.submit()">
      <option value="">All Roles</option>
      <option value="barangay"  <?= $filter_role==='barangay'?'selected':'' ?>>Barangay Officer</option>
      <option value="community" <?= $filter_role==='community'?'selected':'' ?>>Community User</option>
    </select>
    <select name="filter" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"    <?= $filter_status==='active'?'selected':'' ?>>Active</option>
      <option value="pending"   <?= $filter_status==='pending'?'selected':'' ?>>Pending Approval</option>
      <option value="suspended" <?= $filter_status==='suspended'?'selected':'' ?>>Suspended</option>
    </select>
    <select name="barangay" onchange="this.form.submit()">
      <option value="">All Barangays</option>
      <?php foreach ($barangays as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $filter_barangay==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <a href="?page=users" class="btn btn-ghost btn-sm">Clear</a>
  </form>
</div>

<!-- User Table -->
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
        <a href="<?= qstr(['p'=>$page_num-1]) ?>" class="btn btn-outline btn-sm">← Prev</a>
      <?php endif; ?>
      <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?>
        <a href="<?= qstr(['p'=>$i]) ?>" class="btn <?= $i===$page_num?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page_num < $total_pages): ?>
        <a href="<?= qstr(['p'=>$page_num+1]) ?>" class="btn btn-outline btn-sm">Next →</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ MODAL: Add User ══ -->
<div class="modal-overlay" id="modal-add-user">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add New User</span>
      <button class="modal-close" onclick="closeModal('modal-add-user')">×</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group"><label>First Name <span class="req">*</span></label><input type="text" id="add-first" placeholder="First name"></div>
        <div class="form-group"><label>Last Name <span class="req">*</span></label><input type="text" id="add-last" placeholder="Last name"></div>
      </div>
      <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" id="add-email" placeholder="email@domain.com"></div>
      <div class="form-row">
        <div class="form-group">
          <label>Role <span class="req">*</span></label>
          <select id="add-role">
            <option value="community">Community User</option>
            <option value="barangay">Barangay Officer</option>
          </select>
        </div>
        <div class="form-group">
          <label>Barangay</label>
          <select id="add-barangay">
            <option value="">— Select —</option>
            <?php foreach ($barangays as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Password <span class="req">*</span></label><input type="password" id="add-pw" placeholder="Min. 8 characters"></div>
        <div class="form-group"><label>Contact</label><input type="text" id="add-contact" placeholder="09XXXXXXXXX"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-add-user')">Cancel</button>
      <button class="btn btn-primary" onclick="submitAddUser()">Create User</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Edit User ══ -->
<div class="modal-overlay" id="modal-edit-user">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit User</span>
      <button class="modal-close" onclick="closeModal('modal-edit-user')">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit-uid">
      <div class="form-group"><label>Full Name</label><input type="text" id="edit-name" readonly style="background:var(--surface)"></div>
      <div class="form-group"><label>Email</label><input type="email" id="edit-email"></div>
      <div class="form-row">
        <div class="form-group">
          <label>Role</label>
          <select id="edit-role">
            <option value="community">Community User</option>
            <option value="barangay">Barangay Officer</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select id="edit-status">
            <option value="1">Active</option>
            <option value="0">Pending</option>
            <option value="2">Suspended</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Reset Password (leave blank to keep)</label><input type="password" id="edit-pw" placeholder="New password"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-edit-user')">Cancel</button>
      <button class="btn btn-primary" onclick="submitEditUser()">Save Changes</button>
    </div>
  </div>
</div>

<script>
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
}

function openUserModal(id, name, email, role, bgy, status) {
  document.getElementById('edit-uid').value    = id;
  document.getElementById('edit-name').value   = name;
  document.getElementById('edit-email').value  = email;
  document.getElementById('edit-role').value   = role;
  document.getElementById('edit-status').value = status;
  openModal('modal-edit-user');
}

function userAction(id, action) {
  const labels = { approve:'Approve', suspend:'Suspend', activate:'Activate', delete:'Permanently delete' };
  if (!confirm(labels[action] + ' this user?')) return;
  loading(true);
  fetch('ajax/user_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, id })
  })
  .then(r => r.json())
  .then(d => {
    loading(false);
    if (d.success) {
      if (action === 'delete') {
        document.getElementById('user-row-' + id)?.remove();
      } else {
        location.reload();
      }
      showToast(d.message, 'success');
    } else {
      showToast(d.message, 'error');
    }
  }).catch(() => { loading(false); showToast('Request failed.','error'); });
}

function submitAddUser() {
  const data = {
    action:    'create',
    first_name: document.getElementById('add-first').value.trim(),
    last_name:  document.getElementById('add-last').value.trim(),
    email:      document.getElementById('add-email').value.trim(),
    role:       document.getElementById('add-role').value,
    barangay_id: document.getElementById('add-barangay').value,
    password:   document.getElementById('add-pw').value,
    contact:    document.getElementById('add-contact').value.trim(),
  };
  if (!data.first_name || !data.last_name || !data.email || !data.password) {
    return showToast('Please fill in all required fields.','error');
  }
  loading(true);
  fetch('ajax/user_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
    .then(r=>r.json()).then(d=>{ loading(false); closeModal('modal-add-user'); showToast(d.message, d.success?'success':'error'); if(d.success) setTimeout(()=>location.reload(),800); })
    .catch(()=>{ loading(false); showToast('Request failed.','error'); });
}

function submitEditUser() {
  const data = {
    action:   'edit',
    id:       document.getElementById('edit-uid').value,
    email:    document.getElementById('edit-email').value.trim(),
    role:     document.getElementById('edit-role').value,
    status:   document.getElementById('edit-status').value,
    password: document.getElementById('edit-pw').value,
  };
  loading(true);
  fetch('ajax/user_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) })
    .then(r=>r.json()).then(d=>{ loading(false); closeModal('modal-edit-user'); showToast(d.message, d.success?'success':'error'); if(d.success) setTimeout(()=>location.reload(),800); })
    .catch(()=>{ loading(false); showToast('Request failed.','error'); });
}

function executeBulk() {
  const action = document.getElementById('bulk-action').value;
  const ids = [...document.querySelectorAll('.row-check:checked')].map(c => parseInt(c.value));
  if (!action) return showToast('Select a bulk action first.','error');
  if (!ids.length) return showToast('Select at least one user.','error');
  if (!confirm(`${action.charAt(0).toUpperCase()+action.slice(1)} ${ids.length} user(s)?`)) return;
  loading(true);
  fetch('ajax/user_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'bulk', bulk_action:action, ids }) })
    .then(r=>r.json()).then(d=>{ loading(false); showToast(d.message, d.success?'success':'error'); if(d.success) setTimeout(()=>location.reload(),800); })
    .catch(()=>{ loading(false); showToast('Request failed.','error'); });
}

function exportUsers() {
  window.location = 'ajax/export_users.php<?= "?".http_build_query(array_filter(["role"=>$filter_role,"filter"=>$filter_status,"barangay"=>$filter_barangay,"search"=>$filter_search])) ?>';
}
</script>
