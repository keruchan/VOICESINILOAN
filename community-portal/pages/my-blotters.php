<?php
// pages/my-blotters.php

// ── AJAX: return blotter detail as JSON ──────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'blotter_detail' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $uid  = (int)$user['id'];
    $bid  = (int)$_GET['id'];
    try {
        // Only allow the complainant to view their own blotter
        $s = $pdo->prepare("SELECT * FROM blotters WHERE id = ? AND complainant_user_id = ? LIMIT 1");
        $s->execute([$bid, $uid]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['error' => 'Not found']); exit; }

        // Fetch attachments
        $a = $pdo->prepare("SELECT original_name, file_path, mime_type, file_size FROM blotter_attachments WHERE blotter_id = ? ORDER BY created_at ASC");
        $a->execute([$bid]);
        $row['attachments'] = $a->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'data' => $row]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$uid = (int)$user['id'];
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

// Tab counts
$tcounts = [];
try {
    $rows = $pdo->query("SELECT status, COUNT(*) c FROM blotters WHERE complainant_user_id=$uid GROUP BY status")->fetchAll();
    foreach ($rows as $r) $tcounts[$r['status']] = (int)$r['c'];
} catch (PDOException $e) {}
$tcounts['all'] = array_sum($tcounts);

function mbq(array $o = []): string {
    $b = array_filter(['page'=>'my-blotters','status'=>$_GET['status']??'','search'=>$_GET['search']??''], fn($v)=>$v!=='');
    return '?' . http_build_query(array_merge($b, $o));
}
$lm = ['minor'=>'ch-green','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-green','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'];
?>

<div class="page-hdr">
  <div class="page-hdr-left"><h2>My Blotters</h2><p>Reports you have filed with your barangay</p></div>
  <a href="?page=file-report" class="btn btn-primary">+ File New Report</a>
</div>

<div class="tab-bar" style="margin-bottom:0;border-bottom:none">
  <?php
  $tabs = ['' => 'All', 'pending_review' => 'Pending', 'active' => 'Active', 'mediation_set' => 'Mediation Set', 'resolved' => 'Resolved', 'closed' => 'Closed'];
  foreach ($tabs as $val => $lbl):
    $cnt = $val === '' ? ($tcounts['all']??0) : ($tcounts[$val]??0);
  ?>
  <a class="tab-item <?= $f_status===$val?'active':'' ?>" href="<?= mbq(['status'=>$val,'pg'=>1]) ?>">
    <?= $lbl ?><?php if ($cnt): ?> <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= $cnt ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<div style="height:1px;background:var(--ink-100);margin-bottom:14px"></div>

<form method="GET" class="filter-bar">
  <input type="hidden" name="page" value="my-blotters">
  <?php if ($f_status): ?><input type="hidden" name="status" value="<?= e($f_status) ?>"><?php endif; ?>
  <div class="inp-icon" style="flex:1;max-width:280px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Case no., type, respondent…" value="<?= e($f_search) ?>">
  </div>
  <button type="submit" class="btn btn-outline btn-sm">Search</button>
  <a href="?page=my-blotters" class="btn btn-ghost btn-sm">Clear</a>
</form>

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
          <td><span class="chip <?= $lm[$b['violation_level']]??'ch-slate' ?>"><?= ucfirst($b['violation_level']) ?></span></td>
          <td><span class="chip <?= $sm[$b['status']]??'ch-slate' ?>"><?= ucwords(str_replace('_',' ',$b['status'])) ?></span></td>
          <td style="font-size:12px;color:var(--ink-500)"><?= e(ucwords(str_replace('_',' ',$b['prescribed_action']??''))) ?: '—' ?></td>
          <td style="font-size:12px;color:var(--ink-400)"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
          <td><button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <div class="pager">
      <span class="pager-info">Showing <?= min($off+1,$total) ?>–<?= min($off+$per,$total) ?> of <?= $total ?></span>
      <div class="pager-btns">
        <?php if ($pg>1): ?><a href="<?= mbq(['pg'=>$pg-1]) ?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?><a href="<?= mbq(['pg'=>$i]) ?>" class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a><?php endfor; ?>
        <?php if ($pg<$total_pages): ?><a href="<?= mbq(['pg'=>$pg+1]) ?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══════════ BLOTTER DETAIL MODAL ══════════ -->
