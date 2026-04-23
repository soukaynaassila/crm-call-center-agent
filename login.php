<?php
/**
 * login.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Authentication page.
 * GET  → show the login form
 * POST → process credentials, start session, redirect to dashboard
 * ─────────────────────────────────────────────────────────────────────────────
 * NO insert/signup logic lives here. Use seed.php to create demo agents.
 */

require_once 'config.php';
start_session();

// Already authenticated → go to dashboard
if (!empty($_SESSION['agent_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$email  = '';

// ── POST: process login ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // ── Input validation ─────────────────────────────────────────────────────
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez saisir une adresse e-mail valide.';
    }
    if ($password === '') {
        $errors[] = 'Le mot de passe est requis.';
    }

    // ── Database lookup (only if inputs are clean) ────────────────────────────
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT id, name, email, password, is_active FROM agents WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $agent = $stmt->fetch();
        } catch (PDOException $e) {
            // If the agents table doesn't exist yet, give a helpful message
            error_log('[CRM] Login DB error: ' . $e->getMessage());
            if (APP_DEBUG) {
                $errors[] = 'Erreur base de données : ' . htmlspecialchars($e->getMessage())
                          . ' — Avez-vous exécuté seed.php ou database.sql ?';
            } else {
                $errors[] = 'Une erreur interne est survenue. Contactez l\'administrateur.';
            }
            $agent = null;
        }

        if (!isset($errors[0])) { // no DB error
            // ── Verify password ───────────────────────────────────────────────
            if ($agent && password_verify($password, $agent['password'])) {

                // Check the account is active
                if (!(bool) $agent['is_active']) {
                    $errors[] = 'Votre compte est désactivé. Contactez un administrateur.';
                } else {
                    // ── Session fixation prevention ───────────────────────────
                    session_regenerate_id(true);

                    $_SESSION['agent_id']    = $agent['id'];
                    $_SESSION['agent_name']  = $agent['name'];
                    $_SESSION['agent_email'] = $agent['email'];

                    set_flash('success', 'Bienvenue, ' . htmlspecialchars($agent['name']) . ' !');
                    redirect('dashboard.php');
                }

            } else {
                // Generic error message — do NOT reveal whether email exists
                $errors[] = 'Email ou mot de passe incorrect.';

                // DEBUG HINT (remove in production)
                if (APP_DEBUG && $agent) {
                    error_log('[CRM DEBUG] Login failed for ' . $email
                        . ' | hash stored: ' . substr($agent['password'], 0, 20) . '…'
                        . ' | password_verify result: ' . (password_verify($password, $agent['password']) ? 'true' : 'false'));
                }
            }
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Call Center CRM</title>
    <meta name="description" content="Interface de connexion agents du Call Center CRM.">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Login-page extras ── */
        .login-form .form-group { margin-bottom: 16px; }

        .login-remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            cursor: pointer;
        }
        .login-remember input[type="checkbox"] {
            width: 15px; height: 15px;
            accent-color: var(--accent);
        }

        .demo-hint {
            background: var(--bg-input);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-top: 22px;
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        .demo-hint strong { color: var(--accent); }

        /* Debug panel — only shown when APP_DEBUG = true */
        .debug-panel {
            background: #1a1a2e;
            border: 1px solid #f59e0b;
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-top: 12px;
            font-size: 11px;
            color: #f59e0b;
            font-family: monospace;
        }
        .debug-panel summary { cursor: pointer; font-weight: 700; }
    </style>
</head>
<body>
<div class="login-page">
    <div class="login-card">

        <!-- Brand -->
        <div class="login-brand">
            <div class="brand-icon">📞</div>
            <h1>Call Center CRM</h1>
            <p>Connectez-vous pour accéder à votre espace agent</p>
        </div>

        <!-- Flash message (after redirect) -->
        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>">
                <span class="flash-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Validation errors -->
        <?php if (!empty($errors)): ?>
            <div class="flash flash-error" id="login-error-box">
                <span class="flash-icon">❌</span>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="login-separator">Identifiants agent</div>

        <!-- Login Form -->
        <form method="POST" action="login.php" class="login-form" novalidate id="login-form">
            <div class="form-group">
                <label for="email">Adresse Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    placeholder="agent@callcenter.com"
                    autocomplete="email"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <label class="login-remember">
                <input type="checkbox" name="remember"> Se souvenir de moi
            </label>

            <button
                type="submit"
                class="btn btn-primary w-full"
                id="btn-login-submit"
                style="justify-content:center; padding:12px;"
            >
                🔐&nbsp;Se connecter
            </button>
        </form>

        <!-- Demo credentials hint -->
        <div class="demo-hint">
            <strong>Démo :</strong> sophie@callcenter.com &nbsp;/&nbsp; <strong>password</strong><br>
            <span style="font-size:11px;color:var(--text-muted);">
                Vous n'avez pas encore de compte ?
                <a href="seed.php" style="color:var(--accent);">Exécuter seed.php →</a>
            </span>
        </div>

        <?php if (APP_DEBUG): ?>
        <!-- Debug panel — REMOVE in production -->
        <details class="debug-panel">
            <summary>🛠 Debug info (visible car APP_DEBUG = true)</summary>
            <ul style="margin-top:8px;padding-left:16px;line-height:2;">
                <li>PHP <?= PHP_VERSION ?></li>
                <li>Session status: <?= session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive' ?></li>
                <li>Session ID: <?= session_id() ?: 'none' ?></li>
                <li>password_hash() available: <?= function_exists('password_hash') ? '✅' : '❌' ?></li>
                <li>DB connected: ✅ (if you see this page, PDO is working)</li>
                <?php if (!empty($email)): ?>
                <li>Last attempted email: <?= e($email) ?></li>
                <?php endif; ?>
            </ul>
            <p style="margin-top:8px;color:#94a3b8;">
                If login fails, run <a href="seed.php" style="color:#f59e0b;">seed.php</a>
                to reset the demo data with fresh hashes.
            </p>
        </details>
        <?php endif; ?>

    </div>
</div>

<script>
// Auto-focus password field if email is already pre-filled
const emailInput = document.getElementById('email');
const passInput  = document.getElementById('password');
if (emailInput && emailInput.value.trim() !== '' && passInput) {
    passInput.focus();
}

// Prevent double-submit
const loginForm = document.getElementById('login-form');
const loginBtn  = document.getElementById('btn-login-submit');
if (loginForm && loginBtn) {
    loginForm.addEventListener('submit', () => {
        loginBtn.disabled = true;
        loginBtn.textContent = '⏳ Connexion…';
    });
}
</script>
</body>
</html>
