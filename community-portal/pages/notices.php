<?php
// community-portal/pages/notices.php
$uid   = (int)$user['id'];
$bid   = (int)$user['barangay_id'];
$uname = $user['name'] ?? '';

// Name parts for loose matching (handles "Dela Cruz, Juan Santos" vs "Dela Cruz Juan Santos")
$name_parts = array_filter(preg_split('/[\s,]+/', $uname), fn($p) => strlen($p) > 2);
$name_likes = implode(' AND ', array_map(fn($p) => "b.respondent_name LIKE '%" . addslashes($p) . "%'", $name_parts));
$name_likes_plain = $name_likes ?: "1=0"; // safe fallback

// ── Notifications (all — both complainant and respondent roles) ─
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            pn.id, pn.blotter_id, pn.notification_type, pn.recipient_type,
            pn.subject, pn.message, pn.channel, pn.status,
            pn.sent_at, pn.read_at, pn.created_at,
            b.case_number, b.incident_type, b.violation_level,
            b.status AS blotter_status, b.complainant_name, b.respondent_name,
            ms.hearing_date, ms.hearing_time, ms.venue
        FROM party_notifications pn
        LEFT JOIN blotters b             ON b.id  = pn.blotter_id
        LEFT JOIN mediation_schedules ms ON ms.id = pn.mediation_schedule_id
        WHERE pn.recipient_user_id = ?
        ORDER BY pn.created_at DESC
    ");
    $stmt->execute([$uid]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ── Formal Penalties — as RESPONDENT ─────────────────────────
// Must match via: respondent_user_id (direct link) OR name match (walk-in)
$penalties_as_respondent = [];
try {
    $penalties_as_respondent = $pdo->query("
        SELECT p.id, p.reason, p.amount, p.community_hours, p.due_date,
               p.status AS penalty_status, p.missed_party, p.created_at,
               b.case_number, b.incident_type, b.complainant_name,
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
               b.case_number, b.incident_type, b.respondent_name,
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
    // 1. Missed hearings — as RESPONDENT (use no_show_by or missed_session + name/uid match)
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
    // Match: respondent_user_id OR name match; action is refer_police/vawc/escalate OR status=escalated
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

    // 4. Cases referred/escalated — as COMPLAINANT (informational — good news, their case moved forward)
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

    // 5. Dismissed cases — as COMPLAINANT (status = 'dismissed', not prescribed_action)
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

    // 6. Dismissed cases — as RESPONDENT (good news: case dropped against them)
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

// ── Mark unread as read ───────────────────────────────────────
try {
    $pdo->prepare("
        UPDATE party_notifications
        SET status = 'read', read_at = NOW()
        WHERE recipient_user_id = ? AND status != 'read'
    ")->execute([$uid]);
} catch (PDOException $e) {}

// ── Notification type config ──────────────────────────────────
$notif_config = [
    'hearing_scheduled'   => ['ch-teal',    '📅', 'Hearing Scheduled'],
    'hearing_reminder'    => ['ch-teal',    '🔔', 'Hearing Reminder'],
    'hearing_rescheduled' => ['ch-amber',   '📅', 'Hearing Rescheduled'],
    'no_show_warning'     => ['ch-rose',    '⚠️',  'No-Show Warning'],
    'case_dismissed'      => ['ch-slate',   '📋', 'Case Dismissed'],
    'cfa_issued'          => ['ch-violet',  '⚖️',  'CFA Issued'],
    'mediation_completed' => ['ch-emerald', '✅', 'Mediation Completed'],
    'mediation_cancelled' => ['ch-slate',   '❌', 'Mediation Cancelled'],
    'case_escalated'      => ['ch-rose',    '🚨', 'Case Escalated'],
    'general'             => ['ch-slate',   '📄', 'General Notice'],
];

$penalty_chip = [
    'pending' => 'ch-amber',
    'paid'    => 'ch-emerald',
    'waived'  => 'ch-slate',
    'overdue' => 'ch-rose',
];

// Config for event-derived sanction display
$referred_labels = [
    'refer_police'          => ['🚔', 'Referred to Police (PNP)',         'rose',  'This case has been referred to the Philippine National Police.'],
    'refer_vawc'            => ['🛡️', 'Referred to VAWC Desk',             'rose',  'This case was escalated to the Violence Against Women and Children desk.'],
    'refer_dswd'            => ['👨‍👩‍👧', 'Referred to DSWD / WCPD',          'rose',  'This case was referred to the Department of Social Welfare and Development.'],
    'refer_nbi'             => ['🔍', 'Referred to NBI',                   'rose',  'This case has been referred to the National Bureau of Investigation.'],
    'refer_attorney'        => ['⚖️', 'Referred to Attorney / PAO',        'amber', 'This case was referred for legal representation.'],
    'escalate_municipality' => ['🏛️', 'Escalated to Municipality',         'amber', 'This case has been escalated to the municipal level for further action.'],
    'certificate_to_file'   => ['📜', 'Certificate to File Action Issued', 'rose',  'A Certificate to File Action has been issued. You may be summoned to court.'],
];

// Split notifications by role for tab counts
$notifs_as_complainant = array_filter($notifications, fn($n) => $n['recipient_type'] === 'complainant');
$notifs_as_respondent  = array_filter($notifications, fn($n) => $n['recipient_type'] === 'respondent');

// Tab counts
$count_sanctions = count($penalties_as_respondent) + count($penalties_as_complainant) + count($event_sanctions);
$count_notices   = count($notifications);

$has_any = $count_sanctions > 0 || $count_notices > 0;

// Unread count for badge
$unread_count = count(array_filter($notifications, fn($n) => $n['status'] !== 'read' && $n['read_at'] === null));
?>

<!-- ══════════════════════════════════════════
     PAGE-LEVEL STYLES
══════════════════════════════════════════ -->
<style>
/* ── Tabs ──────────────────────────────── */
.notices-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--ink-100);
    margin-bottom: 22px;
}
.ntab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-400);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color .12s, border-color .12s;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
    font-family: inherit;
    white-space: nowrap;
}
.ntab:hover { color: var(--ink-700); }
.ntab.active { color: var(--teal-600); border-bottom-color: var(--teal-600); }
.ntab.active.danger { color: var(--rose-600); border-bottom-color: var(--rose-600); }
.ntab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}
.nb-teal  { background: var(--teal-50);   color: var(--teal-600); }
.nb-rose  { background: var(--rose-50);   color: var(--rose-600); }
.nb-amber { background: var(--amber-50);  color: var(--amber-600); }
.nb-slate { background: var(--ink-50);    color: var(--ink-400); }

