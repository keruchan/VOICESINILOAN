<?php
// pages/user-management.php

$bid = (int)$user['barangay_id'];

// ── Fetch users with filters ───────────────────────────────────
$search        = trim($_GET['q']      ?? '');
$filter_status = $_GET['status']      ?? 'all';
$filter_sort   = $_GET['sort']        ?? 'newest';
$page_num      = max(1, (int)($_GET['p'] ?? 1));
$per_page      = 15;

$where  = ["u.barangay_id = ?", "u.role = 'community'"];
$params = [$bid];

if ($search !== '') {
    $where[]  = "(u.full_name LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)";
    $like     = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filter_status === 'active')   { $where[] = "u.is_active = 1"; }
if ($filter_status === 'inactive') { $where[] = "u.is_active = 0"; }

$order_map = [
    'newest'  => 'u.created_at DESC',
    'oldest'  => 'u.created_at ASC',
    'name_az' => 'u.full_name ASC',
    'name_za' => 'u.full_name DESC',
];
$order_sql = $order_map[$filter_sort] ?? 'u.created_at DESC';
$where_sql = 'WHERE ' . implode(' AND ', $where);

$total_users = 0;
try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
    $cnt->execute($params);
    $total_users = (int)$cnt->fetchColumn();
} catch (PDOException $e) {}

$total_pages = max(1, (int)ceil($total_users / $per_page));
$page_num    = min($page_num, $total_pages);
$offset      = ($page_num - 1) * $per_page;