<div id="blotter-modal" style="
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,.45);backdrop-filter:blur(3px);
  align-items:center;justify-content:center;padding:16px">

  <div style="
    background:var(--surface,#fff);border-radius:var(--r-xl,16px);
    width:100%;max-width:640px;max-height:90vh;overflow-y:auto;
    box-shadow:0 24px 64px rgba(0,0,0,.22);position:relative">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:20px 24px 0;position:sticky;top:0;background:var(--surface,#fff);
                border-bottom:1px solid var(--ink-100,#e5e7eb);padding-bottom:14px;z-index:1">
      <div>
        <div style="font-size:11px;font-weight:700;color:var(--ink-400);letter-spacing:.08em;
                    text-transform:uppercase;margin-bottom:2px">Blotter Report</div>
        <div id="bm-case" style="font-family:var(--font-mono,monospace);font-size:18px;
                                  font-weight:700;color:var(--ink-900)"></div>
      </div>
      <button onclick="closeBlotterModal()"
              style="width:34px;height:34px;border-radius:50%;border:1px solid var(--ink-100);
                     background:var(--surface-2,#f9fafb);cursor:pointer;font-size:18px;
                     display:flex;align-items:center;justify-content:center;
                     color:var(--ink-500);flex-shrink:0">×</button>
    </div>

    <!-- Loading state -->
    <div id="bm-loading" style="padding:48px;text-align:center;color:var(--ink-400);font-size:14px">
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" style="animation:bm-spin 1s linear infinite;margin-bottom:10px">
        <circle cx="14" cy="14" r="11" stroke-opacity=".25"/>
        <path d="M14 3a11 11 0 0 1 11 11"/>
      </svg><br>Loading report…
    </div>

    <!-- Content -->
    <div id="bm-content" style="display:none;padding:20px 24px 24px">

      <!-- Status + Level chips -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
        <span id="bm-status-chip" class="chip"></span>
        <span id="bm-level-chip"  class="chip"></span>
      </div>

      <!-- Two-column detail grid -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 20px;margin-bottom:18px">
        <div><div class="bm-lbl">Incident Type</div><div id="bm-type"     class="bm-val"></div></div>
        <div><div class="bm-lbl">Incident Date</div><div id="bm-date"     class="bm-val"></div></div>
        <div><div class="bm-lbl">Location</div>     <div id="bm-location" class="bm-val"></div></div>
        <div><div class="bm-lbl">Prescribed Action</div><div id="bm-action" class="bm-val"></div></div>
        <div><div class="bm-lbl">Respondent</div>  <div id="bm-respondent" class="bm-val"></div></div>
        <div><div class="bm-lbl">Filed On</div>    <div id="bm-filed"    class="bm-val"></div></div>
      </div>

      <!-- Narrative -->
      <div style="margin-bottom:18px">
        <div class="bm-lbl">Narrative / Description</div>
        <div id="bm-narrative"
             style="font-size:13px;color:var(--ink-700);line-height:1.75;
                    background:var(--surface-2,#f9fafb);border:1px solid var(--ink-100);
                    border-radius:var(--r-md,8px);padding:12px 14px;white-space:pre-wrap"></div>
      </div>

      <!-- Attachments -->
      <div id="bm-attach-wrap" style="display:none;margin-bottom:4px">
        <div class="bm-lbl" style="margin-bottom:8px">Attachments</div>
        <div id="bm-attach-list" style="display:flex;flex-wrap:wrap;gap:8px"></div>
      </div>

    </div>

    <!-- Error state -->
    <div id="bm-error"
         style="display:none;padding:40px 24px;text-align:center;color:var(--rose-600,#be123c);font-size:14px">
      <div style="font-size:28px;margin-bottom:8px">⚠️</div>
      <div id="bm-error-msg">Could not load the report. Please try again.</div>
    </div>

  </div>
</div>

<style>
.bm-lbl { font-size:10px;font-weight:700;color:var(--ink-400);letter-spacing:.07em;
           text-transform:uppercase;margin-bottom:3px; }
.bm-val { font-size:13px;color:var(--ink-800);font-weight:500;line-height:1.4; }
@keyframes bm-spin { to { transform:rotate(360deg); } }
#blotter-modal.open { display:flex; }
.bm-thumb {
  width:80px;height:80px;border-radius:var(--r-sm,6px);overflow:hidden;
  border:1px solid var(--ink-100);object-fit:cover;cursor:pointer;
  transition:opacity .15s;
}
.bm-thumb:hover { opacity:.85; }
</style>

<script>
const STATUS_MAP = {
  pending_review:'ch-amber', active:'ch-teal', mediation_set:'ch-navy',
  resolved:'ch-green', closed:'ch-slate', escalated:'ch-rose', transferred:'ch-slate'
};
const LEVEL_MAP = {
  minor:'ch-green', moderate:'ch-amber', serious:'ch-rose', critical:'ch-violet'
};
const STATUS_LABEL = {
  pending_review:'Pending Review', active:'Active', mediation_set:'Mediation Set',
  resolved:'Resolved', closed:'Closed', escalated:'Escalated', transferred:'Transferred'
};

function viewBlotter(id) {
  // Show modal in loading state
  const modal = document.getElementById('blotter-modal');
  document.getElementById('bm-loading').style.display = '';
  document.getElementById('bm-content').style.display = 'none';
  document.getElementById('bm-error').style.display   = 'none';
  document.getElementById('bm-case').textContent       = '…';
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';

  // Fetch detail via AJAX — uses the same page URL with ?ajax=blotter_detail&id=X
  const url = '?page=my-blotters&ajax=blotter_detail&id=' + encodeURIComponent(id);
  fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(res => {
      if (!res.ok || res.error) throw new Error(res.error || 'Unknown error');
      populateModal(res.data);
    })
    .catch(err => {
      document.getElementById('bm-loading').style.display   = 'none';
      document.getElementById('bm-error').style.display     = '';
      document.getElementById('bm-error-msg').textContent   = 'Request failed: ' + err.message;
    });
}

function populateModal(d) {
  // Case number
  document.getElementById('bm-case').textContent = d.case_number || '—';

  // Status chip
  const sc = document.getElementById('bm-status-chip');
  sc.className   = 'chip ' + (STATUS_MAP[d.status] || 'ch-slate');
  sc.textContent = STATUS_LABEL[d.status] || d.status;

  // Level chip
  const lc = document.getElementById('bm-level-chip');
  lc.className   = 'chip ' + (LEVEL_MAP[d.violation_level] || 'ch-slate');
  lc.textContent = d.violation_level
    ? d.violation_level.charAt(0).toUpperCase() + d.violation_level.slice(1)
    : '—';

  // Fields
  document.getElementById('bm-type').textContent      = d.incident_type || '—';
  document.getElementById('bm-location').textContent  = d.incident_location || '—';
  document.getElementById('bm-respondent').textContent= d.respondent_name || '—';
  document.getElementById('bm-narrative').textContent = d.narrative || '—';
  document.getElementById('bm-action').textContent    =
    d.prescribed_action
      ? d.prescribed_action.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase())
      : '—';

  // Dates
  document.getElementById('bm-date').textContent  = d.incident_date
    ? new Date(d.incident_date).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})
    : '—';
  document.getElementById('bm-filed').textContent = d.created_at
    ? new Date(d.created_at).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})
    : '—';

  // Attachments
  const attachWrap = document.getElementById('bm-attach-wrap');
  const attachList = document.getElementById('bm-attach-list');
  attachList.innerHTML = '';
  if (d.attachments && d.attachments.length > 0) {
    d.attachments.forEach(att => {
      if (att.mime_type && att.mime_type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src       = '/' + att.file_path;
        img.alt       = att.original_name;
        img.className = 'bm-thumb';
        img.title     = att.original_name;
        img.onclick   = () => window.open('/' + att.file_path, '_blank');
        attachList.appendChild(img);
      } else {
        const a = document.createElement('a');
        a.href      = '/' + att.file_path;
        a.target    = '_blank';
        a.textContent = att.original_name;
        a.style.cssText = 'font-size:12px;color:var(--green-600);display:block';
        attachList.appendChild(a);
      }
    });
    attachWrap.style.display = '';
  } else {
    attachWrap.style.display = 'none';
  }

  document.getElementById('bm-loading').style.display = 'none';
  document.getElementById('bm-content').style.display = '';
}

function closeBlotterModal() {
  document.getElementById('blotter-modal').classList.remove('open');
  document.body.style.overflow = '';
}

// Close on backdrop click
document.getElementById('blotter-modal').addEventListener('click', function (e) {
  if (e.target === this) closeBlotterModal();
});

// Close on Escape
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeBlotterModal();
});
</script>