/* ── Tab panels ────────────────────────── */
.ntab-panel { display: none; }
.ntab-panel.active { display: block; }

/* ── Section subheader ─────────────────── */
.notices-subhdr {
    font-size: 11px;
    font-weight: 700;
    color: var(--ink-400);
    letter-spacing: .07em;
    text-transform: uppercase;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.notices-subhdr::after { content:''; flex:1; height:1px; background:var(--ink-100); }

/* ── Respondent penalty card (red-toned) ── */
.penalty-card-respondent {
    border-left: 4px solid var(--rose-400);
    background: linear-gradient(135deg, rgba(254,242,242,.6) 0%, var(--white) 60%);
}
/* ── Complainant penalty card (amber-toned) ── */
.penalty-card-complainant {
    border-left: 4px solid var(--amber-400);
    background: linear-gradient(135deg, rgba(255,251,235,.6) 0%, var(--white) 60%);
}

/* ── Notification card: respondent role ─── */
.notif-card-respondent {
    border-left: 4px solid var(--rose-300);
    background: linear-gradient(135deg, rgba(254,242,242,.35) 0%, var(--white) 50%);
}
/* ── Notification card: complainant role ── */
.notif-card-complainant {
    border-left: 4px solid var(--teal-400);
}
/* ── New badge highlight ────────────────── */
.notif-card-new {
    box-shadow: 0 0 0 1px var(--amber-200);
}

/* ── Role pill ─────────────────────────── */
.role-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.rp-respondent  { background: var(--rose-50);  color: var(--rose-600);  border: 1px solid var(--rose-100); }
.rp-complainant { background: var(--teal-50);  color: var(--teal-700);  border: 1px solid var(--teal-100); }

/* ── Penalty amount display ─────────────── */
.penalty-amount {
    font-size: 22px;
    font-weight: 800;
    line-height: 1;
}
.pa-respondent  { color: var(--rose-600); }
.pa-complainant { color: var(--amber-600); }

/* ── Hearing callout ───────────────────── */
.hearing-callout {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: var(--r-md);
    margin-top: 10px;
    font-size: 12px;
    font-weight: 600;
}
.hc-teal   { background: var(--teal-50);  border: 1px solid var(--teal-100);  color: var(--teal-700); }
.hc-rose   { background: var(--rose-50);  border: 1px solid var(--rose-100);  color: var(--rose-700); }

/* ── Event-derived sanction cards ──────── */
.event-card-missed-resp {
    border-left: 4px solid var(--rose-400);
    background: linear-gradient(135deg, rgba(254,242,242,.5) 0%, var(--white) 60%);
}
.event-card-missed-comp {
    border-left: 4px solid var(--amber-400);
    background: linear-gradient(135deg, rgba(255,251,235,.5) 0%, var(--white) 60%);
}
.event-card-referred-resp {
    border-left: 4px solid var(--rose-500);
    background: linear-gradient(135deg, rgba(254,226,226,.5) 0%, var(--white) 60%);
}
.event-card-referred-comp {
    border-left: 4px solid var(--teal-400);
    background: linear-gradient(135deg, rgba(240,253,250,.5) 0%, var(--white) 60%);
}
.event-card-dismissed {
    border-left: 4px solid var(--ink-300);
    background: linear-gradient(135deg, rgba(248,250,252,.8) 0%, var(--white) 60%);
}
.event-tag {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: .04em;
}
.et-warning { background: var(--rose-50);  color: var(--rose-600);  border: 1px solid var(--rose-200); }
.et-caution { background: var(--amber-50); color: var(--amber-700); border: 1px solid var(--amber-200); }
.et-info    { background: var(--teal-50);  color: var(--teal-700);  border: 1px solid var(--teal-200); }
.et-neutral { background: var(--ink-50);   color: var(--ink-500);   border: 1px solid var(--ink-100); }

/* ── Filter bar spacing ─────────────────── */
.notices-filter-bar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.nf-count {
    font-size: 12px;
    color: var(--ink-400);
    white-space: nowrap;
    align-self: center;
}

/* ── Empty ─────────────────────────────── */
.ntab-empty {
    text-align: center;
    padding: 48px 24px;
    color: var(--ink-300);
}
.ntab-empty .es-icon  { font-size: 36px; margin-bottom: 12px; }
.ntab-empty .es-title { font-size: 15px; font-weight: 600; color: var(--ink-400); margin-bottom: 6px; }
.ntab-empty .es-sub   { font-size: 13px; }
</style>

<!-- ══════════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════════ -->
<div class="page-hdr">
  <div class="page-hdr-left">
    <h2>Notices &amp; Sanctions</h2>
    <p>Notifications and penalties issued to you by your barangay</p>
  </div>
  <?php if ($unread_count > 0): ?>
  <div class="page-hdr-actions">
    <span class="chip ch-amber"><?= $unread_count ?> unread</span>
  </div>
  <?php endif; ?>
</div>

<?php if (!$has_any): ?>
<div class="empty-state">
  <div class="es-icon">🔔</div>
  <div class="es-title">No notices yet</div>
  <div class="es-sub">Formal notices from your barangay will appear here</div>
</div>

<?php else: ?>

<!-- ══════════════════════════════════════════
     TABS
══════════════════════════════════════════ -->
<div class="notices-tabs">

  <!-- Tab 1: Sanctions -->
  <button class="ntab active danger" onclick="switchTab('sanctions', this)">
    ⚖️ Sanctions &amp; Penalties
    <?php if ($count_sanctions > 0): ?>
    <span class="ntab-badge nb-rose"><?= $count_sanctions ?></span>
    <?php endif; ?>
  </button>

  <!-- Tab 2: All Notices -->
  <button class="ntab" onclick="switchTab('notices', this)">
    🔔 Case Notifications
    <?php if ($count_notices > 0): ?>
    <span class="ntab-badge <?= $unread_count > 0 ? 'nb-amber' : 'nb-teal' ?>"><?= $count_notices ?></span>
    <?php endif; ?>
  </button>

</div>

<!-- ══════════════════════════════════════════
     PANEL 1: SANCTIONS & PENALTIES
══════════════════════════════════════════ -->
<div id="panel-sanctions" class="ntab-panel active">

  <?php if (empty($penalties_as_respondent) && empty($penalties_as_complainant) && empty($event_sanctions)): ?>
    <div class="ntab-empty">
      <div class="es-icon">✅</div>
      <div class="es-title">No sanctions on record</div>
      <div class="es-sub">Penalties and case events issued to you will appear here</div>
    </div>

  <?php else: ?>

    <!-- ─────────────────────────────────────────────
         SECTION A: Formal Penalties (from penalties table)
    ──────────────────────────────────────────────── -->

    <?php if (!empty($penalties_as_respondent)): ?>
    <div class="notices-subhdr" style="color:var(--rose-600)">
      <span>🚨 Formal Penalties Against You</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($penalties_as_respondent as $p):
        $is_overdue = ($p['penalty_status'] === 'pending' && !empty($p['due_date']) && $p['due_date'] < date('Y-m-d'));
        $pchip  = $is_overdue ? 'ch-rose' : ($penalty_chip[$p['penalty_status']] ?? 'ch-slate');
        $plabel = $is_overdue ? 'Overdue' : ucfirst($p['penalty_status']);
      ?>
      <div class="card penalty-card-respondent">
        <div class="card-body" style="padding:16px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:24px;line-height:1;margin-top:2px">🚨</div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)"><?= e($p['reason']) ?></div>
                  <span class="role-pill rp-respondent">You are the respondent</span>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($p['case_number']) ?>
                  <?php if ($p['incident_type']): ?>&nbsp;·&nbsp;<?= e($p['incident_type']) ?><?php endif; ?>
                  <?php if ($p['complainant_name']): ?>&nbsp;·&nbsp;Filed by <?= e($p['complainant_name']) ?><?php endif; ?>
                </div>
                <div style="margin-top:10px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                  <div class="penalty-amount pa-respondent">&#8369;<?= number_format((float)$p['amount'], 2) ?></div>
                  <?php if ((int)$p['community_hours'] > 0): ?>
                  <div style="font-size:12px;color:var(--ink-600);font-weight:500">+ <?= (int)$p['community_hours'] ?> hrs community service</div>
                  <?php endif; ?>
                </div>
                <?php if ($is_overdue): ?>
                <div style="margin-top:8px;font-size:12px;font-weight:700;color:var(--rose-600)">⏰ This penalty is overdue. Please contact your barangay immediately.</div>
                <?php endif; ?>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <span class="chip <?= $pchip ?>"><?= $plabel ?></span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px">Issued: <?= date('M j, Y', strtotime($p['created_at'])) ?></div>
              <?php if ($p['due_date']): ?>
              <div style="font-size:11px;margin-top:2px;font-weight:<?= $is_overdue?'700':'400' ?>;color:<?= $is_overdue?'var(--rose-600)':'var(--ink-400)' ?>">
                Due: <?= date('M j, Y', strtotime($p['due_date'])) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($penalties_as_complainant)): ?>
    <div class="notices-subhdr" style="color:var(--amber-600)">
      <span>📋 Formal Penalties Issued to You as Complainant</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($penalties_as_complainant as $p):
        $is_overdue = ($p['penalty_status'] === 'pending' && !empty($p['due_date']) && $p['due_date'] < date('Y-m-d'));
        $pchip  = $is_overdue ? 'ch-rose' : ($penalty_chip[$p['penalty_status']] ?? 'ch-slate');
        $plabel = $is_overdue ? 'Overdue' : ucfirst($p['penalty_status']);
      ?>
      <div class="card penalty-card-complainant">
        <div class="card-body" style="padding:16px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:24px;line-height:1;margin-top:2px">💰</div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)"><?= e($p['reason']) ?></div>
                  <span class="role-pill rp-complainant">You filed this case</span>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($p['case_number']) ?>
                  <?php if ($p['incident_type']): ?>&nbsp;·&nbsp;<?= e($p['incident_type']) ?><?php endif; ?>
                  <?php if ($p['respondent_name']): ?>&nbsp;·&nbsp;Against <?= e($p['respondent_name']) ?><?php endif; ?>
                </div>
                <div style="margin-top:10px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                  <div class="penalty-amount pa-complainant">&#8369;<?= number_format((float)$p['amount'], 2) ?></div>
                  <?php if ((int)$p['community_hours'] > 0): ?>
                  <div style="font-size:12px;color:var(--ink-600);font-weight:500">+ <?= (int)$p['community_hours'] ?> hrs community service</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <span class="chip <?= $pchip ?>"><?= $plabel ?></span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px">Issued: <?= date('M j, Y', strtotime($p['created_at'])) ?></div>
              <?php if ($p['due_date']): ?>
              <div style="font-size:11px;margin-top:2px;font-weight:<?= $is_overdue?'700':'400' ?>;color:<?= $is_overdue?'var(--rose-600)':'var(--ink-400)' ?>">
                Due: <?= date('M j, Y', strtotime($p['due_date'])) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────
         SECTION B: Event-derived sanctions
         (missed hearings, escalations, dismissals)
    ──────────────────────────────────────────────── -->

    <?php
    // Group event sanctions by type+role for cleaner display
    $ev_missed_resp     = array_values(array_filter($event_sanctions, fn($e) => $e['event_type']==='missed_hearing'  && $e['my_role']==='respondent'));
    $ev_missed_comp     = array_values(array_filter($event_sanctions, fn($e) => $e['event_type']==='missed_hearing'  && $e['my_role']==='complainant'));
    $ev_referred_resp   = array_values(array_filter($event_sanctions, fn($e) => $e['event_type']==='case_referred'   && $e['my_role']==='respondent'));
    $ev_referred_comp   = array_values(array_filter($event_sanctions, fn($e) => $e['event_type']==='case_referred'   && $e['my_role']==='complainant'));
    $ev_dismissed_comp  = array_values(array_filter($event_sanctions, fn($e) => $e['event_type']==='case_dismissed'  && $e['my_role']==='complainant'));
    $ev_dismissed_resp  = array_values(array_filter($event_sanctions, fn($e) => $e['event_type']==='case_dismissed'  && $e['my_role']==='respondent'));

    // Consequence descriptions by missed count
    $resp_consequences = [
        1 => ['⚠️ 1st missed session', 'Hearing rescheduled. A final warning has been issued. A second no-show will allow the complainant to bring this case to court (CFA).', 'var(--amber-700)', 'var(--amber-50)', 'var(--amber-100)'],
        2 => ['🚨 2nd missed session', 'A Certification to File Action (CFA) has been issued to the complainant. They may now elevate this case to court. Contact your barangay immediately.', 'var(--rose-700)', 'var(--rose-50)', 'var(--rose-100)'],
    ];
    $comp_consequences = [
        1 => ['⚠️ 1st missed session', 'Hearing rescheduled. This is your first absence. A second no-show may result in your case being dismissed by the barangay.', 'var(--amber-700)', 'var(--amber-50)', 'var(--amber-100)'],
        2 => ['🚫 2nd missed session', 'Your case has been dismissed due to repeated absence. You are barred from filing the same case in court (Sec. 412, LGC).', 'var(--rose-700)', 'var(--rose-50)', 'var(--rose-100)'],
    ];
    ?>

    <?php if (!empty($ev_missed_resp)): ?>
    <div class="notices-subhdr" style="color:var(--rose-600)">
      <span>❌ Missed Hearings — You Were Required to Attend</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($ev_missed_resp as $e):
        $miss_count  = (int)($e['respondent_missed'] ?? 0);
        // Clamp to max rule index
        $rule_key    = min($miss_count, 2);
        $rule        = $rule_key > 0 ? ($resp_consequences[$rule_key] ?? null) : null;
        $action_chip = $e['action_type'] === 'cfa_issued' ? '<span class="chip ch-violet" style="font-size:10px">CFA Issued</span>' : '';
      ?>
      <div class="card event-card-missed-resp">
        <div class="card-body" style="padding:14px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px">❌</div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)">Missed Mediation Hearing</div>
                  <?php if ($miss_count > 0): ?>
                  <span class="event-tag <?= $miss_count >= 2 ? 'et-warning' : 'et-caution' ?>"><?= $miss_count ?>x missed</span>
                  <?php endif; ?>
                  <span class="role-pill rp-respondent">Respondent</span>
                  <?= $action_chip ?>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($e['case_number']) ?>&nbsp;·&nbsp;<?= e($e['incident_type']) ?>
                  <?php if ($e['complainant_name']): ?>&nbsp;·&nbsp;Filed by <?= e($e['complainant_name']) ?><?php endif; ?>
                </div>
                <?php if ($rule): ?>
                <div style="margin-top:8px;font-size:12px;font-weight:600;color:<?= $rule[2] ?>;line-height:1.6;background:<?= $rule[3] ?>;border:1px solid <?= $rule[4] ?>;border-radius:var(--r-sm);padding:8px 10px">
                  <?= $rule[0] ?> — <?= $rule[1] ?>
                </div>
                <?php else: ?>
                <div style="margin-top:8px;font-size:12px;color:var(--rose-700);line-height:1.6;background:var(--rose-50);border:1px solid var(--rose-100);border-radius:var(--r-sm);padding:8px 10px">
                  You were required to attend this mediation hearing but did not appear. Contact your barangay officer if you had a valid reason.
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;white-space:nowrap">
              <span class="chip ch-rose">No-Show</span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px"><?= date('M j, Y', strtotime($e['hearing_date'])) ?></div>
              <?php if ($e['hearing_time']): ?><div style="font-size:11px;color:var(--ink-400)"><?= date('g:i A', strtotime($e['hearing_time'])) ?></div><?php endif; ?>
              <div style="font-size:11px;color:var(--ink-400)">📍 <?= e($e['venue'] ?: 'Barangay Hall') ?></div>
            </div>
          </div>
        </div>
        <div class="card-foot">
          <button class="act-btn" onclick="viewBlotter(<?= $e['blotter_id'] ?>)">View Case</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($ev_missed_comp)): ?>
    <div class="notices-subhdr" style="color:var(--amber-600)">
      <span>⚠️ Missed Hearings — Cases You Filed</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($ev_missed_comp as $e):
        $miss_count = (int)($e['complainant_missed'] ?? 0);
        $rule_key   = min($miss_count, 2);
        $rule       = $rule_key > 0 ? ($comp_consequences[$rule_key] ?? null) : null;
      ?>
      <div class="card event-card-missed-comp">
        <div class="card-body" style="padding:14px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px">⚠️</div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)">You Missed a Mediation Hearing</div>
                  <?php if ($miss_count > 0): ?>
                  <span class="event-tag <?= $miss_count >= 2 ? 'et-warning' : 'et-caution' ?>"><?= $miss_count ?>x missed</span>
                  <?php endif; ?>
                  <span class="role-pill rp-complainant">Complainant</span>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($e['case_number']) ?>&nbsp;·&nbsp;<?= e($e['incident_type']) ?>
                  <?php if ($e['respondent_name']): ?>&nbsp;·&nbsp;Against <?= e($e['respondent_name']) ?><?php endif; ?>
                </div>
                <?php if ($rule): ?>
                <div style="margin-top:8px;font-size:12px;font-weight:600;color:<?= $rule[2] ?>;line-height:1.6;background:<?= $rule[3] ?>;border:1px solid <?= $rule[4] ?>;border-radius:var(--r-sm);padding:8px 10px">
                  <?= $rule[0] ?> — <?= $rule[1] ?>
                </div>
                <?php else: ?>
                <div style="margin-top:8px;font-size:12px;color:var(--amber-800);line-height:1.6;background:var(--amber-50);border:1px solid var(--amber-100);border-radius:var(--r-sm);padding:8px 10px">
                  As the complainant, your attendance at hearings is required. Repeated no-shows may result in your case being dismissed under the <em>Katarungang Pambarangay Law</em>.
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;white-space:nowrap">
              <span class="chip ch-amber">No-Show</span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px"><?= date('M j, Y', strtotime($e['hearing_date'])) ?></div>
              <?php if ($e['hearing_time']): ?><div style="font-size:11px;color:var(--ink-400)"><?= date('g:i A', strtotime($e['hearing_time'])) ?></div><?php endif; ?>
              <div style="font-size:11px;color:var(--ink-400)">📍 <?= e($e['venue'] ?: 'Barangay Hall') ?></div>
            </div>
          </div>
        </div>
        <div class="card-foot">
          <button class="act-btn" onclick="viewBlotter(<?= $e['blotter_id'] ?>)">View Case</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($ev_referred_resp)): ?>
    <div class="notices-subhdr" style="color:var(--rose-600)">
      <span>🚔 Cases Referred to Authorities — You Are the Respondent</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($ev_referred_resp as $e):
        $ref = $referred_labels[$e['prescribed_action']] ?? ['⚖️','Case Referred','rose','This case has been referred to an external authority.'];
        [$ref_icon, $ref_label, $ref_color, $ref_desc] = $ref;
      ?>
      <div class="card event-card-referred-resp">
        <div class="card-body" style="padding:14px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px"><?= $ref_icon ?></div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)"><?= $ref_label ?></div>
                  <span class="event-tag et-warning">Action Required</span>
                  <span class="role-pill rp-respondent">You are the respondent</span>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($e['case_number']) ?>&nbsp;·&nbsp;<?= e($e['incident_type']) ?>
                  <?php if ($e['complainant_name']): ?>&nbsp;·&nbsp;Filed by <?= e($e['complainant_name']) ?><?php endif; ?>
                </div>
                <div style="margin-top:8px;font-size:12px;color:var(--rose-700);line-height:1.6;background:var(--rose-50);border:1px solid var(--rose-100);border-radius:var(--r-sm);padding:8px 10px">
                  <?= $ref_desc ?> You may be contacted or summoned. Contact your barangay officer for guidance.
                </div>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;white-space:nowrap">
              <span class="chip ch-rose">Referred</span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px"><?= date('M j, Y', strtotime($e['event_date'])) ?></div>
            </div>
          </div>
        </div>
        <div class="card-foot">
          <button class="act-btn" onclick="viewBlotter(<?= $e['blotter_id'] ?>)">View Case</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($ev_referred_comp)): ?>
    <div class="notices-subhdr" style="color:var(--teal-600)">
      <span>📋 Cases You Filed — Referred to Authorities</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($ev_referred_comp as $e):
        $ref = $referred_labels[$e['prescribed_action']] ?? ['⚖️','Case Referred','teal','This case has been referred to an external authority.'];
        [$ref_icon, $ref_label, $ref_color, $ref_desc] = $ref;
      ?>
      <div class="card event-card-referred-comp">
        <div class="card-body" style="padding:14px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px"><?= $ref_icon ?></div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)"><?= $ref_label ?></div>
                  <span class="event-tag et-info">Update</span>
                  <span class="role-pill rp-complainant">You filed this case</span>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($e['case_number']) ?>&nbsp;·&nbsp;<?= e($e['incident_type']) ?>
                  <?php if ($e['respondent_name']): ?>&nbsp;·&nbsp;Against <?= e($e['respondent_name']) ?><?php endif; ?>
                </div>
                <div style="margin-top:8px;font-size:12px;color:var(--teal-700);line-height:1.6;background:var(--teal-50);border:1px solid var(--teal-100);border-radius:var(--r-sm);padding:8px 10px">
                  Your case has been escalated beyond the barangay level. <?= $ref_desc ?>
                  A barangay officer will update you on next steps.
                </div>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;white-space:nowrap">
              <span class="chip ch-teal">Escalated</span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px"><?= date('M j, Y', strtotime($e['event_date'])) ?></div>
            </div>
          </div>
        </div>
        <div class="card-foot">
          <button class="act-btn" onclick="viewBlotter(<?= $e['blotter_id'] ?>)">View Case</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($ev_dismissed_comp)): ?>
    <div class="notices-subhdr" style="color:var(--ink-400)">
      <span>📁 Dismissed Cases — Cases You Filed</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($ev_dismissed_comp as $e): ?>
      <div class="card event-card-dismissed">
        <div class="card-body" style="padding:14px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px">🚫</div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)">Case Dismissed</div>
                  <span class="event-tag et-neutral">Dismissed</span>
                  <span class="role-pill rp-complainant">You filed this case</span>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($e['case_number']) ?>&nbsp;·&nbsp;<?= e($e['incident_type']) ?>
                  <?php if ($e['respondent_name']): ?>&nbsp;·&nbsp;Against <?= e($e['respondent_name']) ?><?php endif; ?>
                </div>
                <?php $miss_count = (int)($e['complainant_missed'] ?? 0); ?>
                <div style="margin-top:8px;font-size:12px;color:var(--ink-600);line-height:1.6;background:var(--surface-2);border:1px solid var(--ink-100);border-radius:var(--r-sm);padding:8px 10px">
                  <?php if ($miss_count >= 2): ?>
                    Your case was dismissed because you failed to appear at <?= $miss_count ?> scheduled mediation hearings.
                    Under Sec. 412 of the Local Government Code, you are barred from filing the same case in court.
                    Contact your barangay officer if you believe this is in error.
                  <?php else: ?>
                    This case was dismissed by the barangay officer. If you believe the dismissal is incorrect,
                    you may request a review or request a Certificate to File Action to elevate to a higher body.
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;white-space:nowrap">
              <span class="chip ch-slate">Dismissed</span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px"><?= date('M j, Y', strtotime($e['event_date'])) ?></div>
            </div>
          </div>
        </div>
        <div class="card-foot">
          <button class="act-btn" onclick="viewBlotter(<?= $e['blotter_id'] ?>)">View Case</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($ev_dismissed_resp)): ?>
    <div class="notices-subhdr" style="color:var(--green-700)">
      <span>✅ Dismissed Cases — Against You (Resolved in Your Favor)</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px">
      <?php foreach ($ev_dismissed_resp as $e): ?>
      <div class="card" style="border-left:4px solid var(--green-400);background:linear-gradient(135deg,rgba(240,253,244,.6) 0%,var(--white) 60%)">
        <div class="card-body" style="padding:14px 18px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px">✅</div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)">Case Dismissed Against You</div>
                  <span class="event-tag et-info">Good News</span>
                  <span class="role-pill rp-respondent">Respondent</span>
                </div>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($e['case_number']) ?>&nbsp;·&nbsp;<?= e($e['incident_type']) ?>
                  <?php if ($e['complainant_name']): ?>&nbsp;·&nbsp;Filed by <?= e($e['complainant_name']) ?><?php endif; ?>
                </div>
                <div style="margin-top:8px;font-size:12px;color:var(--green-700);line-height:1.6;background:var(--green-50);border:1px solid var(--green-100);border-radius:var(--r-sm);padding:8px 10px">
                  This case has been dismissed. No further action is required from you at this time.
                  Keep this record for your reference in case the same matter is raised again.
                </div>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;white-space:nowrap">
              <span class="chip ch-emerald">Dismissed</span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:6px"><?= date('M j, Y', strtotime($e['event_date'])) ?></div>
            </div>
          </div>
        </div>
        <div class="card-foot">
          <button class="act-btn" onclick="viewBlotter(<?= $e['blotter_id'] ?>)">View Case</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  <?php endif; // no penalties/events ?>
