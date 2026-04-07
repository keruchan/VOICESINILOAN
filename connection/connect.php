<?php
/**
 * connect.php
 * ─────────────────────────────────────────────────────────────
 * Central database connection file for VOICE2 Barangay Blotter System.
 * Include this file in any page that needs a database connection.
 *
 * Usage:
 *   require_once '../connection/connect.php';
 *   // $pdo is now available
 *
 * ─────────────────────────────────────────────────────────────
 */

// ── DATABASE CREDENTIALS ──────────────────────────────────────
// Update these values to match your MySQL setup.
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'u727297653_voicesiniloan');      // your database name
define('DB_USER',    'u727297653_voicesiniloan');           // your MySQL username
define('DB_PASS',    'Voice1234Siniloan@');  
define('DB_CHARSET', 'utf8mb4');

// ── PDO CONNECTION ────────────────────────────────────────────
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
);

$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // return arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                    // use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch (PDOException $e) {
    // In production, log the error and show a generic message.
    // Never expose DB credentials or raw error messages to the browser.
    error_log('[VOICE2 DB Error] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please contact the system administrator.'
    ]));
}

// ── SESSION CONFIGURATION ─────────────────────────────────────
// Start a session if one hasn't been started yet.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,           // session cookie (expires on browser close)
        'path'     => '/',
        'secure'   => false,       // set to true when using HTTPS
        'httponly' => true,        // prevent JS access to session cookie
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── HELPER: Redirect shorthand ────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── HELPER: Check if user is logged in ───────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ── HELPER: Get current logged-in user info ───────────────────
function currentUser(): array {
    return [
        'id'         => $_SESSION['user_id']   ?? null,
        'name'       => $_SESSION['user_name'] ?? null,
        'role'       => $_SESSION['user_role'] ?? null,
        'barangay_id'=> $_SESSION['barangay_id'] ?? null,
    ];
}

// ── HELPER: Require login (redirect if not) ───────────────────
function requireLogin(string $redirect_to = '../connection/login.php'): void {
    if (!isLoggedIn()) {
        redirect($redirect_to);
    }
}

// ── HELPER: Require a specific role ───────────────────────────
function requireRole(string $role, string $redirect_to = '../connection/login.php'): void {
    requireLogin($redirect_to);
    if ($_SESSION['user_role'] !== $role) {
        http_response_code(403);
        die('Access denied.');
    }
}

// ── HELPER: Sanitize output to prevent XSS ───────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ── HELPER: JSON response (for AJAX endpoints) ───────────────
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}
