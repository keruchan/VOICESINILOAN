<?php
// ajax/case_finder_search.php — relevance-ranked "smart" search.
// Given a free-text description, ranks Sanctions Book entries and past
// blotter cases by how strongly each word in the query matches — using
// MySQL/MariaDB's built-in FULLTEXT relevance scoring (natural language mode,
// falling back to a wildcard boolean-mode search for short/technical terms).
// No external API involved — entirely local, zero cost.
require_once '../../connection/auth.php';
guardRole('barangay');
header('Content-Type: application/json');

$user = currentUser();
$bid  = (int)$user['barangay_id'];

$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;
$q     = trim($input['q'] ?? '');

if ($q === '') jsonResponse(false, 'Enter a description to search.');
if (mb_strlen($q) > 1000) $q = mb_substr($q, 0, 1000);

// ── Build a boolean-mode fallback query: word1* word2* ... ──────────────────
$words = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
$words = array_slice(array_unique(array_filter($words, fn($w) => mb_strlen($w) >= 2)), 0, 15);
$boolean_query = implode(' ', array_map(fn($w) => $w . '*', $words));

function run_search(PDO $pdo, string $table, string $match_cols, int $bid, string $q, string $boolean_query, int $limit): array {
    $rows = [];
    try {
        $stmt = $pdo->prepare("
            SELECT *, MATCH($match_cols) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance
            FROM $table
            WHERE barangay_id = ?
            HAVING relevance > 0
            ORDER BY relevance DESC
            LIMIT $limit
        ");
        $stmt->execute([$q, $bid]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {}

    if (empty($rows) && $boolean_query !== '') {
        try {
            $stmt = $pdo->prepare("
                SELECT *, MATCH($match_cols) AGAINST (? IN BOOLEAN MODE) AS relevance
                FROM $table
                WHERE barangay_id = ?
                HAVING relevance > 0
                ORDER BY relevance DESC
                LIMIT $limit
            ");
            $stmt->execute([$boolean_query, $bid]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {}
    }

    if (empty($rows)) return [];

    $max = (float)$rows[0]['relevance'];
    foreach ($rows as &$r) {
        $r['match_pct'] = $max > 0 ? (int)round(((float)$r['relevance'] / $max) * 100) : 0;
    }
    return $rows;
}

$sanctions = run_search($pdo, 'sanctions_book', 'violation_type,sanction_name,description,legal_explanation,legal_basis,ordinance_ref', $bid, $q, $boolean_query, 10);
$blotters  = run_search($pdo, 'blotters', 'incident_type,narrative,remarks', $bid, $q, $boolean_query, 8);

// Trim heavy/irrelevant fields from the blotter rows before returning
foreach ($blotters as &$b) {
    unset($b['complainant_contact'], $b['respondent_contact'], $b['incident_lat'], $b['incident_lng']);
}

jsonResponse(true, '', ['sanctions' => $sanctions, 'blotters' => $blotters, 'query' => $q]);
