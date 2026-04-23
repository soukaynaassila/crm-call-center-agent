<?php
/**
 * config.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Database connection (PDO) + shared helper functions.
 * Included first by every page.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Environment flag ─────────────────────────────────────────────────────────
// Set to false in production so DB errors are logged, not displayed.
define('APP_DEBUG', true);

// ── Database constants ───────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'crm_db');
define('DB_USER',    'root');    // XAMPP default
define('DB_PASS',    '');        // XAMPP default (empty)
define('DB_CHARSET', 'utf8mb4');

// ── PDO connection ────────────────────────────────────────────────────────────
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return assoc arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
    ]);
} catch (PDOException $e) {
    if (APP_DEBUG) {
        // Show details only on localhost — helps troubleshoot XAMPP setup
        $msg = htmlspecialchars($e->getMessage());
        die("
            <style>body{font-family:monospace;background:#0f172a;color:#ef4444;padding:40px}</style>
            <h2>🔴 Database Connection Error</h2>
            <p>Could not connect to MySQL. Check that XAMPP MySQL is running and credentials are correct.</p>
            <pre style='background:#1e293b;padding:16px;border-radius:6px;color:#fca5a5'>{$msg}</pre>
            <p style='color:#94a3b8'>DB: <strong>" . DB_NAME . "</strong> | Host: <strong>" . DB_HOST . "</strong> | User: <strong>" . DB_USER . "</strong></p>
        ");
    }
    // Production: log the real error, show a generic message
    error_log('[CRM] DB connection failed: ' . $e->getMessage());
    die('Service temporarily unavailable. Please try again later.');
}

// ─────────────────────────────────────────────────────────────────────────────
// Session helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Start session safely — call once at the top of each page.
 * Configures secure cookie settings.
 */
function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return; // already started
    }

    // Harden session cookie
    session_set_cookie_params([
        'lifetime' => 0,                  // expires when browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,              // set to true if using HTTPS
        'httponly' => true,               // JS cannot access session cookie
        'samesite' => 'Lax',
    ]);

    session_start();
}

// ─────────────────────────────────────────────────────────────────────────────
// Flash message helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Store a flash message in session.
 * @param  string $type    'success' | 'error' | 'warning' | 'info'
 * @param  string $message Human-readable message
 */
function set_flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message.
 * @return array{type:string, message:string}|null
 */
function get_flash(): ?array
{
    start_session();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Redirect helper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Redirect to a URL and stop execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Auth guard
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Require an authenticated session.
 * Redirects to login.php if the agent is not logged in.
 */
function require_auth(): void
{
    start_session();
    if (empty($_SESSION['agent_id'])) {
        redirect('login.php');
    }
}

/**
 * Safely escape output (shorthand for htmlspecialchars).
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
