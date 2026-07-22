<?php
// community-portal/pages/notices.php
$uid   = (int)$user['id'];
$bid   = (int)$user['barangay_id'];
$uname = $user['name'] ?? '';

// Name parts for loose matching (handles "Dela Cruz, Juan Santos" vs "Dela Cruz Juan Santos")
$name_parts = array_filter(preg_split('/[\s,]+/', $uname), fn($p) => strlen($p) > 2);
$name_likes = implode(' AND ', array_map(fn($p) => "b.respondent_name LIKE '%" . addslashes($p) . "%'", $name_parts));
$name_likes_plain = $name_likes ?: "1=0"; // safe fallback

// ── Formal Penalties — as RESPONDENT ─────────────────────────
$penalties_as_respondent = [];
try {
    $penalties_as_respondent = $pdo->query("
        SELECT p.id, p.reason, p.amount, p.community_hours, p.due_date,
               p.status AS penalty_status, p.missed_party, p.created_at,
               b.id AS blotter_id, b.case_number, b.incident_type, b.complainant_name,
               b.complainant_missed, b.respondent_missed
        FROM penalties p
        JOIN blotters b ON b.id = p.blotter_id
        WHERE b.barangay_id = $bid
          AND p.missed_party IN ('respondent','both')
          AND (b.respondent_user_id = $uid OR ($name_likes_plain))
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('penalties_resp: '.$e->getMessage()); }

// ── Formal Penalties — as COMPLAINANT ────────────────────────
$penalties_as_complainant = [];
try {
    $penalties_as_complainant = $pdo->query("
        SELECT p.id, p.reason, p.amount, p.community_hours, p.due_date,
               p.status AS penalty_status, p.missed_party, p.created_at,
               b.id AS blotter_id, b.case_number, b.incident_type, b.respondent_name,
               b.complainant_missed, b.respondent_missed
        FROM penalties p
        JOIN blotters b ON b.id = p.blotter_id
        WHERE b.barangay_id = $bid
          AND b.complainant_user_id = $uid
          AND p.missed_party IN ('complainant','both')
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('penalties_comp: '.$e->getMessage()); }

// ── Event-derived sanctions ───────────────────────────────────
$event_sanctions = [];
try {
    // 1. Missed hearings — as RESPONDENT
    $ev = $pdo->query("
        SELECT ms.id AS med_id, ms.hearing_date, ms.hearing_time, ms.venue,
               ms.status, ms.no_show_by, ms.missed_session, ms.action_type,
               b.id AS blotter_id, b.case_number, b.incident_type,
               b.complainant_name, b.status AS blotter_status,
               b.respondent_missed,
               'respondent' AS my_role, 'missed_hearing' AS event_type
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid
          AND ms.missed_session = 1
          AND ms.no_show_by IN ('respondent','both')
          AND (b.respondent_user_id = $uid OR ($name_likes_plain))
        ORDER BY ms.hearing_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ev as $row) $event_sanctions[] = $row;

    // 2. Missed hearings — as COMPLAINANT
    $ev = $pdo->query("
        SELECT ms.id AS med_id, ms.hearing_date, ms.hearing_time, ms.venue,
               ms.status, ms.no_show_by, ms.missed_session, ms.action_type,
               b.id AS blotter_id, b.case_number, b.incident_type,
               b.respondent_name, b.status AS blotter_status,
               b.complainant_missed,
               'complainant' AS my_role, 'missed_hearing' AS event_type
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE b.barangay_id = $bid
          AND b.complainant_user_id = $uid
          AND ms.missed_session = 1
          AND ms.no_show_by IN ('complainant','both')
        ORDER BY ms.hearing_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ev as $row) $event_sanctions[] = $row;

    // 3. Cases referred/escalated — as RESPONDENT
    $ev = $pdo->query("
        SELECT b.id AS blotter_id, b.case_number, b.incident_type,
               b.prescribed_action, b.status AS blotter_status,
               b.complainant_name, b.updated_at AS event_date,
               b.respondent_missed,
               'respondent' AS my_role, 'case_referred' AS event_type
        FROM blotters b
        WHERE b.barangay_id = $bid
          AND (b.respondent_user_id = $uid OR ($name_likes_plain))
          AND (
            b.prescribed_action IN ('refer_police','refer_vawc','escalate_municipality')
            OR b.status IN ('escalated','cfa_issued','transferred')
          )
        ORDER BY b.updated_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ev as $row) $event_sanctions[] = $row;

    // 4. Cases referred/escalated — as COMPLAINANT
    $ev = $pdo->query("
        SELECT b.id AS blotter_id, b.case_number, b.incident_type,
               b.prescribed_action, b.status AS blotter_status,
               b.respondent_name, b.updated_at AS event_date,
               b.complainant_missed,
               'complainant' AS my_role, 'case_referred' AS event_type
        FROM blotters b
        WHERE b.barangay_id = $bid
          AND b.complainant_user_id = $uid
          AND (
            b.prescribed_action IN ('refer_police','refer_vawc','escalate_municipality')
            OR b.status IN ('escalated','cfa_issued','transferred')
          )
        ORDER BY b.updated_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ev as $row) $event_sanctions[] = $row;

    // 5. Dismissed cases — as COMPLAINANT
    $ev = $pdo->query("
        SELECT b.id AS blotter_id, b.case_number, b.incident_type,
               b.prescribed_action, b.status AS blotter_status,
               b.respondent_name, b.updated_at AS event_date,
               b.complainant_missed,
               'complainant' AS my_role, 'case_dismissed' AS event_type
        FROM blotters b
        WHERE b.barangay_id = $bid
          AND b.complainant_user_id = $uid
          AND b.status = 'dismissed'
        ORDER BY b.updated_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ev as $row) $event_sanctions[] = $row;

    // 6. Dismissed cases — as RESPONDENT
    $ev = $pdo->query("
        SELECT b.id AS blotter_id, b.case_number, b.incident_type,
               b.prescribed_action, b.status AS blotter_status,
               b.complainant_name, b.updated_at AS event_date,
               b.respondent_missed,
               'respondent' AS my_role, 'case_dismissed' AS event_type
        FROM blotters b
        WHERE b.barangay_id = $bid
          AND (b.respondent_user_id = $uid OR ($name_likes_plain))
          AND b.status = 'dismissed'
        ORDER BY b.updated_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ev as $row) $event_sanctions[] = $row;

} catch (PDOException $e) {
    error_log('notices.php event_sanctions: ' . $e->getMessage());
}

$penalty_chip = ['pending'=>'ch-amber','paid'=>'ch-emerald','waived'=>'ch-slate','overdue'=>'ch-rose'];

$referred_labels = [
    'refer_police'          => 'Referred to Police (PNP)',
    'refer_vawc'            => 'Referred to VAWC Desk',
    'refer_dswd'            => 'Referred to DSWD / WCPD',
    'refer_nbi'             => 'Referred to NBI',
    'refer_attorney'        => 'Referred to Attorney / PAO',
    'escalate_municipality' => 'Escalated to Municipality',
    'certificate_to_file'   => 'Certificate to File Action Issued',
];

// ══════════════════════════════════════════════════════════════
// Normalize everything in the Sanctions tab into ONE row shape so
// it can render as a single clean table instead of stacked, bespoke
// card sections per category.
// ══════════════════════════════════════════════════════════════
$sanction_rows = [];

foreach ($penalties_as_respondent as $p) {
    $is_overdue = ($p['penalty_status'] === 'pending' && !empty($p['due_date']) && $p['due_date'] < date('Y-m-d'));
    $sanction_rows[] = [
        'type' => 'penalty', 'type_label' => 'Formal Penalty',
        'role' => 'respondent', 'role_label' => 'Respondent', 'severity' => 'rose',
        'title' => $p['reason'],
        'case_number' => $p['case_number'], 'incident_type' => $p['incident_type'],
        'counterpart' => $p['complainant_name'], 'counterpart_label' => 'Filed by',
        'amount' => (float)$p['amount'], 'hours' => (int)$p['community_hours'],
        'status_label' => $is_overdue ? 'Overdue' : ucfirst($p['penalty_status']),
        'status_chip'  => $is_overdue ? 'ch-rose' : ($penalty_chip[$p['penalty_status']] ?? 'ch-slate'),
        'date' => $p['created_at'], 'due_date' => $p['due_date'],
        'detail' => $is_overdue
            ? 'This penalty is past its due date. Please settle it at the Barangay Hall or contact your barangay officer immediately.'
            : 'A formal penalty was issued against you as the respondent in this case. Settle at the Barangay Hall on or before the due date.',
        'blotter_id' => $p['blotter_id'],
        'search' => strtolower(trim(($p['case_number']??'').' '.($p['incident_type']??'').' '.$p['reason'].' '.($p['complainant_name']??''))),
    ];
}
foreach ($penalties_as_complainant as $p) {
    $is_overdue = ($p['penalty_status'] === 'pending' && !empty($p['due_date']) && $p['due_date'] < date('Y-m-d'));
    $sanction_rows[] = [
        'type' => 'penalty', 'type_label' => 'Formal Penalty',
        'role' => 'complainant', 'role_label' => 'Complainant', 'severity' => 'amber',
        'title' => $p['reason'],
        'case_number' => $p['case_number'], 'incident_type' => $p['incident_type'],
        'counterpart' => $p['respondent_name'], 'counterpart_label' => 'Against',
        'amount' => (float)$p['amount'], 'hours' => (int)$p['community_hours'],
        'status_label' => $is_overdue ? 'Overdue' : ucfirst($p['penalty_status']),
        'status_chip'  => $is_overdue ? 'ch-rose' : ($penalty_chip[$p['penalty_status']] ?? 'ch-slate'),
        'date' => $p['created_at'], 'due_date' => $p['due_date'],
        'detail' => 'A formal penalty was issued to you in your role as complainant in this case. Settle at the Barangay Hall on or before the due date.',
        'blotter_id' => $p['blotter_id'],
        'search' => strtolower(trim(($p['case_number']??'').' '.($p['incident_type']??'').' '.$p['reason'].' '.($p['respondent_name']??''))),
    ];
}

$resp_consequences = [
    1 => ['1st missed session', 'Hearing rescheduled. A final warning has been issued. A second no-show will allow the complainant to bring this case to court (Certification to File Action).'],
    2 => ['2nd missed session — CFA issued', 'A Certification to File Action (CFA) has been issued to the complainant. They may now elevate this case to court. Contact your barangay immediately.'],
];
$comp_consequences = [
    1 => ['1st missed session', 'Hearing rescheduled. This is your first absence. A second no-show may result in your case being dismissed by the barangay.'],
    2 => ['2nd missed session — case dismissed', 'Your case has been dismissed due to repeated absence. You are barred from filing the same case in court (Sec. 412, Local Government Code).'],
];

foreach ($event_sanctions as $e) {
    if ($e['event_type'] === 'missed_hearing' && $e['my_role'] === 'respondent') {
        $miss = (int)($e['respondent_missed'] ?? 0);
        $rule = $resp_consequences[min($miss, 2)] ?? null;
        $sanction_rows[] = [
            'type' => 'missed_hearing', 'type_label' => 'Missed Hearing',
            'role' => 'respondent', 'role_label' => 'Respondent', 'severity' => $miss >= 2 ? 'rose' : 'amber',
            'title' => $rule ? $rule[0] : 'Missed mediation hearing',
            'case_number' => $e['case_number'], 'incident_type' => $e['incident_type'],
            'counterpart' => $e['complainant_name'] ?? null, 'counterpart_label' => 'Filed by',
            'amount' => null, 'hours' => null,
            'status_label' => 'No-Show', 'status_chip' => $miss >= 2 ? 'ch-rose' : 'ch-amber',
            'date' => $e['hearing_date'],
            'detail' => $rule ? $rule[1] : 'You were required to attend this mediation hearing but did not appear. Contact your barangay officer if you had a valid reason.',
            'blotter_id' => $e['blotter_id'],
            'search' => strtolower(trim(($e['case_number']??'').' '.($e['incident_type']??'').' missed hearing no-show '.($e['complainant_name']??''))),
        ];
    } elseif ($e['event_type'] === 'missed_hearing' && $e['my_role'] === 'complainant') {
        $miss = (int)($e['complainant_missed'] ?? 0);
        $rule = $comp_consequences[min($miss, 2)] ?? null;
        $sanction_rows[] = [
            'type' => 'missed_hearing', 'type_label' => 'Missed Hearing',
            'role' => 'complainant', 'role_label' => 'Complainant', 'severity' => $miss >= 2 ? 'rose' : 'amber',
            'title' => $rule ? $rule[0] : 'You missed a mediation hearing',
            'case_number' => $e['case_number'], 'incident_type' => $e['incident_type'],
            'counterpart' => $e['respondent_name'] ?? null, 'counterpart_label' => 'Against',
            'amount' => null, 'hours' => null,
            'status_label' => 'No-Show', 'status_chip' => $miss >= 2 ? 'ch-rose' : 'ch-amber',
            'date' => $e['hearing_date'],
            'detail' => $rule ? $rule[1] : 'As the complainant, your attendance at hearings is required. Repeated no-shows may result in your case being dismissed under the Katarungang Pambarangay Law.',
            'blotter_id' => $e['blotter_id'],
            'search' => strtolower(trim(($e['case_number']??'').' '.($e['incident_type']??'').' missed hearing no-show '.($e['respondent_name']??''))),
        ];
    } elseif ($e['event_type'] === 'case_referred' && $e['my_role'] === 'respondent') {
        $label = $referred_labels[$e['prescribed_action']] ?? 'Case Referred to Authorities';
        $sanction_rows[] = [
            'type' => 'case_referred', 'type_label' => 'Referred / Escalated',
            'role' => 'respondent', 'role_label' => 'Respondent', 'severity' => 'rose',
            'title' => $label,
            'case_number' => $e['case_number'], 'incident_type' => $e['incident_type'],
            'counterpart' => $e['complainant_name'] ?? null, 'counterpart_label' => 'Filed by',
            'amount' => null, 'hours' => null,
            'status_label' => 'Referred', 'status_chip' => 'ch-rose',
            'date' => $e['event_date'],
            'detail' => 'This case has been referred beyond the barangay level. You may be contacted or summoned. Contact your barangay officer for guidance.',
            'blotter_id' => $e['blotter_id'],
            'search' => strtolower(trim(($e['case_number']??'').' '.($e['incident_type']??'').' '.$label.' referred escalated '.($e['complainant_name']??''))),
        ];
    } elseif ($e['event_type'] === 'case_referred' && $e['my_role'] === 'complainant') {
        $label = $referred_labels[$e['prescribed_action']] ?? 'Case Referred to Authorities';
        $sanction_rows[] = [
            'type' => 'case_referred', 'type_label' => 'Referred / Escalated',
            'role' => 'complainant', 'role_label' => 'Complainant', 'severity' => 'teal',
            'title' => $label,
            'case_number' => $e['case_number'], 'incident_type' => $e['incident_type'],
            'counterpart' => $e['respondent_name'] ?? null, 'counterpart_label' => 'Against',
            'amount' => null, 'hours' => null,
            'status_label' => 'Escalated', 'status_chip' => 'ch-teal',
            'date' => $e['event_date'],
            'detail' => 'Your case has been escalated beyond the barangay level. A barangay officer will update you on next steps.',
            'blotter_id' => $e['blotter_id'],
            'search' => strtolower(trim(($e['case_number']??'').' '.($e['incident_type']??'').' '.$label.' referred escalated '.($e['respondent_name']??''))),
        ];
    } elseif ($e['event_type'] === 'case_dismissed' && $e['my_role'] === 'complainant') {
        $miss = (int)($e['complainant_missed'] ?? 0);
        $sanction_rows[] = [
            'type' => 'case_dismissed', 'type_label' => 'Case Dismissed',
            'role' => 'complainant', 'role_label' => 'Complainant', 'severity' => 'slate',
            'title' => 'Case dismissed',
            'case_number' => $e['case_number'], 'incident_type' => $e['incident_type'],
            'counterpart' => $e['respondent_name'] ?? null, 'counterpart_label' => 'Against',
            'amount' => null, 'hours' => null,
            'status_label' => 'Dismissed', 'status_chip' => 'ch-slate',
            'date' => $e['event_date'],
            'detail' => $miss >= 2
                ? "Your case was dismissed because you failed to appear at $miss scheduled mediation hearings. Under Sec. 412 of the Local Government Code, you are barred from filing the same case in court."
                : 'This case was dismissed by the barangay officer. If you believe the dismissal is incorrect, you may request a review.',
            'blotter_id' => $e['blotter_id'],
            'search' => strtolower(trim(($e['case_number']??'').' '.($e['incident_type']??'').' dismissed '.($e['respondent_name']??''))),
        ];
    } elseif ($e['event_type'] === 'case_dismissed' && $e['my_role'] === 'respondent') {
        $sanction_rows[] = [
            'type' => 'case_dismissed', 'type_label' => 'Case Dismissed',
            'role' => 'respondent', 'role_label' => 'Respondent', 'severity' => 'teal',
            'title' => 'Case dismissed in your favor',
            'case_number' => $e['case_number'], 'incident_type' => $e['incident_type'],
            'counterpart' => $e['complainant_name'] ?? null, 'counterpart_label' => 'Filed by',
            'amount' => null, 'hours' => null,
            'status_label' => 'Dismissed', 'status_chip' => 'ch-emerald',
            'date' => $e['event_date'],
            'detail' => 'This case has been dismissed. No further action is required from you at this time. Keep this record for reference in case the same matter is raised again.',
            'blotter_id' => $e['blotter_id'],
            'search' => strtolower(trim(($e['case_number']??'').' '.($e['incident_type']??'').' dismissed '.($e['complainant_name']??''))),
        ];
    }
}

// Sort newest first
usort($sanction_rows, fn($a, $b) => strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01'));

$count_sanctions = count($sanction_rows);

$severity_chip = ['rose'=>'ch-rose','amber'=>'ch-amber','teal'=>'ch-teal','slate'=>'ch-slate'];
?>

<style>
.ntab-bar { display:flex; gap:0; border-bottom:2px solid var(--ink-100); margin-bottom:18px; }
.ntab { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; font-size:13px; font-weight:600;
        color:var(--ink-400); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px;
        transition:color .12s,border-color .12s; background:none; border-top:none; border-left:none; border-right:none;
        font-family:inherit; white-space:nowrap; }
.ntab:hover { color:var(--ink-700); }
.ntab.active { color:var(--teal-600); border-bottom-color:var(--teal-600); }
.ntab-badge { display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px;
              padding:0 5px; border-radius:20px; font-size:10px; font-weight:700; background:var(--ink-50); color:var(--ink-400); }
.ntab-badge.attn { background:var(--amber-50); color:var(--amber-600); }
.ntab-panel { display:none; }
.ntab-panel.active { display:block; }
.notices-filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; align-items:center; }
.nf-count { font-size:12px; color:var(--ink-400); white-space:nowrap; align-self:center; }
.role-pill { display:inline-flex; align-items:center; font-size:10px; font-weight:700; padding:2px 8px;
             border-radius:20px; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
.rp-respondent  { background:var(--rose-50); color:var(--rose-600); border:1px solid var(--rose-100); }
.rp-complainant { background:var(--teal-50); color:var(--teal-700); border:1px solid var(--teal-100); }
tr.unread-row td:first-child { box-shadow: inset 3px 0 0 var(--amber-400); }
</style>

<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Sanctions &amp; Penalties</h2>
    <p>Penalties and case events issued to you by your barangay</p>
  </div>
</div>

<?php if ($count_sanctions === 0): ?>
<div class="empty-state">
  <div class="es-icon">OK</div>
  <div class="es-title">No sanctions on record</div>
  <div class="es-sub">Penalties and case events issued to you will appear here</div>
</div>

<?php else: ?>

<div id="panel-sanctions" class="ntab-panel active">

  <div class="notices-filter-bar">
    <div class="inp-icon" style="flex:1;min-width:180px;max-width:280px">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="6" cy="6" r="4"/><path d="M10 10l2.5 2.5"/></svg>
      <input type="search" id="sanction-search" placeholder="Search case no., type, party…" oninput="filterSanctions(true)">
    </div>
    <select id="sanction-filter-role" onchange="filterSanctions(true)" style="width:auto;min-width:150px">
      <option value="">All Roles</option>
      <option value="complainant">As Complainant</option>
      <option value="respondent">As Respondent</option>
    </select>
    <select id="sanction-filter-type" onchange="filterSanctions(true)" style="width:auto;min-width:170px">
      <option value="">All Types</option>
      <option value="penalty">Formal Penalties</option>
      <option value="missed_hearing">Missed Hearings</option>
      <option value="case_referred">Referred / Escalated</option>
      <option value="case_dismissed">Dismissed Cases</option>
    </select>
    <button class="btn btn-outline btn-sm" onclick="clearSanctionFilters()">✕ Clear</button>
    <span id="sanction-count" class="nf-count"></span>
  </div>

  <div id="sanction-no-results" style="display:none">
    <div class="empty-state"><div class="es-icon">🔍</div><div class="es-title">No sanctions match</div><div class="es-sub">Try adjusting the search or filter options.</div></div>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Case</th><th>Type</th><th>Role</th><th>Amount / Consequence</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody id="sanction-tbody">
        <?php foreach ($sanction_rows as $i => $r): ?>
          <tr class="sanction-row"
              data-role="<?= e($r['role']) ?>"
              data-type="<?= e($r['type']) ?>"
              data-search="<?= e($r['search']) ?>">
            <td>
              <div class="td-mono"><?= e($r['case_number'] ?: '—') ?></div>
              <div style="font-size:11px;color:var(--ink-400)"><?= e($r['incident_type'] ?: '') ?></div>
            </td>
            <td style="font-size:12px"><?= e($r['type_label']) ?></td>
            <td><span class="role-pill <?= $r['role']==='respondent'?'rp-respondent':'rp-complainant' ?>"><?= e($r['role_label']) ?></span></td>
            <td>
              <?php if ($r['amount'] !== null): ?>
                <div style="font-weight:700;color:var(--rose-600)">₱<?= number_format($r['amount'], 2) ?></div>
                <?php if ($r['hours']): ?><div style="font-size:11px;color:var(--ink-500)">+ <?= (int)$r['hours'] ?> hrs service</div><?php endif; ?>
              <?php else: ?>
                <div style="font-size:12px;color:var(--ink-700);max-width:260px"><?= e($r['title']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="chip <?= e($r['status_chip']) ?>"><?= e($r['status_label']) ?></span></td>
            <td style="font-size:12px;color:var(--ink-400);white-space:nowrap"><?= $r['date'] ? date('M j, Y', strtotime($r['date'])) : '—' ?></td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:nowrap">
                <button class="act-btn" onclick="viewSanctionDetail(<?= $i ?>)">Details</button>
                <?php if ($r['blotter_id']): ?><button class="act-btn" onclick="viewBlotter(<?= (int)$r['blotter_id'] ?>)">Case</button><?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="sanction-pager" class="card-foot" style="display:none"></div>
  </div>

</div>

<!-- Sanction detail modal -->
<div class="modal-overlay" id="modal-detail">
  <div class="modal" style="width:600px">
    <div class="modal-hdr"><span class="modal-title" id="detail-title">Details</span><button class="modal-x" onclick="closeModal('modal-detail')">×</button></div>
    <div class="modal-body">
      <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap" id="detail-chips"></div>
      <div class="dr"><span class="dr-lbl">Case</span><span class="dr-val" id="detail-case"></span></div>
      <div class="dr" id="detail-counterpart-row"><span class="dr-lbl" id="detail-counterpart-label"></span><span class="dr-val" id="detail-counterpart"></span></div>
      <div class="dr" id="detail-amount-row"><span class="dr-lbl">Amount</span><span class="dr-val" id="detail-amount" style="font-weight:700;color:var(--rose-600)"></span></div>
      <div class="dr" id="detail-due-row"><span class="dr-lbl">Due Date</span><span class="dr-val" id="detail-due"></span></div>
      <div class="dr"><span class="dr-lbl">Date</span><span class="dr-val" id="detail-date"></span></div>
      <div id="detail-text-wrap" style="margin-top:14px;padding:12px 14px;border-radius:var(--r-md);background:var(--surface);border:1px solid var(--ink-100)">
        <p id="detail-text" style="font-size:13px;line-height:1.7;color:var(--ink-700);margin:0;white-space:pre-wrap"></p>
      </div>
      <div id="detail-hearing-row" style="display:none;margin-top:10px;font-size:12px;color:var(--teal-700);background:var(--teal-50);border:1px solid var(--teal-100);border-radius:var(--r-sm);padding:8px 10px"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('modal-detail')">Close</button>
      <button class="btn btn-primary" id="detail-view-case-btn">View Case</button>
    </div>
  </div>
</div>


<script>
const SANCTION_ROWS = <?= json_encode(array_values($sanction_rows)) ?>;

var sanctionPageSize = 10, sanctionPage = 1;

function renderPager(elId, page, total, pageSize, fnName) {
  var el = document.getElementById(elId);
  if (!el) return;
  var pages = Math.max(1, Math.ceil(total / pageSize));
  if (total <= pageSize) { el.style.display = 'none'; el.innerHTML = ''; return; }
  page = Math.min(Math.max(1, page), pages);
  var start = ((page - 1) * pageSize) + 1;
  var end = Math.min(page * pageSize, total);
  var first = Math.max(1, page - 2), last = Math.min(pages, page + 2);
  var html = '<div class="pager"><span class="pager-info">Showing ' + start + '-' + end + ' of ' + total + '</span><div class="pager-btns">';
  if (page > 1) html += '<button type="button" class="btn btn-outline btn-sm" onclick="' + fnName + '(' + (page - 1) + ')">Prev</button>';
  for (var i = first; i <= last; i++) html += '<button type="button" class="btn ' + (i === page ? 'btn-primary' : 'btn-outline') + ' btn-sm" onclick="' + fnName + '(' + i + ')">' + i + '</button>';
  if (page < pages) html += '<button type="button" class="btn btn-outline btn-sm" onclick="' + fnName + '(' + (page + 1) + ')">Next</button>';
  html += '</div></div>';
  el.innerHTML = html; el.style.display = '';
}

function filterSanctions(resetPage) {
  if (resetPage) sanctionPage = 1;
  var search = (document.getElementById('sanction-search')?.value || '').trim().toLowerCase();
  var role   = document.getElementById('sanction-filter-role')?.value || '';
  var type   = document.getElementById('sanction-filter-type')?.value || '';
  var rows = document.querySelectorAll('#sanction-tbody .sanction-row');
  var matched = [];
  rows.forEach(row => {
    var m = (!search || (row.dataset.search||'').includes(search)) && (!role || row.dataset.role===role) && (!type || row.dataset.type===type);
    if (m) matched.push(row);
  });
  var pages = Math.max(1, Math.ceil(matched.length / sanctionPageSize));
  sanctionPage = Math.min(Math.max(1, sanctionPage), pages);
  var start = (sanctionPage-1)*sanctionPageSize, end = start+sanctionPageSize;
  rows.forEach(row => { var idx = matched.indexOf(row); row.style.display = (idx>=start && idx<end) ? '' : 'none'; });
  document.getElementById('sanction-no-results').style.display = (rows.length>0 && matched.length===0) ? '' : 'none';
  renderPager('sanction-pager', sanctionPage, matched.length, sanctionPageSize, 'changeSanctionPage');
  var countEl = document.getElementById('sanction-count');
  var hasFilter = search || role || type;
  countEl.textContent = hasFilter ? 'Showing ' + matched.length + ' of ' + rows.length : '';
}
function changeSanctionPage(p) { sanctionPage = p; filterSanctions(false); }
function clearSanctionFilters() {
  ['sanction-search','sanction-filter-role','sanction-filter-type'].forEach(id => { var el = document.getElementById(id); if (el) el.value=''; });
  filterSanctions(true);
}

const SEVERITY_CHIP = { rose:'ch-rose', amber:'ch-amber', teal:'ch-teal', slate:'ch-slate' };

function viewSanctionDetail(i) {
  const r = SANCTION_ROWS[i];
  document.getElementById('detail-title').textContent = r.title;
  document.getElementById('detail-chips').innerHTML =
    '<span class="chip ' + (SEVERITY_CHIP[r.severity]||'ch-slate') + '">' + r.type_label + '</span>' +
    '<span class="role-pill ' + (r.role==='respondent'?'rp-respondent':'rp-complainant') + '">' + r.role_label + '</span>' +
    '<span class="chip ' + r.status_chip + '">' + r.status_label + '</span>';
  document.getElementById('detail-case').textContent = (r.case_number||'—') + (r.incident_type ? ' · ' + r.incident_type : '');

  const cpRow = document.getElementById('detail-counterpart-row');
  if (r.counterpart) {
    cpRow.style.display = '';
    document.getElementById('detail-counterpart-label').textContent = r.counterpart_label;
    document.getElementById('detail-counterpart').textContent = r.counterpart;
  } else cpRow.style.display = 'none';

  const amtRow = document.getElementById('detail-amount-row');
  if (r.amount !== null) {
    amtRow.style.display = '';
    document.getElementById('detail-amount').textContent = '₱' + Number(r.amount).toLocaleString(undefined,{minimumFractionDigits:2}) + (r.hours ? ' + ' + r.hours + ' hrs community service' : '');
  } else amtRow.style.display = 'none';

  const dueRow = document.getElementById('detail-due-row');
  if (r.due_date) { dueRow.style.display = ''; document.getElementById('detail-due').textContent = new Date(r.due_date).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}); }
  else dueRow.style.display = 'none';

  document.getElementById('detail-date').textContent = r.date ? new Date(r.date).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—';
  document.getElementById('detail-text').textContent = r.detail || '';
  document.getElementById('detail-hearing-row').style.display = 'none';

  const btn = document.getElementById('detail-view-case-btn');
  if (r.blotter_id) { btn.style.display = ''; btn.onclick = () => { closeModal('modal-detail'); viewBlotter(r.blotter_id); }; }
  else btn.style.display = 'none';

  openModal('modal-detail');
}


filterSanctions(false);
</script>
<?php endif; ?>
