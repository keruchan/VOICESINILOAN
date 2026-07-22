<?php
// ajax/mediation_action.php — KP Law compliant mediation process
require_once '../../connection/auth.php';
require_once '../../connection/mailer.php'; // sendAppMail() — same mailer used by the email-verification flow
guardRole('barangay');
header('Content-Type: application/json');

$bid = (int)($_SESSION['barangay_id'] ?? 0);
$uid = (int)($_SESSION['user_id']     ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$act   = $input['action'] ?? '';

finalize_due_settlements($pdo, $bid);

// ── Helpers ──────────────────────────────────────────────────────────────────

function log_act(PDO $pdo, int $uid, int $bid, string $action, int $blotter_id, string $desc): void {
    try {
        $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,?,?,?,?,NOW())")
            ->execute([$uid, $bid, $action, 'blotter', $blotter_id, $desc]);
    } catch (Exception $e) {}
}

/**
 * Build and send the branded hearing-schedule email through sendAppMail()
 * (the same mailer used by the email-verification/OTP flow — mailer.php
 * itself is untouched, this just reuses it for a different message).
 */
function send_hearing_email(string $email, string $name, string $subject, string $message, array $hearing = []): bool {
    $date_block = '';
    if (!empty($hearing['date'])) {
        $date_block = "
          <div style='background:#E8FAFB;border:1px solid #D0F3F5;border-radius:8px;padding:14px 18px;margin:16px 0'>
            <div style='font-size:17px;font-weight:700;color:#0A5E62'>" . htmlspecialchars($hearing['date'], ENT_QUOTES) . "</div>"
            . (!empty($hearing['time']) ? "<div style='font-size:14px;color:#0D7377;font-weight:600;margin-top:2px'>" . htmlspecialchars($hearing['time'], ENT_QUOTES) . "</div>" : '')
            . "<div style='font-size:12px;color:#4D6580;margin-top:4px'>&#128205; " . htmlspecialchars($hearing['venue'] ?? 'Barangay Hall', ENT_QUOTES) . "</div>
          </div>";
    }
    $case_line = !empty($hearing['case_number'])
        ? "<p style='font-size:12px;color:#666'>Case No.: <strong>" . htmlspecialchars($hearing['case_number'], ENT_QUOTES) . "</strong></p>"
        : '';

    $body = "
      <div style='font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;color:#0F1C2E'>
        <div style='background:#0D1B2E;padding:20px 24px;border-radius:8px 8px 0 0'>
          <span style='color:#2EBAC6;font-size:18px;font-weight:bold'>VOICE</span>
          <span style='color:#fff;font-size:12px;margin-left:8px'>Barangay Mediation Notice</span>
        </div>
        <div style='border:1px solid #eee;border-top:none;padding:24px;border-radius:0 0 8px 8px'>
          <p>Dear " . htmlspecialchars($name, ENT_QUOTES) . ",</p>
          <p>" . nl2br(htmlspecialchars($message, ENT_QUOTES)) . "</p>
          $date_block
          $case_line
          <p style='font-size:12px;color:#666;margin-top:20px'>This is an automated notice from your barangay's mediation schedule. For questions, please contact your barangay hall directly.</p>
        </div>
      </div>
    ";

    return sendAppMail($email, $name, $subject, $body);
}

