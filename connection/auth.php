<?php
/**
 * auth.php
 * ─────────────────────────────────────────────────────────────
 * Lightweight authentication guard.
 * Include this at the very top of any protected PHP page.
 *
 * Usage (community portal page):
 *   <?php require_once '../connection/auth.php'; guardRole('community'); ?>
 *
 * Usage (barangay portal page):
 *   <?php require_once '../connection/auth.php'; guardRole('barangay'); ?>
 *
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/connect.php';

/**
 * Ensure the user is logged in and matches the expected role.
 * Redirects to login if not authenticated or wrong role.
 *
 * @param string|array $allowed_roles  Single role string or array of allowed roles.
 */
function guardRole($allowed_roles): void {
    if (!isLoggedIn()) {
        redirect(__DIR__ . '/login.php');
    }

    $role = $_SESSION['user_role'] ?? '';

    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        // Optionally redirect to their correct portal instead of showing 403
        switch ($role) {
            case 'community':  redirect('../community-portal/index.html'); break;
            case 'barangay':   redirect('../barangay-portal/index.html');  break;
            case 'superadmin': redirect('../superadmin-portal/index.html'); break;
            default: die('403 — Access Denied');
        }
    }
}
