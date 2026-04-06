<?php
// barangay-portal/ajax/search_users.php
require_once '../../connection/auth.php';
guardRole('barangay');

header('Content-Type: application/json');
ob_clean();

$user = null;
try { $user = currentUser(); } catch (Throwable $e) {}
if (!$user || empty($user['id'])) {
    echo json_encode(['success' => false, 'results' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    // Search community users only — officers don't report other officers
    $stmt = $pdo->prepare("
        SELECT id, full_name
        FROM users
        WHERE full_name LIKE ?
          AND role NOT IN ('barangay', 'admin', 'superadmin')
        ORDER BY full_name ASC
        LIMIT 8
    ");
    $stmt->execute(['%' . $q . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'results' => array_map(fn($r) => [
            'id'   => (int)$r['id'],
            'name' => $r['full_name'],
        ], $rows),
    ]);
} catch (PDOException $e) {
    error_log('bgy search_users.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'results' => []]);
}
