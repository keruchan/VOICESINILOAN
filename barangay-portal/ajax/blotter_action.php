<?php
// ajax/blotter_action.php
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$bid   = (int)($_SESSION['barangay_id'] ?? 0);
$uid   = (int)($_SESSION['user_id']     ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$act   = $input['action'] ?? '';

function gen_case(PDO $pdo, int $bid): string {
    $last = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(case_number,'-',-1) AS UNSIGNED)) FROM blotters WHERE barangay_id=$bid")->fetchColumn();
    return 'BL-' . date('Y') . '-' . str_pad($bid,3,'0',STR_PAD_LEFT) . '-' . str_pad($last+1,4,'0',STR_PAD_LEFT);
}

function log_act(PDO $pdo, int $uid, int $bid, string $action, string $entity, int $eid, string $desc): void {
    try { $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,?,?,?,?,NOW())")->execute([$uid,$bid,$action,$entity,$eid,$desc]); } catch(Exception $e){}
}

function own_blotter(PDO $pdo, int $id, int $bid): bool {
    $s = $pdo->prepare("SELECT id FROM blotters WHERE id=? AND barangay_id=? LIMIT 1");
    $s->execute([$id,$bid]); return (bool)$s->fetch();
}

try {
    switch ($act) {

        case 'create':
            $inc   = trim($input['incident_type']      ?? '');
            $level = in_array($input['violation_level']??'',['minor','moderate','serious','critical']) ? $input['violation_level'] : 'minor';
            $date  = $input['incident_date']  ?? date('Y-m-d');
            $cn    = trim($input['complainant_name']    ?? '');
            $cc    = trim($input['complainant_contact'] ?? '');
            $rn    = trim($input['respondent_name']     ?? '');
            $rc    = trim($input['respondent_contact']  ?? '');
            $loc   = trim($input['incident_location']   ?? '');
            $narr  = trim($input['narrative']           ?? '');

            if (!$inc || !$cn || !$narr) jsonResponse(false, 'Incident type, complainant name, and narrative are required.');

            $case_no = gen_case($pdo, $bid);
            $pdo->prepare("
                INSERT INTO blotters
                  (barangay_id, case_number, complainant_name, complainant_contact,
                   respondent_name, respondent_contact, incident_type, violation_level,
                   incident_date, incident_location, narrative,
                   prescribed_action, status, filed_by_user_id, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,'document_only','pending_review',?,NOW(),NOW())
            ")->execute([$bid,$case_no,$cn,$cc,$rn,$rc,$inc,$level,$date,$loc,$narr,$uid]);

            $new_id = (int)$pdo->lastInsertId();
            log_act($pdo,$uid,$bid,'blotter_filed','blotter',$new_id,"Filed $case_no");
            jsonResponse(true, "Blotter $case_no filed successfully.", ['case_number'=>$case_no,'id'=>$new_id]);

        case 'update_status':
            $id     = (int)($input['id']                ?? 0);
            $status = $input['status']                  ?? '';
            $pa     = $input['prescribed_action']       ?? 'document_only';
            $rem    = trim($input['remarks']            ?? '');
            $valid_s = ['pending_review','active','mediation_set','escalated','resolved','closed','transferred'];
            $valid_p = ['document_only','mediation','refer_barangay','refer_police','refer_vawc','escalate_municipality'];
            if (!$id || !in_array($status,$valid_s)) jsonResponse(false, 'Invalid data.');
            if (!own_blotter($pdo,$id,$bid)) jsonResponse(false, 'Access denied.');
            if (!in_array($pa,$valid_p)) $pa='document_only';
            $pdo->prepare("UPDATE blotters SET status=?, prescribed_action=?, updated_at=NOW() WHERE id=?")->execute([$status,$pa,$id]);
            $desc = "Status → $status" . ($rem ? ". $rem" : '');
            log_act($pdo,$uid,$bid,'status_updated','blotter',$id,$desc);
            jsonResponse(true, 'Blotter updated successfully.');

        default:
            jsonResponse(false, 'Unknown action.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Database error. Please try again.');
}