</div><!-- /panel-sanctions -->

<!-- ══════════════════════════════════════════
     PANEL 2: CASE NOTIFICATIONS
══════════════════════════════════════════ -->
<div id="panel-notices" class="ntab-panel">

  <?php if (empty($notifications)): ?>
    <div class="ntab-empty">
      <div class="es-icon">🔔</div>
      <div class="es-title">No notifications yet</div>
      <div class="es-sub">Case updates from your barangay will appear here</div>
    </div>

  <?php else: ?>

    <!-- Filter bar -->
    <div class="notices-filter-bar">
      <select id="notif-filter-role" onchange="filterNotifs()" style="width:auto;min-width:160px">
        <option value="">All Roles</option>
        <option value="complainant">As Complainant</option>
        <option value="respondent">As Respondent</option>
      </select>
      <select id="notif-filter-type" onchange="filterNotifs()" style="width:auto;min-width:170px">
        <option value="">All Types</option>
        <option value="hearing_scheduled">Hearing Scheduled</option>
        <option value="hearing_reminder">Hearing Reminder</option>
        <option value="hearing_rescheduled">Hearing Rescheduled</option>
        <option value="no_show_warning">No-Show Warning</option>
        <option value="mediation_completed">Mediation Completed</option>
        <option value="case_escalated">Case Escalated</option>
        <option value="cfa_issued">CFA Issued</option>
        <option value="case_dismissed">Case Dismissed</option>
        <option value="general">General</option>
      </select>
      <select id="notif-filter-read" onchange="filterNotifs()" style="width:auto;min-width:140px">
        <option value="">All Notices</option>
        <option value="unread">Unread Only</option>
        <option value="read">Read Only</option>
      </select>
      <button class="btn btn-outline btn-sm" onclick="clearNotifFilters()">✕ Clear</button>
      <span id="notif-count" class="nf-count"></span>
    </div>

    <!-- No filter results -->
    <div id="notif-no-results" style="display:none">
      <div class="ntab-empty">
        <div class="es-icon">🔍</div>
        <div class="es-title">No notifications match</div>
        <div class="es-sub">Try adjusting the filters above.</div>
      </div>
    </div>

    <!-- Notifications list -->
    <?php if (!empty($notifs_as_respondent)): ?>
    <div class="notices-subhdr" id="subhdr-respondent" style="color:var(--rose-600)">
      <span>⚠️ Notifications — You are the Respondent</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px" id="group-respondent">
      <?php foreach ($notifs_as_respondent as $n):
        [$chip, $icon, $type_lbl] = $notif_config[$n['notification_type']] ?? ['ch-slate','📄','Notice'];
        $is_new       = ($n['status'] !== 'read' && $n['read_at'] === null);
        $show_hearing = !empty($n['hearing_date']) && in_array($n['notification_type'], [
            'hearing_scheduled','hearing_reminder','hearing_rescheduled',
        ]);
      ?>
      <div class="card notif-card-respondent <?= $is_new ? 'notif-card-new' : '' ?>"
           data-role="respondent"
           data-type="<?= e($n['notification_type']) ?>"
           data-read="<?= $n['status'] === 'read' ? 'read' : 'unread' ?>">
        <div class="card-body" style="padding:16px 18px">

          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px"><?= $icon ?></div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)"><?= e($n['subject'] ?: $type_lbl) ?></div>
                  <span class="role-pill rp-respondent">Respondent</span>
                  <?php if ($is_new): ?><span class="chip ch-amber" style="font-size:10px">New</span><?php endif; ?>
                </div>
                <?php if ($n['case_number']): ?>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($n['case_number']) ?>
                  <?php if ($n['incident_type']): ?>&nbsp;·&nbsp;<?= e($n['incident_type']) ?><?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <span class="chip <?= $chip ?>"><?= $type_lbl ?></span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:4px"><?= date('M j, Y', strtotime($n['created_at'])) ?></div>
            </div>
          </div>

          <?php if ($n['message']): ?>
          <div style="font-size:13px;color:var(--ink-600);line-height:1.7;padding:12px;background:rgba(254,242,242,.5);border:1px solid var(--rose-100);border-radius:var(--r-sm)">
            <?= nl2br(e($n['message'])) ?>
          </div>
          <?php endif; ?>

          <?php if ($show_hearing): ?>
          <div class="hearing-callout hc-rose">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1.5" y="2" width="11" height="10.5" rx="1.5"/><path d="M1.5 5.5h11M4.5 2V.5M9.5 2V.5"/></svg>
            <span>
              <?= date('D, F j, Y', strtotime($n['hearing_date'])) ?>
              <?php if ($n['hearing_time']): ?>&nbsp;at&nbsp;<?= date('g:i A', strtotime($n['hearing_time'])) ?><?php endif; ?>
              <?php if ($n['venue']): ?>&nbsp;·&nbsp;<?= e($n['venue']) ?><?php endif; ?>
            </span>
          </div>
          <?php endif; ?>

          <?php if (!empty($n['sent_at']) && !empty($n['channel'])): ?>
          <div style="font-size:11px;color:var(--ink-400);margin-top:8px">
            Sent <?= date('M j, Y g:i A', strtotime($n['sent_at'])) ?> via <?= e(str_replace(',',', ',$n['channel'])) ?>
          </div>
          <?php endif; ?>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($notifs_as_complainant)): ?>
    <div class="notices-subhdr" id="subhdr-complainant" style="color:var(--teal-700)">
      <span>📋 Notifications — You Filed This Case</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px" id="group-complainant">
      <?php foreach ($notifs_as_complainant as $n):
        [$chip, $icon, $type_lbl] = $notif_config[$n['notification_type']] ?? ['ch-slate','📄','Notice'];
        $is_new       = ($n['status'] !== 'read' && $n['read_at'] === null);
        $show_hearing = !empty($n['hearing_date']) && in_array($n['notification_type'], [
            'hearing_scheduled','hearing_reminder','hearing_rescheduled',
        ]);
      ?>
      <div class="card notif-card-complainant <?= $is_new ? 'notif-card-new' : '' ?>"
           data-role="complainant"
           data-type="<?= e($n['notification_type']) ?>"
           data-read="<?= $n['status'] === 'read' ? 'read' : 'unread' ?>">
        <div class="card-body" style="padding:16px 18px">

          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px">
            <div style="display:flex;align-items:flex-start;gap:12px">
              <div style="font-size:22px;line-height:1;margin-top:2px"><?= $icon ?></div>
              <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px">
                  <div style="font-size:14px;font-weight:700;color:var(--ink-900)"><?= e($n['subject'] ?: $type_lbl) ?></div>
                  <span class="role-pill rp-complainant">Complainant</span>
                  <?php if ($is_new): ?><span class="chip ch-amber" style="font-size:10px">New</span><?php endif; ?>
                </div>
                <?php if ($n['case_number']): ?>
                <div style="font-size:11px;font-family:var(--font-mono);color:var(--ink-400)">
                  <?= e($n['case_number']) ?>
                  <?php if ($n['incident_type']): ?>&nbsp;·&nbsp;<?= e($n['incident_type']) ?><?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <span class="chip <?= $chip ?>"><?= $type_lbl ?></span>
              <div style="font-size:11px;color:var(--ink-400);margin-top:4px"><?= date('M j, Y', strtotime($n['created_at'])) ?></div>
            </div>
          </div>

          <?php if ($n['message']): ?>
          <div style="font-size:13px;color:var(--ink-600);line-height:1.7;padding:12px;background:var(--surface);border-radius:var(--r-sm)">
            <?= nl2br(e($n['message'])) ?>
          </div>
          <?php endif; ?>

          <?php if ($show_hearing): ?>
          <div class="hearing-callout hc-teal">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1.5" y="2" width="11" height="10.5" rx="1.5"/><path d="M1.5 5.5h11M4.5 2V.5M9.5 2V.5"/></svg>
            <span>
              <?= date('D, F j, Y', strtotime($n['hearing_date'])) ?>
              <?php if ($n['hearing_time']): ?>&nbsp;at&nbsp;<?= date('g:i A', strtotime($n['hearing_time'])) ?><?php endif; ?>
              <?php if ($n['venue']): ?>&nbsp;·&nbsp;<?= e($n['venue']) ?><?php endif; ?>
            </span>
          </div>
          <?php endif; ?>

          <?php if (!empty($n['sent_at']) && !empty($n['channel'])): ?>
          <div style="font-size:11px;color:var(--ink-400);margin-top:8px">
            Sent <?= date('M j, Y g:i A', strtotime($n['sent_at'])) ?> via <?= e(str_replace(',',', ',$n['channel'])) ?>
          </div>
          <?php endif; ?>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  <?php endif; // empty notifications ?>
