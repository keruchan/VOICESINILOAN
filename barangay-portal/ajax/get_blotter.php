<?php
// ajax/get_blotter.php
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

// Prevent any stray output from corrupting JSON
ob_clean();

$bid = (int)($_SESSION['barangay_id'] ?? 0);
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit; }

try {
    $s = $pdo->prepare("SELECT * FROM blotters WHERE id = ? AND barangay_id = ? LIMIT 1");
    $s->execute([$id, $bid]);
    $b = $s->fetch(PDO::FETCH_ASSOC);
    if (!$b) { echo json_encode(['success'=>false,'message'=>'Not found or access denied.']); exit; }

    // Activity log timeline
    $tl = $pdo->prepare("
        SELECT action, description, created_at
        FROM activity_log
        WHERE entity_type = 'blotter' AND entity_id = ?
        ORDER BY created_at DESC LIMIT 20
    ");
    $tl->execute([$id]);
    $b['timeline'] = $tl->fetchAll(PDO::FETCH_ASSOC);

    // Attachments
    $att = $pdo->prepare("
        SELECT id, file_path, original_name, file_size, mime_type, created_at
        FROM blotter_attachments
        WHERE blotter_id = ?
        ORDER BY created_at ASC
    ");
    $att->execute([$id]);
    $b['attachments'] = $att->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Mediation sessions (for Case Report)
    $meds = $pdo->prepare("
        SELECT id, hearing_date, hearing_time, venue, status,
               complainant_attended, respondent_attended, outcome, next_steps, created_at
        FROM mediation_schedules
        WHERE blotter_id = ?
        ORDER BY hearing_date ASC
    ");
    $meds->execute([$id]);
    $b['mediation_sessions'] = $meds->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Recording officer name
    if (!empty($b['filed_by_user_id'])) {
        $off = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $off->execute([$b['filed_by_user_id']]);
        $b['filed_by_name'] = $off->fetchColumn() ?: null;
    } else {
        $b['filed_by_name'] = null;
    }

    echo json_encode(['success'=>true,'message'=>'OK','data'=>$b]);
} catch (PDOException $e) {
    error_log('get_blotter.php: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Could not load the case. Please try again.']);
}