/**
 * Queue a notification for a party (complainant or respondent) and, for
 * hearing-schedule notices, also email them for real if they have a linked
 * account with an email on file. Registered users always see the notice
 * in-app (Notices & Sanctions / the bell) regardless of email delivery.
 *
 * @return bool true if a real email was sent to this party.
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
    ?int $med_id = null,
    array $hearing = []     // ['date','time','venue','case_number'] — for schedule-type emails
): bool {
    $channel      = $contact ? 'inapp,sms' : 'inapp';
    $status       = 'pending';
    $sent_at      = null;
    $email_sent   = false;
    $schedule_types = ['hearing_scheduled', 'hearing_rescheduled', 'hearing_reminder'];

    if ($user_id && in_array($type, $schedule_types, true)) {
        try {
            $u = $pdo->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
            $u->execute([$user_id]);
            $email = $u->fetchColumn();
            if ($email && send_hearing_email($email, $name, $subject, $message, $hearing)) {
                $email_sent = true;
                $channel   .= ',email';
                $status     = 'sent';
                $sent_at    = date('Y-m-d H:i:s');
            }
        } catch (PDOException $ex) { error_log('[notify_party email] ' . $ex->getMessage()); }
    }

    try {
        $pdo->prepare("
            INSERT INTO party_notifications
              (blotter_id, mediation_schedule_id, barangay_id,
               recipient_type, recipient_user_id, recipient_name, recipient_contact,
               notification_type, subject, message, channel, status, sent_at, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ")->execute([
            $blotter_id, $med_id, $bid,
            $party, $user_id, $name, $contact,
            $type, $subject, $message, $channel, $status, $sent_at, $uid
        ]);
    } catch (PDOException $ex) { error_log('[notify_party] ' . $ex->getMessage()); }

    return $email_sent;
}

/**
 * Notify BOTH parties from a single call using blotter contact data.
 * @return int number of real emails sent (0–2).
 */
function notify_both(PDO $pdo, array $ms, array $b, int $bid, int $uid, string $type, string $subject, string $comp_msg, string $resp_msg, ?int $med_id = null, array $hearing = []): int {
    $emailed = 0;

    if (notify_party($pdo, (int)$b['id'], $bid, $uid, $type, 'complainant',
        $b['complainant_name'], $b['complainant_contact'] ?? null,
        $b['complainant_user_id'] ? (int)$b['complainant_user_id'] : null,
        $subject, $comp_msg, $med_id, $hearing)) $emailed++;

    if ($b['respondent_name'] && $b['respondent_name'] !== 'Unknown') {
        if (notify_party($pdo, (int)$b['id'], $bid, $uid, $type, 'respondent',
            $b['respondent_name'], $b['respondent_contact'] ?? null,
            $b['respondent_user_id'] ? (int)$b['respondent_user_id'] : null,
            $subject, $resp_msg, $med_id, $hearing)) $emailed++;
    }

    return $emailed;
}

function fdate(string $d): string { return date('F j, Y', strtotime($d)); }
function ftime(string $t): string { return date('g:i A', strtotime($t)); }

