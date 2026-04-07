<?php
// ajax/mediation_action.php — KP Law compliant mediation process
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$bid = (int)($_SESSION['barangay_id'] ?? 0);
$uid = (int)($_SESSION['user_id']     ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$act   = $input['action'] ?? '';

// ── Helpers ──────────────────────────────────────────────────────────────────

function log_act(PDO $pdo, int $uid, int $bid, string $action, int $blotter_id, string $desc): void {
    try {
        $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,?,?,?,?,NOW())")
            ->execute([$uid, $bid, $action, 'blotter', $blotter_id, $desc]);
    } catch (Exception $e) {}
}

/**
 * Queue a notification for a party (complainant or respondent).
 * Notifications sit in party_notifications until an SMS gateway or
 * print handler picks them up. Registered users also see them in-app.
 */
function notify_party(
    PDO $pdo, int $blotter_id, int $bid, int $uid,
    string $type,           // notification_type enum
    string $party,          // 'complainant' | 'respondent'
    string $name,
    ?string $contact,
    ?int $user_id,
    string $subject,
    string $message,
    ?int $med_id = null
): void {
    try {
        $channel = $contact ? 'inapp,sms' : 'inapp';
        $pdo->prepare("
            INSERT INTO party_notifications
              (blotter_id, mediation_schedule_id, barangay_id,
               recipient_type, recipient_user_id, recipient_name, recipient_contact,
               notification_type, subject, message, channel, status, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',?,NOW())
        ")->execute([
            $blotter_id, $med_id, $bid,
            $party, $user_id, $name, $contact,
            $type, $subject, $message, $channel, $uid
        ]);
    } catch (PDOException $ex) { error_log('[notify_party] ' . $ex->getMessage()); }
}

/**
 * Notify BOTH parties from a single call using blotter contact data.
 */
function notify_both(PDO $pdo, array $ms, array $b, int $bid, int $uid, string $type, string $subject, string $comp_msg, string $resp_msg, ?int $med_id = null): void {
    notify_party($pdo, (int)$b['id'], $bid, $uid, $type, 'complainant',
        $b['complainant_name'], $b['complainant_contact'] ?? null,
        $b['complainant_user_id'] ? (int)$b['complainant_user_id'] : null,
        $subject, $comp_msg, $med_id);

    if ($b['respondent_name'] && $b['respondent_name'] !== 'Unknown') {
        notify_party($pdo, (int)$b['id'], $bid, $uid, $type, 'respondent',
            $b['respondent_name'], $b['respondent_contact'] ?? null, null,
            $subject, $resp_msg, $med_id);
    }
}

function fdate(string $d): string { return date('F j, Y', strtotime($d)); }
function ftime(string $t): string { return date('g:i A', strtotime($t)); }