$users_list = [];
try {
    $lst = $pdo->prepare("
        SELECT u.id, u.full_name, u.first_name, u.middle_name, u.last_name,
               u.email, u.contact_number, u.address, u.birth_date,
               u.is_active, u.created_at,
               (SELECT COUNT(*) FROM blotters b WHERE b.complainant_user_id = u.id) AS blotters_filed,
               (SELECT COUNT(*) FROM blotters b WHERE b.respondent_user_id  = u.id) AS blotters_respondent
        FROM users u
        $where_sql
        ORDER BY $order_sql
        LIMIT $per_page OFFSET $offset
    ");
    $lst->execute($params);
    $users_list = $lst->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$count_total = $count_active = $count_inactive = $count_new_month = 0;
try {
    $count_total    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE barangay_id=$bid AND role='community'")->fetchColumn();
    $count_active   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE barangay_id=$bid AND role='community' AND is_active=1")->fetchColumn();
    $count_inactive = $count_total - $count_active;
    $count_new_month= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE barangay_id=$bid AND role='community' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
} catch (PDOException $e) {}

function umPageUrl(int $p, string $q, string $status, string $sort): string {
    $args = ['page' => 'user-management', 'p' => $p];
    if ($q)               $args['q']      = $q;
    if ($status !== 'all')    $args['status'] = $status;
    if ($sort   !== 'newest') $args['sort']   = $sort;
    return '?' . http_build_query($args);
}

$av_palette = ['#0D7377','#047857','#6D28D9','#1F4068','#B45309','#BE123C','#0369A1','#7C3AED'];
?>

<!-- PAGE HEADER -->
<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>User Management</h2>
    <p><?= e($bgy['name']) ?> &nbsp;·&nbsp; Community Members</p>
  </div>
  <div class="page-hdr-actions">
    <button class="btn btn-primary" onclick="openModal('modal-register-user')">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 2v10M2 7h10"/></svg>
      Register New User
    </button>
  </div>
</div>

<!-- STAT CARDS -->
<div class="um-stat-row">
  <div class="um-stat <?= $filter_status==='all'&&!$search?'um-stat-hl':'' ?>" onclick="setStatusFilter('all')" style="cursor:pointer">
    <div class="um-stat-icon" style="background:var(--teal-50);color:var(--teal-600)">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="7" cy="5.5" r="2.5"/><path d="M2 15c0-3 2.5-5 5-5s5 2.2 5 5"/><circle cx="13.5" cy="5" r="1.8"/><path d="M12 10.2c1.2.6 2 1.9 2 3.8"/></svg>
    </div>
    <div><div class="um-stat-val"><?= $count_total ?></div><div class="um-stat-lbl">Total Members</div></div>
  </div>
  <div class="um-stat <?= $filter_status==='active'?'um-stat-hl':'' ?>" onclick="setStatusFilter('active')" style="cursor:pointer">
    <div class="um-stat-icon" style="background:var(--emerald-50);color:var(--emerald-600)">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 9.5l4 4 8-8"/></svg>
    </div>
    <div><div class="um-stat-val" style="color:var(--emerald-600)"><?= $count_active ?></div><div class="um-stat-lbl">Active</div></div>
  </div>
  <div class="um-stat <?= $filter_status==='inactive'?'um-stat-hl':'' ?>" onclick="setStatusFilter('inactive')" style="cursor:pointer">
    <div class="um-stat-icon" style="background:var(--amber-50);color:var(--amber-600)">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="9" r="7"/><path d="M6.5 11.5l5-5M11.5 11.5l-5-5"/></svg>
    </div>
    <div><div class="um-stat-val" style="color:var(--amber-600)"><?= $count_inactive ?></div><div class="um-stat-lbl">Pending / Inactive</div></div>
  </div>
  <div class="um-stat">
    <div class="um-stat-icon" style="background:var(--violet-50);color:var(--violet-600)">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M9 2v4M4.5 4.5l2.8 2.8M2 9h4M4.5 13.5l2.8-2.8M9 16v-4M13.5 13.5l-2.8-2.8M16 9h-4M13.5 4.5l-2.8 2.8"/></svg>
    </div>
    <div><div class="um-stat-val" style="color:var(--violet-600)"><?= $count_new_month ?></div><div class="um-stat-lbl">New This Month</div></div>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card" style="margin-bottom:16px">
  <div style="padding:14px 18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <div class="inp-icon" style="flex:1;min-width:220px;max-width:340px">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M10 10l2.5 2.5"/></svg>
      <input type="search" id="um-search" placeholder="Search by name, email or contact…" value="<?= e($search) ?>" oninput="umSearchDebounce()">
    </div>
    <select id="um-status" onchange="umApplyFilters()" style="width:auto;min-width:150px">
      <option value="all"      <?= $filter_status==='all'?'selected':'' ?>>All Status</option>
      <option value="active"   <?= $filter_status==='active'?'selected':'' ?>>✅ Active</option>
      <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>⏳ Pending / Inactive</option>
    </select>
    <select id="um-sort" onchange="umApplyFilters()" style="width:auto;min-width:150px">
      <option value="newest"  <?= $filter_sort==='newest'?'selected':'' ?>>Newest First</option>
      <option value="oldest"  <?= $filter_sort==='oldest'?'selected':'' ?>>Oldest First</option>
      <option value="name_az" <?= $filter_sort==='name_az'?'selected':'' ?>>Name A → Z</option>
      <option value="name_za" <?= $filter_sort==='name_za'?'selected':'' ?>>Name Z → A</option>
    </select>
    <div style="margin-left:auto;font-size:12px;color:var(--ink-400);white-space:nowrap">
      <?php if ($search): ?>
        <?= $total_users ?> result<?= $total_users!=1?'s':'' ?> for "<strong><?= e($search) ?></strong>"
        <button onclick="umClearSearch()" style="background:none;border:none;cursor:pointer;color:var(--rose-400);font-size:11px;margin-left:6px;font-family:inherit">✕ Clear</button>
      <?php else: ?>
        Showing <?= $total_users>0?(($page_num-1)*$per_page+1):0 ?>–<?= min($page_num*$per_page,$total_users) ?> of <?= $total_users ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- USER TABLE -->
<div class="card" style="margin-bottom:16px">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:40px"></th>
          <th>Member</th>
          <th>Contact</th>
          <th>Address</th>
          <th>Registered</th>
          <th style="text-align:center">Cases</th>
          <th>Status</th>
          <th style="text-align:center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($users_list)): ?>
        <tr><td colspan="8">
          <div class="empty-state" style="padding:52px 24px">
            <div class="es-icon"><?= $search?'🔍':'👥' ?></div>
            <div class="es-title"><?= $search?'No users match your search':'No community members yet' ?></div>
            <div class="es-sub">
              <?php if ($search): ?>Try a different name, email, or contact number.
              <?php elseif ($filter_status!=='all'): ?>No <?= $filter_status ?> users found.
              <?php else: ?>Register the first community member using the button above.<?php endif; ?>
            </div>
          </div>
        </td></tr>
      <?php else: foreach ($users_list as $u):
          $av_col    = $av_palette[crc32($u['email']) % count($av_palette)];
          $initials  = strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1));
          $age       = $u['birth_date'] ? (int)floor((time()-strtotime($u['birth_date']))/31557600) : null;
          $tot_cases = (int)$u['blotters_filed']+(int)$u['blotters_respondent'];
          $row_json  = htmlspecialchars(json_encode([
              'id'      => (int)$u['id'],
              'name'    => $u['full_name'],
              'first'   => $u['first_name'],
              'middle'  => $u['middle_name'] ?? '',
              'last'    => $u['last_name'],
              'email'   => $u['email'],
              'contact' => $u['contact_number'] ?? '',
              'address' => $u['address'] ?? '',
              'birth'   => $u['birth_date'] ?? '',
              'active'  => (int)$u['is_active'],
              'created' => $u['created_at'],
              'filed'   => (int)$u['blotters_filed'],
              'resp'    => (int)$u['blotters_respondent'],
              'initials'=> $initials,
              'avcolor' => $av_col,
          ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
      ?>
        <tr class="um-row">
          <td><div class="um-av" style="background:<?= $av_col ?>"><?= $initials ?></div></td>
          <td>
            <div class="td-main"><?= e($u['full_name']) ?></div>
            <div style="font-size:11px;color:var(--ink-400);margin-top:1px"><?= e($u['email']) ?><?= $age?" · {$age} yrs":'' ?></div>
          </td>
          <td style="font-size:12px;color:var(--ink-600)"><?= e($u['contact_number']?:'—') ?></td>
          <td style="font-size:12px;color:var(--ink-600)">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="<?= e($u['address']) ?>"><?= e($u['address']?:'—') ?></div>
          </td>
          <td style="font-size:12px;color:var(--ink-400);white-space:nowrap"><?= date('M j, Y',strtotime($u['created_at'])) ?></td>
          <td style="text-align:center">
            <?php if ($tot_cases>0): ?>
              <span class="chip <?= $u['blotters_respondent']>0?'ch-rose':'ch-teal' ?>" style="font-size:10px"><?= $tot_cases ?> case<?= $tot_cases!=1?'s':'' ?></span>
            <?php else: ?>
              <span style="font-size:12px;color:var(--ink-200)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="chip ch-emerald"><svg width="7" height="7" viewBox="0 0 7 7" fill="currentColor"><circle cx="3.5" cy="3.5" r="3.5"/></svg> Active</span>
            <?php else: ?>
              <span class="chip ch-amber"><svg width="7" height="7" viewBox="0 0 7 7" fill="currentColor"><circle cx="3.5" cy="3.5" r="3.5"/></svg> Pending</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;white-space:nowrap">
            <!-- FIX: data-user attribute avoids single-quote breakage in onclick for names like O'Brien -->
            <button class="act-btn um-view-btn" data-user="<?= $row_json ?>">View</button>
            <?php if ($u['is_active']): ?>
              <button class="act-btn red um-toggle-btn" style="margin-left:4px"
                data-uid="<?= $u['id'] ?>" data-status="0" data-name="<?= e($u['full_name']) ?>">Deactivate</button>
            <?php else: ?>
              <button class="act-btn green um-toggle-btn" style="margin-left:4px"
                data-uid="<?= $u['id'] ?>" data-status="1" data-name="<?= e($u['full_name']) ?>">&#x2713; Activate</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages>1): ?>
  <div class="card-foot">
    <div class="pager">
      <div class="pager-info">Page <?= $page_num ?> of <?= $total_pages ?> &nbsp;·&nbsp; <?= $total_users ?> users</div>
      <div class="pager-btns">
        <?php if ($page_num>1): ?><a href="<?= umPageUrl($page_num-1,$search,$filter_status,$filter_sort) ?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for($pp=max(1,$page_num-2);$pp<=min($total_pages,$page_num+2);$pp++): ?>
          <a href="<?= umPageUrl($pp,$search,$filter_status,$filter_sort) ?>" class="btn btn-sm <?= $pp===$page_num?'btn-primary':'btn-outline' ?>"><?= $pp ?></a>
        <?php endfor; ?>
        <?php if ($page_num<$total_pages): ?><a href="<?= umPageUrl($page_num+1,$search,$filter_status,$filter_sort) ?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ════════ USER DETAIL PANEL ════════ -->
<div class="panel-overlay" id="panel-user">
  <div class="slide-panel" style="width:520px;display:flex;flex-direction:column;height:100%">

    <!-- Fixed header -->
    <div class="panel-hdr" style="flex-shrink:0">
      <div style="display:flex;align-items:center;gap:12px;min-width:0">
        <div class="um-av um-av-lg" id="pd-avatar" style="background:#0D7377;flex-shrink:0">??</div>
        <div style="min-width:0">
          <div class="panel-title" id="pd-name" style="font-size:16px">Loading…</div>
          <div style="font-size:12px;color:var(--ink-400);margin-top:1px" id="pd-email"></div>
          <div style="margin-top:4px" id="pd-status-chip"></div>
        </div>
      </div>
      <button class="panel-x" onclick="closePanel('panel-user')">&#x2715;</button>
    </div>

    <!-- Scrollable body -->
    <div id="pd-scroll-body" style="overflow-y:auto;flex:1;padding:0 22px 32px">

      <!-- Loading state -->
      <div id="pd-loading" style="text-align:center;padding:60px 0;color:var(--ink-300);font-size:13px">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
             style="animation:um-spin .75s linear infinite;display:block;margin:0 auto 12px">
          <circle cx="12" cy="12" r="9" stroke-opacity=".2"/>
          <path d="M12 3a9 9 0 0 1 9 9"/>
        </svg>
        Loading user information…
      </div>

      <!-- Error state -->
      <div id="pd-error" style="display:none;padding:20px;margin-top:20px;background:var(--rose-50);border:1px solid var(--rose-100);border-radius:var(--r-md);color:var(--rose-600);font-size:13px;line-height:1.6"></div>

      <!-- All content (hidden until loaded) -->
      <div id="pd-content" style="display:none">

        <!-- ── SECTION: Personal Information ── -->
        <div style="font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;text-transform:uppercase;margin:20px 0 10px">Personal Information</div>
        <div style="background:var(--surface-2,var(--ink-50));border-radius:var(--r-md);overflow:hidden;margin-bottom:18px">
          <div id="pd-info-body"></div>
        </div>

        <!-- Activate/Deactivate button -->
        <div id="pd-toggle-wrap" style="margin-bottom:24px;display:flex;gap:8px"></div>

        <!-- ── SECTION: Blotter Cases ── -->
        <div style="height:1px;background:var(--ink-100);margin-bottom:18px"></div>
        <div style="font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;text-transform:uppercase;margin-bottom:12px">Blotter Cases</div>
        <div id="pd-cases-body"></div>

        <!-- ── SECTION: Edit Information ── -->
        <div style="height:1px;background:var(--ink-100);margin:24px 0 18px"></div>
        <div style="font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px">Edit Member Information</div>
        <div style="background:var(--amber-50);border:1px solid var(--amber-100);border-radius:var(--r-md);padding:10px 14px;font-size:12px;color:var(--amber-700);margin-bottom:14px;line-height:1.6">
          &#9888; Changes require your <strong>admin password</strong> to confirm.
        </div>
        <div id="edit-msg" style="display:none;border-radius:var(--r-md);padding:10px 14px;font-size:12px;margin-bottom:14px"></div>
        <div class="fr3" style="margin-bottom:12px">
          <div class="fg" style="margin-bottom:0"><label>First Name <span class="req">*</span></label><input type="text" id="edit-first" maxlength="80"></div>
          <div class="fg" style="margin-bottom:0"><label>Middle Name</label><input type="text" id="edit-middle" maxlength="80"></div>
          <div class="fg" style="margin-bottom:0"><label>Last Name <span class="req">*</span></label><input type="text" id="edit-last" maxlength="80"></div>
        </div>
        <div class="fr2" style="margin-bottom:12px">
          <div class="fg" style="margin-bottom:0"><label>Email <span class="req">*</span></label><input type="email" id="edit-email" maxlength="160"></div>
          <div class="fg" style="margin-bottom:0"><label>Contact</label><input type="tel" id="edit-contact" maxlength="15" placeholder="09XXXXXXXXX"></div>
        </div>
        <div class="fg" style="margin-bottom:12px"><label>Address</label><input type="text" id="edit-address" maxlength="200"></div>
        <div class="fg" style="margin-bottom:14px"><label>Date of Birth</label><input type="date" id="edit-birth" max="<?= date('Y-m-d') ?>"></div>
        <div style="height:1px;background:var(--ink-100);margin-bottom:14px"></div>
        <div class="fg" style="margin-bottom:14px">
          <label>Your Admin Password <span class="req">*</span></label>
          <input type="password" id="edit-adminpass" placeholder="Required to save changes" autocomplete="current-password">
        </div>
        <div style="display:flex;justify-content:flex-end">
          <button class="btn btn-primary btn-sm" id="edit-save-btn" onclick="saveUserInfo()">Save Changes</button>
        </div>

        <!-- ── SECTION: Reset Password ── -->
        <div style="height:1px;background:var(--ink-100);margin:24px 0 18px"></div>
        <div style="font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px">Reset Member Password</div>
        <div style="background:var(--rose-50);border:1px solid var(--rose-100);border-radius:var(--r-md);padding:10px 14px;font-size:12px;color:var(--rose-600);margin-bottom:14px;line-height:1.6">
          &#128273; The member must use the new password on their next login. Requires your admin password.
        </div>
        <div id="pass-msg" style="display:none;border-radius:var(--r-md);padding:10px 14px;font-size:12px;margin-bottom:14px"></div>
        <div class="fr2" style="margin-bottom:12px">
          <div class="fg" style="margin-bottom:0"><label>New Password <span class="req">*</span></label><input type="password" id="pass-new" placeholder="Min. 8 characters"></div>
          <div class="fg" style="margin-bottom:0"><label>Confirm Password <span class="req">*</span></label><input type="password" id="pass-confirm" placeholder="Repeat password"></div>
        </div>
        <div style="height:1px;background:var(--ink-100);margin-bottom:14px"></div>
        <div class="fg" style="margin-bottom:14px">
          <label>Your Admin Password <span class="req">*</span></label>
          <input type="password" id="pass-adminpass" placeholder="Required to reset password" autocomplete="current-password">
        </div>
        <div style="display:flex;justify-content:flex-end">
          <button class="btn btn-danger btn-sm" id="pass-save-btn" onclick="saveNewPassword()">Reset Password</button>
        </div>

      </div><!-- /pd-content -->
    </div><!-- /pd-scroll-body -->
  </div>
</div>

<!-- ════════ CONFIRM MODAL ════════ -->
<div class="modal-overlay" id="modal-um-confirm">
  <div class="modal" style="max-width:420px">
    <div class="modal-hdr">
      <span class="modal-title" id="confirm-title">Confirm</span>
      <button class="modal-x" onclick="closeModal('modal-um-confirm')">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p id="confirm-msg" style="font-size:14px;color:var(--ink-600);line-height:1.7"></p>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-um-confirm')">Cancel</button>
      <button class="btn" id="confirm-btn" onclick="executeToggle()">Confirm</button>
    </div>
  </div>
</div>

<!-- ════════ REGISTER MODAL ════════ -->
<div class="modal-overlay" id="modal-register-user">
  <div class="modal modal-lg" style="max-width:640px;max-height:90vh;overflow-y:auto">
    <div class="modal-hdr" style="position:sticky;top:0;background:var(--surface);z-index:10;border-bottom:1px solid var(--ink-100)">
      <span class="modal-title">Register New Community Member</span>
      <button class="modal-x" onclick="closeModal('modal-register-user')">&#x2715;</button>
    </div>
    <div class="modal-body" style="padding:20px 24px">
      <div style="background:var(--teal-50);border:1px solid var(--teal-100);border-radius:var(--r-md);padding:11px 14px;font-size:12px;color:var(--teal-700);margin-bottom:20px;line-height:1.6">
        <strong>Auto-Activated:</strong> Accounts registered here are immediately active — no approval step needed.
      </div>
      <div id="reg-error" style="display:none;background:var(--rose-50);border:1px solid var(--rose-100);border-radius:var(--r-md);padding:10px 14px;font-size:13px;color:var(--rose-700);margin-bottom:16px"></div>
      <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.07em;text-transform:uppercase;margin-bottom:12px">Personal Information</div>
      <div class="fr3" style="margin-bottom:12px">
        <div class="fg" style="margin-bottom:0"><label>First Name <span class="req">*</span></label><input type="text" id="reg-first" placeholder="Juan" maxlength="80"></div>
        <div class="fg" style="margin-bottom:0"><label>Middle Name</label><input type="text" id="reg-middle" placeholder="Santos" maxlength="80"></div>
        <div class="fg" style="margin-bottom:0"><label>Last Name <span class="req">*</span></label><input type="text" id="reg-last" placeholder="Dela Cruz" maxlength="80"></div>
      </div>
      <div class="fr2" style="margin-bottom:12px">
        <div class="fg" style="margin-bottom:0"><label>Date of Birth <span class="req">*</span></label><input type="date" id="reg-birth" max="<?= date('Y-m-d') ?>"></div>
        <div class="fg" style="margin-bottom:0"><label>Contact Number <span class="req">*</span></label><input type="tel" id="reg-contact" placeholder="09171234567" maxlength="15"></div>
      </div>
      <div class="fg" style="margin-bottom:12px"><label>Home Address <span class="req">*</span></label><input type="text" id="reg-address" placeholder="House/Lot No., Street, Purok" maxlength="200"></div>
      <div style="height:1px;background:var(--ink-100);margin:16px 0"></div>
      <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.07em;text-transform:uppercase;margin-bottom:12px">Account Information</div>
      <div class="fg" style="margin-bottom:12px"><label>Email Address <span class="req">*</span></label><input type="email" id="reg-email" placeholder="juan@email.com" maxlength="160"></div>
      <div class="fr2" style="margin-bottom:4px">
        <div class="fg" style="margin-bottom:0"><label>Password <span class="req">*</span></label><input type="password" id="reg-pass" placeholder="Min. 8 characters"></div>
        <div class="fg" style="margin-bottom:0"><label>Confirm Password <span class="req">*</span></label><input type="password" id="reg-pass2" placeholder="Repeat password"></div>
      </div>
      <div style="font-size:11px;color:var(--ink-400)">At least 8 characters.</div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-register-user')">Cancel</button>
      <button class="btn btn-primary" id="reg-submit-btn" onclick="submitRegister()">
        <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6.5 1v11M1 6.5h11"/></svg>
        Register &amp; Activate
      </button>
    </div>
  </div>
</div>

<!-- SCRIPTS -->
<script>
var UM_AJAX = 'ajax/um_action.php';
var pdUser  = null;  // currently loaded user object

// ── Delegated click handlers for table buttons ───
document.addEventListener('click', function(e) {
  var viewBtn = e.target.closest('.um-view-btn');
  if (viewBtn) {
    var raw = viewBtn.getAttribute('data-user');
    try { openUserPanel(JSON.parse(raw)); }
    catch(ex) { console.error('um-view-btn parse error', ex, raw); alert('Could not open user panel. Check console.'); }
    return;
  }
  var togBtn = e.target.closest('.um-toggle-btn');
  if (togBtn) {
    confirmToggle(
      parseInt(togBtn.getAttribute('data-uid'), 10),
      parseInt(togBtn.getAttribute('data-status'), 10),
      togBtn.getAttribute('data-name')
    );
  }
});

// ── Filters ───────────────────────────────────────
var umST = null;
function umSearchDebounce(){ clearTimeout(umST); umST = setTimeout(umApplyFilters, 380); }
function umApplyFilters(){
  var q = document.getElementById('um-search').value.trim();
  var s = document.getElementById('um-status').value;
  var o = document.getElementById('um-sort').value;
  var u = '?page=user-management';
  if (q) u += '&q=' + encodeURIComponent(q);
  if (s !== 'all')    u += '&status=' + encodeURIComponent(s);
  if (o !== 'newest') u += '&sort='   + encodeURIComponent(o);
  window.location.href = u;
}
function setStatusFilter(v){ document.getElementById('um-status').value = v; umApplyFilters(); }
function umClearSearch(){ document.getElementById('um-search').value = ''; umApplyFilters(); }

// ── Panel open/close ──────────────────────────────
function openPanel(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closePanel(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
document.getElementById('panel-user').addEventListener('click', function(e){
  if (e.target === this) closePanel('panel-user');
});

// ── Open user panel — show skeleton, fire AJAX ────
function openUserPanel(rowData) {
  pdUser = (typeof rowData === 'object') ? rowData : JSON.parse(rowData);

  // Populate header from table row data immediately (no waiting)
  document.getElementById('pd-avatar').textContent        = pdUser.initials || '??';
  document.getElementById('pd-avatar').style.background  = pdUser.avcolor  || '#0D7377';
  document.getElementById('pd-name').textContent          = pdUser.name;
  document.getElementById('pd-email').textContent         = pdUser.email;
  document.getElementById('pd-status-chip').innerHTML     = pdUser.active
    ? '<span class="chip ch-emerald" style="font-size:10px">&#9679; Active</span>'
    : '<span class="chip ch-amber"   style="font-size:10px">&#9679; Pending / Inactive</span>';

  // Reset body to loading state
  document.getElementById('pd-loading').style.display = 'block';
  document.getElementById('pd-error').style.display   = 'none';
  document.getElementById('pd-content').style.display = 'none';
  document.getElementById('pd-scroll-body').scrollTop = 0;

  // Clear any stale form messages
  hideMsg('edit-msg');
  hideMsg('pass-msg');

  openPanel('panel-user');

  // Fetch full user data + cases
  fetch(UM_AJAX + '?ajax_action=get_user&user_id=' + pdUser.id)
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status + ' — server error');
      var ct = r.headers.get('content-type') || '';
      if (ct.indexOf('json') === -1) {
        return r.text().then(function(txt) {
          throw new Error('Non-JSON response. First 200 chars: ' + txt.substring(0, 200));
        });
      }
      return r.json();
    })
    .then(function(d) {
      document.getElementById('pd-loading').style.display = 'none';
      if (!d.success) {
        showPdError('Server returned an error: ' + (d.message || 'Unknown error.'));
        return;
      }
      renderPanel(d.user);
    })
    .catch(function(err) {
      document.getElementById('pd-loading').style.display = 'none';
      showPdError('Failed to load user data.<br><small style="opacity:.7">' + umEsc(err.message) + '</small>');
      console.error('openUserPanel fetch error:', err);
    });
}

function showPdError(msg) {
  var el = document.getElementById('pd-error');
  el.innerHTML = '&#10060; ' + msg;
  el.style.display = 'block';
}

// ── Render all panel sections from AJAX data ──────
function renderPanel(u) {
  // Merge AJAX data back into pdUser (AJAX has full_name, contact_number etc.)
  pdUser.name    = u.full_name    || pdUser.name;
  pdUser.email   = u.email        || pdUser.email;
  pdUser.contact = u.contact_number || '';
  pdUser.address = u.address      || '';
  pdUser.birth   = u.birth_date   || '';
  pdUser.created = u.created_at   || '';
  pdUser.active  = parseInt(u.is_active, 10);
  pdUser.first   = u.first_name   || '';
  pdUser.middle  = u.middle_name  || '';
  pdUser.last    = u.last_name    || '';

  // Update header with any fresher data
  document.getElementById('pd-name').textContent  = pdUser.name;
  document.getElementById('pd-email').textContent = pdUser.email;
  document.getElementById('pd-status-chip').innerHTML = pdUser.active
    ? '<span class="chip ch-emerald" style="font-size:10px">&#9679; Active</span>'
    : '<span class="chip ch-amber"   style="font-size:10px">&#9679; Pending / Inactive</span>';

  renderInfoRows(pdUser);
  renderToggleBtn(pdUser);
  renderCasesSection(u.cases_filed || [], u.cases_respondent || []);
  prefillEditForm(pdUser);

  document.getElementById('pd-content').style.display = 'block';
}

// ── Info rows ─────────────────────────────────────
function renderInfoRows(u) {
  var age = '';
  if (u.birth) {
    var diff = (Date.now() - new Date(u.birth).getTime()) / (365.25 * 24 * 3600 * 1000);
    age = ' (' + Math.floor(diff) + ' yrs)';
  }
  var born = u.birth
    ? (new Date(u.birth + 'T00:00:00').toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'}) + age)
    : '—';
  var reg = u.created
    ? new Date(u.created).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})
    : '—';

  var rows = [
    ['Full Name',            umEsc(u.name)],
    ['Email',                '<a href="mailto:'+umEsc(u.email)+'" style="color:var(--teal-600)">'+umEsc(u.email)+'</a>'],
    ['Contact Number',       umEsc(u.contact || '—')],
    ['Date of Birth',        born],
    ['Home Address',         umEsc(u.address || '—')],
    ['Registered',           reg],
    ['Account Status',       u.active
      ? '<span class="chip ch-emerald" style="font-size:11px">Active</span>'
      : '<span class="chip ch-amber"   style="font-size:11px">Pending / Inactive</span>'],
    ['Blotters Filed',       '<strong style="color:var(--teal-600)">'+(u.filed||0)+'</strong>'],
    ['Listed as Respondent', '<strong style="color:'+(u.resp>0?'var(--rose-600)':'var(--ink-400)')+'">'+(u.resp||0)+'</strong>'],
  ];

  var html = '';
  rows.forEach(function(r) {
    html += '<div class="dr" style="padding:9px 14px;border-bottom:1px solid var(--ink-100)">'
          + '<span class="dr-lbl" style="font-size:12px">'+r[0]+'</span>'
          + '<span class="dr-val" style="font-size:12px;max-width:62%;text-align:right;word-break:break-word">'+r[1]+'</span>'
          + '</div>';
  });
  document.getElementById('pd-info-body').innerHTML = html;
}

// ── Activate/Deactivate button in panel ───────────
function renderToggleBtn(u) {
  var w = document.getElementById('pd-toggle-wrap');
  if (u.active) {
    w.innerHTML = '<button class="btn btn-outline btn-sm pd-toggle-btn" '
      + 'style="color:var(--rose-600);border-color:var(--rose-200)" '
      + 'data-uid="'+u.id+'" data-status="0" data-name="'+umEsc(u.name)+'">Deactivate Account</button>';
  } else {
    w.innerHTML = '<button class="btn btn-success btn-sm pd-toggle-btn" '
      + 'data-uid="'+u.id+'" data-status="1" data-name="'+umEsc(u.name)+'">&#x2713; Activate Account</button>';
  }
  var btn = w.querySelector('.pd-toggle-btn');
  if (btn) {
    btn.addEventListener('click', function() {
      var uid    = parseInt(this.getAttribute('data-uid'), 10);
      var status = parseInt(this.getAttribute('data-status'), 10);
      var name   = this.getAttribute('data-name');
      closePanel('panel-user');
      confirmToggle(uid, status, name);
    });
  }
}

// ── Cases section ─────────────────────────────────
function renderCasesSection(filed, resp) {
  var html = '';

  html += '<div style="font-size:11px;font-weight:600;color:var(--teal-600);margin-bottom:8px">'
        + 'Filed as Complainant <span style="color:var(--ink-400);font-weight:400">('+filed.length+')</span></div>';
  if (!filed.length) {
    html += '<div style="font-size:12px;color:var(--ink-300);padding:6px 0 14px">No blotters filed.</div>';
  } else {
    html += '<div style="margin-bottom:16px">';
    filed.forEach(function(b){ html += caseRow(b, 'var(--teal-500)'); });
    html += '</div>';
  }

  html += '<div style="height:1px;background:var(--ink-100);margin-bottom:12px"></div>';

  html += '<div style="font-size:11px;font-weight:600;color:var(--rose-600);margin-bottom:8px">'
        + 'Listed as Respondent <span style="color:var(--ink-400);font-weight:400">('+resp.length+')</span></div>';
  if (!resp.length) {
    html += '<div style="font-size:12px;color:var(--ink-300);padding:6px 0">Not listed as respondent in any case.</div>';
  } else {
    resp.forEach(function(b){ html += caseRow(b, 'var(--rose-400)'); });
  }

  document.getElementById('pd-cases-body').innerHTML = html;
}

function caseRow(b, dot) {
  var sc = {pending_review:'ch-amber',active:'ch-teal',mediation_set:'ch-navy',resolved:'ch-emerald',closed:'ch-slate',escalated:'ch-rose',transferred:'ch-slate'};
  var lc = {minor:'ch-emerald',moderate:'ch-amber',serious:'ch-rose',critical:'ch-violet'};
  var sl = (b.status||'').replace(/_/g,' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
  var dt = b.incident_date
    ? new Date(b.incident_date+'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})
    : '—';
  return '<div class="pd-case">'
    + '<div class="pd-case-dot" style="background:'+dot+'"></div>'
    + '<div style="flex:1;min-width:0">'
    +   '<div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:2px">'
    +     '<span style="font-family:var(--font-mono,monospace);font-size:11px;font-weight:700;color:var(--ink-900)">'+umEsc(b.case_number)+'</span>'
    +     '<span class="chip '+(lc[b.violation_level]||'ch-slate')+'" style="font-size:10px">'+umEsc(b.violation_level||'')+'</span>'
    +     '<span class="chip '+(sc[b.status]||'ch-slate')+'" style="font-size:10px">'+sl+'</span>'
    +   '</div>'
    +   '<div style="color:var(--ink-600);font-size:12px">'+umEsc(b.incident_type||'')+'</div>'
    +   '<div style="color:var(--ink-400);font-size:11px;margin-top:1px">'+dt+'</div>'
    + '</div></div>';
}

// ── Edit info form ────────────────────────────────
function prefillEditForm(u) {
  document.getElementById('edit-first').value   = u.first   || '';
  document.getElementById('edit-middle').value  = u.middle  || '';
  document.getElementById('edit-last').value    = u.last    || '';
  document.getElementById('edit-email').value   = u.email   || '';
  document.getElementById('edit-contact').value = u.contact || '';
  document.getElementById('edit-address').value = u.address || '';
  document.getElementById('edit-birth').value   = u.birth   || '';
  document.getElementById('edit-adminpass').value = '';
  hideMsg('edit-msg');
}

function saveUserInfo() {
  if (!pdUser) return;
  var ap = document.getElementById('edit-adminpass').value;
  if (!ap) { showMsg('edit-msg', 'error', 'Admin password is required.'); return; }
  var btn = document.getElementById('edit-save-btn');
  btn.disabled = true; btn.textContent = 'Saving…';
  var fd = new FormData();
  fd.append('ajax_action',    'update_user');
  fd.append('user_id',        pdUser.id);
  fd.append('first_name',     document.getElementById('edit-first').value.trim());
  fd.append('middle_name',    document.getElementById('edit-middle').value.trim());
  fd.append('last_name',      document.getElementById('edit-last').value.trim());
  fd.append('email',          document.getElementById('edit-email').value.trim());
  fd.append('contact_number', document.getElementById('edit-contact').value.trim());
  fd.append('address',        document.getElementById('edit-address').value.trim());
  fd.append('birth_date',     document.getElementById('edit-birth').value);
  fd.append('admin_password', ap);
  fetch(UM_AJAX, {method:'POST', body:fd})
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(d){
      btn.disabled = false; btn.textContent = 'Save Changes';
      if (d.success) {
        showMsg('edit-msg', 'success', d.message);
        showToast(d.message, 'success');
        // Update pdUser in memory
        var nf = document.getElementById('edit-first').value.trim();
        var nm = document.getElementById('edit-middle').value.trim();
        var nl = document.getElementById('edit-last').value.trim();
        pdUser.name    = nl + ', ' + nf + (nm ? ' ' + nm : '');
        pdUser.first   = nf; pdUser.middle = nm; pdUser.last = nl;
        pdUser.email   = document.getElementById('edit-email').value.trim();
        pdUser.contact = document.getElementById('edit-contact').value.trim();
        pdUser.address = document.getElementById('edit-address').value.trim();
        pdUser.birth   = document.getElementById('edit-birth').value;
        // Refresh panel header + info rows
        document.getElementById('pd-name').textContent  = pdUser.name;
        document.getElementById('pd-email').textContent = pdUser.email;
        document.getElementById('pd-avatar').textContent = (nf[0]||'') + (nl[0]||'');
        renderInfoRows(pdUser);
        document.getElementById('edit-adminpass').value = '';
        setTimeout(function(){ location.reload(); }, 1500);
      } else {
        showMsg('edit-msg', 'error', d.message || 'Could not save changes.');
      }
    })
    .catch(function(err){
      btn.disabled = false; btn.textContent = 'Save Changes';
      showMsg('edit-msg', 'error', 'Request failed: ' + err.message);
    });
}

// ── Reset password ────────────────────────────────
function saveNewPassword() {
  if (!pdUser) return;
  var np = document.getElementById('pass-new').value;
  var cp = document.getElementById('pass-confirm').value;
  var ap = document.getElementById('pass-adminpass').value;
  if (!np || !cp || !ap) { showMsg('pass-msg', 'error', 'All fields are required.'); return; }
  if (np !== cp)         { showMsg('pass-msg', 'error', 'New passwords do not match.'); return; }
  if (np.length < 8)     { showMsg('pass-msg', 'error', 'Password must be at least 8 characters.'); return; }
  var btn = document.getElementById('pass-save-btn');
  btn.disabled = true; btn.textContent = 'Resetting…';
  var fd = new FormData();
  fd.append('ajax_action',     'reset_password');
  fd.append('user_id',         pdUser.id);
  fd.append('new_password',    np);
  fd.append('confirm_password',cp);
  fd.append('admin_password',  ap);
  fetch(UM_AJAX, {method:'POST', body:fd})
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(d){
      btn.disabled = false; btn.textContent = 'Reset Password';
      if (d.success) {
        showMsg('pass-msg', 'success', d.message);
        showToast(d.message, 'success');
        document.getElementById('pass-new').value      = '';
        document.getElementById('pass-confirm').value  = '';
        document.getElementById('pass-adminpass').value = '';
      } else {
        showMsg('pass-msg', 'error', d.message || 'Password reset failed.');
      }
    })
    .catch(function(err){
      btn.disabled = false; btn.textContent = 'Reset Password';
      showMsg('pass-msg', 'error', 'Request failed: ' + err.message);
    });
}

// ── Activate / Deactivate ─────────────────────────
var pendingId = null, pendingStatus = null;
function confirmToggle(uid, status, name) {
  pendingId = uid; pendingStatus = status;
  var title = status === 1 ? 'Activate Account' : 'Deactivate Account';
  document.getElementById('confirm-title').textContent = title;
  document.getElementById('confirm-msg').innerHTML = status === 1
    ? 'Activate the account of <strong>' + umEsc(name) + '</strong>? They will be able to log in immediately.'
    : 'Deactivate the account of <strong>' + umEsc(name) + '</strong>? They will lose access until reactivated.';
  var btn = document.getElementById('confirm-btn');
  btn.textContent = title;
  btn.className   = 'btn ' + (status === 1 ? 'btn-success' : 'btn-danger');
  openModal('modal-um-confirm');
}
function executeToggle() {
  if (pendingId === null) return;
  var btn = document.getElementById('confirm-btn');
  btn.disabled = true; btn.textContent = 'Please wait…';
  var fd = new FormData();
  fd.append('ajax_action', pendingStatus === 1 ? 'activate' : 'deactivate');
  fd.append('user_id', pendingId);
  fetch(UM_AJAX, {method:'POST', body:fd})
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(d){
      btn.disabled = false;
      closeModal('modal-um-confirm');
      if (d.success) { showToast(d.message, 'success'); setTimeout(function(){ location.reload(); }, 700); }
      else            { showToast(d.message || 'Action failed.', 'error'); }
    })
    .catch(function(err){
      btn.disabled = false;
      closeModal('modal-um-confirm');
      showToast('Request failed: ' + err.message, 'error');
    });
}

