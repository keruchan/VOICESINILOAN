<?php
// ajax/blotter_action.php
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');
ob_clean();

// Use currentUser() — same pattern as index.php — instead of raw $_SESSION keys
$officer = currentUser();
$bid     = (int)($officer['barangay_id'] ?? 0);
$uid     = (int)($officer['id']          ?? 0);

$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);
$action = $data['action'] ?? '';

if (!$action) { echo json_encode(['success'=>false,'message'=>'No action specified.']); exit; }

// ─────────────────────────────────────────────────────────────────────────────
// Helper: auto-derive status from prescribed_action
// ─────────────────────────────────────────────────────────────────────────────
function autoStatus(string $prescribed): ?string {
    if (in_array($prescribed, [
        'refer_police', 'refer_vawc', 'refer_dswd', 'refer_nbi',
        'escalate_municipality', 'transfer_barangay', 'certificate_to_file', 'refer_attorney'
    ])) return 'transferred';
    if (in_array($prescribed, [
        'mediation', 'conciliation', 'summon_issued', 'lupon_hearing', 'pangkat_hearing'
    ])) return 'mediation_set';
    if (in_array($prescribed, [
        'written_agreement', 'sanction_imposed', 'no_action_needed',
        'withdrawn_by_complainant', 'dismissed'
    ])) return 'resolved';
    if ($prescribed === 'document_only') return 'closed';
    if (in_array($prescribed, [
        'active_response', 'noise_abatement', 'cleanup_order', 'site_inspection'
    ])) return 'active';
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: new_blotter — filed by barangay officer via modal
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'new_blotter') {
    $inc_type    = trim($data['incident_type']       ?? '');
    $level       = trim($data['violation_level']     ?? 'minor');
    $idate       = trim($data['incident_date']       ?? date('Y-m-d'));
    $iloc        = trim($data['incident_location']   ?? '');
    $comp_name   = trim($data['complainant_name']    ?? '');
    $comp_contact= trim($data['complainant_contact'] ?? '');
    $resp_uid    = (int)($data['respondent_user_id'] ?? 0);
    $resp_name   = trim($data['respondent_name']     ?? '');
    $resp_contact= trim($data['respondent_contact']  ?? '');
    $narrative   = trim($data['narrative']           ?? '');

    // Server-side validation
    if (!$inc_type)   { echo json_encode(['success'=>false,'message'=>'Incident type is required.']);  exit; }
    if (!$comp_name)  { echo json_encode(['success'=>false,'message'=>'Complainant name is required.']); exit; }
    if (!$narrative || strlen($narrative) < 20)
                      { echo json_encode(['success'=>false,'message'=>'Narrative is required (min 20 chars).']); exit; }
    if (!in_array($level, ['minor','moderate','serious','critical']))
        $level = 'minor';

    try {
        // Generate case number
        $last    = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(case_number,'-',-1) AS UNSIGNED)) FROM blotters WHERE barangay_id=$bid")->fetchColumn();
        $case_no = 'BL-' . date('Y') . '-' . str_pad($bid,3,'0',STR_PAD_LEFT) . '-' . str_pad($last+1,4,'0',STR_PAD_LEFT);

        $pdo->prepare("
            INSERT INTO blotters
              (barangay_id, case_number, complainant_user_id, complainant_name, complainant_contact,
               respondent_user_id, respondent_name, respondent_contact,
               incident_type, violation_level, incident_date, incident_location,
               narrative, prescribed_action, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'document_only','pending_review',NOW(),NOW())
        ")->execute([
            $bid, $case_no, null, $comp_name, $comp_contact,
            ($resp_uid > 0 ? $resp_uid : null), $resp_name, $resp_contact,
            $inc_type, $level, $idate, $iloc,
            $narrative,
        ]);

        $new_id = (int)$pdo->lastInsertId();

        // Activity log
        try {
            $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,'blotter_filed','blotter',?,?,NOW())")
                ->execute([$uid, $bid, $new_id, "Blotter filed by officer: $case_no"]);
        } catch (Exception $ex) {}

        echo json_encode(['success' => true, 'message' => 'Blotter filed successfully.', 'case_number' => $case_no]);

    } catch (PDOException $e) {
        error_log('blotter_action new_blotter: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: update_status
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $id                 = (int)($data['id'] ?? 0);
    $manual_status      = trim($data['status']            ?? '');
    $prescribed_action  = trim($data['prescribed_action'] ?? '');
    $level              = trim($data['violation_level']   ?? '');
    $remarks            = trim($data['remarks']           ?? '');

    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid blotter ID.']); exit; }

    try {
        $check = $pdo->prepare("SELECT id, status, prescribed_action FROM blotters WHERE id = ? AND barangay_id = ? LIMIT 1");
        $check->execute([$id, $bid]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if (!$existing) { echo json_encode(['success'=>false,'message'=>'Blotter not found or access denied.']); exit; }
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]); exit;
    }

    $derived_status = $prescribed_action ? autoStatus($prescribed_action) : null;
    $final_status   = $derived_status ?? ($manual_status ?: $existing['status']);

    $sets   = ['status = ?', 'updated_at = NOW()'];
    $values = [$final_status];

    if ($prescribed_action !== '') { $sets[] = 'prescribed_action = ?'; $values[] = $prescribed_action; }
    if ($level !== '' && in_array($level, ['minor','moderate','serious','critical'])) {
        $sets[] = 'violation_level = ?'; $values[] = $level;
    }
    if ($remarks !== '') { $sets[] = 'remarks = ?'; $values[] = $remarks; }

    $values[] = $id;

    try {
        $pdo->prepare("UPDATE blotters SET " . implode(', ', $sets) . " WHERE id = ?")
            ->execute($values);

        $desc = "Status → $final_status";
        if ($prescribed_action) $desc .= " | Action → " . str_replace('_',' ',$prescribed_action);
        if ($remarks)           $desc .= " | Remarks: $remarks";

        $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,'blotter_updated','blotter',?,?,NOW())")
            ->execute([$uid, $bid, $id, $desc]);

        $msg = $derived_status
            ? 'Saved. Status auto-set to "' . ucwords(str_replace('_',' ',$derived_status)) . '" based on prescribed action.'
            : 'Case updated successfully.';

        echo json_encode(['success'=>true, 'message'=>$msg, 'new_status'=>$final_status]);

    } catch (PDOException $e) {
        error_log('blotter_action update_status: ' . $e->getMessage());
        // Retry without remarks if that column is missing
        if ($remarks !== '') {
            try {
                $sets2 = ['status = ?', 'updated_at = NOW()']; $values2 = [$final_status];
                if ($prescribed_action !== '') { $sets2[] = 'prescribed_action = ?'; $values2[] = $prescribed_action; }
                if ($level !== '')             { $sets2[] = 'violation_level = ?';   $values2[] = $level; }
                $values2[] = $id;
                $pdo->prepare("UPDATE blotters SET ".implode(', ',$sets2)." WHERE id = ?")->execute($values2);
                try {
                    $pdo->prepare("INSERT INTO activity_log(user_id,barangay_id,action,entity_type,entity_id,description,created_at) VALUES(?,?,'blotter_updated','blotter',?,?,NOW())")
                        ->execute([$uid,$bid,$id,$desc]);
                } catch (Exception $ex) {}
                echo json_encode(['success'=>true,'message'=>'Case updated (add remarks column for full support).','new_status'=>$final_status]);
                exit;
            } catch (PDOException $e2) {
                echo json_encode(['success'=>false,'message'=>'DB error: '.$e2->getMessage()]); exit;
            }
        }
        echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]); exit;
    }
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action: '.$action]);