// ── Fetch full blotter from a mediation schedule ID ──────────────────────────
function get_ms_and_blotter(PDO $pdo, int $med_id, int $bid): ?array {
    $s = $pdo->prepare("
        SELECT ms.*, b.id AS bid, b.case_number, b.complainant_name, b.complainant_contact,
               b.complainant_user_id, b.respondent_name, b.respondent_contact,
               b.complainant_missed, b.respondent_missed, b.status AS blotter_status
        FROM mediation_schedules ms
        JOIN blotters b ON b.id = ms.blotter_id
        WHERE ms.id = ? AND b.barangay_id = ?
        LIMIT 1
    ");
    $s->execute([$med_id, $bid]);
    return $s->fetch() ?: null;
}

// ─────────────────────────────────────────────────────────────────────────────

try {
    switch ($act) {

    // ══════════════════════════════════════════════════════════════════════════
    // SCHEDULE NEW MEDIATION
    // ══════════════════════════════════════════════════════════════════════════
    case 'schedule_mediation':
        $blotter_id = (int)($input['blotter_id'] ?? 0);
        $date  = trim($input['date']  ?? '');
        $time  = trim($input['time']  ?? '');
        $venue = trim($input['venue'] ?? 'Barangay Hall');
        $notes = trim($input['notes'] ?? '');

        if (!$blotter_id || !$date || !$time) jsonResponse(false, 'Blotter, date and time are required.');

        // Verify ownership
        $bl = $pdo->prepare("SELECT * FROM blotters WHERE id=? AND barangay_id=? LIMIT 1");
        $bl->execute([$blotter_id, $bid]); $b = $bl->fetch();
        if (!$b) jsonResponse(false, 'Access denied.');

        $pdo->prepare("
            INSERT INTO mediation_schedules (blotter_id, barangay_id, hearing_date, hearing_time, venue, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
        ")->execute([$blotter_id, $bid, $date, $time, $venue]);
        $new_med_id = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE blotters SET status='mediation_set', updated_at=NOW() WHERE id=?")->execute([$blotter_id]);

        $date_fmt = fdate($date); $time_fmt = ftime($time);
        $comp_msg = "Dear {$b['complainant_name']}, your mediation hearing for case {$b['case_number']} is scheduled on $date_fmt at $time_fmt at $venue. Please make sure to attend.";
        $resp_msg = "Dear {$b['respondent_name']}, you are required to appear at a mediation hearing for case {$b['case_number']} on $date_fmt at $time_fmt at $venue.";
        notify_both($pdo, [], $b, $bid, $uid, 'hearing_scheduled', "Mediation Hearing Scheduled — {$b['case_number']}", $comp_msg, $resp_msg, $new_med_id);

        log_act($pdo, $uid, $bid, 'mediation_scheduled', $blotter_id, "Hearing scheduled for $date_fmt at $venue.");
        jsonResponse(true, "Hearing scheduled for $date_fmt. Both parties notified.");

    // ══════════════════════════════════════════════════════════════════════════
    // RECORD OUTCOME
    // ══════════════════════════════════════════════════════════════════════════
    case 'record_outcome':
        $med_id  = (int)($input['id'] ?? 0);
        $status  = in_array($input['status'] ?? '', ['completed','missed','cancelled','rescheduled']) ? $input['status'] : 'completed';
        $comp    = isset($input['complainant_attended']) ? (int)$input['complainant_attended'] : 1;
        $resp    = isset($input['respondent_attended'])  ? (int)$input['respondent_attended']  : 1;
        $outcome = trim($input['outcome']         ?? '');
        $next    = trim($input['next_steps']      ?? '');
        $redate  = trim($input['reschedule_date'] ?? '');
        $retime  = trim($input['reschedule_time'] ?? '');

        if (!$med_id) jsonResponse(false, 'Invalid session ID.');

        $ms = get_ms_and_blotter($pdo, $med_id, $bid);
        if (!$ms) jsonResponse(false, 'Session not found or access denied.');

        $blotter_id     = (int)$ms['bid'];
        $case_no        = $ms['case_number'];
        $comp_missed    = (int)$ms['complainant_missed'];
        $resp_missed    = (int)$ms['respondent_missed'];

        // ── Auto-correct: if someone absent, override to 'missed' ──────
        if ($status === 'completed' && (!$comp || !$resp)) {
            $status = 'missed';
        }

        // ── Determine absent party ─────────────────────────────────────
        $no_show_by = 'none';
        $is_missed  = false;
        if ($status === 'missed') {
            $is_missed = true;
            if (!$comp && !$resp) $no_show_by = 'both';
            elseif (!$comp)       $no_show_by = 'complainant';
            else                  $no_show_by = 'respondent';
        }

        // ── If rescheduled requires a date ────────────────────────────
        if ($status === 'rescheduled' && !$redate) jsonResponse(false, 'New hearing date is required when rescheduling.');

        // ── Update mediation record ───────────────────────────────────
        $pdo->prepare("
            UPDATE mediation_schedules
            SET status=?, complainant_attended=?, respondent_attended=?,
                outcome=?, next_steps=?, no_show_by=?,
                missed_session=?, reschedule_date=?, reschedule_time=?,
                notified_at=NOW(), updated_at=NOW()
            WHERE id=?
        ")->execute([
            $status, $comp, $resp, $outcome, $next, $no_show_by,
            $is_missed ? 1 : 0,
            $redate ?: null, $retime ?: null,
            $med_id
        ]);

        // ══════════════════════════════════════════════════════════════
        // OUTCOME: COMPLETED
        // ══════════════════════════════════════════════════════════════
        if ($status === 'completed') {
            $pdo->prepare("UPDATE blotters SET status='resolved', updated_at=NOW() WHERE id=?")->execute([$blotter_id]);
            log_act($pdo, $uid, $bid, 'mediation_completed', $blotter_id, "Mediation completed — $case_no. $outcome");

            $msg = "Case $case_no has been successfully resolved through mediation. Thank you for your cooperation.";
            notify_both($pdo, $ms, $ms, $bid, $uid, 'mediation_completed', "Mediation Completed — $case_no", $msg, $msg, $med_id);
            jsonResponse(true, 'Mediation completed. Blotter marked resolved. Both parties notified. ✅');
        }

        // ══════════════════════════════════════════════════════════════
        // OUTCOME: CANCELLED (barangay decision, no attendance issue)
        // ══════════════════════════════════════════════════════════════
        if ($status === 'cancelled') {
            $pdo->prepare("UPDATE blotters SET status='active', updated_at=NOW() WHERE id=?")->execute([$blotter_id]);
            log_act($pdo, $uid, $bid, 'mediation_cancelled', $blotter_id, "Mediation cancelled — $case_no.");

            $msg = "The mediation hearing for case $case_no has been cancelled by the barangay. You will be notified of further actions.";
            notify_both($pdo, $ms, $ms, $bid, $uid, 'mediation_cancelled', "Hearing Cancelled — $case_no", $msg, $msg, $med_id);
            jsonResponse(true, 'Hearing cancelled. Blotter returned to active. Both parties notified.');
        }

        // ══════════════════════════════════════════════════════════════
        // OUTCOME: RESCHEDULED (barangay decision — NO missed count)
        // ══════════════════════════════════════════════════════════════
        if ($status === 'rescheduled') {
            // Create new scheduled session
            $pdo->prepare("
                INSERT INTO mediation_schedules (blotter_id, barangay_id, hearing_date, hearing_time, venue, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
            ")->execute([$blotter_id, $bid, $redate, $retime ?: '09:00:00', $ms['venue'] ?: 'Barangay Hall']);
            $new_id = (int)$pdo->lastInsertId();

            log_act($pdo, $uid, $bid, 'mediation_rescheduled', $blotter_id, "Mediation rescheduled — $case_no to " . fdate($redate) . ".");

            $redate_fmt = fdate($redate);
            $retime_fmt = $retime ? ftime($retime) : 'TBD';
            $venue_fmt  = $ms['venue'] ?: 'Barangay Hall';
            $comp_msg   = "Dear {$ms['complainant_name']}, your mediation hearing for case $case_no has been rescheduled to $redate_fmt at $retime_fmt at $venue_fmt.";
            $resp_msg   = "Dear {$ms['respondent_name']}, the mediation hearing for case $case_no has been rescheduled to $redate_fmt at $retime_fmt at $venue_fmt.";
            notify_both($pdo, $ms, $ms, $bid, $uid, 'hearing_rescheduled', "Hearing Rescheduled — $case_no", $comp_msg, $resp_msg, $new_id);

            jsonResponse(true, "Hearing rescheduled to " . fdate($redate) . ". New session created. Both parties notified.");
        }

        // ══════════════════════════════════════════════════════════════
        // OUTCOME: MISSED (no-show — apply KP Law consequences)
        // ══════════════════════════════════════════════════════════════
        if ($status === 'missed') {

            $new_comp_missed = $comp_missed + ($no_show_by === 'complainant' || $no_show_by === 'both' ? 1 : 0);
            $new_resp_missed = $resp_missed + ($no_show_by === 'respondent' || $no_show_by === 'both' ? 1 : 0);

            // Update missed counters on blotter
            $pdo->prepare("UPDATE blotters SET complainant_missed=?, respondent_missed=?, updated_at=NOW() WHERE id=?")
                ->execute([$new_comp_missed, $new_resp_missed, $blotter_id]);

            $action_taken = '';
            $blotter_new_status = null;
            $return_msg = '';

            // ── BOTH ABSENT ──────────────────────────────────────────
            if ($no_show_by === 'both') {
                // Any occurrence of both absent = case dismissed/abandoned
                $blotter_new_status = 'dismissed';
                $pdo->prepare("UPDATE mediation_schedules SET action_issued=1, action_type='dismissed' WHERE id=?")->execute([$med_id]);
                $action_taken = 'dismissed';

                $msg = "Case $case_no has been dismissed due to the absence of both parties. If you wish to pursue this matter, a new complaint must be filed.";
                notify_both($pdo, $ms, $ms, $bid, $uid, 'case_dismissed', "Case Dismissed — $case_no", $msg, $msg, $med_id);

                log_act($pdo, $uid, $bid, 'case_dismissed', $blotter_id, "Case dismissed — both parties absent. $case_no.");
                $return_msg = "Both parties absent. Case $case_no dismissed/abandoned. Both notified. To pursue, a new complaint must be filed.";
            }

            // ── COMPLAINANT ABSENT ───────────────────────────────────
            elseif ($no_show_by === 'complainant') {
                if ($new_comp_missed === 1) {
                    // 1st miss: reschedule with notification
                    if ($redate) {
                        $pdo->prepare("
                            INSERT INTO mediation_schedules (blotter_id, barangay_id, hearing_date, hearing_time, venue, status, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
                        ")->execute([$blotter_id, $bid, $redate, $retime ?: '09:00:00', $ms['venue'] ?: 'Barangay Hall']);
                        $new_id2 = (int)$pdo->lastInsertId();
                        $pdo->prepare("UPDATE mediation_schedules SET action_issued=1, action_type='rescheduled_1st' WHERE id=?")->execute([$med_id]);

                        $redate_fmt = fdate($redate); $retime_fmt = $retime ? ftime($retime) : 'TBD';
                        $comp_warn  = "Dear {$ms['complainant_name']}, you failed to appear at the mediation hearing for case $case_no. This is your FIRST missed session. A new hearing has been scheduled on $redate_fmt at $retime_fmt. A second absence may result in case dismissal. Please attend.";
                        $resp_info  = "Dear {$ms['respondent_name']}, the complainant did not appear at today's hearing for case $case_no. The hearing has been rescheduled to $redate_fmt at $retime_fmt.";

                        notify_party($pdo, $blotter_id, $bid, $uid, 'no_show_warning', 'complainant', $ms['complainant_name'], $ms['complainant_contact'] ?? null, $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null, "⚠️ Missed Hearing Warning — $case_no", $comp_warn, $new_id2);
                        notify_party($pdo, $blotter_id, $bid, $uid, 'hearing_rescheduled', 'respondent', $ms['respondent_name'], $ms['respondent_contact'] ?? null, null, "Hearing Rescheduled — $case_no", $resp_info, $new_id2);

                        log_act($pdo, $uid, $bid, 'complainant_no_show_1', $blotter_id, "Complainant 1st miss — $case_no. Rescheduled to $redate_fmt.");
                        $return_msg = "Complainant absent (1st time). Warning sent. New hearing scheduled for $redate_fmt.";
                    } else {
                        $return_msg = "Complainant absent (1st time). Warning recorded. Schedule a new hearing date.";
                        log_act($pdo, $uid, $bid, 'complainant_no_show_1', $blotter_id, "Complainant 1st miss — $case_no. No reschedule date set.");
                    }
                } else {
                    // 2nd+ miss: case dismissed — complainant barred from refiling same case in court
                    $blotter_new_status = 'dismissed';
                    $pdo->prepare("UPDATE mediation_schedules SET action_issued=1, action_type='dismissed' WHERE id=?")->execute([$med_id]);
                    $action_taken = 'dismissed';

                    $comp_msg = "Dear {$ms['complainant_name']}, case $case_no has been dismissed due to your repeated failure to appear at mediation hearings. You are barred from filing this same case in court (Section 412, Local Government Code).";
                    $resp_msg = "Dear {$ms['respondent_name']}, case $case_no has been dismissed. The complainant failed to attend scheduled hearings. No further action is required from you at this time.";

                    notify_party($pdo, $blotter_id, $bid, $uid, 'case_dismissed', 'complainant', $ms['complainant_name'], $ms['complainant_contact'] ?? null, $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null, "Case Dismissed — $case_no", $comp_msg, $med_id);
                    notify_party($pdo, $blotter_id, $bid, $uid, 'case_dismissed', 'respondent',  $ms['respondent_name'],  $ms['respondent_contact']  ?? null, null, "Case Dismissed — $case_no", $resp_msg, $med_id);

                    log_act($pdo, $uid, $bid, 'case_dismissed', $blotter_id, "Case dismissed — complainant 2nd miss. Barred from refiling. $case_no.");
                    $return_msg = "Complainant absent (2nd time). Case $case_no dismissed. Complainant barred from filing same case in court. Both parties notified.";
                }
            }

            // ── RESPONDENT ABSENT ────────────────────────────────────
            elseif ($no_show_by === 'respondent') {
                if ($new_resp_missed === 1) {
                    // 1st miss: reschedule with warning to respondent
                    if ($redate) {
                        $pdo->prepare("
                            INSERT INTO mediation_schedules (blotter_id, barangay_id, hearing_date, hearing_time, venue, status, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
                        ")->execute([$blotter_id, $bid, $redate, $retime ?: '09:00:00', $ms['venue'] ?: 'Barangay Hall']);
                        $new_id3 = (int)$pdo->lastInsertId();
                        $pdo->prepare("UPDATE mediation_schedules SET action_issued=1, action_type='rescheduled_1st' WHERE id=?")->execute([$med_id]);

                        $redate_fmt = fdate($redate); $retime_fmt = $retime ? ftime($retime) : 'TBD';
                        $resp_warn  = "Dear {$ms['respondent_name']}, you failed to appear at the mediation hearing for case $case_no. This is your FIRST missed session. A new hearing has been scheduled on $redate_fmt at $retime_fmt. A second absence may result in a Certification to File Action (CFA) being issued to the complainant, allowing them to pursue this case in court.";
                        $comp_info  = "Dear {$ms['complainant_name']}, the respondent did not appear at today's hearing for case $case_no. The hearing has been rescheduled to $redate_fmt at $retime_fmt.";

                        notify_party($pdo, $blotter_id, $bid, $uid, 'no_show_warning', 'respondent',  $ms['respondent_name'],  $ms['respondent_contact']  ?? null, null, "⚠️ Final Warning — $case_no", $resp_warn, $new_id3);
                        notify_party($pdo, $blotter_id, $bid, $uid, 'hearing_rescheduled', 'complainant', $ms['complainant_name'], $ms['complainant_contact'] ?? null, $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null, "Hearing Rescheduled — $case_no", $comp_info, $new_id3);

                        log_act($pdo, $uid, $bid, 'respondent_no_show_1', $blotter_id, "Respondent 1st miss — $case_no. Rescheduled to $redate_fmt.");
                        $return_msg = "Respondent absent (1st time). Warning sent. New hearing scheduled for $redate_fmt.";
                    } else {
                        $return_msg = "Respondent absent (1st time). Warning recorded. Schedule a new hearing date.";
                        log_act($pdo, $uid, $bid, 'respondent_no_show_1', $blotter_id, "Respondent 1st miss — $case_no. No reschedule date set.");
                    }
                } else {
                    // 2nd+ miss: issue CFA — complainant may now file in court
                    $blotter_new_status = 'cfa_issued';
                    $pdo->prepare("UPDATE mediation_schedules SET action_issued=1, action_type='cfa_issued' WHERE id=?")->execute([$med_id]);
                    $action_taken = 'cfa_issued';

                    $comp_msg = "Dear {$ms['complainant_name']}, the respondent has repeatedly failed to appear at mediation hearings for case $case_no. The Barangay is issuing a Certification to File Action (CFA) in your favor. You may now bring this case to the proper court or government office.";
                    $resp_msg = "Dear {$ms['respondent_name']}, you have failed to appear at multiple scheduled mediation hearings for case $case_no. A Certification to File Action (CFA) has been issued to the complainant. This allows them to file the case in court.";

                    notify_party($pdo, $blotter_id, $bid, $uid, 'cfa_issued', 'complainant', $ms['complainant_name'], $ms['complainant_contact'] ?? null, $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null, "CFA Issued — $case_no", $comp_msg, $med_id);
                    notify_party($pdo, $blotter_id, $bid, $uid, 'cfa_issued', 'respondent',  $ms['respondent_name'],  $ms['respondent_contact']  ?? null, null, "CFA Issued — $case_no", $resp_msg, $med_id);

                    log_act($pdo, $uid, $bid, 'cfa_issued', $blotter_id, "CFA issued — respondent 2nd miss. $case_no. Complainant may file in court.");
                    $return_msg = "Respondent absent (2nd time). CFA issued to complainant. They may now file in court. Both parties notified.";
                }
            }

            // Update blotter status if changed
            if ($blotter_new_status) {
                $pdo->prepare("UPDATE blotters SET status=?, updated_at=NOW() WHERE id=?")
                    ->execute([$blotter_new_status, $blotter_id]);
            }

            jsonResponse(true, $return_msg);
        }

        jsonResponse(true, 'Outcome recorded.');

    // ══════════════════════════════════════════════════════════════════════════
    // CANCEL (barangay decision — no attendance issue)
    // ══════════════════════════════════════════════════════════════════════════
    case 'cancel':
        $med_id = (int)($input['id'] ?? 0);
        if (!$med_id) jsonResponse(false, 'Invalid ID.');
        $ms = get_ms_and_blotter($pdo, $med_id, $bid);
        if (!$ms) jsonResponse(false, 'Not found.');
        $blotter_id = (int)$ms['bid'];

        $pdo->prepare("UPDATE mediation_schedules SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$med_id]);
        $pdo->prepare("UPDATE blotters SET status='active', updated_at=NOW() WHERE id=?")->execute([$blotter_id]);

        $msg = "The mediation hearing for case {$ms['case_number']} has been cancelled. You will be notified of further actions.";
        notify_both($pdo, $ms, $ms, $bid, $uid, 'mediation_cancelled', "Hearing Cancelled — {$ms['case_number']}", $msg, $msg, $med_id);

        log_act($pdo, $uid, $bid, 'mediation_cancelled', $blotter_id, "Hearing cancelled — {$ms['case_number']}.");
        jsonResponse(true, 'Hearing cancelled. Blotter returned to active. Both parties notified.');

    // ══════════════════════════════════════════════════════════════════════════
    // ADJUST MISSED COUNT (manual correction with reason — emergencies, etc.)
    // ══════════════════════════════════════════════════════════════════════════
    case 'adjust_missed':
        $blotter_id   = (int)($input['blotter_id']      ?? 0);
        $comp_missed  = max(0, (int)($input['comp_missed'] ?? 0));
        $resp_missed  = max(0, (int)($input['resp_missed'] ?? 0));
        $reason       = trim($input['reason'] ?? '');

        if (!$blotter_id) jsonResponse(false, 'Invalid blotter ID.');
        if (!$reason)     jsonResponse(false, 'A reason is required for manual adjustment.');

        // Verify ownership
        $own = $pdo->prepare("SELECT id FROM blotters WHERE id=? AND barangay_id=? LIMIT 1");
        $own->execute([$blotter_id, $bid]);
        if (!$own->fetch()) jsonResponse(false, 'Access denied.');

        $pdo->prepare("UPDATE blotters SET complainant_missed=?, respondent_missed=?, updated_at=NOW() WHERE id=?")
            ->execute([$comp_missed, $resp_missed, $blotter_id]);

        log_act($pdo, $uid, $bid, 'missed_count_adjusted', $blotter_id,
            "Missed counts manually adjusted. Complainant: $comp_missed, Respondent: $resp_missed. Reason: $reason");

        jsonResponse(true, 'Missed session counts updated. Adjustment logged.');

    // ══════════════════════════════════════════════════════════════════════════
    // SEND MANUAL NOTIFICATION to one or both parties
    // ══════════════════════════════════════════════════════════════════════════
    case 'send_notification':
        $blotter_id = (int)($input['blotter_id'] ?? 0);
        $party      = $input['party'] ?? 'both'; // 'complainant' | 'respondent' | 'both'
        $subject    = trim($input['subject'] ?? '');
        $message    = trim($input['message'] ?? '');
        if (!$blotter_id || !$subject || !$message) jsonResponse(false, 'Blotter, subject and message are required.');

        $bl = $pdo->prepare("SELECT * FROM blotters WHERE id=? AND barangay_id=? LIMIT 1");
        $bl->execute([$blotter_id, $bid]); $b = $bl->fetch();
        if (!$b) jsonResponse(false, 'Access denied.');

        $count = 0;
        if ($party !== 'respondent') {
            notify_party($pdo, $blotter_id, $bid, $uid, 'general', 'complainant', $b['complainant_name'], $b['complainant_contact'] ?? null, $b['complainant_user_id'] ? (int)$b['complainant_user_id'] : null, $subject, $message);
            $count++;
        }
        if ($party !== 'complainant' && $b['respondent_name'] && $b['respondent_name'] !== 'Unknown') {
            notify_party($pdo, $blotter_id, $bid, $uid, 'general', 'respondent', $b['respondent_name'], $b['respondent_contact'] ?? null, null, $subject, $message);
            $count++;
        }
        jsonResponse(true, "Notification queued for $count party/parties.");

    // ══════════════════════════════════════════════════════════════════════════
    // MARK NOTIFICATION AS SENT (for SMS gateway or print handler)
    // ══════════════════════════════════════════════════════════════════════════
    case 'mark_notif_sent':
        $notif_id = (int)($input['notif_id'] ?? 0);
        if (!$notif_id) jsonResponse(false, 'Invalid ID.');
        $pdo->prepare("UPDATE party_notifications SET status='sent', sent_at=NOW() WHERE id=?")
            ->execute([$notif_id]);
        jsonResponse(true, 'Notification marked as sent.');

    default:
        jsonResponse(false, 'Unknown action.');
    }

} catch (PDOException $e) {
    error_log('[mediation_action] ' . $e->getMessage());
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