// ── Register new user ─────────────────────────────
function submitRegister() {
  hideRegError();
  var p = document.getElementById('reg-pass').value;
  var p2= document.getElementById('reg-pass2').value;
  if (p !== p2) { showRegError('Passwords do not match.'); return; }
  var btn = document.getElementById('reg-submit-btn');
  btn.disabled = true; btn.innerHTML = 'Registering…';
  var fd = new FormData();
  fd.append('ajax_action', 'register_user');
  fd.append('first_name',  document.getElementById('reg-first').value.trim());
  fd.append('middle_name', document.getElementById('reg-middle').value.trim());
  fd.append('last_name',   document.getElementById('reg-last').value.trim());
  fd.append('birth_date',  document.getElementById('reg-birth').value);
  fd.append('contact',     document.getElementById('reg-contact').value.trim());
  fd.append('address',     document.getElementById('reg-address').value.trim());
  fd.append('email',       document.getElementById('reg-email').value.trim());
  fd.append('password',    p);
  fetch(UM_AJAX, {method:'POST', body:fd})
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(d){
      btn.disabled = false; btn.innerHTML = 'Register &amp; Activate';
      if (d.success) {
        showToast('User registered and activated!', 'success');
        closeModal('modal-register-user');
        resetRegisterForm();
        setTimeout(function(){ location.reload(); }, 800);
      } else {
        var errs = d.errors || [d.message || 'Failed.'];
        showRegError(errs.join('<br>'));
      }
    })
    .catch(function(err){
      btn.disabled = false; btn.innerHTML = 'Register &amp; Activate';
      showRegError('Request failed: ' + err.message);
    });
}
function showRegError(m){ var e=document.getElementById('reg-error'); e.innerHTML=m; e.style.display='block'; e.scrollIntoView({behavior:'smooth',block:'center'}); }
function hideRegError(){ document.getElementById('reg-error').style.display='none'; }
function resetRegisterForm(){
  ['reg-first','reg-middle','reg-last','reg-birth','reg-contact','reg-address','reg-email','reg-pass','reg-pass2']
    .forEach(function(id){ document.getElementById(id).value=''; });
  hideRegError();
}

