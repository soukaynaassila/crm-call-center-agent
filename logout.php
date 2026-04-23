<?php
/**
 * logout.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Destroys the current agent session completely and redirects to login.
 */

require_once 'config.php';
start_session();

// 1. Clear all session data
$_SESSION = [];

// 2. Expire the session cookie in the browser
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

// 4. Redirect with a flash message
// We need to re-start a fresh session just to store the flash
session_start();
set_flash('success', 'Vous avez été déconnecté avec succès.');
redirect('login.php');
