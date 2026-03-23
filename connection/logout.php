<?php
/**
 * logout.php
 * ─────────────────────────────────────────────────────────────
 * Destroys the current session and redirects the user to the
 * login page.
 *
 * Usage: Link any "Logout" button to this file.
 *   <a href="../connection/logout.php">Logout</a>
 *
 * ─────────────────────────────────────────────────────────────
 */

require_once 'connect.php';

// 1. Unset all session variables
$_SESSION = [];

// 2. Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Redirect to login page
header('Location: login.php?logged_out=1');
exit;
