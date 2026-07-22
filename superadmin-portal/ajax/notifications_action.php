<?php
// ajax/notifications_action.php — powers the topbar notification bell for
// superadmins. Two layers:
//   - "insights": live-computed municipality-wide alerts (pending approvals,
//     escalated blotters, inactive barangays, unassigned barangays, recent
//     filings) — always current, not stored, not dismissible.
//   - "notifications": discrete stored events (e.g. new user registration)
//     with full read/unread/delete, scoped to this admin's account.
require_once '../../connection/auth.php';
guardRole('superadmin');
header('Content-Type: application/json');

$user = currentUser();
$uid  = (int)$user['id'];

function superadmin_unread_sql(): string {
    return "(status != 'read' OR read_at IS NULL)";
}

function superadmin_insights(PDO $pdo): array {
    $insights = [];
    try {
        $pending_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='community'")->fetchColumn();
        if ($pending_users > 0) {
            $insights[] = ['icon'=>'user','color'=>'#F59E0B','title'=>"$pending_users community user(s) unverified",'sub'=>'Awaiting email verification — manual activation available.','link'=>'?page=users&filter=pending'];
        }

        $pending_officers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0 AND role='barangay'")->fetchColumn();
        if ($pending_officers > 0) {
            $insights[] = ['icon'=>'officer','color'=>'#A78BFA','title'=>"$pending_officers barangay officer(s) pending activation",'sub'=>'Officer accounts need to be activated.','link'=>'?page=users&filter=pending'];
        }

        $escalated = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE status='escalated'")->fetchColumn();
        if ($escalated > 0) {
            $insights[] = ['icon'=>'alert','color'=>'#FB7185','title'=>"$escalated blotter(s) escalated to municipality",'sub'=>'Requires superadmin attention.','link'=>'?page=reports'];
        }

        $inactive_bgy = (int)$pdo->query("SELECT COUNT(*) FROM barangays WHERE is_active=0")->fetchColumn();
        if ($inactive_bgy > 0) {
            $insights[] = ['icon'=>'bgy','color'=>'#94A3B8','title'=>"$inactive_bgy barangay(s) currently inactive",'sub'=>'Review and activate if needed.','link'=>'?page=barangays'];
        }

        $no_officer = (int)$pdo->query("
            SELECT COUNT(*) FROM barangays b
            WHERE b.is_active=1
              AND NOT EXISTS (SELECT 1 FROM users u WHERE u.barangay_id=b.id AND u.role='barangay' AND u.is_active=1)
        ")->fetchColumn();
        if ($no_officer > 0) {
            $insights[] = ['icon'=>'warning','color'=>'#F59E0B','title'=>"$no_officer active barangay(s) have no assigned officer",'sub'=>'No active barangay officer account found.','link'=>'?page=barangays'];
        }

        $recent_filed = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE created_at >= NOW() - INTERVAL 24 HOUR")->fetchColumn();
        if ($recent_filed > 0) {
            $insights[] = ['icon'=>'doc','color'=>'#2EBAC6','title'=>"$recent_filed new blotter(s) filed in the last 24 hours",'sub'=>'Across all barangays.','link'=>'?page=reports'];
        }
    } catch (Exception $e) {}
    return $insights;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'count') {
        $unread = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE recipient_user_id=? AND portal='superadmin' AND " . superadmin_unread_sql());
            $stmt->execute([$uid]);
            $unread = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {}
        jsonResponse(true, '', ['unread' => $unread]);
    }

    if ($action === 'list') {
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit  = max(1, min(30, (int)($_GET['limit'] ?? 8)));
        $rows = []; $total = 0; $unread = 0;
        try {
            $c = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE recipient_user_id=? AND portal='superadmin'");
            $c->execute([$uid]);
            $total = (int)$c->fetchColumn();

            $u = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE recipient_user_id=? AND portal='superadmin' AND " . superadmin_unread_sql());
            $u->execute([$uid]);
            $unread = (int)$u->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT id, type, subject, message, link_page, link_blotter_id, status, read_at, created_at,
                       CASE WHEN status = 'read' AND read_at IS NOT NULL THEN 0 ELSE 1 END AS is_unread
                FROM system_notifications
                WHERE recipient_user_id = ? AND portal='superadmin'
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$uid, $limit, $offset]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}

        jsonResponse(true, '', [
            'insights' => $offset === 0 ? superadmin_insights($pdo) : [],
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
            $pdo->prepare("UPDATE system_notifications SET status='read', read_at=NOW() WHERE id=? AND recipient_user_id=? AND portal='superadmin'")
                ->execute([$id, $uid]);
            jsonResponse(true, 'Marked as read.');

        case 'mark_unread':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid notification.');
            $pdo->prepare("UPDATE system_notifications SET status='unread', read_at=NULL WHERE id=? AND recipient_user_id=? AND portal='superadmin'")
                ->execute([$id, $uid]);
            jsonResponse(true, 'Marked as unread.');

        case 'mark_all_read':
            $pdo->prepare("UPDATE system_notifications SET status='read', read_at=NOW() WHERE recipient_user_id=? AND portal='superadmin' AND " . superadmin_unread_sql())
                ->execute([$uid]);
            jsonResponse(true, 'All notifications marked as read.');

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid notification.');
            $stmt = $pdo->prepare("DELETE FROM system_notifications WHERE id=? AND recipient_user_id=? AND portal='superadmin'");
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
