<?php
/**
 * dashboard.php
 * Main dashboard — shows KPI stats and recent activity.
 */

require_once 'config.php';
require_auth();

start_session();

$activePage = 'dashboard';

// ── Stats queries ──────────────────────────────────────────────
$totalClients  = (int) $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$totalCalls    = (int) $pdo->query("SELECT COUNT(*) FROM calls")->fetchColumn();
$callsTraite   = (int) $pdo->query("SELECT COUNT(*) FROM calls WHERE status = 'Traité'")->fetchColumn();
$callsAttente  = (int) $pdo->query("SELECT COUNT(*) FROM calls WHERE status = 'En attente'")->fetchColumn();
$callsRappeler = (int) $pdo->query("SELECT COUNT(*) FROM calls WHERE status = 'À rappeler'")->fetchColumn();

// ── Recent calls (last 8) ─────────────────────────────────────
$recentCalls = $pdo->query("
    SELECT c.*, cl.name AS client_name, cl.phone AS client_phone
    FROM calls c
    JOIN clients cl ON cl.id = c.client_id
    ORDER BY c.created_at DESC
    LIMIT 8
")->fetchAll();

// ── Recent clients (last 5) ───────────────────────────────────
$recentClients = $pdo->query("
    SELECT * FROM clients ORDER BY created_at DESC LIMIT 5
")->fetchAll();

$flash = get_flash();

/** Maps a call status to the badge CSS class */
function statusBadge(string $status): string {
    return match($status) {
        'Traité'     => 'badge-success',
        'En attente' => 'badge-warning',
        'À rappeler' => 'badge-danger',
        default      => 'badge-info',
    };
}

/** Maps a call status to an emoji */
function statusEmoji(string $status): string {
    return match($status) {
        'Traité'     => '✅',
        'En attente' => '⏳',
        'À rappeler' => '📲',
        default      => '❓',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — Call Center CRM</title>
    <meta name="description" content="Vue d'ensemble des activités du call center : clients, appels et statuts.">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Dashboard-specific extras */
        .welcome-banner {
            background: linear-gradient(130deg, #1a2a4a 0%, #0d1117 60%, #1a1040 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px 30px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner::before {
            content: '📞';
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 80px;
            opacity: .06;
        }
        .welcome-banner h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .welcome-banner p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .welcome-meta {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .welcome-meta span {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .grid-2col { grid-template-columns: 1fr; }
        }
        .donut-wrap {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .status-legend { flex: 1; min-width: 160px; }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .legend-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .legend-label { font-size: 13px; color: var(--text-secondary); flex: 1; }
        .legend-count { font-size: 14px; font-weight: 700; color: var(--text-primary); }
        .legend-pct   { font-size: 11px; color: var(--text-muted); }
        canvas#donutChart { max-width: 160px; max-height: 160px; }
    </style>
</head>
<body>
<div class="app-wrapper">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- Top Header -->
        <header class="top-header">
            <div>
                <div class="header-title">Tableau de bord</div>
                <div class="header-meta">Vue d'ensemble des activités du centre d'appels</div>
            </div>
            <div class="header-actions">
                <span class="status-badge-online">En ligne</span>
                <a href="calls.php?action=add" class="btn btn-primary btn-sm">+ Nouvel appel</a>
            </div>
        </header>

        <div class="page-body">

            <!-- Flash -->
            <?php if ($flash): ?>
                <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
                    <span class="flash-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div>
                    <h2>Bonjour, <?= htmlspecialchars($_SESSION['agent_name']) ?> 👋</h2>
                    <p>Voici un résumé de l'activité du centre d'appels aujourd'hui.</p>
                    <div class="welcome-meta">
                        <span>📅 <?= date('l d F Y') ?></span>
                        <span>🕐 <?= date('H:i') ?></span>
                    </div>
                </div>
            </div>

            <!-- KPI Stats Grid -->
            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-icon blue">👥</div>
                    <div class="stat-body">
                        <div class="stat-value"><?= $totalClients ?></div>
                        <div class="stat-label">Total Clients</div>
                        <div class="stat-sub">enregistrés</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">📋</div>
                    <div class="stat-body">
                        <div class="stat-value"><?= $totalCalls ?></div>
                        <div class="stat-label">Total Appels</div>
                        <div class="stat-sub">tous statuts</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-body">
                        <div class="stat-value"><?= $callsTraite ?></div>
                        <div class="stat-label">Traités</div>
                        <div class="stat-sub">résolus</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon amber">⏳</div>
                    <div class="stat-body">
                        <div class="stat-value"><?= $callsAttente ?></div>
                        <div class="stat-label">En attente</div>
                        <div class="stat-sub">à traiter</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">📲</div>
                    <div class="stat-body">
                        <div class="stat-value"><?= $callsRappeler ?></div>
                        <div class="stat-label">À rappeler</div>
                        <div class="stat-sub">relances</div>
                    </div>
                </div>

            </div><!-- /stats-grid -->

            <!-- Charts + Recent Clients -->
            <div class="grid-2col">

                <!-- Status Chart -->
                <div class="panel mb-0">
                    <div class="panel-header">
                        <div class="panel-title">
                            <span class="panel-icon">📊</span>
                            Répartition des statuts
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="donut-wrap">
                            <canvas id="donutChart"></canvas>
                            <div class="status-legend">
                                <?php
                                $legendData = [
                                    ['label'=>'Traités',     'count'=>$callsTraite,   'color'=>'#22c55e'],
                                    ['label'=>'En attente',  'count'=>$callsAttente,  'color'=>'#f59e0b'],
                                    ['label'=>'À rappeler',  'count'=>$callsRappeler, 'color'=>'#ef4444'],
                                ];
                                foreach ($legendData as $l):
                                    $pct = $totalCalls > 0 ? round($l['count'] / $totalCalls * 100) : 0;
                                ?>
                                <div class="legend-item">
                                    <div class="legend-dot" style="background:<?= $l['color'] ?>"></div>
                                    <span class="legend-label"><?= $l['label'] ?></span>
                                    <span class="legend-count"><?= $l['count'] ?></span>
                                    <span class="legend-pct"><?= $pct ?>%</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Clients -->
                <div class="panel mb-0">
                    <div class="panel-header">
                        <div class="panel-title">
                            <span class="panel-icon">👥</span>
                            Derniers clients
                        </div>
                        <a href="clients.php" class="btn btn-ghost btn-sm">Voir tout</a>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <?php if (empty($recentClients)): ?>
                            <div class="empty-state" style="padding:30px 0;">
                                <div class="empty-icon">👥</div>
                                <h3>Aucun client</h3>
                            </div>
                        <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Téléphone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentClients as $cl): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($cl['name']) ?></div>
                                            <div class="td-muted" style="font-size:12px;"><?= htmlspecialchars($cl['email'] ?? '—') ?></div>
                                        </td>
                                        <td class="td-muted"><?= htmlspecialchars($cl['phone']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /grid-2col -->

            <!-- Recent Calls -->
            <div class="panel" style="margin-top:20px;">
                <div class="panel-header">
                    <div class="panel-title">
                        <span class="panel-icon">📞</span>
                        Activité récente — Appels
                    </div>
                    <a href="calls.php" class="btn btn-ghost btn-sm">Voir tout</a>
                </div>
                <div class="panel-body" style="padding:0;">
                    <?php if (empty($recentCalls)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <h3>Aucun appel enregistré</h3>
                        </div>
                    <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Client</th>
                                    <th>Problème</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCalls as $call): ?>
                                <tr>
                                    <td class="td-muted">#<?= $call['id'] ?></td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($call['client_name']) ?></div>
                                        <div class="td-muted" style="font-size:12px;"><?= htmlspecialchars($call['client_phone']) ?></div>
                                    </td>
                                    <td style="max-width:280px;white-space:normal;">
                                        <?= htmlspecialchars(mb_strimwidth($call['problem'], 0, 70, '…')) ?>
                                    </td>
                                    <td class="td-muted"><?= date('d/m/Y', strtotime($call['call_date'])) ?></td>
                                    <td>
                                        <span class="badge <?= statusBadge($call['status']) ?>">
                                            <?= statusEmoji($call['status']) ?> <?= htmlspecialchars($call['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /page-body -->
    </div><!-- /main-content -->
</div><!-- /app-wrapper -->

<script>
// ── Donut Chart (vanilla canvas, no lib needed) ──────────────────────────────
(function () {
    const canvas = document.getElementById('donutChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const data   = [<?= $callsTraite ?>, <?= $callsAttente ?>, <?= $callsRappeler ?>];
    const colors = ['#22c55e', '#f59e0b', '#ef4444'];
    const total  = data.reduce((a,b) => a+b, 0);
    const dpr    = window.devicePixelRatio || 1;
    const size   = 160;
    canvas.width  = size * dpr;
    canvas.height = size * dpr;
    canvas.style.width  = size + 'px';
    canvas.style.height = size + 'px';
    ctx.scale(dpr, dpr);

    const cx = size / 2, cy = size / 2, r = 62, innerR = 38;
    let startAngle = -Math.PI / 2;

    if (total === 0) {
        // Empty state ring
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, 2 * Math.PI);
        ctx.arc(cx, cy, innerR, 0, 2 * Math.PI, true);
        ctx.fillStyle = '#21262d';
        ctx.fill('evenodd');
        ctx.fillStyle = '#484f58';
        ctx.font = 'bold 14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('0', cx, cy);
        return;
    }

    data.forEach((val, i) => {
        if (val === 0) return;
        const slice = (val / total) * 2 * Math.PI;
        const endAngle = startAngle + slice;

        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, startAngle, endAngle);
        ctx.closePath();
        ctx.fillStyle = colors[i];
        ctx.fill();
        startAngle = endAngle;
    });

    // Punch donut hole
    ctx.beginPath();
    ctx.arc(cx, cy, innerR, 0, 2 * Math.PI);
    ctx.fillStyle = '#161b22';
    ctx.fill();

    // Center label
    ctx.fillStyle = '#e6edf3';
    ctx.font = 'bold 18px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(total, cx, cy - 5);
    ctx.fillStyle = '#8b949e';
    ctx.font = '10px Inter, sans-serif';
    ctx.fillText('appels', cx, cy + 11);
})();
</script>
</body>
</html>
