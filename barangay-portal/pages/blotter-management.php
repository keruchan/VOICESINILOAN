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

  document.getElementById('panel-body').innerHTML = `
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
      ${levelChip(b.violation_level)} ${statusChip(b.status)}
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
