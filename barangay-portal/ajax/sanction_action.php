<?php
// ajax/sanction_action.php — real cols: violation_type, sanction_name, community_hours, ordinance_ref
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$bid   = (int)($_SESSION['barangay_id'] ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$act   = $input['action'] ?? '';

try {
    switch ($act) {
        case 'save':
            $id    = (int)($input['id'] ?? 0);
            $vtype = trim($input['violation_type']  ?? '');
            $level = in_array($input['violation_level']??'',['minor','moderate','serious','critical']) ? $input['violation_level'] : 'minor';
            $lcat  = in_array($input['legal_category']??'',['kp_law','local_ordinance','barangay_program']) ? $input['legal_category'] : 'local_ordinance';
            $sname = trim($input['sanction_name']   ?? '');
            $fine  = max(0, (float)($input['fine_amount']    ?? 0));
            $csh   = max(0, (int)  ($input['community_hours']?? 0));
            $ord   = trim($input['ordinance_ref']   ?? '');
            $lbasis= trim($input['legal_basis']       ?? '');
            $lexpl = trim($input['legal_explanation'] ?? '');
            $desc  = trim($input['description']     ?? '');
            if (!$vtype || !$sname) jsonResponse(false, 'Violation type and sanction name are required.');
            if ($lcat !== 'barangay_program' && !$lexpl) jsonResponse(false, 'A legal explanation is required so this entry can be verified against KP Law or the cited ordinance.');
            if ($id > 0) {
                $pdo->prepare("UPDATE sanctions_book SET violation_type=?,violation_level=?,legal_category=?,sanction_name=?,fine_amount=?,community_hours=?,ordinance_ref=?,legal_basis=?,legal_explanation=?,description=? WHERE id=? AND barangay_id=?")
                    ->execute([$vtype,$level,$lcat,$sname,$fine,$csh,$ord,$lbasis?:null,$lexpl?:null,$desc,$id,$bid]);
                jsonResponse(true, 'Sanction entry updated.');
            } else {
                $pdo->prepare("INSERT INTO sanctions_book(barangay_id,violation_type,violation_level,legal_category,sanction_name,fine_amount,community_hours,ordinance_ref,legal_basis,legal_explanation,description,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW())")
                    ->execute([$bid,$vtype,$level,$lcat,$sname,$fine,$csh,$ord,$lbasis?:null,$lexpl?:null,$desc]);
                jsonResponse(true, 'Sanction entry added.');
            }
        case 'delete':
            $id = (int)($input['id'] ?? 0);
            $pdo->prepare("DELETE FROM sanctions_book WHERE id=? AND barangay_id=?")->execute([$id,$bid]);
            jsonResponse(true, 'Entry deleted.');
        default:
            jsonResponse(false, 'Unknown action.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Database error.');
}
