<?php
// ajax/records_search.php — returns the Records Archive table fragment for
// the current year/resolution/type/pg query (used by liveFilter for instant search).
require_once '../../connection/auth.php';
guardRole('barangay');

$user = currentUser();
$bid  = (int)$user['barangay_id'];

require __DIR__ . '/../pages/partials/records-table.php';
