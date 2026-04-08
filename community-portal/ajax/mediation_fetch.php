<?php
require '../config/db.php';       // adjust path
require '../includes/session.php'; // ensures $user

$uid = (int)$user['id'];

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$role   = $_GET['role'] ?? '';

// BASE QUERY
$sql = "
SELECT 
    ms.hearing_date, 
    ms.hearing_time, 
    ms.venue, 
    ms.status,
    b.id AS blotter_id,
    b.case_number, 
    b.incident_type,
    b.violation_level,
    b.complainant_name,
    b.respondent_name,
    CASE 
        WHEN b.complainant_user_id = ? THEN 'complainant'
        ELSE 'respondent'
    END AS my_role
FROM mediation_schedules ms
JOIN blotters b ON b.id = ms.blotter_id
WHERE (b.complainant_user_id = ? OR b.respondent_user_id = ?)
";

$params = [$uid, $uid, $uid];

// ROLE FILTER
if ($role === 'complainant') {
    $sql .= " AND b.complainant_user_id = ?";
    $params[] = $uid;
}
if ($role === 'respondent') {
    $sql .= " AND b.respondent_user_id = ?";
    $params[] = $uid;
}

// SEARCH
if ($search !== '') {
    $sql .= " AND (
        b.case_number LIKE ? OR
        b.incident_type LIKE ? OR
        b.complainant_name LIKE ? OR
        b.respondent_name LIKE ? OR
        ms.venue LIKE ?
    )";
    for ($i=0;$i<5;$i++) $params[] = "%$search%";
}

// EXECUTE
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── SPLIT UPCOMING / PAST ──
$upcoming = [];
$past = [];

foreach ($rows as $h) {
    $datetime = strtotime($h['hearing_date'].' '.($h['hearing_time'] ?? '23:59:59'));

    if ($h['status'] === 'scheduled' && $datetime >= time()) {
        $upcoming[] = $h;
    } else {
        $past[] = $h;
    }
}

// ── OUTPUT ──
if (!$rows) {
    echo "<div class='empty-state'>No hearings found</div>";
    exit;
}

/* ================= UPCOMING ================= */
if ($status !== 'past' && !empty($upcoming)) {
    echo "<div style='font-size:12px;font-weight:700;margin-bottom:10px'>📅 UPCOMING</div>";
    echo "<div class='g2 mb22'>";

    foreach ($upcoming as $h) {
        echo "
        <div class='card'>
            <div class='card-body'>
                <strong>{$h['case_number']}</strong><br>
                {$h['incident_type']}<br><br>

                📅 ".date('D, M j, Y', strtotime($h['hearing_date']))."<br>
                ⏰ ".($h['hearing_time'] ? date('g:i A', strtotime($h['hearing_time'])) : '')."<br>
                📍 ".htmlspecialchars($h['venue'] ?: 'Barangay Hall')."<br><br>

                👤 You are <b>{$h['my_role']}</b>
            </div>
        </div>";
    }

    echo "</div>";
}

/* ================= PAST ================= */
if ($status !== 'upcoming' && !empty($past)) {
    echo "<div style='font-size:12px;font-weight:700;margin-bottom:10px'>🕐 PAST</div>";
    echo "<div class='card'><div class='tbl-wrap'><table>";

    echo "<thead>
        <tr>
            <th>Case</th>
            <th>Type</th>
            <th>Date</th>
            <th>Role</th>
            <th>Status</th>
        </tr>
    </thead><tbody>";

    foreach ($past as $h) {
        echo "
        <tr>
            <td>{$h['case_number']}</td>
            <td>{$h['incident_type']}</td>
            <td>".date('M j, Y', strtotime($h['hearing_date']))."</td>
            <td>{$h['my_role']}</td>
            <td>{$h['status']}</td>
        </tr>";
    }

    echo "</tbody></table></div></div>";
}