// ── Fetch full blotter from a mediation schedule ID ──────────────────────────
function get_ms_and_blotter(PDO $pdo, int $med_id, int $bid): ?array {
    $s = $pdo->prepare("
        SELECT ms.*, b.id AS bid, b.case_number, b.complainant_name, b.complainant_contact,
               b.complainant_user_id, b.respondent_name, b.respondent_contact, b.respondent_user_id,
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
        $hearing  = ['date' => $date_fmt, 'time' => $time_fmt, 'venue' => $venue, 'case_number' => $b['case_number']];
        $emailed  = notify_both($pdo, [], $b, $bid, $uid, 'hearing_scheduled', "Mediation Hearing Scheduled — {$b['case_number']}", $comp_msg, $resp_msg, $new_med_id, $hearing);

        log_act($pdo, $uid, $bid, 'mediation_scheduled', $blotter_id, "Hearing scheduled for $date_fmt at $venue.");
        jsonResponse(true, "Hearing scheduled for $date_fmt. Both parties notified" . ($emailed > 0 ? " ($emailed by email)." : '.'));

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
        $terms   = trim($input['settlement_terms'] ?? '');

        if (!$med_id) jsonResponse(false, 'Invalid session ID.');
        if ($status === 'completed' && (int)($input['complainant_attended'] ?? 1) && (int)($input['respondent_attended'] ?? 1) && $terms === '')
            jsonResponse(false, 'Settlement terms are required to record a completed mediation (Kasunduang Pag-aayos).');

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

            // ── Record the Amicable Settlement (Kasunduang Pag-aayos) ──
            // Sec. 416, RA 7160: the settlement has the force of a final court
            // judgment if not repudiated within 10 days of signing.
            $settled_date = date('Y-m-d');
            $deadline     = date('Y-m-d', strtotime('+10 days'));
            $pdo->prepare("
                INSERT INTO amicable_settlements
                  (blotter_id, mediation_schedule_id, barangay_id, terms, settled_date, repudiation_deadline, status, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
            ")->execute([$blotter_id, $med_id, $bid, $terms, $settled_date, $deadline, $uid]);

            $deadline_fmt = fdate($deadline);
            $msg = "Case $case_no has been resolved through mediation. Your Kasunduang Pag-aayos (Amicable Settlement) is now on record. Either party may repudiate it in writing at the Barangay Hall on valid grounds (fraud, violence, intimidation, or mistake of fact) on or before $deadline_fmt. If not repudiated by then, the settlement becomes final and enforceable as a court judgment.";
            notify_both($pdo, $ms, $ms, $bid, $uid, 'mediation_completed', "Mediation Completed — $case_no", $msg, $msg, $med_id);
            jsonResponse(true, "Mediation completed. Settlement recorded — repudiation window open until $deadline_fmt. Both parties notified. ✅");
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
            $hearing    = ['date' => $redate_fmt, 'time' => $retime_fmt, 'venue' => $venue_fmt, 'case_number' => $case_no];
            $emailed    = notify_both($pdo, $ms, $ms, $bid, $uid, 'hearing_rescheduled', "Hearing Rescheduled — $case_no", $comp_msg, $resp_msg, $new_id, $hearing);

            jsonResponse(true, "Hearing rescheduled to " . fdate($redate) . ". New session created. Both parties notified" . ($emailed > 0 ? " ($emailed by email)." : '.'));
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
                        $venue_fmt  = $ms['venue'] ?: 'Barangay Hall';
                        $comp_warn  = "Dear {$ms['complainant_name']}, you failed to appear at the mediation hearing for case $case_no. This is your FIRST missed session. A new hearing has been scheduled on $redate_fmt at $retime_fmt. A second absence may result in case dismissal. Please attend.";
                        $resp_info  = "Dear {$ms['respondent_name']}, the complainant did not appear at today's hearing for case $case_no. The hearing has been rescheduled to $redate_fmt at $retime_fmt.";
                        $hearing2   = ['date' => $redate_fmt, 'time' => $retime_fmt, 'venue' => $venue_fmt, 'case_number' => $case_no];

                        notify_party($pdo, $blotter_id, $bid, $uid, 'no_show_warning', 'complainant', $ms['complainant_name'], $ms['complainant_contact'] ?? null, $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null, "⚠️ Missed Hearing Warning — $case_no", $comp_warn, $new_id2);
                        notify_party($pdo, $blotter_id, $bid, $uid, 'hearing_rescheduled', 'respondent', $ms['respondent_name'], $ms['respondent_contact'] ?? null, $ms['respondent_user_id'] ? (int)$ms['respondent_user_id'] : null, "Hearing Rescheduled — $case_no", $resp_info, $new_id2, $hearing2);

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
                    notify_party($pdo, $blotter_id, $bid, $uid, 'case_dismissed', 'respondent',  $ms['respondent_name'],  $ms['respondent_contact']  ?? null, $ms['respondent_user_id'] ? (int)$ms['respondent_user_id'] : null, "Case Dismissed — $case_no", $resp_msg, $med_id);

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
                        $venue_fmt  = $ms['venue'] ?: 'Barangay Hall';
                        $resp_warn  = "Dear {$ms['respondent_name']}, you failed to appear at the mediation hearing for case $case_no. This is your FIRST missed session. A new hearing has been scheduled on $redate_fmt at $retime_fmt. A second absence may result in a Certification to File Action (CFA) being issued to the complainant, allowing them to pursue this case in court.";
                        $comp_info  = "Dear {$ms['complainant_name']}, the respondent did not appear at today's hearing for case $case_no. The hearing has been rescheduled to $redate_fmt at $retime_fmt.";
                        $hearing3   = ['date' => $redate_fmt, 'time' => $retime_fmt, 'venue' => $venue_fmt, 'case_number' => $case_no];

                        notify_party($pdo, $blotter_id, $bid, $uid, 'no_show_warning', 'respondent',  $ms['respondent_name'],  $ms['respondent_contact']  ?? null, $ms['respondent_user_id'] ? (int)$ms['respondent_user_id'] : null, "⚠️ Final Warning — $case_no", $resp_warn, $new_id3);
                        notify_party($pdo, $blotter_id, $bid, $uid, 'hearing_rescheduled', 'complainant', $ms['complainant_name'], $ms['complainant_contact'] ?? null, $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null, "Hearing Rescheduled — $case_no", $comp_info, $new_id3, $hearing3);

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
                    notify_party($pdo, $blotter_id, $bid, $uid, 'cfa_issued', 'respondent',  $ms['respondent_name'],  $ms['respondent_contact']  ?? null, $ms['respondent_user_id'] ? (int)$ms['respondent_user_id'] : null, "CFA Issued — $case_no", $resp_msg, $med_id);

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
    // NOTIFY PARTIES — INFO for the management modal: autofilled per-party
    // messages, contact/email/account status, and this hearing's send history.
    // ══════════════════════════════════════════════════════════════════════════
    case 'notify_hearing_info':
        $med_id = (int)($input['id'] ?? 0);
        if (!$med_id) jsonResponse(false, 'Invalid session ID.');
        $ms = get_ms_and_blotter($pdo, $med_id, $bid);
        if (!$ms) jsonResponse(false, 'Session not found or access denied.');

        $date_fmt = fdate($ms['hearing_date']);
        $time_fmt = $ms['hearing_time'] ? ftime($ms['hearing_time']) : 'TBD';
        $venue    = $ms['venue'] ?: 'Barangay Hall';
        $has_respondent = (bool)($ms['respondent_name'] && $ms['respondent_name'] !== 'Unknown');

        $lookup_email = function (?int $user_id) use ($pdo): ?string {
            if (!$user_id) return null;
            $e = $pdo->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
            $e->execute([$user_id]);
            return $e->fetchColumn() ?: null;
        };
        $comp_user_id = $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null;
        $resp_user_id = $ms['respondent_user_id']  ? (int)$ms['respondent_user_id']  : null;

        // Full send history tied to this specific hearing schedule
        $hist = $pdo->prepare("
            SELECT recipient_type, notification_type, channel, status, created_at, sent_at
            FROM party_notifications
            WHERE mediation_schedule_id = ?
            ORDER BY created_at DESC
        ");
        $hist->execute([$med_id]);
        $history = $hist->fetchAll(PDO::FETCH_ASSOC);

        $summarize = function (string $party) use ($history): array {
            $rows = array_values(array_filter($history, fn($h) => $h['recipient_type'] === $party));
            $emailed = count(array_filter($rows, fn($h) => str_contains($h['channel'] ?? '', 'email') && $h['status'] === 'sent'));
            return [
                'attempts'   => count($rows),
                'emailed'    => $emailed,
                'last_sent'  => $rows[0]['created_at'] ?? null,
            ];
        };

        jsonResponse(true, '', [
            'case_number' => $ms['case_number'],
            'status'      => $ms['status'],
            'hearing'     => ['date' => $date_fmt, 'time' => $time_fmt, 'venue' => $venue],
            'complainant' => array_merge([
                'name'    => $ms['complainant_name'],
                'contact' => $ms['complainant_contact'],
                'linked'  => (bool)$comp_user_id,
                'email'   => $lookup_email($comp_user_id),
                'message' => "Dear {$ms['complainant_name']}, this is a reminder that your mediation hearing for case {$ms['case_number']} is scheduled on $date_fmt at $time_fmt at $venue. Please make sure to attend.",
            ], $summarize('complainant')),
            'respondent'  => $has_respondent ? array_merge([
                'name'    => $ms['respondent_name'],
                'contact' => $ms['respondent_contact'],
                'linked'  => (bool)$resp_user_id,
                'email'   => $lookup_email($resp_user_id),
                'message' => "Dear {$ms['respondent_name']}, this is a reminder that you are required to appear at a mediation hearing for case {$ms['case_number']} on $date_fmt at $time_fmt at $venue.",
            ], $summarize('respondent')) : null,
            'history' => $history,
        ]);

    // ══════════════════════════════════════════════════════════════════════════
    // NOTIFY PARTIES of a mediation schedule — real email (if the party has a
    // linked account with an email on file) + in-account notice. Recipients
    // and message text are chosen from the management modal.
    // ══════════════════════════════════════════════════════════════════════════
    case 'notify_hearing':
        $med_id = (int)($input['id'] ?? 0);
        if (!$med_id) jsonResponse(false, 'Invalid session ID.');
        $ms = get_ms_and_blotter($pdo, $med_id, $bid);
        if (!$ms) jsonResponse(false, 'Session not found or access denied.');
        if ($ms['status'] !== 'scheduled') jsonResponse(false, 'This hearing is no longer scheduled — nothing to notify.');

        $recipients = $input['recipients'] ?? ['complainant', 'respondent'];
        if (!is_array($recipients) || empty($recipients)) jsonResponse(false, 'Select at least one recipient.');
        $notify_comp = in_array('complainant', $recipients, true);
        $notify_resp = in_array('respondent', $recipients, true);

        $date_fmt = fdate($ms['hearing_date']);
        $time_fmt = $ms['hearing_time'] ? ftime($ms['hearing_time']) : 'TBD';
        $venue    = $ms['venue'] ?: 'Barangay Hall';
        $hearing  = ['date' => $date_fmt, 'time' => $time_fmt, 'venue' => $venue, 'case_number' => $ms['case_number']];
        $subject  = "Mediation Hearing Reminder — {$ms['case_number']}";

        $default_comp_msg = "Dear {$ms['complainant_name']}, this is a reminder that your mediation hearing for case {$ms['case_number']} is scheduled on $date_fmt at $time_fmt at $venue. Please make sure to attend.";
        $default_resp_msg = "Dear {$ms['respondent_name']}, this is a reminder that you are required to appear at a mediation hearing for case {$ms['case_number']} on $date_fmt at $time_fmt at $venue.";
        $comp_msg = trim($input['comp_message'] ?? '') ?: $default_comp_msg;
        $resp_msg = trim($input['resp_message'] ?? '') ?: $default_resp_msg;

        $emailed = 0; $notified_count = 0;

        if ($notify_comp) {
            if (notify_party($pdo, (int)$ms['bid'], $bid, $uid, 'hearing_reminder', 'complainant',
                $ms['complainant_name'], $ms['complainant_contact'] ?? null,
                $ms['complainant_user_id'] ? (int)$ms['complainant_user_id'] : null,
                $subject, $comp_msg, $med_id, $hearing)) $emailed++;
            $notified_count++;
        }
        if ($notify_resp && $ms['respondent_name'] && $ms['respondent_name'] !== 'Unknown') {
            if (notify_party($pdo, (int)$ms['bid'], $bid, $uid, 'hearing_reminder', 'respondent',
                $ms['respondent_name'], $ms['respondent_contact'] ?? null,
                $ms['respondent_user_id'] ? (int)$ms['respondent_user_id'] : null,
                $subject, $resp_msg, $med_id, $hearing)) $emailed++;
            $notified_count++;
        }
        if ($notified_count === 0) jsonResponse(false, 'No valid recipient to notify (respondent may be unidentified).');

        log_act($pdo, $uid, $bid, 'hearing_notification_sent', (int)$ms['bid'], "Manual hearing reminder sent for {$ms['case_number']} to $notified_count part(y/ies). $emailed email(s) delivered.");

        $party_word = $notified_count > 1 ? 'parties' : 'party';
        if ($emailed > 0) {
            jsonResponse(true, "Reminder sent to $notified_count $party_word — $emailed by email. Also recorded in their account.");
        } else {
            jsonResponse(true, "Reminder recorded in-app for $notified_count $party_word. No linked email on file, so nothing was sent by email.");
        }

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
    // REPUDIATE AMICABLE SETTLEMENT (Sec. 416/418, RA 7160)
    // A party may reject the settlement within 10 days of signing, on grounds
    // of fraud, violence, intimidation, or mistake of fact. Case reopens and
    // a penalty is issued against the repudiating party (sanctions_book:
    // "Violation of Amicable Settlement").
    // ══════════════════════════════════════════════════════════════════════════
    case 'repudiate_settlement':
        $settlement_id = (int)($input['settlement_id'] ?? 0);
        $party         = in_array($input['party'] ?? '', ['complainant','respondent']) ? $input['party'] : '';
        $reason        = trim($input['reason'] ?? '');

        if (!$settlement_id) jsonResponse(false, 'Invalid settlement ID.');
        if (!$party)         jsonResponse(false, 'Select which party is repudiating.');
        if (!$reason)        jsonResponse(false, 'A ground for repudiation is required (fraud, violence, intimidation, or mistake of fact).');

        $s = $pdo->prepare("
            SELECT s.*, b.case_number, b.complainant_name, b.complainant_contact, b.complainant_user_id,
                   b.respondent_name, b.respondent_contact, b.respondent_user_id
            FROM amicable_settlements s
            JOIN blotters b ON b.id = s.blotter_id
            WHERE s.id = ? AND s.barangay_id = ?
            LIMIT 1
        ");
        $s->execute([$settlement_id, $bid]);
        $st = $s->fetch();
        if (!$st) jsonResponse(false, 'Settlement not found or access denied.');
        if ($st['status'] !== 'active') jsonResponse(false, 'This settlement is no longer open for repudiation (already ' . $st['status'] . ').');
        if (date('Y-m-d') > $st['repudiation_deadline']) jsonResponse(false, 'The 10-day repudiation window has lapsed. The settlement is now final.');

        $blotter_id = (int)$st['blotter_id'];
        $case_no    = $st['case_number'];

        $pdo->prepare("
            UPDATE amicable_settlements
            SET status='repudiated', repudiated_by=?, repudiation_reason=?, repudiated_at=NOW(), updated_at=NOW()
            WHERE id=?
        ")->execute([$party, $reason, $settlement_id]);

        $pdo->prepare("UPDATE blotters SET status='repudiated', updated_at=NOW() WHERE id=?")->execute([$blotter_id]);

        // Fine amount from this barangay's sanctions catalog (falls back to the KP default of ₱1000)
        $fine = $pdo->prepare("SELECT fine_amount, community_hours FROM sanctions_book WHERE barangay_id=? AND violation_type='Settlement' AND sanction_name LIKE '%Violation of Amicable Settlement%' AND is_active=1 LIMIT 1");
        $fine->execute([$bid]);
        $frow = $fine->fetch();
        $amount = $frow ? (float)$frow['fine_amount'] : 1000.00;
        $hours  = $frow ? (int)$frow['community_hours'] : 0;

        $pdo->prepare("
            INSERT INTO penalties (blotter_id, mediation_schedule_id, missed_party, barangay_id, reason, amount, community_hours, due_date, status, issued_by, created_at)
            VALUES (?, ?, ?, ?, 'Repudiation of amicable settlement', ?, ?, ?, 'pending', ?, NOW())
        ")->execute([$blotter_id, $st['mediation_schedule_id'], $party, $bid, $amount, $hours, date('Y-m-d', strtotime('+14 days')), $uid]);

        $party_label = ucfirst($party);
        $msg = "The Amicable Settlement for case $case_no has been repudiated by the $party_label within the 10-day period (Sec. 416/418, RA 7160). Reason on record: \"$reason\". The case is reopened; a new mediation hearing or further barangay action may follow.";
        notify_both($pdo, $st, $st, $bid, $uid, 'settlement_repudiated', "Settlement Repudiated — $case_no", $msg, $msg);

        log_act($pdo, $uid, $bid, 'settlement_repudiated', $blotter_id, "Settlement repudiated by $party. $case_no. Reason: $reason. Penalty ₱" . number_format($amount,2) . " issued.");
        jsonResponse(true, "Settlement repudiated by $party_label. Case reopened. Penalty of ₱" . number_format($amount,2) . " issued. Both parties notified.");

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
            notify_party($pdo, $blotter_id, $bid, $uid, 'general', 'respondent', $b['respondent_name'], $b['respondent_contact'] ?? null, $b['respondent_user_id'] ? (int)$b['respondent_user_id'] : null, $subject, $message);
            $count++;
        }
        jsonResponse(true, "Notification queued for $count party/parties.");

    default:
        jsonResponse(false, 'Unknown action.');
    }

} catch (PDOException $e) {
    error_log('[mediation_action] ' . $e->getMessage());
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
