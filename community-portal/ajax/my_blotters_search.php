<?php
// ajax/my_blotters_search.php — returns the My Blotters table fragment for
// the current search/status/pg query (used by liveFilter for instant search).
require_once '../../connection/auth.php';
guardRole('community');

$user = currentUser();
$uid  = (int)$user['id'];

require __DIR__ . '/../pages/partials/my-blotters-table.php';
