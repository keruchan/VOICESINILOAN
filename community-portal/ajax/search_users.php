<?php
// community-portal/ajax/search_users.php
require_once '../../connection/auth.php';

header('Content-Type: application/json');
ob_clean();

$user = null;
try { $user = currentUser(); } catch (Throwable $e) {}

if (!$user || empty($user['id'])) {
    echo json_encode(['success' => false, 'results' => [], 'debug' => 'Not authenticated']);
    exit;
}

$uid = (int)$user['id'];
$q   = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, full_name
        FROM users
        WHERE full_name LIKE ?
          AND id != ?
          AND role NOT IN ('barangay', 'admin', 'superadmin')
        ORDER BY full_name ASC
        LIMIT 8
    ");
    $stmt->execute(['%' . $q . '%', $uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'results' => array_map(fn($r) => [
            'id'   => (int)$r['id'],
            'name' => $r['full_name'],   // JS always reads .name
        ], $rows),
    ]);
} catch (PDOException $e) {
    error_log('search_users.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'results' => [], 'debug' => $e->getMessage()]);
}
