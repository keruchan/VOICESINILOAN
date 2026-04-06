<?php
// ajax/mediation_action.php
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$bid   = (int)($_SESSION['barangay_id'] ?? 0);
$uid   = (int)($_SESSION['user_id']     ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$act   = $input['action'] ?? '';

function own_blotter_med(PDO $pdo, int $blotter_id, int $bid): bool {
    $s = $pdo->prepare("SELECT id FROM blotters WHERE id=? AND barangay_id=? LIMIT 1");
    $s->execute([$blotter_id, $bid]); return (bool)$s->fetch();
}

function log_med(PDO $pdo, int $uid, int $bid, string $action, int $eid, string $desc): void {
    try {
        $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,?,?,?,?,NOW())")
            ->execute([$uid, $bid, $action, 'blotter', $eid, $desc]);
    } catch (Exception $e) {}
}

// Penalty rules by missed session count (applies to no-show party)
function get_penalty_rule(int $missed_count): array {
    if ($missed_count >= 3) return ['Failure to appear at mediation (3rd offense)', 2000.00, 16];
    if ($missed_count === 2) return ['Failure to appear at mediation (2nd offense)', 1000.00, 8];
    return ['Failure to appear at mediation (1st offense)', 500.00, 4];
}

try {
    switch ($act) {

        // ── Schedule new mediation ──────────────────────────────────
        case 'schedule_mediation':
            $blotter_id = (int)($input['blotter_id'] ?? 0);
            $date  = trim($input['date']  ?? '');
            $time  = trim($input['time']  ?? '');
            $venue = trim($input['venue'] ?? 'Barangay Hall');
            $notes = trim($input['notes'] ?? '');

            if (!$blotter_id || !$date || !$time) jsonResponse(false, 'Blotter, date and time are required.');
            if (!own_blotter_med($pdo, $blotter_id, $bid)) jsonResponse(false, 'Access denied.');

            $pdo->prepare("
                INSERT INTO mediation_schedules
                  (blotter_id, barangay_id, hearing_date, hearing_time, venue, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
            ")->execute([$blotter_id, $bid, $date, $time, $venue]);

            $pdo->prepare("UPDATE blotters SET status='mediation_set', updated_at=NOW() WHERE id=?")
                ->execute([$blotter_id]);

            log_med($pdo, $uid, $bid, 'mediation_scheduled', $blotter_id,
                "Mediation hearing scheduled for " . date('M j, Y', strtotime($date)) . " at $venue");

            jsonResponse(true, 'Mediation hearing scheduled for ' . date('F j, Y', strtotime($date)) . '.');

        // ── Record hearing outcome ──────────────────────────────────
        case 'record_outcome':
            $id      = (int)($input['id'] ?? 0);
            $status  = in_array($input['status'] ?? '', ['completed','missed','cancelled','rescheduled'])
                       ? $input['status'] : 'completed';
            $comp    = isset($input['complainant_attended']) ? (int)$input['complainant_attended'] : 1;
            $resp    = isset($input['respondent_attended'])  ? (int)$input['respondent_attended']  : 1;
            $outcome = trim($input['outcome']         ?? '');
            $next    = trim($input['next_steps']      ?? '');
            $redate  = trim($input['reschedule_date'] ?? '');
            $retime  = trim($input['reschedule_time'] ?? '');

            if (!$id) jsonResponse(false, 'Invalid ID.');

            // Verify this hearing belongs to this barangay
            $ms_row = $pdo->prepare("
                SELECT ms.*, b.id AS blotter_id, b.case_number, b.complainant_name, b.respondent_name
                FROM mediation_schedules ms
                JOIN blotters b ON b.id = ms.blotter_id
                WHERE ms.id = ? AND b.barangay_id = ? LIMIT 1
            ");
            $ms_row->execute([$id, $bid]);
            $ms = $ms_row->fetch();
            if (!$ms) jsonResponse(false, 'Access denied or session not found.');

            // ── Determine who is absent / no_show_by ──
            $no_show_by   = 'none';
            $is_missed    = false;
            $penalty_party = null; // user_id or name of who gets penalised

            if ($status === 'missed') {
                $is_missed = true;
                if (!$comp && !$resp) $no_show_by = 'both';
                elseif (!$comp)       $no_show_by = 'complainant';
                elseif (!$resp)       $no_show_by = 'respondent';
                else                  $no_show_by = 'both'; // both marked present but status forced missed — treat as both
            }

            // Auto-correct: if someone is absent, override to missed
            if ($status === 'completed' && (!$comp || !$resp)) {
                $status = 'missed';
                $is_missed = true;
                $no_show_by = (!$comp && !$resp) ? 'both' : (!$comp ? 'complainant' : 'respondent');
            }

            // ── Update the mediation record ──
            $pdo->prepare("
                UPDATE mediation_schedules
                SET status = ?, complainant_attended = ?, respondent_attended = ?,
                    outcome = ?, next_steps = ?, no_show_by = ?,
                    missed_session = ?, reschedule_date = ?, reschedule_time = ?,
                    notified_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $status, $comp, $resp, $outcome, $next, $no_show_by,
                $is_missed ? 1 : 0,
                $redate ?: null, $retime ?: null,
                $id
            ]);

            $blotter_id = (int)$ms['blotter_id'];

            // ── Handle each outcome ──────────────────────────────────

            if ($status === 'completed') {
                // Resolve blotter
                $pdo->prepare("UPDATE blotters SET status='resolved', updated_at=NOW() WHERE id=?")
                    ->execute([$blotter_id]);
                log_med($pdo, $uid, $bid, 'mediation_completed', $blotter_id,
                    "Mediation completed for {$ms['case_number']}. $outcome");
                jsonResponse(true, 'Hearing marked as completed. Blotter resolved. ✅');
            }

            if ($status === 'cancelled') {
                // Revert blotter to active (don't leave as mediation_set)
                $pdo->prepare("UPDATE blotters SET status='active', updated_at=NOW() WHERE id=?")
                    ->execute([$blotter_id]);
                log_med($pdo, $uid, $bid, 'mediation_cancelled', $blotter_id,
                    "Mediation cancelled for {$ms['case_number']}.");
                jsonResponse(true, 'Mediation cancelled. Blotter returned to active.');
            }

            if ($status === 'rescheduled') {
                // Create a NEW scheduled session for the reschedule date
                if ($redate) {
                    $pdo->prepare("
                        INSERT INTO mediation_schedules
                          (blotter_id, barangay_id, hearing_date, hearing_time, venue, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
                    ")->execute([$blotter_id, $bid, $redate, $retime ?: '09:00:00', $ms['venue'] ?: 'Barangay Hall']);
                }
                log_med($pdo, $uid, $bid, 'mediation_rescheduled', $blotter_id,
                    "Mediation rescheduled for {$ms['case_number']}" . ($redate ? " to " . date('M j, Y', strtotime($redate)) : '') . ".");
                jsonResponse(true, 'Mediation rescheduled' . ($redate ? ' to ' . date('F j, Y', strtotime($redate)) : '') . '. New hearing created.');
            }

            if ($status === 'missed') {
                // Count total missed sessions for this blotter
                $missed_count = (int)$pdo->query("
                    SELECT COUNT(*) FROM mediation_schedules
                    WHERE blotter_id = $blotter_id AND missed_session = 1
                ")->fetchColumn();

                // Update violation missed_hearings counter (if violation record exists)
                $pdo->prepare("
                    UPDATE violations SET missed_hearings = ?,
                    risk_score = LEAST(100, risk_score + 15), updated_at = NOW()
                    WHERE blotter_id = ?
                ")->execute([$missed_count, $blotter_id]);

                // Auto-issue penalty
                [$reason, $amount, $csh] = get_penalty_rule($missed_count);
                $due_date = date('Y-m-d', strtotime('+15 days'));

                $pdo->prepare("
                    INSERT INTO penalties
                      (blotter_id, barangay_id, mediation_schedule_id, missed_party,
                       reason, amount, community_hours, due_date, status, issued_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ")->execute([
                    $blotter_id, $bid, $id, $no_show_by,
                    $reason, $amount, $csh, $due_date, $uid
                ]);

                // Mark penalty issued on the mediation record
                $pdo->prepare("UPDATE mediation_schedules SET penalty_issued=1 WHERE id=?")
                    ->execute([$id]);

                // Escalate blotter if 3+ misses
                if ($missed_count >= 3) {
                    $pdo->prepare("UPDATE blotters SET status='escalated', updated_at=NOW() WHERE id=?")
                        ->execute([$blotter_id]);
                    log_med($pdo, $uid, $bid, 'blotter_escalated', $blotter_id,
                        "Auto-escalated: {$ms['case_number']} has $missed_count missed mediations.");
                }

                $who_label = ['complainant'=>'complainant','respondent'=>'respondent','both'=>'both parties'][$no_show_by] ?? 'absent party';
                log_med($pdo, $uid, $bid, 'mediation_no_show', $blotter_id,
                    "No show recorded for {$ms['case_number']} (miss #{$missed_count}). $no_show_by absent. Penalty ₱{$amount} issued.");

                $msg = "No show recorded. Penalty ₱" . number_format($amount) . " issued to $who_label.";
                if ($missed_count >= 3) $msg .= " ⚠️ Case auto-escalated after 3 misses.";
                jsonResponse(true, $msg);
            }

            jsonResponse(true, 'Outcome recorded.');

        // ── Cancel a hearing (no-show flag NOT set) ─────────────────
        case 'cancel':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid ID.');
            $pdo->prepare("UPDATE mediation_schedules SET status='cancelled', updated_at=NOW() WHERE id=?")
                ->execute([$id]);
            // Revert blotter
            $r = $pdo->prepare("SELECT blotter_id FROM mediation_schedules WHERE id=? LIMIT 1");
            $r->execute([$id]); $row = $r->fetch();
            if ($row) $pdo->prepare("UPDATE blotters SET status='active', updated_at=NOW() WHERE id=?")->execute([$row['blotter_id']]);
            jsonResponse(true, 'Mediation cancelled. Blotter returned to active.');

        // ── Manually issue penalty from History tab ─────────────────
        case 'issue_penalty':
            $med_id   = (int)($input['med_id']  ?? 0);
            $party    = in_array($input['party']??'',['complainant','respondent','both']) ? $input['party'] : 'respondent';
            $amount   = max(0, (float)($input['amount']   ?? 500));
            $csh      = max(0, (int)  ($input['csh']      ?? 0));
            $due_date = trim($input['due_date'] ?? date('Y-m-d', strtotime('+15 days')));
            $reason   = trim($input['reason']   ?? 'Failure to appear at scheduled mediation hearing');
            if (!$med_id) jsonResponse(false, 'Invalid ID.');

            $ms2 = $pdo->prepare("SELECT blotter_id FROM mediation_schedules WHERE id=? LIMIT 1");
            $ms2->execute([$med_id]); $ms2_row = $ms2->fetch();
            if (!$ms2_row) jsonResponse(false, 'Session not found.');

            $bid2 = (int)$pdo->query("SELECT barangay_id FROM blotters WHERE id={$ms2_row['blotter_id']} LIMIT 1")->fetchColumn();
            if ($bid2 !== $bid) jsonResponse(false, 'Access denied.');

            $pdo->prepare("
                INSERT INTO penalties
                  (blotter_id, barangay_id, mediation_schedule_id, missed_party,
                   reason, amount, community_hours, due_date, status, issued_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ")->execute([$ms2_row['blotter_id'], $bid, $med_id, $party, $reason, $amount, $csh, $due_date, $uid]);

            $pdo->prepare("UPDATE mediation_schedules SET penalty_issued=1 WHERE id=?")->execute([$med_id]);

            jsonResponse(true, "Penalty of ₱" . number_format($amount) . " issued to $party.");

        default:
            jsonResponse(false, 'Unknown action.');
    }

} catch (PDOException $e) {
    error_log('[mediation_action] ' . $e->getMessage());
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
