<?php
// ajax/notifications_action.php — powers the topbar notification bell for
// barangay officers. Two layers:
//   - "insights": live-computed operational alerts (pending review, overdue
//     mediation documentation, hearings today, settlements about to lapse) —
//     always current, not stored, not dismissible.
//   - "notifications": discrete stored events (e.g. new resident-filed report)
//     with full read/unread/delete, scoped to this officer's account.
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$user = currentUser();
$uid  = (int)$user['id'];
$bid  = (int)$user['barangay_id'];

function barangay_unread_sql(): string {
    return "(status != 'read' OR read_at IS NULL)";
}

function barangay_insights(PDO $pdo, int $bid): array {
    $insights = [];
    try {
        $pending = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE barangay_id=$bid AND status='pending_review'")->fetchColumn();
        if ($pending > 0) {
            $insights[] = ['icon'=>'doc','color'=>'#F59E0B','title'=>"$pending report(s) awaiting review",'sub'=>'New resident-filed reports need a prescribed action.','link'=>'?page=blotter-management&status=pending_review'];
        }

        $overdue = (int)$pdo->query("
            SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id
            WHERE b.barangay_id=$bid AND ms.status='scheduled' AND ms.hearing_date < CURDATE()
        ")->fetchColumn();
        if ($overdue > 0) {
            $insights[] = ['icon'=>'warning','color'=>'#FB7185','title'=>"$overdue hearing(s) need documentation",'sub'=>'These were scheduled but the date passed undocumented.','link'=>'?page=mediation&tab=overdue'];
        }

        $today = (int)$pdo->query("
            SELECT COUNT(*) FROM mediation_schedules ms JOIN blotters b ON b.id=ms.blotter_id
            WHERE b.barangay_id=$bid AND ms.status='scheduled' AND ms.hearing_date = CURDATE()
        ")->fetchColumn();
        if ($today > 0) {
            $insights[] = ['icon'=>'bgy','color'=>'#2EBAC6','title'=>"$today hearing(s) scheduled today",'sub'=>'Mediation sessions on today\'s calendar.','link'=>'?page=mediation&tab=upcoming'];
        }

        $expiring = (int)$pdo->query("
            SELECT COUNT(*) FROM amicable_settlements
            WHERE barangay_id=$bid AND status='active' AND DATEDIFF(repudiation_deadline, CURDATE()) BETWEEN 0 AND 2
        ")->fetchColumn();
        if ($expiring > 0) {
            $insights[] = ['icon'=>'alert','color'=>'#A78BFA','title'=>"$expiring settlement(s) finalizing soon",'sub'=>'Repudiation window closes within 2 days.','link'=>'?page=mediation&tab=settlements'];
        }
    } catch (PDOException $e) {}
    return $insights;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'count') {
        $unread = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE recipient_user_id=? AND portal='barangay' AND " . barangay_unread_sql());
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
            $c = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE recipient_user_id=? AND portal='barangay'");
            $c->execute([$uid]);
            $total = (int)$c->fetchColumn();

            $u = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE recipient_user_id=? AND portal='barangay' AND " . barangay_unread_sql());
            $u->execute([$uid]);
            $unread = (int)$u->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT sn.id, sn.type, sn.subject, sn.message, sn.link_page, sn.link_blotter_id,
                       sn.status, sn.read_at, sn.created_at, b.case_number,
                       CASE WHEN sn.status = 'read' AND sn.read_at IS NOT NULL THEN 0 ELSE 1 END AS is_unread
                FROM system_notifications sn
                LEFT JOIN blotters b ON b.id = sn.link_blotter_id
                WHERE sn.recipient_user_id = ? AND sn.portal='barangay'
                ORDER BY sn.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$uid, $limit, $offset]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}

        jsonResponse(true, '', [
            'insights' => $offset === 0 ? barangay_insights($pdo, $bid) : [],
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
            $pdo->prepare("UPDATE system_notifications SET status='read', read_at=NOW() WHERE id=? AND recipient_user_id=? AND portal='barangay'")
                ->execute([$id, $uid]);
            jsonResponse(true, 'Marked as read.');

        case 'mark_unread':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid notification.');
            $pdo->prepare("UPDATE system_notifications SET status='unread', read_at=NULL WHERE id=? AND recipient_user_id=? AND portal='barangay'")
                ->execute([$id, $uid]);
            jsonResponse(true, 'Marked as unread.');

        case 'mark_all_read':
            $pdo->prepare("UPDATE system_notifications SET status='read', read_at=NOW() WHERE recipient_user_id=? AND portal='barangay' AND " . barangay_unread_sql())
                ->execute([$uid]);
            jsonResponse(true, 'All notifications marked as read.');

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(false, 'Invalid notification.');
            $stmt = $pdo->prepare("DELETE FROM system_notifications WHERE id=? AND recipient_user_id=? AND portal='barangay'");
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