// ── Shared helpers ────────────────────────────────
function showMsg(id, type, msg){
  var el=document.getElementById(id), ok=(type==='success');
  el.style.background = ok ? 'var(--emerald-50)' : 'var(--rose-50)';
  el.style.border     = '1px solid ' + (ok ? 'var(--emerald-100)' : 'var(--rose-100)');
  el.style.color      = ok ? 'var(--emerald-600)' : 'var(--rose-600)';
  el.innerHTML = (ok ? '&#10003; ' : '&#10060; ') + msg;
  el.style.display = 'block';
}
function hideMsg(id){ var el=document.getElementById(id); if(el) el.style.display='none'; }
function umEsc(s){ return String(s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
</script>
<style>
@keyframes um-spin { to { transform: rotate(360deg); } }
.um-stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.um-stat{background:var(--white);border:1px solid var(--ink-100);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;transition:border-color .15s,box-shadow .15s}
.um-stat:hover{border-color:var(--teal-200);box-shadow:var(--shadow-sm)}
.um-stat-hl{border-color:var(--teal-400)!important;box-shadow:0 0 0 3px rgba(20,145,155,.1)!important}
.um-stat-icon{width:40px;height:40px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.um-stat-val{font-size:26px;font-weight:700;color:var(--ink-900);line-height:1.1}
.um-stat-lbl{font-size:11px;color:var(--ink-400);font-weight:500;margin-top:2px}
.um-av{width:32px;height:32px;border-radius:50%;color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.um-av-lg{width:46px;height:46px;font-size:16px}
.um-row:hover td{background:var(--teal-50)!important}
.pd-case{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--surface-2);font-size:12px}
.pd-case:last-child{border-bottom:none}
.pd-case-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:4px}
@media(max-width:1100px){.um-stat-row{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){.um-stat-row{grid-template-columns:1fr 1fr}}
</style>
