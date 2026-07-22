<?php
// ajax/sanctions_search.php — returns the Sanctions Book table fragment for
// the current search/level/category query (used by liveFilter for instant search).
require_once '../../connection/auth.php';
guardRole('barangay');

$user = currentUser();
$bid  = (int)$user['barangay_id'];

require __DIR__ . '/../pages/partials/sanctions-table.php';
