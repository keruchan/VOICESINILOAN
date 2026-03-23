<?php
// ajax/mediation_action.php — columns: hearing_date, hearing_time, venue, outcome, next_steps
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

try {
    switch ($act) {

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

            $pdo->prepare("UPDATE blotters SET status='mediation_set', updated_at=NOW() WHERE id=?")->execute([$blotter_id]);

            jsonResponse(true, 'Mediation hearing scheduled for ' . date('F j, Y', strtotime($date)) . '.');

        case 'record_outcome':
            $id     = (int)($input['id'] ?? 0);
            $status = in_array($input['status']??'',['completed','missed','cancelled','rescheduled']) ? $input['status'] : 'completed';
            $comp   = (int)($input['complainant_attended'] ?? 1);
            $resp   = (int)($input['respondent_attended']  ?? 1);
            $outcome= trim($input['outcome']    ?? '');
            $next   = trim($input['next_steps'] ?? '');
            if (!$id) jsonResponse(false, 'Invalid ID.');

            $pdo->prepare("
                UPDATE mediation_schedules
                SET status=?, complainant_attended=?, respondent_attended=?,
                    outcome=?, next_steps=?, updated_at=NOW()
                WHERE id=?
            ")->execute([$status, $comp, $resp, $outcome, $next, $id]);

            // Auto-resolve blotter when completed
            if ($status === 'completed') {
                $r = $pdo->prepare("SELECT blotter_id FROM mediation_schedules WHERE id=? LIMIT 1");
                $r->execute([$id]); $row = $r->fetch();
                if ($row) {
                    $pdo->prepare("UPDATE blotters SET status='resolved', updated_at=NOW() WHERE id=?")->execute([$row['blotter_id']]);
                    try { $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,'mediation_completed','blotter',?,?,NOW())")->execute([$uid,$bid,$row['blotter_id'],'Mediation completed — ' . $outcome]); } catch(Exception $e){}
                }
            }
            jsonResponse(true, 'Hearing outcome recorded.');

        case 'cancel':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid ID.');
            $pdo->prepare("UPDATE mediation_schedules SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
            jsonResponse(true, 'Mediation cancelled.');

        default:
            jsonResponse(false, 'Unknown action.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Database error.');
}
