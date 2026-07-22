<?php
// ajax/users_search.php — returns the User Management table fragment for the
// current search/role/filter/barangay/p query (used by liveFilter for instant search).
require_once '../../connection/auth.php';
guardRole('superadmin');

require __DIR__ . '/../pages/partials/users-table.php';
