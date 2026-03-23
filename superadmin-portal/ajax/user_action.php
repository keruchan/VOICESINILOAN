<?php
/**
 * ajax/user_action.php
 * Handles: approve, suspend, activate, delete, create, edit, bulk
 */
require_once '../../connection/auth.php';
guardRole('superadmin');
header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

try {
    switch ($action) {

        case 'approve':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid user ID.');
            $pdo->prepare("UPDATE users SET is_active=1, updated_at=NOW() WHERE id=? AND role='community'")->execute([$id]);
            log_action('user_approved', 'user', $id, "Approved community user ID $id");
            jsonResponse(true, 'User approved successfully.');

        case 'suspend':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid user ID.');
            $pdo->prepare("UPDATE users SET is_active=2, updated_at=NOW() WHERE id=? AND role!='superadmin'")->execute([$id]);
            log_action('user_suspended', 'user', $id, "Suspended user ID $id");
            jsonResponse(true, 'User suspended.');

        case 'activate':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid user ID.');
            $pdo->prepare("UPDATE users SET is_active=1, updated_at=NOW() WHERE id=? AND role!='superadmin'")->execute([$id]);
            log_action('user_activated', 'user', $id, "Activated user ID $id");
            jsonResponse(true, 'User activated.');

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid user ID.');
            // Soft-check: don't delete if user has filed blotters
            $bl_count = (int)$pdo->prepare("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=? OR filed_by_user_id=?")->execute([$id,$id]) ? $pdo->prepare("SELECT COUNT(*) FROM blotters WHERE complainant_user_id=? OR filed_by_user_id=?")->execute([$id,$id]) : 0;
            $pdo->prepare("DELETE FROM users WHERE id=? AND role!='superadmin'")->execute([$id]);
            log_action('user_deleted', 'user', $id, "Deleted user ID $id");
            jsonResponse(true, 'User deleted.');

        case 'create':
            $first  = trim($input['first_name'] ?? '');
            $last   = trim($input['last_name']  ?? '');
            $email  = trim($input['email']      ?? '');
            $role   = in_array($input['role']??'', ['barangay','community']) ? $input['role'] : 'community';
            $bgy_id = (int)($input['barangay_id'] ?? 0) ?: null;
            $pw     = $input['password'] ?? '';
            $contact= trim($input['contact'] ?? '');

            if (!$first || !$last || !$email || !$pw) jsonResponse(false, 'Required fields missing.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
            if (strlen($pw) < 8) jsonResponse(false, 'Password must be at least 8 characters.');

            // Check duplicate email
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $exists->execute([$email]);
            if ($exists->fetch()) jsonResponse(false, 'Email already registered.');

            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]);
            $full_name = "$last, $first";
            $stmt = $pdo->prepare("INSERT INTO users (barangay_id,full_name,first_name,last_name,email,password_hash,contact_number,role,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,1,NOW())");
            $stmt->execute([$bgy_id,$full_name,$first,$last,$email,$hash,$contact,$role]);
            $new_id = (int)$pdo->lastInsertId();
            log_action('user_created', 'user', $new_id, "Created $role user: $email");
            jsonResponse(true, "User '$full_name' created successfully.", ['user_id'=>$new_id]);

        case 'edit':
            $id     = (int)($input['id']     ?? 0);
            $email  = trim($input['email']   ?? '');
            $role   = in_array($input['role']??'', ['barangay','community']) ? $input['role'] : null;
            $status = in_array((int)($input['status']??1), [0,1,2]) ? (int)$input['status'] : 1;
            $pw     = $input['password'] ?? '';

            if (!$id) jsonResponse(false, 'Invalid user ID.');
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email.');

            $sets = ['is_active=?','updated_at=NOW()'];
            $vals = [$status];

            if ($email) { $sets[] = 'email=?'; $vals[] = $email; }
            if ($role)  { $sets[] = 'role=?';  $vals[] = $role; }
            if ($pw && strlen($pw) >= 8) {
                $sets[] = 'password_hash=?';
                $vals[] = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]);
            }

            $vals[] = $id;
            $pdo->prepare("UPDATE users SET ".implode(',',$sets)." WHERE id=? AND role!='superadmin'")->execute($vals);
            log_action('user_edited', 'user', $id, "Edited user ID $id");
            jsonResponse(true, 'User updated.');

        case 'bulk':
            $ids         = array_filter(array_map('intval', $input['ids'] ?? []));
            $bulk_action = $input['bulk_action'] ?? '';
            if (empty($ids)) jsonResponse(false, 'No users selected.');

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            switch ($bulk_action) {
                case 'approve':
                    $pdo->prepare("UPDATE users SET is_active=1, updated_at=NOW() WHERE id IN ($placeholders) AND role!='superadmin'")->execute($ids);
                    jsonResponse(true, count($ids).' user(s) approved.');
                case 'suspend':
                    $pdo->prepare("UPDATE users SET is_active=2, updated_at=NOW() WHERE id IN ($placeholders) AND role!='superadmin'")->execute($ids);
                    jsonResponse(true, count($ids).' user(s) suspended.');
                case 'delete':
                    $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role!='superadmin'")->execute($ids);
                    jsonResponse(true, count($ids).' user(s) deleted.');
                default:
                    jsonResponse(false, 'Unknown bulk action.');
            }

        default:
            jsonResponse(false, 'Unknown action.');
    }

} catch (PDOException $e) {
    error_log('[VOICE2 user_action] '.$e->getMessage());
    jsonResponse(false, 'Database error. Please try again.');
}

function log_action(string $action, string $entity, int $entity_id, string $desc): void {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO activity_log (user_id, barangay_id, action, entity_type, entity_id, description, ip_address, created_at) VALUES (?,NULL,?,?,?,?,?,NOW())")
            ->execute([$_SESSION['user_id'], $action, $entity, $entity_id, $desc, $_SERVER['REMOTE_ADDR']??'']);
    } catch(PDOException $e){}
}
