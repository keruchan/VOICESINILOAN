<?php
// pages/blotter-management.php
$bid = (int)$user['barangay_id'];

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

$tab_counts = [];
try {
    $rows = $pdo->query("SELECT status, COUNT(*) c FROM blotters WHERE barangay_id=$bid GROUP BY status")->fetchAll();
    foreach ($rows as $r) $tab_counts[$r['status']] = (int)$r['c'];
} catch (PDOException $e) {}
$tab_counts['all'] = array_sum($tab_counts);

function bq(array $o = []): string {
    $base = array_filter(['page'=>'blotter-management','status'=>$_GET['status']??'','level'=>$_GET['level']??'','type'=>$_GET['type']??'','search'=>$_GET['search']??''], fn($v)=>$v!=='');
    return '?' . http_build_query(array_merge($base, $o));
}

$inc_types = ['Noise Disturbance','Physical Altercation','Verbal Abuse / Threat','Property Damage','Domestic Dispute','VAWC','Trespassing','Theft / Estafa','Drug-Related','Traffic Incident','Public Disturbance','Other'];
$lm = ['minor'=>'ch-emerald','moderate'=>'ch-amber','serious'=>'ch-rose','critical'=>'ch-violet'];
$sm = ['pending_review'=>'ch-amber','active'=>'ch-teal','mediation_set'=>'ch-navy','resolved'=>'ch-emerald','closed'=>'ch-slate','escalated'=>'ch-rose','transferred'=>'ch-slate'];
?>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Blotter Management</h2>
    <p>All cases for <?= e($bgy['name']) ?></p>
  </div>
  <div class="page-hdr-actions">
    <button class="btn btn-outline btn-sm" onclick="exportCSV()">⬇ Export CSV</button>
    <button class="btn btn-primary" onclick="openModal('modal-new-blotter')">+ New Blotter</button>
  </div>
</div>

<!-- Status tabs -->
<div class="tab-bar" style="margin-bottom:0;border-bottom:none">
  <?php
  $tabs = [''=>'All','pending_review'=>'Pending','active'=>'Active','mediation_set'=>'Mediation Set','resolved'=>'Resolved','deliberation'=>'Deliberation','transferred'=>'Transferred','closed'=>'Closed','escalated'=>'Escalated'];
  foreach ($tabs as $val => $lbl):
    $cnt = $val === '' ? ($tab_counts['all']??0) : ($tab_counts[$val]??0);
  ?>
  <a class="tab-item <?= $f_status===$val?'active':'' ?>" href="<?= bq(['status'=>$val,'pg'=>1]) ?>">
    <?= $lbl ?><?php if ($cnt): ?> <span style="font-size:10px;background:var(--surface-2);padding:0 6px;border-radius:10px;margin-left:3px"><?= $cnt ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<div style="height:1px;background:var(--ink-100);margin-bottom:14px"></div>

<!-- Filter bar -->
<form method="GET" class="filter-bar">
  <input type="hidden" name="page" value="blotter-management">
  <?php if ($f_status): ?><input type="hidden" name="status" value="<?= e($f_status) ?>"><?php endif; ?>
  <div class="inp-icon" style="flex:1;max-width:260px">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M11 11l-2.5-2.5"/></svg>
    <input type="search" name="search" placeholder="Case no., name…" value="<?= e($f_search) ?>">
  </div>
  <select name="level" onchange="this.form.submit()">
    <option value="">All Levels</option>
    <?php foreach (['minor','moderate','serious','critical'] as $l): ?>
      <option value="<?= $l ?>" <?= $f_level===$l?'selected':'' ?>><?= ucfirst($l) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type" onchange="this.form.submit()">
    <option value="">All Types</option>
    <?php foreach ($inc_types as $t): ?>
      <option value="<?= $t ?>" <?= $f_type===$t?'selected':'' ?>><?= $t ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline btn-sm">Search</button>
  <a href="?page=blotter-management" class="btn btn-ghost btn-sm">Clear</a>
</form>

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
              <button class="act-btn" onclick="viewBlotter(<?= $b['id'] ?>)">View</button>
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
        <?php if ($pg>1): ?><a href="<?= bq(['pg'=>$pg-1]) ?>" class="btn btn-outline btn-sm">← Prev</a><?php endif; ?>
        <?php for ($i=max(1,$pg-2);$i<=min($total_pages,$pg+2);$i++): ?>
          <a href="<?= bq(['pg'=>$i]) ?>" class="btn <?= $i===$pg?'btn-primary':'btn-outline' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pg<$total_pages): ?><a href="<?= bq(['pg'=>$pg+1]) ?>" class="btn btn-outline btn-sm">Next →</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══ Case Report Modal ══ -->
<div class="modal-overlay" id="modal-case-report">
  <div class="modal" style="max-width:860px;width:95vw;max-height:95vh;overflow:hidden;display:flex;flex-direction:column;padding:0;border-radius:var(--r-xl)">
    <div class="modal-hdr" style="position:sticky;top:0;z-index:10;background:var(--white);flex-shrink:0">
      <span class="modal-title">📄 Barangay Case Report</span>
      <div style="display:flex;gap:6px;align-items:center">
        <button class="btn btn-outline btn-sm" onclick="printCaseReport()">🖨️ Print</button>
        <button class="btn btn-primary btn-sm" onclick="exportCaseReportPDF()">⬇ Export PDF</button>
        <button class="modal-x" onclick="closeModal('modal-case-report')">×</button>
      </div>
    </div>
    <div style="flex:1;overflow-y:auto;background:var(--surface-2);padding:24px 20px">
      <div id="cr-loading" style="text-align:center;padding:60px;color:var(--ink-300)">
        <div class="spinner" style="margin:0 auto 16px;width:32px;height:32px;border-width:2px;border-color:rgba(20,145,155,.2);border-top-color:var(--teal-600)"></div>
        Generating case report…
      </div>
      <div id="cr-document" style="display:none;background:white;max-width:794px;margin:0 auto;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:56px 64px;min-height:1000px"></div>
    </div>
    <div class="modal-foot" style="flex-shrink:0;justify-content:space-between">
      <button class="btn btn-ghost" onclick="closeModal('modal-case-report')">Close</button>
      <div style="display:flex;gap:8px">
        <button class="btn btn-outline" onclick="printCaseReport()">🖨️ Print</button>
        <button class="btn btn-primary" onclick="exportCaseReportPDF()">⬇ Export PDF</button>
      </div>
    </div>
  </div>
