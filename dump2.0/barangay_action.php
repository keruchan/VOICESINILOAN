<?php
/**
 * ajax/barangay_action.php
 * Handles: create, edit, toggle (activate/deactivate)
 */
require_once '../../connection/auth.php';
guardRole('superadmin');
header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

try {
    switch ($action) {

        case 'create':
            $name     = trim($input['name']         ?? '');
            $muni     = trim($input['municipality'] ?? '');
            $province = trim($input['province']     ?? '');
            $psgc     = trim($input['psgc_code']    ?? '');
            $contact  = trim($input['contact_no']   ?? '');
            $captain  = trim($input['captain_name'] ?? '');
            $email    = trim($input['email']        ?? '');

            if (!$name || !$muni) jsonResponse(false, 'Barangay name and municipality are required.');

            // Check duplicate
            $exists = $pdo->prepare("SELECT id FROM barangays WHERE name=? AND municipality=? LIMIT 1");
            $exists->execute([$name, $muni]);
            if ($exists->fetch()) jsonResponse(false, 'This barangay already exists.');

            $pdo->prepare("INSERT INTO barangays (name,municipality,province,psgc_code,contact_no,captain_name,email,is_active,created_at) VALUES (?,?,?,?,?,?,?,1,NOW())")
                ->execute([$name,$muni,$province,$psgc,$contact,$captain,$email]);
            $new_id = (int)$pdo->lastInsertId();

            jsonResponse(true, "Barangay '$name' added successfully.", ['barangay_id'=>$new_id]);

        case 'edit':
            $id      = (int)($input['id'] ?? 0);
            $name    = trim($input['name']         ?? '');
            $muni    = trim($input['municipality'] ?? '');
            $captain = trim($input['captain_name'] ?? '');
            $contact = trim($input['contact_no']   ?? '');
            $active  = in_array((int)($input['is_active']??1),[0,1]) ? (int)$input['is_active'] : 1;

            if (!$id)   jsonResponse(false, 'Invalid ID.');
            if (!$name) jsonResponse(false, 'Name is required.');

            $pdo->prepare("UPDATE barangays SET name=?, municipality=?, captain_name=?, contact_no=?, is_active=?, updated_at=NOW() WHERE id=?")
                ->execute([$name,$muni,$captain,$contact,$active,$id]);

            jsonResponse(true, 'Barangay updated.');

        case 'toggle':
            $id     = (int)($input['id']     ?? 0);
            $active = (int)($input['active'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid ID.');
            $pdo->prepare("UPDATE barangays SET is_active=?, updated_at=NOW() WHERE id=?")->execute([$active, $id]);
            jsonResponse(true, $active ? 'Barangay activated.' : 'Barangay deactivated.');

        default:
            jsonResponse(false, 'Unknown action.');
    }

} catch (PDOException $e) {
    error_log('[VOICE2 barangay_action] '.$e->getMessage());
    jsonResponse(false, 'Database error. Please try again.');
}
