<?php
/**
 * ajax/export_report.php
 * Exports cross-barangay blotter report as CSV
 */
require_once '../../connection/auth.php';
guardRole('superadmin');

$bgy_id = (int)($_GET['barangay'] ?? 0);
$year   = (int)($_GET['year']     ?? date('Y'));

$bgy_w  = $bgy_id ? "AND bl.barangay_id = $bgy_id" : '';
$year_w = "AND YEAR(bl.created_at) = $year";

try {
    $rows = $pdo->query("
        SELECT bl.case_number, bg.name as barangay, bl.complainant_name, bl.respondent_name,
               bl.incident_type, bl.violation_level, bl.prescribed_action, bl.status,
               bl.incident_date, bl.created_at
        FROM blotters bl
        JOIN barangays bg ON bg.id = bl.barangay_id
        WHERE 1=1 $bgy_w $year_w
        ORDER BY bl.created_at DESC
    ")->fetchAll();
} catch(PDOException $e) { die('Export failed.'); }

$filename = 'blotter_report_'.$year.'_'.date('Ymd').'.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$fp = fopen('php://output','w');
fputcsv($fp, ['Case No.','Barangay','Complainant','Respondent','Incident Type','Level','Prescribed Action','Status','Incident Date','Filed Date']);
foreach ($rows as $r) {
    fputcsv($fp, [$r['case_number'],$r['barangay'],$r['complainant_name'],$r['respondent_name'],$r['incident_type'],$r['violation_level'],$r['prescribed_action'],$r['status'],$r['incident_date'],$r['created_at']]);
}
fclose($fp);
