<?php
// ajax/export_blotters.php
require_once '../../connection/auth.php';
guardRole(['barangay','superadmin']);

$bid    = (int)($_GET['barangay_id'] ?? $_SESSION['barangay_id'] ?? 0);
$status = $_GET['status'] ?? '';
$level  = $_GET['level']  ?? '';
$search = $_GET['search'] ?? '';

$where  = ["barangay_id = $bid"]; $params = [];
if ($status === 'archived') $where[] = "status IN ('resolved','closed','transferred')";
elseif ($status)            { $where[] = 'status = ?'; $params[] = $status; }
if ($level)  { $where[] = 'violation_level = ?'; $params[] = $level; }
if ($search) { $where[] = '(case_number LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ?)'; $like="%$search%"; $params=array_merge($params,[$like,$like,$like]); }
$ws = 'WHERE ' . implode(' AND ', $where);

try {
    $s = $pdo->prepare("SELECT case_number, complainant_name, complainant_contact, respondent_name, respondent_contact, incident_type, violation_level, prescribed_action, status, incident_date, created_at FROM blotters $ws ORDER BY created_at DESC");
    $s->execute($params); $rows = $s->fetchAll();
} catch (PDOException $e) { die('Export failed.'); }

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="blotters_' . date('Ymd_His') . '.csv"');
$fp = fopen('php://output', 'w');
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
fputcsv($fp, ['Case No.','Complainant','Comp. Contact','Respondent','Resp. Contact','Incident Type','Level','Prescribed Action','Status','Incident Date','Filed Date']);
foreach ($rows as $r) {
    fputcsv($fp, [
        $r['case_number'], $r['complainant_name'], $r['complainant_contact'],
        $r['respondent_name'], $r['respondent_contact'], $r['incident_type'],
        $r['violation_level'], $r['prescribed_action'], $r['status'],
        $r['incident_date'], $r['created_at']
    ]);
}
fclose($fp);