</div>

<!-- Schedule Mediation Modal -->
<div class="modal-overlay" id="modal-schedule-med">
  <div class="modal">
    <div class="modal-hdr">
      <span class="modal-title">Schedule Mediation</span>
      <button class="modal-x" onclick="closeModal('modal-schedule-med')">×</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="sm-blotter-id">
      <div class="fg"><label>Case</label><input type="text" id="sm-case-no" readonly style="background:var(--surface)"></div>
      <div class="fr2">
        <div class="fg"><label>Hearing Date <span class="req">*</span></label><input type="date" id="sm-date" min="<?= date('Y-m-d') ?>"></div>
        <div class="fg"><label>Hearing Time <span class="req">*</span></label><input type="time" id="sm-time"></div>
      </div>
      <div class="fg"><label>Venue</label><input type="text" id="sm-venue" value="Barangay Hall"></div>
      <div class="fg"><label>Notes</label><textarea id="sm-notes" rows="2" placeholder="Instructions for parties…"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-schedule-med')">Cancel</button>
      <button class="btn btn-primary" onclick="submitSchedule()">Schedule Hearing</button>
    </div>
  </div>
</div>

<script>
// ─── Auto-status map (mirrors PHP autoStatus()) ──────────────────────────────
const AUTO_STATUS = {
  // Referred out
  refer_police:'transferred', refer_vawc:'transferred', refer_dswd:'transferred',
  refer_nbi:'transferred', escalate_municipality:'transferred',
  transfer_barangay:'transferred', certificate_to_file:'transferred',
  // Barangay proceedings
  mediation:'mediation_set', conciliation:'mediation_set',
  summon_issued:'mediation_set', lupon_hearing:'mediation_set', pangkat_hearing:'mediation_set',
  // Resolution complete
  written_agreement:'resolved', sanction_imposed:'resolved',
  no_action_needed:'resolved', withdrawn_by_complainant:'resolved', dismissed:'resolved',
  // Document only = closed
  document_only:'closed',
  // Active engagement
  active_response:'active', noise_abatement:'active',
  cleanup_order:'active', site_inspection:'active',
};

const ACTION_LABELS = {
  refer_police:'🚔 Refer to Police',         refer_vawc:'🛡️ Refer to VAWC',
  refer_dswd:'👨‍👩‍👧 Refer to DSWD/WCPD',       refer_nbi:'🔍 Refer to NBI',
  escalate_municipality:'🏛️ Escalate to Municipality',
  transfer_barangay:'🔀 Transfer Barangay',  certificate_to_file:'📜 Certificate to File',
  mediation:'🤝 Barangay Mediation',         conciliation:'🕊️ Conciliation',
  summon_issued:'📬 Summon Issued',           lupon_hearing:'👥 Lupon Hearing',
  pangkat_hearing:'🏛️ Pangkat Hearing',      written_agreement:'📝 Written Agreement',
  sanction_imposed:'⚖️ Sanction / Fine',      no_action_needed:'✅ No Action Needed',
  withdrawn_by_complainant:'↩️ Withdrawn',    dismissed:'🚫 Dismissed',
  document_only:'📄 Document Only',          active_response:'🚨 Active Response',
  noise_abatement:'🔇 Noise Abatement',      cleanup_order:'🧹 Cleanup Order',
  site_inspection:'🔎 Site Inspection',
};

const STATUS_LABELS = {
  pending_review:'Pending Review', active:'Active', mediation_set:'Mediation Set',
  escalated:'Escalated', resolved:'Resolved', closed:'Closed', transferred:'Transferred'
};

// ─── updateStatus — called by Save Update button ──────────────────────────────
function updateStatus(id) {
  const status     = document.getElementById('p-status')?.value    || '';
  const action     = document.getElementById('p-action')?.value    || '';
  const level      = document.getElementById('p-level')?.value     || '';
  const remarks    = document.getElementById('p-remarks')?.value?.trim() || '';

  // Preview the auto-derived status before sending
  const derived = AUTO_STATUS[action] || null;
  const finalStatus = derived || status;

  if (!status && !action) { showToast('Please select a status or prescribed action.', 'error'); return; }

  loading(true);
  fetch('ajax/blotter_action.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action:'update_status', id, status, prescribed_action:action, violation_level:level, remarks })
  })
  .then(r => r.json())
  .then(d => {
    loading(false);
    showToast(d.message, d.success ? 'success' : 'error');
    if (d.success) {
      // Re-render panel with fresh data
      fetch('ajax/get_blotter.php?id=' + id)
        .then(r => r.json())
        .then(res => { if (res.success) renderPanel(res.data); });
      setTimeout(() => location.reload(), 1400);
    }
  })
  .catch(err => { loading(false); showToast('Request failed: ' + err.message, 'error'); });
}

// ─── quickAction ──────────────────────────────────────────────────────────────
function quickAction(id, status, prescribed_action, remarks) {
  loading(true);
  fetch('ajax/blotter_action.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'update_status', id, status, prescribed_action, remarks})
  })
  .then(r => r.json())
  .then(d => {
    loading(false);
    showToast(d.message, d.success ? 'success' : 'error');
    if (d.success) {
      fetch('ajax/get_blotter.php?id=' + id)
        .then(r => r.json())
        .then(res => { if (res.success) renderPanel(res.data); });
      setTimeout(() => location.reload(), 1400);
    }
  })
  .catch(() => { loading(false); showToast('Request failed.', 'error'); });
}

// ─── quickApprove ─────────────────────────────────────────────────────────────
function quickApprove(id) {
  if (!confirm('Move this blotter to Active status?')) return;
  quickAction(id, 'active', '', 'Approved by officer');
}

