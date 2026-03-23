<?php
// ajax/get_blotter.php
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$bid = (int)($_SESSION['barangay_id'] ?? 0);
$id  = (int)($_GET['id'] ?? 0);
if (!$id) jsonResponse(false, 'Invalid ID.');

try {
    $s = $pdo->prepare("SELECT * FROM blotters WHERE id = ? AND barangay_id = ? LIMIT 1");
    $s->execute([$id, $bid]);
    $b = $s->fetch();
    if (!$b) jsonResponse(false, 'Not found or access denied.');

    // Activity log timeline
    $tl = $pdo->prepare("SELECT action, description, created_at FROM activity_log WHERE entity_type='blotter' AND entity_id=? ORDER BY created_at DESC LIMIT 10");
    $tl->execute([$id]);
    $b['timeline'] = $tl->fetchAll();

    jsonResponse(true, 'OK', $b);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Database error.');
}
