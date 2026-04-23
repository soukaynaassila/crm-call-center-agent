<?php
/**
 * includes/sidebar.php
 * Shared sidebar navigation — included by every authenticated page.
 * Expects: $activePage (string) — 'dashboard' | 'clients' | 'calls'
 */

// Determine active page for nav highlighting
$activePage = $activePage ?? '';

// Agent initials for avatar
$agentName    = $_SESSION['agent_name'] ?? 'Agent';
$agentEmail   = $_SESSION['agent_email'] ?? '';
$nameParts    = explode(' ', $agentName);
$initials     = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

// Count pending calls for badge
$stmtPending = $pdo->prepare("SELECT COUNT(*) FROM calls WHERE status = 'En attente'");
$stmtPending->execute();
$pendingCount = (int) $stmtPending->fetchColumn();
?>
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon">📞</div>
        <div class="logo-text">
            CRM Center
            <span>Call Center Suite</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu principal</div>

        <a href="dashboard.php"
           class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>"
           id="nav-dashboard">
            <span class="nav-icon">🏠</span>
            Tableau de bord
        </a>

        <a href="clients.php"
           class="nav-link <?= $activePage === 'clients' ? 'active' : '' ?>"
           id="nav-clients">
            <span class="nav-icon">👥</span>
            Clients
        </a>

        <a href="calls.php"
           class="nav-link <?= $activePage === 'calls' ? 'active' : '' ?>"
           id="nav-calls">
            <span class="nav-icon">📋</span>
            Appels
            <?php if ($pendingCount > 0): ?>
                <span class="nav-badge"><?= htmlspecialchars($pendingCount) ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section-label" style="margin-top:16px;">Compte</div>

        <a href="logout.php"
           class="nav-link"
           id="nav-logout"
           onclick="return confirm('Voulez-vous vous déconnecter ?');">
            <span class="nav-icon">🚪</span>
            Déconnexion
        </a>
    </nav>

    <!-- Agent Card -->
    <div class="sidebar-footer">
        <div class="agent-card">
            <div class="agent-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="agent-info">
                <div class="agent-name"><?= htmlspecialchars($agentName) ?></div>
                <div class="agent-role">Agent Support</div>
                
            </div>
        </div>
    </div>

</aside>
