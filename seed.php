<?php
/**
 * seed.php
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE-TIME setup script: creates tables (if missing) and inserts demo data.
 *
 * ⚠️  SECURITY: DELETE or restrict this file after running it in production!
 *
 * Usage: visit http://localhost/call%20center%20agent/seed.php in your browser.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// Prevent running in a production environment accidentally
// (Remove this guard if you are sure you want to run it)
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    die('Forbidden: seed.php can only be run from localhost.');
}

require_once __DIR__ . '/config.php';

// ── Helper to print styled output ────────────────────────────────────────────
function log_msg(string $type, string $msg): void {
    $colors = ['ok' => '#22c55e', 'warn' => '#f59e0b', 'err' => '#ef4444', 'info' => '#60a5fa'];
    $color  = $colors[$type] ?? '#94a3b8';
    echo "<p style=\"font-family:monospace;font-size:14px;margin:4px 0;color:{$color};\">$msg</p>";
    ob_flush(); flush();
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Seed — Call Center CRM</title>
    <style>
        body { background:#0f172a; color:#e2e8f0; font-family:monospace; padding:40px; }
        h1   { color:#60a5fa; margin-bottom:4px; }
        h2   { color:#94a3b8; font-size:14px; font-weight:400; margin-bottom:30px; }
        hr   { border:none; border-top:1px solid #1e293b; margin:20px 0; }
        .box { background:#1e293b; border:1px solid #334155; border-radius:10px;
               padding:24px 30px; max-width:680px; }
        .btn { display:inline-block; margin-top:24px; padding:10px 22px;
               background:#2563eb; color:#fff; border-radius:6px;
               text-decoration:none; font-size:14px; }
    </style>
</head>
<body>
<div class="box">
    <h1>🌱 Call Center CRM — Seed Script</h1>
    <h2>Creates tables and inserts demo data with correct bcrypt hashes</h2>
    <hr>
<?php

// ── STEP 1: Create tables ─────────────────────────────────────────────────────
log_msg('info', '▶ STEP 1 — Creating tables (if not exists)…');

$tables = [

    'agents' => "
        CREATE TABLE IF NOT EXISTS agents (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            email      VARCHAR(150) NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            role       ENUM('agent','admin') NOT NULL DEFAULT 'agent',
            is_active  TINYINT(1)   NOT NULL DEFAULT 1,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'clients' => "
        CREATE TABLE IF NOT EXISTS clients (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            phone      VARCHAR(30)  NOT NULL,
            email      VARCHAR(150) DEFAULT NULL,
            notes      TEXT         DEFAULT NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'calls' => "
        CREATE TABLE IF NOT EXISTS calls (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id  INT UNSIGNED NOT NULL,
            problem    TEXT         NOT NULL,
            call_date  DATE         NOT NULL,
            status     ENUM('Traité','En attente','À rappeler') NOT NULL DEFAULT 'En attente',
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_calls_client
                FOREIGN KEY (client_id) REFERENCES clients(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
];

foreach ($tables as $tableName => $sql) {
    try {
        $pdo->exec($sql);
        log_msg('ok', "  ✅ Table `{$tableName}` OK");
    } catch (PDOException $e) {
        log_msg('err', "  ❌ Error creating `{$tableName}`: " . htmlspecialchars($e->getMessage()));
        die('</div></body></html>');
    }
}

// ── STEP 2: Wipe existing demo data ──────────────────────────────────────────
log_msg('info', '▶ STEP 2 — Clearing existing data…');

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE calls');
    $pdo->exec('TRUNCATE TABLE clients');
    $pdo->exec('TRUNCATE TABLE agents');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    log_msg('ok', '  ✅ Tables cleared (TRUNCATE with FK checks disabled)');
} catch (PDOException $e) {
    log_msg('err', '  ❌ Could not clear tables: ' . htmlspecialchars($e->getMessage()));
    die('</div></body></html>');
}

// ── STEP 3: Insert agents with fresh bcrypt hashes ────────────────────────────
log_msg('info', '▶ STEP 3 — Inserting demo agents…');

$demoAgents = [
    [
        'name'     => 'Sophie Martin',
        'email'    => 'sophie@callcenter.com',
        'password' => 'password',   // plain text — will be hashed below
        'role'     => 'admin',
    ],
    [
        'name'     => 'Lucas Bernard',
        'email'    => 'lucas@callcenter.com',
        'password' => 'password',
        'role'     => 'agent',
    ],
];

$stmtAgent = $pdo->prepare(
    'INSERT INTO agents (name, email, password, role) VALUES (:name, :email, :password, :role)'
);

foreach ($demoAgents as $agent) {
    $hash = password_hash($agent['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmtAgent->execute([
        ':name'     => $agent['name'],
        ':email'    => $agent['email'],
        ':password' => $hash,
        ':role'     => $agent['role'],
    ]);
    log_msg('ok', "  ✅ Agent <strong>{$agent['name']}</strong> ({$agent['email']}) inserted");
    log_msg('info', "     Hash: <span style='color:#94a3b8'>{$hash}</span>");

    // Verify immediately
    $verify = password_verify($agent['password'], $hash);
    log_msg($verify ? 'ok' : 'err',
        "     password_verify() → " . ($verify ? '✅ PASS' : '❌ FAIL (critical bug!)'));
}

// ── STEP 4: Insert clients ────────────────────────────────────────────────────
log_msg('info', '▶ STEP 4 — Inserting demo clients…');

$demoClients = [
    ['Jean Dupont',     '0601234567', 'jean.dupont@email.com'],
    ['Marie Leclerc',   '0698765432', 'marie.leclerc@email.com'],
    ['Pierre Lambert',  '0712345678', 'pierre.lambert@email.com'],
    ['Claire Fontaine', '0623456789', 'claire.fontaine@email.com'],
    ['Antoine Morin',   '0745678901', 'antoine.morin@email.com'],
];

$stmtClient = $pdo->prepare(
    'INSERT INTO clients (name, phone, email) VALUES (?, ?, ?)'
);
foreach ($demoClients as [$name, $phone, $email]) {
    $stmtClient->execute([$name, $phone, $email]);
    log_msg('ok', "  ✅ Client <strong>{$name}</strong> inserted");
}

// ── STEP 5: Insert calls ──────────────────────────────────────────────────────
log_msg('info', '▶ STEP 5 — Inserting demo calls…');

$demoCalls = [
    [1, 'Problème de facturation — double prélèvement détecté.',        '2026-04-20', 'Traité'],
    [1, 'Demande de modification du forfait abonnement.',               '2026-04-22', 'En attente'],
    [2, 'Panne de connexion internet depuis 3 jours.',                  '2026-04-21', 'À rappeler'],
    [3, 'Réclamation sur la qualité du service client.',                '2026-04-19', 'Traité'],
    [4, "Demande d'informations sur les nouveaux tarifs.",              '2026-04-23', 'En attente'],
    [5, 'Résiliation du contrat souhaitée pour fin de mois.',           '2026-04-18', 'À rappeler'],
    [2, 'Mise à jour des coordonnées bancaires.',                       '2026-04-22', 'Traité'],
    [3, "Demande de remboursement suite à une erreur de facturation.", '2026-04-23', 'En attente'],
];

$stmtCall = $pdo->prepare(
    'INSERT INTO calls (client_id, problem, call_date, status) VALUES (?, ?, ?, ?)'
);
foreach ($demoCalls as $call) {
    $stmtCall->execute($call);
}
log_msg('ok', '  ✅ ' . count($demoCalls) . ' calls inserted');

// ── STEP 6: Summary ───────────────────────────────────────────────────────────
echo '<hr>';
log_msg('ok', '🎉 Seed complete! Database is ready.');

// Show PHP version info for debugging
$phpVersion  = PHP_VERSION;
$bcryptAvail = function_exists('password_hash') ? '✅ Yes' : '❌ No';
log_msg('info', "PHP version: {$phpVersion} | password_hash() available: {$bcryptAvail}");

?>
    <a class="btn" href="login.php">🔐 Go to Login →</a>
</div>
</body>
</html>