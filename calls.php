<?php
/**
 * calls.php
 * Call Management — Add, Edit, Delete, Filter by status, Update status.
 *
 * GET  ?action=add                  → show add form
 * GET  ?action=edit&id=N            → show edit form pre-filled
 * GET  ?action=delete&id=N          → delete call (then redirect)
 * POST ?action=add                  → process add form
 * POST ?action=edit&id=N            → process edit form
 * POST ?action=update_status&id=N   → quick status update from table row
 * GET  ?status=X                    → filter table by status
 * GET  ?client_id=N                 → filter table by client
 */

require_once 'config.php';
require_auth();

start_session();

$activePage = 'calls';
$action     = trim($_GET['action'] ?? '');
$callId     = (int) ($_GET['id']        ?? 0);
$errors     = [];

// Allowed statuses (used for validation throughout)
$statuses = ['Traité', 'En attente', 'À rappeler'];

// Default form data
$formData = [
    'client_id'   => '',
    'problem'     => '',
    'call_date'   => date('Y-m-d'),
    'status'      => 'En attente',
];

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — POST handlers
// ══════════════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Quick status update (inline dropdown in table) ─────────────────────
    if ($action === 'update_status' && $callId > 0) {
        $newStatus = trim($_POST['status'] ?? '');
        if (in_array($newStatus, $statuses, true)) {
            $pdo->prepare('UPDATE calls SET status = :s WHERE id = :id')
                ->execute([':s' => $newStatus, ':id' => $callId]);
            set_flash('success', 'Statut mis à jour avec succès.');
        } else {
            set_flash('error', 'Statut invalide.');
        }
        // Preserve the current filter when redirecting
        $qs = http_build_query(array_filter([
            'status'    => $_GET['status'] ?? '',
            'client_id' => $_GET['client_id'] ?? '',
        ]));
        redirect('calls.php' . ($qs ? '?' . $qs : ''));
    }

    // ── Add / Edit form submissions ────────────────────────────────────────
    $formData['client_id'] = (int) ($_POST['client_id'] ?? 0);
    $formData['problem']   = trim($_POST['problem']   ?? '');
    $formData['call_date'] = trim($_POST['call_date'] ?? '');
    $formData['status']    = trim($_POST['status']    ?? '');

    // Validate
    if ($formData['client_id'] <= 0) {
        $errors[] = 'Veuillez sélectionner un client.';
    }
    if ($formData['problem'] === '') {
        $errors[] = 'La description du problème est obligatoire.';
    } elseif (mb_strlen($formData['problem']) > 2000) {
        $errors[] = 'La description ne doit pas dépasser 2000 caractères.';
    }
    if ($formData['call_date'] === '' || !strtotime($formData['call_date'])) {
        $errors[] = 'La date d\'appel est invalide.';
    }
    if (!in_array($formData['status'], $statuses, true)) {
        $errors[] = 'Le statut sélectionné est invalide.';
    }

    // Verify client exists
    if ($formData['client_id'] > 0 && empty($errors)) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE id = :id');
        $chk->execute([':id' => $formData['client_id']]);
        if (!$chk->fetch()) {
            $errors[] = 'Le client sélectionné n\'existe pas.';
        }
    }

    if (empty($errors)) {
        if ($action === 'add') {
            $pdo->prepare(
                'INSERT INTO calls (client_id, problem, call_date, status)
                 VALUES (:client_id, :problem, :call_date, :status)'
            )->execute([
                ':client_id' => $formData['client_id'],
                ':problem'   => $formData['problem'],
                ':call_date' => $formData['call_date'],
                ':status'    => $formData['status'],
            ]);
            set_flash('success', 'Appel enregistré avec succès.');
            redirect('calls.php');
        }

        if ($action === 'edit' && $callId > 0) {
            $pdo->prepare(
                'UPDATE calls SET client_id = :client_id, problem = :problem,
                 call_date = :call_date, status = :status WHERE id = :id'
            )->execute([
                ':client_id' => $formData['client_id'],
                ':problem'   => $formData['problem'],
                ':call_date' => $formData['call_date'],
                ':status'    => $formData['status'],
                ':id'        => $callId,
            ]);
            set_flash('success', 'Appel mis à jour avec succès.');
            redirect('calls.php');
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — GET: delete handler
// ══════════════════════════════════════════════════════════════════════════════

if ($action === 'delete' && $callId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT c.id, cl.name AS client_name FROM calls c JOIN clients cl ON cl.id = c.client_id WHERE c.id = :id');
    $stmt->execute([':id' => $callId]);
    $toDelete = $stmt->fetch();

    if ($toDelete) {
        $pdo->prepare('DELETE FROM calls WHERE id = :id')->execute([':id' => $callId]);
        set_flash('success', 'Appel #' . $toDelete['id'] . ' (client : ' . $toDelete['client_name'] . ') supprimé.');
    } else {
        set_flash('error', 'Appel introuvable.');
    }
    redirect('calls.php');
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — GET: load edit data
// ══════════════════════════════════════════════════════════════════════════════

if ($action === 'edit' && $callId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM calls WHERE id = :id');
    $stmt->execute([':id' => $callId]);
    $editCall = $stmt->fetch();

    if (!$editCall) {
        set_flash('error', 'Appel introuvable.');
        redirect('calls.php');
    }

    $formData = [
        'client_id' => $editCall['client_id'],
        'problem'   => $editCall['problem'],
        'call_date' => $editCall['call_date'],
        'status'    => $editCall['status'],
    ];
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Fetch data for UI
// ══════════════════════════════════════════════════════════════════════════════

// All clients for the <select> dropdown
$allClients = $pdo->query('SELECT id, name, phone FROM clients ORDER BY name ASC')->fetchAll();

// Filter parameters
$filterStatus   = trim($_GET['status']    ?? '');
$filterClientId = (int) ($_GET['client_id'] ?? 0);

// Build the calls query with optional filters
$conditions = ['1=1'];
$params     = [];

if ($filterStatus !== '' && in_array($filterStatus, $statuses, true)) {
    $conditions[] = 'c.status = :fstatus';
    $params[':fstatus'] = $filterStatus;
}
if ($filterClientId > 0) {
    $conditions[] = 'c.client_id = :fclient';
    $params[':fclient'] = $filterClientId;
}

$whereClause = implode(' AND ', $conditions);

$stmt = $pdo->prepare("
    SELECT c.*, cl.name AS client_name, cl.phone AS client_phone
    FROM calls c
    JOIN clients cl ON cl.id = c.client_id
    WHERE {$whereClause}
    ORDER BY c.call_date DESC, c.id DESC
");
$stmt->execute($params);
$calls = $stmt->fetchAll();

// Status counts for filter pills
$statusCounts = [];
$scStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM calls GROUP BY status");
foreach ($scStmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['cnt'];
}
$totalCallsCount = array_sum($statusCounts);

$flash = get_flash();

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Returns the badge CSS class for a given status */
function callStatusBadge(string $status): string {
    return match($status) {
        'Traité'     => 'badge-success',
        'En attente' => 'badge-warning',
        'À rappeler' => 'badge-danger',
        default      => 'badge-info',
    };
}

/** Returns an emoji for a given status */
function callStatusEmoji(string $status): string {
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
    <title>Appels — Call Center CRM</title>
    <meta name="description" content="Gestion des appels du centre d'appels : ajout, modification, suppression et filtrage par statut.">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Page-specific styles ── */
        .filter-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 600;
            border: 1px solid var(--border-light);
            background: var(--bg-input);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .filter-pill:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .filter-pill.active {
            background: var(--accent-light);
            border-color: var(--accent);
            color: var(--accent);
        }
        .filter-pill.active-success {
            background: var(--success-light);
            border-color: var(--success);
            color: var(--success);
        }
        .filter-pill.active-warning {
            background: var(--warning-light);
            border-color: var(--warning);
            color: var(--warning);
        }
        .filter-pill.active-danger {
            background: var(--danger-light);
            border-color: var(--danger);
            color: var(--danger);
        }
        .pill-count {
            background: rgba(255,255,255,.1);
            border-radius: 10px;
            padding: 0 6px;
            font-size: 11px;
        }
        .form-panel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 24px;
            animation: slideDown .2s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .form-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            background: rgba(37,99,235,.04);
        }
        .form-panel-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-panel-body { padding: 22px; }
        /* Inline status update form */
        .status-form {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .status-select-inline {
            background: var(--bg-input);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 12px;
            padding: 4px 8px;
            outline: none;
            cursor: pointer;
            transition: var(--transition);
        }
        .status-select-inline:focus {
            border-color: var(--accent);
        }
        .problem-cell {
            max-width: 300px;
            white-space: normal;
            font-size: 13px;
            line-height: 1.4;
        }
        .action-col { white-space: nowrap; }
        .filter-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: var(--accent-light);
            border-bottom: 1px solid var(--border);
            font-size: 12.5px;
            color: var(--accent);
        }
    </style>
</head>
<body>
<div class="app-wrapper">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- ── Top Header ── -->
        <header class="top-header">
            <div>
                <div class="header-title">Gestion des Appels</div>
                <div class="header-meta">
                    <?= count($calls) ?> appel<?= count($calls) !== 1 ? 's' : '' ?>
                    <?php if ($filterStatus !== ''): ?>
                        — filtrés : <strong><?= htmlspecialchars($filterStatus) ?></strong>
                    <?php elseif ($filterClientId > 0): ?>
                        — filtrés par client
                    <?php else: ?>
                        au total
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-actions">
                <span class="status-badge-online">En ligne</span>
                <?php if ($action !== 'add'): ?>
                    <a href="calls.php?action=add" class="btn btn-primary btn-sm" id="btn-add-call">
                        ➕ Nouvel appel
                    </a>
                <?php else: ?>
                    <a href="calls.php" class="btn btn-ghost btn-sm">✖ Annuler</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="page-body">

            <!-- ── Flash message ── -->
            <?php if ($flash): ?>
                <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
                    <span class="flash-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════════════════════
                 FORM PANEL — Add or Edit
                 ══════════════════════════════════════════════════════════ -->
            <?php if ($action === 'add' || ($action === 'edit' && $callId > 0)): ?>
            <div class="form-panel" id="call-form-panel">
                <div class="form-panel-header">
                    <div class="form-panel-title">
                        <?= $action === 'add' ? '📞 Enregistrer un nouvel appel' : '✏️ Modifier l\'appel #' . $callId ?>
                    </div>
                    <a href="calls.php" class="btn btn-ghost btn-sm">✖ Annuler</a>
                </div>
                <div class="form-panel-body">

                    <!-- Validation errors -->
                    <?php if (!empty($errors)): ?>
                        <div class="flash flash-error" style="margin-bottom:18px;">
                            <span class="flash-icon">❌</span>
                            <div>
                                <?php foreach ($errors as $err): ?>
                                    <div><?= htmlspecialchars($err) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form
                        method="POST"
                        action="calls.php?action=<?= htmlspecialchars($action) ?><?= $action === 'edit' ? '&id=' . $callId : '' ?>"
                        novalidate
                        id="call-form"
                    >
                        <div class="form-grid">

                            <!-- Client select -->
                            <div class="form-group">
                                <label for="client_id">Client <span style="color:var(--danger)">*</span></label>
                                <?php if (empty($allClients)): ?>
                                    <div class="flash flash-error" style="margin:0;">
                                        ❌ Aucun client enregistré.
                                        <a href="clients.php?action=add" style="margin-left:6px;">Ajouter un client →</a>
                                    </div>
                                <?php else: ?>
                                <select id="client_id" name="client_id" required>
                                    <option value="">— Sélectionner un client —</option>
                                    <?php foreach ($allClients as $cl): ?>
                                        <option
                                            value="<?= $cl['id'] ?>"
                                            <?= (int)$formData['client_id'] === (int)$cl['id'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($cl['name']) ?>
                                            (<?= htmlspecialchars($cl['phone']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>

                            <!-- Call date -->
                            <div class="form-group">
                                <label for="call_date">Date de l'appel <span style="color:var(--danger)">*</span></label>
                                <input
                                    type="date"
                                    id="call_date"
                                    name="call_date"
                                    value="<?= htmlspecialchars($formData['call_date']) ?>"
                                    max="<?= date('Y-m-d') ?>"
                                    required
                                >
                            </div>

                            <!-- Status -->
                            <div class="form-group">
                                <label for="status">Statut <span style="color:var(--danger)">*</span></label>
                                <select id="status" name="status" required>
                                    <?php foreach ($statuses as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= $formData['status'] === $s ? 'selected' : '' ?>>
                                            <?= callStatusEmoji($s) ?> <?= htmlspecialchars($s) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Problem description (full width) -->
                            <div class="form-group full-width">
                                <label for="problem">Description du problème <span style="color:var(--danger)">*</span></label>
                                <textarea
                                    id="problem"
                                    name="problem"
                                    rows="4"
                                    maxlength="2000"
                                    placeholder="Décrivez le problème signalé par le client…"
                                    required
                                ><?= htmlspecialchars($formData['problem']) ?></textarea>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                                    <span id="char-count">0</span> / 2000 caractères
                                </div>
                            </div>

                        </div><!-- /form-grid -->

                        <div class="form-actions" style="margin-top:18px;">
                            <button type="submit" class="btn btn-primary" id="btn-submit-call">
                                <?= $action === 'add' ? '📞 Enregistrer l\'appel' : '💾 Enregistrer les modifications' ?>
                            </button>
                            <a href="calls.php" class="btn btn-ghost">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════════════════════
                 FILTER PILLS + TABLE
                 ══════════════════════════════════════════════════════════ -->
            <div class="panel">

                <!-- Filter pills header -->
                <div class="panel-header" style="flex-wrap:wrap;gap:12px;">
                    <div class="panel-title">
                        <span class="panel-icon">📋</span>
                        Tous les appels
                    </div>
                    <div class="filter-pills" id="filter-pills">

                        <!-- All -->
                        <a href="calls.php"
                           class="filter-pill <?= ($filterStatus === '' && $filterClientId === 0) ? 'active' : '' ?>"
                           id="pill-all">
                            📋 Tous
                            <span class="pill-count"><?= $totalCallsCount ?></span>
                        </a>

                        <!-- Traité -->
                        <a href="calls.php?status=Traité"
                           class="filter-pill <?= $filterStatus === 'Traité' ? 'active-success' : '' ?>"
                           id="pill-traite">
                            ✅ Traités
                            <span class="pill-count"><?= $statusCounts['Traité'] ?? 0 ?></span>
                        </a>

                        <!-- En attente -->
                        <a href="calls.php?status=En+attente"
                           class="filter-pill <?= $filterStatus === 'En attente' ? 'active-warning' : '' ?>"
                           id="pill-attente">
                            ⏳ En attente
                            <span class="pill-count"><?= $statusCounts['En attente'] ?? 0 ?></span>
                        </a>

                        <!-- À rappeler -->
                        <a href="calls.php?status=%C3%80+rappeler"
                           class="filter-pill <?= $filterStatus === 'À rappeler' ? 'active-danger' : '' ?>"
                           id="pill-rappeler">
                            📲 À rappeler
                            <span class="pill-count"><?= $statusCounts['À rappeler'] ?? 0 ?></span>
                        </a>

                    </div>
                </div>

                <!-- Active filter info bar -->
                <?php if ($filterStatus !== '' || $filterClientId > 0): ?>
                    <div class="filter-info">
                        🔍 Filtre actif :
                        <?php if ($filterStatus !== ''): ?>
                            Statut = <strong><?= htmlspecialchars($filterStatus) ?></strong>
                        <?php endif; ?>
                        <?php if ($filterClientId > 0): ?>
                            Client ID = <strong>#<?= $filterClientId ?></strong>
                        <?php endif; ?>
                        — <a href="calls.php" style="color:var(--accent);">Effacer le filtre ✖</a>
                    </div>
                <?php endif; ?>

                <!-- Calls Table -->
                <div class="table-wrapper">
                    <?php if (empty($calls)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <h3>Aucun appel trouvé</h3>
                            <?php if ($filterStatus !== ''): ?>
                                <p>Aucun appel avec le statut « <?= htmlspecialchars($filterStatus) ?> ».</p>
                            <?php else: ?>
                                <p>Cliquez sur <strong>Nouvel appel</strong> pour enregistrer le premier appel.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <table id="calls-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Problème</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Mise à jour rapide</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calls as $call): ?>
                            <tr id="row-call-<?= $call['id'] ?>">

                                <!-- ID -->
                                <td class="td-muted">#<?= $call['id'] ?></td>

                                <!-- Client -->
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($call['client_name']) ?></div>
                                    <div class="td-muted" style="font-size:12px;"><?= htmlspecialchars($call['client_phone']) ?></div>
                                </td>

                                <!-- Problem -->
                                <td class="problem-cell">
                                    <?= htmlspecialchars(mb_strimwidth($call['problem'], 0, 80, '…')) ?>
                                </td>

                                <!-- Date -->
                                <td class="td-muted" style="white-space:nowrap;">
                                    <?= date('d/m/Y', strtotime($call['call_date'])) ?>
                                </td>

                                <!-- Status badge -->
                                <td>
                                    <span class="badge <?= callStatusBadge($call['status']) ?>">
                                        <?= callStatusEmoji($call['status']) ?>
                                        <?= htmlspecialchars($call['status']) ?>
                                    </span>
                                </td>

                                <!-- Quick status update -->
                                <td>
                                    <form
                                        method="POST"
                                        action="calls.php?action=update_status&id=<?= $call['id'] ?><?= $filterStatus !== '' ? '&status=' . urlencode($filterStatus) : '' ?>"
                                        class="status-form"
                                        id="form-status-<?= $call['id'] ?>"
                                    >
                                        <select
                                            name="status"
                                            class="status-select-inline"
                                            id="select-status-<?= $call['id'] ?>"
                                            onchange="this.closest('form').submit()"
                                            title="Changer le statut"
                                        >
                                            <?php foreach ($statuses as $s): ?>
                                                <option
                                                    value="<?= htmlspecialchars($s) ?>"
                                                    <?= $call['status'] === $s ? 'selected' : '' ?>
                                                >
                                                    <?= callStatusEmoji($s) ?> <?= htmlspecialchars($s) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>

                                <!-- Actions -->
                                <td class="action-col" style="text-align:right;">
                                    <a href="calls.php?action=edit&id=<?= $call['id'] ?>"
                                       class="btn btn-ghost btn-sm"
                                       id="btn-edit-call-<?= $call['id'] ?>"
                                       title="Modifier cet appel">
                                        ✏️ Modifier
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm"
                                        id="btn-delete-call-<?= $call['id'] ?>"
                                        title="Supprimer cet appel"
                                        onclick="confirmDeleteCall(
                                            <?= $call['id'] ?>,
                                            '<?= addslashes(htmlspecialchars($call['client_name'])) ?>'
                                        )">
                                        🗑️ Supprimer
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div><!-- /table-wrapper -->
            </div><!-- /panel -->

        </div><!-- /page-body -->
    </div><!-- /main-content -->
</div><!-- /app-wrapper -->

<!-- ══════════════════════════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="delete-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal">
        <div class="modal-icon">🗑️</div>
        <h3 id="modal-title">Supprimer cet appel ?</h3>
        <p id="modal-message">Cette action est irréversible.</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal()" id="btn-cancel-delete">Annuler</button>
            <a href="#" class="btn btn-danger" id="btn-confirm-delete">🗑️ Supprimer</a>
        </div>
    </div>
</div>

<script>
// ── Delete modal ──────────────────────────────────────────────────────────────
function confirmDeleteCall(callId, clientName) {
    const modal   = document.getElementById('delete-modal');
    const message = document.getElementById('modal-message');
    const confirm = document.getElementById('btn-confirm-delete');

    message.innerHTML =
        'Êtes-vous sûr de vouloir supprimer l\'appel <strong>#' + callId + '</strong> ' +
        'du client <strong>' + clientName + '</strong> ?<br>' +
        '<span style="color:var(--danger);font-size:12px;">Cette action est irréversible.</span>';

    confirm.href = 'calls.php?action=delete&id=' + callId;
    modal.classList.add('open');
}

function closeModal() {
    document.getElementById('delete-modal').classList.remove('open');
}

document.getElementById('delete-modal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
});

// ── Character counter for problem textarea ────────────────────────────────────
const problemTA  = document.getElementById('problem');
const charCount  = document.getElementById('char-count');
if (problemTA && charCount) {
    const updateCount = () => { charCount.textContent = problemTA.value.length; };
    updateCount();
    problemTA.addEventListener('input', updateCount);
}

// ── Auto-dismiss flash message ───────────────────────────────────────────────
const flash = document.querySelector('.flash');
if (flash) {
    setTimeout(() => {
        flash.style.transition = 'opacity .5s ease';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 500);
    }, 5000);
}
</script>
</body>
</html>
