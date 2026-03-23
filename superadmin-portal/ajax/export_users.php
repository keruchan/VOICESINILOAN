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

$where  = ["u.role != 'superadmin'"];
$params = [];

if ($role) { $where[] = 'u.role = ?'; $params[] = $role; }
if ($status === 'pending')   $where[] = 'u.is_active = 0';
elseif ($status === 'active')    $where[] = 'u.is_active = 1';
elseif ($status === 'suspended') $where[] = 'u.is_active = 2';
if ($barangay) { $where[] = 'u.barangay_id = ?'; $params[] = $barangay; }
if ($search) {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}

$where_sql = 'WHERE '.implode(' AND ', $where);

try {
    $stmt = $pdo->prepare("SELECT u.full_name, u.email, u.contact_number, u.role, CASE u.is_active WHEN 1 THEN 'Active' WHEN 0 THEN 'Pending' WHEN 2 THEN 'Suspended' END as status, b.name as barangay, u.created_at FROM users u LEFT JOIN barangays b ON b.id=u.barangay_id $where_sql ORDER BY u.created_at DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch(PDOException $e) { die('Export failed.'); }

$filename = 'users_export_'.date('Ymd_His').'.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$fp = fopen('php://output', 'w');
fputcsv($fp, ['Full Name','Email','Contact','Role','Status','Barangay','Registered']);
foreach ($rows as $r) {
    fputcsv($fp, [$r['full_name'],$r['email'],$r['contact_number'],$r['role'],$r['status'],$r['barangay'],$r['created_at']]);
}
fclose($fp);