// ─── Schedule Mediation ───────────────────────────────────────────────────────
function openScheduleMed(id, caseNo) {
  document.getElementById('sm-blotter-id').value = id;
  document.getElementById('sm-case-no').value    = caseNo;
  openModal('modal-schedule-med');
}
function submitSchedule() {
  const data = {
    action:'schedule_mediation',
    blotter_id: document.getElementById('sm-blotter-id').value,
    date:   document.getElementById('sm-date').value,
    time:   document.getElementById('sm-time').value,
    venue:  document.getElementById('sm-venue').value.trim(),
    notes:  document.getElementById('sm-notes').value.trim(),
  };
  if (!data.date || !data.time) return showToast('Date and time are required.', 'error');
  loading(true);
  fetch('ajax/mediation_action.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
  .then(r=>r.json()).then(d=>{
    loading(false); closeModal('modal-schedule-med');
    showToast(d.message, d.success?'success':'error');
    if (d.success) setTimeout(()=>location.reload(),700);
  }).catch(()=>{loading(false);showToast('Request failed.','error');});
}

// ─── Export CSV ───────────────────────────────────────────────────────────────
function exportCSV() {
  window.location = 'ajax/export_blotters.php?' + new URLSearchParams({
    barangay_id: BARANGAY_ID,
    status:'<?= e($f_status) ?>', level:'<?= e($f_level) ?>', search:'<?= e($f_search) ?>'
  });
}

// ─── renderPanel — overrides index.php version with full feature set ──────────
function renderPanel(b) {
  document.getElementById('panel-case-no').textContent  = b.case_number;
  document.getElementById('panel-case-sub').textContent = b.incident_type + ' · ' + b.incident_date;

  const hasRespondent = !!(b.respondent_name && b.respondent_name.trim());
  const isTerminal    = ['resolved','closed','transferred'].includes(b.status);

  // ── Predict auto-status as user changes action dropdown ──
  // (injected into the panel HTML below via onchange)

  // ── Status options ──
  const statusOpts = Object.entries(STATUS_LABELS)
    .map(([v,l]) => `<option value="${v}"${b.status===v?' selected':''}>${l}</option>`).join('');

  // ── Level options ──
  const levelOpts = [['minor','🟢 Minor'],['moderate','🟡 Moderate'],['serious','🔴 Serious'],['critical','🟣 Critical']]
    .map(([v,l]) => `<option value="${v}"${b.violation_level===v?' selected':''}>${l}</option>`).join('');

  // ── Prescribed Action options (optgroups) ──
  const actionGroups = [
    { label:'📋 Documentation', items:[
      ['document_only','📄 Document Only — no further action'],
    ]},
    { label:'🏘️ Barangay Resolution', items:[
      ['mediation',          '🤝 Barangay Mediation'],
      ['conciliation',       '🕊️ Conciliation / Amicable Settlement'],
      ['summon_issued',      '📬 Summon Issued to Parties'],
      ['written_agreement',  '📝 Written Agreement Executed'],
      ['sanction_imposed',   '⚖️ Sanction / Fine Imposed'],
      ['lupon_hearing',      '👥 Lupon Tagapamayapa Hearing'],
      ['pangkat_hearing',    '🏛️ Pangkat Hearing'],
    ]},
    { label:'🚨 Active Response', items:[
      ['active_response',  '🚨 Active Response Required'],
      ['noise_abatement',  '🔇 Noise Abatement / Warning'],
      ['cleanup_order',    '🧹 Cleanup / Environmental Order'],
      ['site_inspection',  '🔎 Site Inspection Dispatched'],
    ]},
    { label:'🔀 Referrals & Escalation', items:[
      ['refer_police',           '🚔 Refer to Police (PNP)'],
      ['refer_vawc',             '🛡️ Refer to VAWC Desk'],
      ['refer_dswd',             '👨‍👩‍👧 Refer to DSWD / WCPD'],
      ['refer_nbi',              '🔍 Refer to NBI'],
      ['refer_attorney',         '⚖️ Refer to Attorney / PAO'],
      ['escalate_municipality',  '🏛️ Escalate to Municipality'],
      ['transfer_barangay',      '🔀 Transfer to Another Barangay'],
      ['certificate_to_file',    '📜 Issue Certificate to File Action'],
    ]},
    { label:'✅ Closure', items:[
      ['withdrawn_by_complainant', '↩️ Withdrawn by Complainant'],
      ['dismissed',               '🚫 Case Dismissed'],
      ['no_action_needed',        '✅ No Further Action Needed'],
    ]},
  ];

  let actionOpts = '<option value="">— Select action —</option>';
  actionGroups.forEach(g => {
    actionOpts += `<optgroup label="${g.label}">`;
    g.items.forEach(([v,l]) => {
      actionOpts += `<option value="${v}"${b.prescribed_action===v?' selected':''}>${l}</option>`;
    });
    actionOpts += '</optgroup>';
  });

  // ── Timeline ──
  const timeline = (b.timeline||[]).map(t=>`
    <div class="tl-item">
      <div class="tl-dot tl-dot-teal"></div>
      <div>
        <div class="tl-title">${ucw(t.action.replace(/_/g,' '))}</div>
        <div class="tl-desc">${t.description||''}</div>
        <div class="tl-time">${t.created_at}</div>
      </div>
    </div>`).join('');

  // ── Attachments ──
  const attachHtml = (b.attachments&&b.attachments.length>0) ? `
    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">📎 Attachments (${b.attachments.length})</span></div>
      <div class="card-body" style="padding:12px 16px">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px">
          ${b.attachments.map(att=>{
            const p='../'+att.file_path;
            return `<div onclick="viewAttachment('${p}','${att.original_name}')"
                         style="border-radius:var(--r-md);overflow:hidden;border:1px solid var(--ink-100);cursor:pointer">
              <img src="${p}" alt="${att.original_name}" style="width:100%;height:88px;object-fit:cover;display:block" onerror="this.style.opacity='.3'">
              <div style="font-size:10px;color:var(--ink-500);padding:3px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:var(--surface-2)">${att.original_name}</div>
            </div>`;
          }).join('')}
        </div>
      </div>
    </div>` : '';

  // ── No-respondent notice ──
  const noRespNote = !hasRespondent ? `
    <div style="background:var(--amber-50,#fffbeb);border:1px solid var(--amber-200,#fde68a);
                border-radius:var(--r-md);padding:10px 14px;font-size:12px;color:var(--amber-700);margin-bottom:12px">
      ℹ️ <strong>No respondent identified.</strong>
      Mediation is unavailable — the case can still be documented, referred, or escalated.
    </div>` : '';

  // ── Quick action buttons (context-aware) ──
  const quickBtns = !isTerminal ? `
    <div style="margin-bottom:16px">
      <div class="panel-section-lbl">Quick Actions</div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        ${b.status!=='resolved'   ? `<button class="btn btn-outline btn-sm" onclick="quickAction(${b.id},'resolved','no_action_needed','Marked resolved')">✅ Resolve</button>` : ''}
        ${b.status==='resolved'   ? `<button class="btn btn-outline btn-sm" onclick="quickAction(${b.id},'active','','Reopened')">🔄 Reopen</button>` : ''}
        ${b.status!=='closed'     ? `<button class="btn btn-outline btn-sm" onclick="quickAction(${b.id},'closed','','Case closed')">🔒 Close</button>` : ''}
        ${b.status!=='escalated'  ? `<button class="btn btn-outline btn-sm" onclick="quickAction(${b.id},'escalated','refer_police','Escalated to police')">🚔 Refer Police</button>` : ''}
        ${(b.incident_type==='VAWC'||b.incident_type==='Domestic Dispute')
          ? `<button class="btn btn-outline btn-sm" onclick="quickAction(${b.id},'transferred','refer_vawc','Referred to VAWC desk')">🛡️ Refer VAWC</button>` : ''}
        ${hasRespondent && b.status!=='mediation_set'
          ? `<button class="btn btn-outline btn-sm" onclick="openScheduleMed(${b.id},'${b.case_number.replace(/'/g,"\\'")}')">🤝 Mediation</button>` : ''}
        <button class="btn btn-outline btn-sm" onclick="quickAction(${b.id},'transferred','refer_attorney','Referred to attorney/PAO')">⚖️ Refer Attorney</button>
      </div>
    </div>` : `
    <div style="background:var(--surface-2);border:1px solid var(--ink-100);border-radius:var(--r-md);
                padding:10px 14px;font-size:12px;color:var(--ink-400);margin-bottom:14px">
      🔒 Case is <strong>${ucw(b.status.replace(/_/g,' '))}</strong>.
      Change status below to reopen or modify.
    </div>`;

  // ── Auto-status preview hint (shown dynamically via onchange) ──
  const autoHint = `
    <div id="auto-status-hint" style="display:none;font-size:11px;font-weight:600;
         color:var(--green-700);background:var(--green-50);border:1px solid var(--green-200);
         border-radius:var(--r-sm);padding:5px 10px;margin-top:6px">
      ✨ Status will auto-set to: <span id="auto-status-label"></span>
    </div>`;

  window._currentBlotter = b;

  document.getElementById('panel-body').innerHTML = `
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
      ${levelChip(b.violation_level)} ${statusChip(b.status)}
      <button class="btn btn-outline btn-sm" style="margin-left:auto;border-color:var(--navy-200);color:var(--navy-700)" onclick="openCaseReport()">📄 Case Report</button>
    </div>

    ${noRespNote}
    ${quickBtns}

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Case Information</span></div>
      <div class="card-body" style="padding:12px 16px">
        <div class="dr"><span class="dr-lbl">Complainant</span>   <span class="dr-val">${b.complainant_name||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Contact</span>       <span class="dr-val">${b.complainant_contact||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Respondent</span>    <span class="dr-val">${b.respondent_name||'<em style="color:var(--ink-300)">Not identified</em>'}</span></div>
        <div class="dr"><span class="dr-lbl">Resp. Contact</span> <span class="dr-val">${b.respondent_contact||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Location</span>      <span class="dr-val">${b.incident_location||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Incident Date</span> <span class="dr-val">${(b.incident_date||'').substring(0,10)||'—'}</span></div>
        <div class="dr"><span class="dr-lbl">Filed</span>         <span class="dr-val">${(b.created_at||'').substring(0,10)||'—'}</span></div>
      </div>
    </div>

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Narrative</span></div>
      <div class="card-body" style="padding:12px 16px">
        <p style="font-size:13px;color:var(--ink-700);line-height:1.75;white-space:pre-wrap">${b.narrative||'No narrative recorded.'}</p>
      </div>
    </div>

    ${attachHtml}

    <div class="card mb16">
      <div class="card-hdr"><span class="card-title">Update Case</span></div>
      <div class="card-body" style="padding:12px 16px">

        <div class="fg" style="margin-bottom:12px">
          <label>Violation Level</label>
          <select id="p-level">${levelOpts}</select>
        </div>

        <div class="fr2">
          <div class="fg">
            <label>Status</label>
            <select id="p-status">${statusOpts}</select>
            <div style="font-size:10px;color:var(--ink-400);margin-top:4px">
              ⚡ Status may be auto-set by action
            </div>
          </div>
          <div class="fg">
            <label>Prescribed Action</label>
            <select id="p-action" onchange="previewAutoStatus(this.value)">${actionOpts}</select>
            ${autoHint}
          </div>
        </div>

        <div class="fg">
          <label>Remarks / Notes</label>
          <textarea id="p-remarks" rows="2" placeholder="Optional officer remarks…"></textarea>
        </div>

        <div style="display:flex;gap:8px;margin-top:4px">
          <button class="btn btn-primary btn-sm" onclick="updateStatus(${b.id})">
            💾 Save Update
          </button>
          <button class="btn btn-ghost btn-sm" onclick="closePanel()">Cancel</button>
        </div>
      </div>
    </div>

    ${timeline ? `
      <div class="panel-section-lbl" style="margin-bottom:8px">Activity Log</div>
      ${timeline}` : ''}
  `;
}

// ─── Case Report ─────────────────────────────────────────────────────────────

const BGY_INFO = <?= json_encode([
  'name'         => $bgy['name']         ?? 'Barangay',
  'municipality' => $bgy['municipality'] ?? '',
  'province'     => $bgy['province']     ?? '',
  'captain_name' => $bgy['captain_name'] ?? '',
  'contact_no'   => $bgy['contact_no']   ?? '',
]) ?>;
const CURRENT_OFFICER = <?= json_encode($user['name'] ?? 'Barangay Officer') ?>;

function openCaseReport() {
  const b = window._currentBlotter;
  if (!b) return;
  openModal('modal-case-report');
  document.getElementById('cr-loading').style.display = 'block';
  document.getElementById('cr-document').style.display = 'none';
  setTimeout(() => renderCaseReport(b), 180);
}

function crEsc(s) {
  return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function crFmtDate(s) {
  if (!s) return '—';
  const d = new Date(s.length === 10 ? s + 'T00:00:00' : s);
  if (isNaN(d)) return String(s).substring(0, 10);
  return d.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
}
function crFmtTime(s) {
  if (!s || s === '00:00:00') return '';
  const p = String(s).split(':');
  let h = parseInt(p[0]), m = p[1] || '00';
  const ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${h}:${m} ${ap}`;
}
function crOrdinal(n) {
  const s = ['th','st','nd','rd'], v = n % 100;
  return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

const CR_ACTION_LABELS = {
  refer_police:'Referral to the Philippine National Police (PNP)',
  refer_vawc:'Referral to the Violence Against Women and Children (VAWC) Desk',
  refer_dswd:'Referral to the Department of Social Welfare and Development (DSWD)',
  refer_nbi:'Referral to the National Bureau of Investigation (NBI)',
  escalate_municipality:'Escalation to the Municipality',
  transfer_barangay:'Transfer to Another Barangay',
  certificate_to_file:'Issuance of Certificate to File Action',
  mediation:'Barangay Mediation',
  conciliation:'Conciliation / Amicable Settlement',
  summon_issued:'Issuance of Summons to the Parties',
  lupon_hearing:'Lupon Tagapamayapa Hearing',
  pangkat_hearing:'Pangkat ng Tagapagkasundo Hearing',
  written_agreement:'Execution of Written Agreement',
  sanction_imposed:'Imposition of Sanction or Fine',
  no_action_needed:'No Further Action Required',
  withdrawn_by_complainant:'Withdrawal by the Complainant',
  dismissed:'Dismissal of the Complaint',
  document_only:'Documentation Only — No Further Action',
  active_response:'Active Response Required',
  noise_abatement:'Noise Abatement Order / Warning',
  cleanup_order:'Cleanup / Environmental Order',
  site_inspection:'Site Inspection Dispatched',
  refer_attorney:"Referral to an Attorney / Public Attorney's Office (PAO)",
};

const CR_STATUS_LABELS = {
  pending_review:'Pending Review', active:'Active', mediation_set:'Mediation Set',
  resolved:'Resolved', closed:'Closed', transferred:'Transferred',
  escalated:'Escalated', dismissed:'Dismissed', deliberation:'Under Deliberation',
};

function generateNarrative(b) {
  const incDate   = crFmtDate(b.incident_date);
  const filedDate = crFmtDate(b.created_at);
  const type      = b.incident_type || 'the reported incident';
  const comp      = b.complainant_name || 'the complainant';
  const loc       = b.incident_location || 'an undisclosed location';
  const levelMap  = { minor:'Minor', moderate:'Moderate', serious:'Serious', critical:'Critical' };
  const lvl       = levelMap[b.violation_level] || (b.violation_level || '');
  const status    = b.status || '';
  const action    = b.prescribed_action || '';
  const narr      = (b.narrative || '').trim();
  const meds      = b.mediation_sessions || [];
  const hasResp   = !!(b.respondent_name && b.respondent_name.trim());
  const resp      = hasResp ? b.respondent_name : 'an unidentified party';
  const bgyFull   = 'Barangay ' + BGY_INFO.name +
    (BGY_INFO.municipality ? ', ' + BGY_INFO.municipality : '') +
    (BGY_INFO.province     ? ', ' + BGY_INFO.province     : '');

  let t = '';

  // ¶1 – Case receipt
  t += `On ${incDate}, a formal complaint was received and duly recorded at the office of ${bgyFull}. `;
  t += `The complaint, classified under the category of ${type}`;
  if (lvl) t += ` and assessed to be of ${lvl} violation level`;
  t += `, was filed by ${comp}`;
  if (b.complainant_contact) t += ` (contact number: ${b.complainant_contact})`;
  t += `. The case was officially assigned Case Number ${b.case_number} and formally logged on ${filedDate}. `;
  t += `The reported incident occurred at ${loc}.\n\n`;

  // ¶2 – Complaint description and respondent
  if (narr) {
    const cn = narr.charAt(0).toUpperCase() + narr.slice(1);
    t += `Based on the account presented by the complainant, ${cn.endsWith('.') ? cn : cn + '.'}`;
  } else {
    t += `The complainant presented a formal account of the incident before the Barangay, the details of which are contained in the official blotter entry for Case Number ${b.case_number}.`;
  }
  if (hasResp) {
    t += ` The complaint was directed against ${resp}`;
    if (b.respondent_contact) t += ` (contact number: ${b.respondent_contact})`;
    t += `.`;
  } else {
    t += ` As of the date of this report, the respondent has not yet been formally identified. The Barangay reserves the right to update this record upon identification of the concerned party.`;
  }
  t += '\n\n';

  // ¶3 – Action taken
  t += `Upon receipt and review of the complaint, the Barangay undertook the necessary steps in accordance with the Katarungang Pambarangay Law (Chapter 7, Title I, Book III of Republic Act No. 7160, otherwise known as the Local Government Code of 1991). `;
  if (action && CR_ACTION_LABELS[action]) {
    t += `The prescribed course of action determined by the Barangay was: ${CR_ACTION_LABELS[action]}.`;
  } else if (action) {
    t += `The prescribed course of action determined by the Barangay was: ${action.replace(/_/g, ' ')}.`;
  } else {
    t += `The case was placed under active review and monitoring pending determination of the appropriate course of action.`;
  }
  if (b.remarks && b.remarks.trim()) {
    t += ` Officer's additional remarks: "${b.remarks.trim()}".`;
  }
  t += '\n\n';

  // ¶4 – Mediation (if any)
  if (meds.length > 0) {
    t += `Pursuant to the Katarungang Pambarangay process, mediation proceedings were formally initiated. `;
    t += `A total of ${meds.length} mediation hearing${meds.length > 1 ? 's were' : ' was'} scheduled in connection with this case. `;
    meds.forEach((m, i) => {
      const md = crFmtDate(m.hearing_date), mt = m.hearing_time ? crFmtTime(m.hearing_time) : '';
      const venue = m.venue || 'Barangay Hall';
      t += `The ${crOrdinal(i + 1)} hearing was held on ${md}${mt ? ' at ' + mt : ''} at ${venue}`;
      if (m.status === 'completed') {
        const ca = m.complainant_attended == 1 ? 'present' : 'absent';
        const ra = m.respondent_attended  == 1 ? 'present' : 'absent';
        t += `, with the complainant ${ca} and the respondent ${ra}. `;
        if (m.outcome && m.outcome.trim()) t += `The outcome of the session: ${m.outcome.trim()}. `;
        else t += `The session was completed. `;
      } else if (m.status === 'missed') {
        t += `. This hearing was recorded as missed due to non-appearance of one or both parties. `;
      } else if (m.status === 'scheduled') {
        t += `. This hearing is currently scheduled and pending. `;
      } else {
        t += `. `;
      }
      if (m.next_steps && m.next_steps.trim()) t += `Next steps noted: ${m.next_steps.trim()}. `;
    });
    t += '\n\n';
  }

  // ¶5 – Resolution
  const statusText = {
    pending_review: 'currently pending review by the Barangay',
    active: 'active and currently being processed by the Barangay',
    mediation_set: 'scheduled for Barangay mediation proceedings',
    resolved: 'resolved through the Barangay dispute resolution process',
    closed: 'formally closed',
    transferred: 'transferred to the appropriate authority for further proceedings',
    escalated: 'escalated to a higher authority',
    dismissed: 'dismissed by the Barangay',
    deliberation: 'currently under Barangay deliberation',
  };
  const nowFmt = crFmtDate(new Date().toISOString());
  t += `As of ${nowFmt}, Case Number ${b.case_number} is ${statusText[status] || status.replace(/_/g, ' ')}. `;
  if (status === 'resolved') {
    t += `The matter has been amicably settled between the parties, and the Barangay records reflect the resolution of the dispute. Appropriate documentation has been executed and placed on record.`;
  } else if (status === 'transferred' || status === 'escalated') {
    t += `The Barangay has forwarded all relevant documentation to the appropriate agency or authority for further proceedings. All parties have been duly notified of this action.`;
  } else if (status === 'closed') {
    t += `No further action is required at the Barangay level. All pertinent records have been duly archived.`;
  } else if (status === 'dismissed') {
    t += `The complaint was dismissed in accordance with Barangay procedures and applicable law. The parties were notified of the Barangay's decision.`;
  } else {
    t += `The Barangay shall continue to process and monitor this case in accordance with the applicable provisions of the Katarungang Pambarangay Law and other pertinent regulations.`;
  }

  return t;
}

function renderCaseReport(b) {
  const med          = b.mediation_sessions || [];
  const narrative    = generateNarrative(b);
  const now          = new Date();
  const dateGen      = now.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
  const timeGen      = now.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });
  const hasResp      = !!(b.respondent_name && b.respondent_name.trim());
  const levelMap     = { minor:'Minor', moderate:'Moderate', serious:'Serious', critical:'Critical' };
  const actionLabel  = CR_ACTION_LABELS[b.prescribed_action] || (b.prescribed_action ? b.prescribed_action.replace(/_/g,' ') : '—');
  const statusLabel  = CR_STATUS_LABELS[b.status] || (b.status || '—').replace(/_/g,' ');

  // Mediation table rows
  let medRows = '';
  if (med.length) {
    med.forEach((m, i) => {
      const ca = m.complainant_attended == 1 ? 'Present' : (m.complainant_attended == 0 ? 'Absent' : '—');
      const ra = m.respondent_attended  == 1 ? 'Present' : (m.respondent_attended  == 0 ? 'Absent' : '—');
      const ms = m.status ? m.status.charAt(0).toUpperCase() + m.status.slice(1) : '—';
      medRows += `<tr style="border-bottom:1px solid #ddd">
        <td style="padding:6px 10px;text-align:center">${i + 1}</td>
        <td style="padding:6px 10px">${crFmtDate(m.hearing_date)}</td>
        <td style="padding:6px 10px">${m.hearing_time ? crFmtTime(m.hearing_time) : '—'}</td>
        <td style="padding:6px 10px">${crEsc(m.venue || 'Barangay Hall')}</td>
        <td style="padding:6px 10px">${ms}</td>
        <td style="padding:6px 10px;text-align:center">${ca}</td>
        <td style="padding:6px 10px;text-align:center">${ra}</td>
        <td style="padding:6px 10px">${crEsc(m.outcome || '—')}</td>
      </tr>`;
    });
  } else {
    medRows = `<tr><td colspan="8" style="padding:12px;text-align:center;color:#777;font-style:italic">No mediation sessions recorded for this case.</td></tr>`;
  }

  // Narrative paragraphs
  const narrativeHtml = narrative.split('\n\n').filter(p => p.trim())
    .map(p => `<p style="margin:0 0 14px 0;text-indent:40px;text-align:justify;line-height:1.8">${crEsc(p.trim()).replace(/\n/g,' ')}</p>`)
    .join('');

  const bgyFull = BGY_INFO.municipality
    ? `Barangay ${crEsc(BGY_INFO.name)}, ${crEsc(BGY_INFO.municipality)}${BGY_INFO.province ? ', ' + crEsc(BGY_INFO.province) : ''}`
    : `Barangay ${crEsc(BGY_INFO.name)}`;

  document.getElementById('cr-document').innerHTML = `
    <div style="font-family:'Times New Roman',Georgia,serif;color:#111;font-size:11.5pt;line-height:1.6">

      <!-- Header -->
      <div style="text-align:center;border-bottom:3px double #1F4068;padding-bottom:20px;margin-bottom:20px">
        <div style="font-size:10pt;color:#555;letter-spacing:.1em;text-transform:uppercase">Republic of the Philippines</div>
        <div style="font-size:10pt;color:#555;margin-bottom:2px">${BGY_INFO.municipality ? crEsc(BGY_INFO.municipality) : ''}${BGY_INFO.province ? ', ' + crEsc(BGY_INFO.province) : ''}</div>
        <div style="font-size:11pt;font-weight:bold;color:#444;text-transform:uppercase;letter-spacing:.04em;margin-bottom:14px">Barangay ${crEsc(BGY_INFO.name)}</div>
        <div style="width:60px;height:60px;border-radius:50%;border:2px solid #1F4068;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;background:#EDF5FB">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="#1F4068" stroke-width="1.4" stroke-linecap="round" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="3" width="22" height="22" rx="2"/>
            <path d="M7 9h14M7 14h14M7 19h9"/>
          </svg>
        </div>
        <div style="font-size:19pt;font-weight:900;color:#0D1B2E;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Barangay Case Report</div>
        <div style="font-size:10pt;color:#666;font-style:italic;margin-bottom:8px">Official Record — Katarungang Pambarangay</div>
        <div style="font-size:10.5pt">Case No.: <strong style="font-size:13pt;color:#0D1B2E;letter-spacing:.06em">${crEsc(b.case_number)}</strong></div>
      </div>

      <!-- I. Case Information -->
      <div style="margin-bottom:22px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:10px">I. Case Information</div>
        <table style="width:100%;border-collapse:collapse">
          <tr><td style="padding:4px 0;width:42%;color:#555;font-size:10.5pt">Blotter / Case Number:</td><td style="font-weight:bold;font-size:10.5pt">${crEsc(b.case_number)}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Date &amp; Time Filed:</td><td style="font-size:10.5pt">${crFmtDate(b.created_at)}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Date of Incident:</td><td style="font-size:10.5pt">${crFmtDate(b.incident_date)}${b.incident_time && b.incident_time !== '00:00:00' ? ' at ' + crFmtTime(b.incident_time) : ''}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Nature of Complaint:</td><td style="font-size:10.5pt">${crEsc(b.incident_type || '—')}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Violation Level:</td><td style="font-size:10.5pt">${levelMap[b.violation_level] || crEsc(b.violation_level || '—')}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Case Status:</td><td style="font-size:10.5pt;font-weight:bold">${statusLabel}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Prescribed Action:</td><td style="font-size:10.5pt">${crEsc(actionLabel)}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Location of Incident:</td><td style="font-size:10.5pt">${crEsc(b.incident_location || '—')}</td></tr>
        </table>
      </div>

      <!-- II. Parties Involved -->
      <div style="margin-bottom:22px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:10px">II. Parties Involved</div>
        <table style="width:100%;border-collapse:collapse">
          <tr>
            <td style="vertical-align:top;width:50%;padding-right:16px;border-right:1px solid #e0e0e0">
              <div style="font-size:9.5pt;font-weight:bold;color:#555;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Complainant</div>
              <table style="width:100%;border-collapse:collapse">
                <tr><td style="padding:3px 0;width:45%;color:#555;font-size:10.5pt">Full Name:</td><td style="font-size:10.5pt;font-weight:bold">${crEsc(b.complainant_name || '—')}</td></tr>
                <tr><td style="padding:3px 0;color:#555;font-size:10.5pt">Contact No.:</td><td style="font-size:10.5pt">${crEsc(b.complainant_contact || '—')}</td></tr>
              </table>
            </td>
            <td style="vertical-align:top;padding-left:16px">
              <div style="font-size:9.5pt;font-weight:bold;color:#555;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Respondent</div>
              <table style="width:100%;border-collapse:collapse">
                <tr><td style="padding:3px 0;width:45%;color:#555;font-size:10.5pt">Full Name:</td><td style="font-size:10.5pt;font-weight:bold">${hasResp ? crEsc(b.respondent_name) : '<em style="color:#999;font-weight:normal">Not Identified</em>'}</td></tr>
                <tr><td style="padding:3px 0;color:#555;font-size:10.5pt">Contact No.:</td><td style="font-size:10.5pt">${hasResp && b.respondent_contact ? crEsc(b.respondent_contact) : '—'}</td></tr>
              </table>
            </td>
          </tr>
        </table>
      </div>

      <!-- III. Incident Details -->
      <div style="margin-bottom:22px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:10px">III. Incident Details</div>
        <table style="width:100%;border-collapse:collapse;margin-bottom:10px">
          <tr><td style="padding:4px 0;width:38%;color:#555;font-size:10.5pt">Date of Incident:</td><td style="font-size:10.5pt">${crFmtDate(b.incident_date)}</td></tr>
          ${b.incident_time && b.incident_time !== '00:00:00' ? `<tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Time of Incident:</td><td style="font-size:10.5pt">${crFmtTime(b.incident_time)}</td></tr>` : ''}
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Location:</td><td style="font-size:10.5pt">${crEsc(b.incident_location || '—')}</td></tr>
        </table>
        <div style="font-size:10pt;color:#555;font-weight:bold;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em">Incident Description (as reported):</div>
        <div style="font-size:10.5pt;line-height:1.75;background:#f7f8fa;padding:12px 16px;border-left:3px solid #1F4068;font-style:italic;color:#333">${crEsc(b.narrative || 'No description recorded.').replace(/\n/g,'<br>')}</div>
      </div>

      <!-- IV. Formal Case Narrative -->
      <div style="margin-bottom:22px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:12px">IV. Formal Case Narrative</div>
        <div style="font-size:10.5pt">${narrativeHtml}</div>
      </div>

      <!-- V. Actions Taken -->
      <div style="margin-bottom:22px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:10px">V. Actions Taken</div>
        <table style="width:100%;border-collapse:collapse">
          <tr><td style="padding:4px 0;width:38%;color:#555;font-size:10.5pt">Prescribed Action:</td><td style="font-size:10.5pt;font-weight:bold">${crEsc(actionLabel)}</td></tr>
          ${b.remarks && b.remarks.trim() ? `<tr><td style="padding:4px 0;color:#555;font-size:10.5pt;vertical-align:top">Officer's Remarks:</td><td style="font-size:10.5pt;text-align:justify">${crEsc(b.remarks)}</td></tr>` : ''}
        </table>
      </div>

      <!-- VI. Mediation Proceedings -->
      <div style="margin-bottom:22px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:10px">VI. Mediation Proceedings</div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;border:1px solid #ccc;font-size:10pt">
            <thead>
              <tr style="background:#1F4068;color:white">
                <th style="padding:7px 10px;text-align:center;white-space:nowrap">#</th>
                <th style="padding:7px 10px;text-align:left;white-space:nowrap">Date</th>
                <th style="padding:7px 10px;text-align:left;white-space:nowrap">Time</th>
                <th style="padding:7px 10px;text-align:left">Venue</th>
                <th style="padding:7px 10px;text-align:left">Status</th>
                <th style="padding:7px 10px;text-align:center">Comp.</th>
                <th style="padding:7px 10px;text-align:center">Resp.</th>
                <th style="padding:7px 10px;text-align:left">Outcome / Notes</th>
              </tr>
            </thead>
            <tbody>${medRows}</tbody>
          </table>
        </div>
      </div>

      <!-- VII. Resolution / Disposition -->
      <div style="margin-bottom:22px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:10px">VII. Resolution / Disposition</div>
        <table style="width:100%;border-collapse:collapse">
          <tr><td style="padding:4px 0;width:38%;color:#555;font-size:10.5pt">Final Case Status:</td><td style="font-size:10.5pt;font-weight:bold">${statusLabel}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Final Action:</td><td style="font-size:10.5pt">${crEsc(actionLabel)}</td></tr>
          <tr><td style="padding:4px 0;color:#555;font-size:10.5pt">Last Updated:</td><td style="font-size:10.5pt">${crFmtDate(b.updated_at || b.created_at)}</td></tr>
        </table>
      </div>

      <!-- VIII. Certification -->
      <div style="margin-bottom:10px">
        <div style="font-size:10.5pt;font-weight:bold;color:#0D1B2E;text-transform:uppercase;letter-spacing:.08em;border-bottom:1.5px solid #1F4068;padding-bottom:4px;margin-bottom:14px">VIII. Certification</div>
        <p style="font-size:10.5pt;text-align:justify;line-height:1.8;margin-bottom:0">
          I hereby certify that the foregoing is a true and accurate record of the blotter case filed before ${bgyFull}, and that all information contained herein has been duly recorded and verified in accordance with the procedures of the Katarungang Pambarangay and applicable laws of the Republic of the Philippines.
        </p>
        <div style="display:flex;justify-content:space-between;margin-top:44px;gap:24px">
          <div style="flex:1;text-align:center">
            <div style="border-top:1.5px solid #111;padding-top:6px;margin-top:0">
              <div style="font-size:10.5pt;font-weight:bold;text-transform:uppercase">${crEsc(CURRENT_OFFICER)}</div>
              <div style="font-size:9.5pt;color:#555">Prepared by / Recording Officer</div>
            </div>
          </div>
          <div style="flex:1;text-align:center">
            <div style="border-top:1.5px solid #111;padding-top:6px;margin-top:0">
              <div style="font-size:10.5pt;font-weight:bold;text-transform:uppercase">${BGY_INFO.captain_name ? crEsc(BGY_INFO.captain_name) : '____________________________'}</div>
              <div style="font-size:9.5pt;color:#555">Barangay Captain</div>
            </div>
          </div>
          <div style="flex:1;text-align:center">
            <div style="border-top:1.5px solid #111;padding-top:6px;margin-top:0">
              <div style="font-size:10.5pt;font-weight:bold">____________________________</div>
              <div style="font-size:9.5pt;color:#555">Barangay Secretary</div>
            </div>
          </div>
        </div>
        <div style="text-align:center;margin-top:20px;font-size:9pt;color:#888;border-top:1px dashed #ccc;padding-top:8px">
          Report generated on ${dateGen} at ${timeGen} &nbsp;·&nbsp; VOICE Barangay Management System
        </div>
      </div>

    </div>
  `;

  document.getElementById('cr-loading').style.display = 'none';
  document.getElementById('cr-document').style.display = 'block';
}

function printCaseReport() {
  const b = window._currentBlotter;
  if (!b) return;
  const doc = document.getElementById('cr-document');
  if (!doc || doc.style.display === 'none') {
    showToast('Please wait for the report to load first.', 'error');
    return;
  }
  const w = window.open('', '_blank', 'width=950,height=800');
  if (!w) { showToast('Allow pop-ups to print the report.', 'error'); return; }
  w.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Case Report — ${b.case_number}</title>
  <style>
    @page { size: A4; margin: 18mm 22mm; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Times New Roman', Georgia, serif; font-size: 11.5pt; color: #111; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    p { margin-bottom: 14pt; }
    table { border-collapse: collapse; width: 100%; }
    thead { background: #1F4068 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @media print { body { margin: 0; } }
  </style>
</head>
<body>${doc.innerHTML}</body>
</html>`);
  w.document.close();
  w.focus();
  setTimeout(() => { w.print(); }, 600);
}

function exportCaseReportPDF() {
  printCaseReport();
}

// ─── Auto-status preview ──────────────────────────────────────────────────────

// Show auto-status preview when action dropdown changes
function previewAutoStatus(action) {
  const hint  = document.getElementById('auto-status-hint');
  const label = document.getElementById('auto-status-label');
  const derived = AUTO_STATUS[action];
  if (derived && action) {
    label.textContent = STATUS_LABELS[derived] || derived;
    hint.style.display = '';
    // Also update the status dropdown to match
    const sel = document.getElementById('p-status');
    if (sel) sel.value = derived;
  } else {
    hint.style.display = 'none';
  }
}
</script>

<style>
.panel-section-lbl {
  font-size:11px;font-weight:700;color:var(--ink-400);
  letter-spacing:.07em;text-transform:uppercase;margin-bottom:8px;
}
</style>
