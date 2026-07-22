<?php
// ajax/notifications_action.php — powers the topbar notification bell:
// unread count, recent list (paginated "load more"), mark read/unread,
// mark all read, and delete. Scoped strictly to the logged-in user.
require_once '../../connection/auth.php';
guardRole('community');
header('Content-Type: application/json');

$user = currentUser();
$uid  = (int)$user['id'];

function community_unread_sql(): string {
    return "(status != 'read' OR read_at IS NULL)";
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'count') {
        $unread = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM party_notifications WHERE recipient_user_id = ? AND " . community_unread_sql());
            $stmt->execute([$uid]);
            $unread = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {}
        jsonResponse(true, '', ['unread' => $unread]);
    }

    if ($action === 'list') {
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit  = max(1, min(30, (int)($_GET['limit'] ?? 8)));
        $rows = []; $total = 0;
        try {
            $c = $pdo->prepare("SELECT COUNT(*) FROM party_notifications WHERE recipient_user_id = ?");
            $c->execute([$uid]);
            $total = (int)$c->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT pn.id, pn.blotter_id, pn.notification_type, pn.subject, pn.message,
                       pn.status, pn.read_at, pn.created_at, b.case_number,
                       CASE WHEN pn.status = 'read' AND pn.read_at IS NOT NULL THEN 0 ELSE 1 END AS is_unread
                FROM party_notifications pn
                LEFT JOIN blotters b ON b.id = pn.blotter_id
                WHERE pn.recipient_user_id = ?
                ORDER BY pn.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$uid, $limit, $offset]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}

        $unread = 0;
        try {
            $u = $pdo->prepare("SELECT COUNT(*) FROM party_notifications WHERE recipient_user_id = ? AND " . community_unread_sql());
            $u->execute([$uid]);
            $unread = (int)$u->fetchColumn();
        } catch (PDOException $e) {}

        jsonResponse(true, '', [
            'notifications' => $rows,
            'total' => $total,
            'unread' => $unread,
            'has_more' => ($offset + count($rows)) < $total,
        ]);
    }

    jsonResponse(false, 'Unknown action.');
}

// ── POST actions ──────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'mark_read':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid notification.');
            $pdo->prepare("UPDATE party_notifications SET status='read', read_at=NOW() WHERE id=? AND recipient_user_id=?")
                ->execute([$id, $uid]);
            jsonResponse(true, 'Marked as read.');

        case 'mark_unread':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid notification.');
            $pdo->prepare("UPDATE party_notifications SET status='sent', read_at=NULL WHERE id=? AND recipient_user_id=?")
                ->execute([$id, $uid]);
            jsonResponse(true, 'Marked as unread.');

        case 'mark_all_read':
            $pdo->prepare("UPDATE party_notifications SET status='read', read_at=NOW() WHERE recipient_user_id=? AND " . community_unread_sql())
                ->execute([$uid]);
            jsonResponse(true, 'All notifications marked as read.');

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid notification.');
            $stmt = $pdo->prepare("DELETE FROM party_notifications WHERE id=? AND recipient_user_id=?");
            $stmt->execute([$id, $uid]);
            if ($stmt->rowCount() === 0) jsonResponse(false, 'Notification not found.');
            jsonResponse(true, 'Notification deleted.');

        default:
            jsonResponse(false, 'Unknown action.');
    }
} catch (PDOException $e) {
    error_log('[notifications_action] ' . $e->getMessage());
    jsonResponse(false, 'Database error.');
}
