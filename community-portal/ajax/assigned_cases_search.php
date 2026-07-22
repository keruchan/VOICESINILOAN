<?php
// ajax/assigned_cases_search.php — returns the "All Cases Against Me" table
// fragment for the current search/level/status/pg query (used by liveFilter).
require_once '../../connection/auth.php';
guardRole('community');

$user  = currentUser();
$uid   = (int)$user['id'];
$bid   = (int)$user['barangay_id'];
$uname = $user['name'] ?? '';

require __DIR__ . '/../pages/partials/assigned-cases-table.php';
