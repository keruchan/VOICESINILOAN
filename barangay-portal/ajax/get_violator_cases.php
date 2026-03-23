<?php
// ajax/get_violator_cases.php
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$bid  = (int)($_SESSION['barangay_id'] ?? 0);
$name = trim($_GET['name'] ?? '');
if (!$name) jsonResponse(false, 'Name required.');

try {
    $s = $pdo->prepare("
        SELECT id, case_number, incident_type, violation_level, status, created_at
        FROM blotters
        WHERE barangay_id = ? AND respondent_name = ?
        ORDER BY created_at DESC
    ");
    $s->execute([$bid, $name]);
    jsonResponse(true, 'OK', ['cases' => $s->fetchAll()]);
} catch (PDOException $e) {
    jsonResponse(false, 'Database error.');
}
