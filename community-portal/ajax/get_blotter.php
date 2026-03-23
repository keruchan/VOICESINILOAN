<?php
// community-portal/ajax/get_blotter.php
require_once '../../connection/auth.php';
guardRole('community');
header('Content-Type: application/json');

$uid = (int)$_SESSION['user_id'];
$bid = (int)($_SESSION['barangay_id'] ?? 0);
$id  = (int)($_GET['id'] ?? 0);
if (!$id) jsonResponse(false, 'Invalid ID.');

try {
    // Community users can view blotters they filed OR where they are named violator
    $uname = $_SESSION['user_name'] ?? '';

    $s = $pdo->prepare("
        SELECT b.* FROM blotters b
        WHERE b.id = ?
          AND b.barangay_id = ?
          AND (
            b.complainant_user_id = ?
            OR EXISTS (SELECT 1 FROM violations v WHERE v.blotter_id=b.id AND v.user_id=?)
            OR b.respondent_name LIKE ?
          )
        LIMIT 1
    ");
    $s->execute([$id, $bid, $uid, $uid, "%$uname%"]);
    $blotter = $s->fetch();
    if (!$blotter) jsonResponse(false, 'Not found or access denied.');

    // Latest mediation hearing
    $ms = $pdo->prepare("
        SELECT hearing_date, hearing_time, venue, status
        FROM mediation_schedules
        WHERE blotter_id = ? AND status = 'scheduled' AND hearing_date >= CURDATE()
        ORDER BY hearing_date ASC LIMIT 1
    ");
    $ms->execute([$id]);
    $blotter['mediation'] = $ms->fetch() ?: null;

    // Activity log timeline (limited to non-sensitive actions)
    $tl = $pdo->prepare("
        SELECT action, description, created_at
        FROM activity_log
        WHERE entity_type = 'blotter' AND entity_id = ?
        ORDER BY created_at DESC LIMIT 8
    ");
    $tl->execute([$id]);
    $blotter['timeline'] = $tl->fetchAll();

    jsonResponse(true, 'OK', $blotter);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Database error.');
}
