<?php
/**
 * ajax/export_users.php
 * Exports filtered user list as CSV
 */
require_once '../../connection/auth.php';
guardRole('superadmin');

$role     = $_GET['role']     ?? '';
$status   = $_GET['filter']   ?? '';
$barangay = (int)($_GET['barangay'] ?? 0);
$search   = $_GET['search']   ?? '';
$is_preview = isset($_GET['preview']);

$where  = ["u.role != 'superadmin'"];
$params = [];

if ($role) { $where[] = 'u.role = ?'; $params[] = $role; }
if ($status === 'pending')   $where[] = 'u.is_active = 0';
elseif ($status === 'active')    $where[] = 'u.is_active = 1';
elseif ($status === 'suspended') $where[] = 'u.is_active = 2';
if ($barangay) { $where[] = 'u.barangay_id = ?'; $params[] = $barangay; }
if ($search) {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$where_sql = 'WHERE '.implode(' AND ', $where);

$columns = ['Full Name','Email','Contact','Role','Status','Barangay','Registered'];
try {
    $total = null;
    if ($is_preview) {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN barangays b ON b.id=u.barangay_id $where_sql");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
    }
    $limit = $is_preview ? ' LIMIT 25' : '';
    $stmt = $pdo->prepare("SELECT u.full_name, u.email, u.contact_number, u.role, CASE u.is_active WHEN 1 THEN 'Active' WHEN 0 THEN 'Pending' WHEN 2 THEN 'Suspended' END as status, b.name as barangay, u.created_at FROM users u LEFT JOIN barangays b ON b.id=u.barangay_id $where_sql ORDER BY u.created_at DESC$limit");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch(PDOException $e) { die('Export failed.'); }

if ($is_preview) {
    $download = $_GET;
    unset($download['preview']);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'title' => 'Users Export',
        'total' => $total,
        'preview_count' => count($rows),
        'columns' => $columns,
        'rows' => array_map(fn($r) => [$r['full_name'],$r['email'],$r['contact_number'],$r['role'],$r['status'],$r['barangay'],$r['created_at']], $rows),
        'download_url' => 'ajax/export_users.php?' . http_build_query($download),
    ]);
    exit;
}

$filename = 'users_export_'.date('Ymd_His').'.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$fp = fopen('php://output', 'w');
fputcsv($fp, $columns);
foreach ($rows as $r) {
    fputcsv($fp, [$r['full_name'],$r['email'],$r['contact_number'],$r['role'],$r['status'],$r['barangay'],$r['created_at']]);
}
fclose($fp);
