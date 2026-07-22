<?php
// ajax/violator_search.php — returns the Violator Monitor table fragment for
// the current search/risk query (used by liveFilter for instant search).
require_once '../../connection/auth.php';
guardRole('barangay');

$user = currentUser();
$bid  = (int)$user['barangay_id'];

require __DIR__ . '/../pages/partials/violator-table.php';
