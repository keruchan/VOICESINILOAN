<?php
// ajax/export_blotters.php
require_once '../../connection/auth.php';
guardRole(['barangay','superadmin']);

$user = currentUser();
$bid    = $user['role'] === 'barangay' ? (int)$user['barangay_id'] : (int)($_GET['barangay_id'] ?? 0);
$status = $_GET['status']     ?? '';
$resolution = $_GET['resolution'] ?? '';
$level  = $_GET['level']      ?? '';
$type   = $_GET['type']       ?? '';
$year   = (int)($_GET['year'] ?? 0);
$search = trim($_GET['search'] ?? '');
$is_preview = isset($_GET['preview']);

$where  = ["b.barangay_id = ?"]; $params = [$bid];
if ($status === 'archived') {
    if (in_array($resolution, ['resolved','closed','transferred'], true)) {
        $where[] = 'b.status = ?';
        $params[] = $resolution;
    } else {
        $where[] = "b.status IN ('resolved','closed','transferred')";
    }
}
elseif ($status)            { $where[] = 'status = ?'; $params[] = $status; }
if ($level)  { $where[] = 'violation_level = ?'; $params[] = $level; }
if ($type)   { $where[] = 'incident_type = ?'; $params[] = $type; }
if ($year)   { $where[] = 'YEAR(b.updated_at) = ?'; $params[] = $year; }
if ($search) {
    $where[] = '(case_number LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ? OR incident_type LIKE ?)';
    $like="%$search%";
    $params=array_merge($params,[$like,$like,$like,$like]);
}
$ws = 'WHERE ' . implode(' AND ', $where);

$columns = ['Case No.','Complainant','Comp. Contact','Respondent','Resp. Contact','Incident Type','Level','Prescribed Action','Status','Incident Date','Filed Date'];
try {
    $total = null;
    if ($is_preview) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM blotters b $ws");
        $c->execute($params);
        $total = (int)$c->fetchColumn();
    }
    $limit = $is_preview ? ' LIMIT 25' : '';
    $s = $pdo->prepare("SELECT case_number, complainant_name, complainant_contact, respondent_name, respondent_contact, incident_type, violation_level, prescribed_action, status, incident_date, created_at FROM blotters b $ws ORDER BY created_at DESC$limit");
    $s->execute($params); $rows = $s->fetchAll();
} catch (PDOException $e) { die('Export failed.'); }

if ($is_preview) {
    $download = $_GET;
    unset($download['preview']);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'title' => $status === 'archived' ? 'Archived Blotter Export' : 'Blotter Export',
        'total' => $total,
        'preview_count' => count($rows),
        'columns' => $columns,
        'rows' => array_map(fn($r) => [
            $r['case_number'], $r['complainant_name'], $r['complainant_contact'],
            $r['respondent_name'], $r['respondent_contact'], $r['incident_type'],
            $r['violation_level'], $r['prescribed_action'], $r['status'],
            $r['incident_date'], $r['created_at']
        ], $rows),
        'download_url' => 'ajax/export_blotters.php?' . http_build_query($download),
    ]);
    exit;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="blotters_' . date('Ymd_His') . '.csv"');
$fp = fopen('php://output', 'w');
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
fputcsv($fp, $columns);
foreach ($rows as $r) {
    fputcsv($fp, [
        $r['case_number'], $r['complainant_name'], $r['complainant_contact'],
        $r['respondent_name'], $r['respondent_contact'], $r['incident_type'],
        $r['violation_level'], $r['prescribed_action'], $r['status'],
        $r['incident_date'], $r['created_at']
    ]);
}
fclose($fp);