</div><!-- /panel-notices -->

<?php endif; // has_any ?>

<script>
// ── Tab switching ─────────────────────────────────────────────
function switchTab(name, btn) {
  document.querySelectorAll('.ntab').forEach(function(t){ t.classList.remove('active'); });
  document.querySelectorAll('.ntab-panel').forEach(function(p){ p.classList.remove('active'); });
  btn.classList.add('active');
  var panel = document.getElementById('panel-' + name);
  if (panel) panel.classList.add('active');
}

// ── Notification filters ──────────────────────────────────────
function filterNotifs() {
  var role  = document.getElementById('notif-filter-role')?.value  || '';
  var type  = document.getElementById('notif-filter-type')?.value  || '';
  var read  = document.getElementById('notif-filter-read')?.value  || '';

  var cards  = document.querySelectorAll('[data-role]');
  var visible = 0;

  cards.forEach(function(card) {
    var matchRole = !role || card.dataset.role === role;
    var matchType = !type || card.dataset.type === type;
    var matchRead = !read || card.dataset.read === read;
    var show = matchRole && matchType && matchRead;
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  // Show/hide subheaders based on visible cards in each group
  ['respondent','complainant'].forEach(function(r) {
    var grp  = document.getElementById('group-' + r);
    var shdr = document.getElementById('subhdr-' + r);
    if (!grp || !shdr) return;
    var anyVisible = Array.from(grp.querySelectorAll('[data-role]')).some(function(c){ return c.style.display !== 'none'; });
    grp.style.display  = anyVisible ? '' : 'none';
    shdr.style.display = anyVisible ? '' : 'none';
  });

  var noResults = document.getElementById('notif-no-results');
  if (noResults) noResults.style.display = (cards.length > 0 && visible === 0) ? '' : 'none';

  var countEl = document.getElementById('notif-count');
  if (countEl) {
    var hasFilter = role || type || read;
    countEl.textContent = hasFilter ? 'Showing ' + visible + ' of ' + cards.length : '';
  }
}

function clearNotifFilters() {
  ['notif-filter-role','notif-filter-type','notif-filter-read'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.value = '';
  });
  filterNotifs();
}
</script>