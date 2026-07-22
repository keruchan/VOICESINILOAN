<?php
// ajax/blotter_search.php — returns the Blotter Management table fragment for
// the current search/status/level/type/pg query (used by liveFilter for instant search).
require_once '../../connection/auth.php';
guardRole('barangay');

$user = currentUser();
$bid  = (int)$user['barangay_id'];

require __DIR__ . '/../pages/partials/blotter-table.php';
