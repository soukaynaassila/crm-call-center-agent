<?php
/**
 * clients.php
 * Client Management — Add, Edit, Delete, Search.
 *
 * GET  ?action=add              → show add form
 * GET  ?action=edit&id=N        → show edit form pre-filled
 * GET  ?action=delete&id=N      → delete client (then redirect)
 * POST ?action=add              → process add form
 * POST ?action=edit&id=N        → process edit form
 * GET  ?search=keyword          → filter table by name or phone
 */

require_once 'config.php';
require_auth();                          // redirect to login.php if not authenticated

start_session();

$activePage = 'clients';                 // highlights "Clients" in sidebar
$action     = trim($_GET['action'] ?? '');
$clientId   = (int) ($_GET['id']     ?? 0);
$errors     = [];
$formData   = ['name' => '', 'phone' => '', 'email' => ''];

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — POST handlers (form submissions)
// ══════════════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Collect & sanitize inputs ──────────────────────────────────────────
    $formData['name']  = trim($_POST['name']  ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');

    // ── Validate ───────────────────────────────────────────────────────────
    if ($formData['name'] === '') {
        $errors[] = 'Le nom du client est obligatoire.';
    } elseif (mb_strlen($formData['name']) > 100) {
        $errors[] = 'Le nom ne doit pas dépasser 100 caractères.';
    }

    if ($formData['phone'] === '') {
        $errors[] = 'Le numéro de téléphone est obligatoire.';
    } elseif (!preg_match('/^[\d\s\+\-\(\)\.]{6,30}$/', $formData['phone'])) {
        $errors[] = 'Le numéro de téléphone n\'est pas valide.';
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    }

    if (empty($errors)) {

        if ($action === 'add') {
            // ── INSERT new client ──────────────────────────────────────────
            $stmt = $pdo->prepare(
                'INSERT INTO clients (name, phone, email) VALUES (:name, :phone, :email)'
            );
            $stmt->execute([
                ':name'  => $formData['name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'] !== '' ? $formData['email'] : null,
            ]);
            set_flash('success', 'Client « ' . $formData['name'] . ' » ajouté avec succès.');
            redirect('clients.php');
        }

        if ($action === 'edit' && $clientId > 0) {
            // ── UPDATE existing client ─────────────────────────────────────
            $stmt = $pdo->prepare(
                'UPDATE clients SET name = :name, phone = :phone, email = :email WHERE id = :id'
            );
            $stmt->execute([
                ':name'  => $formData['name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'] !== '' ? $formData['email'] : null,
                ':id'    => $clientId,
            ]);
            set_flash('success', 'Client mis à jour avec succès.');
            redirect('clients.php');
        }
    }
    // If validation failed, fall through — the form will re-render with $errors
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — GET: delete handler
// ══════════════════════════════════════════════════════════════════════════════

if ($action === 'delete' && $clientId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch client name for the flash message
    $stmt = $pdo->prepare('SELECT name FROM clients WHERE id = :id');
    $stmt->execute([':id' => $clientId]);
    $toDelete = $stmt->fetch();

    if ($toDelete) {
        // DELETE — foreign key CASCADE will remove associated calls
        $pdo->prepare('DELETE FROM clients WHERE id = :id')->execute([':id' => $clientId]);
        set_flash('success', 'Client « ' . $toDelete['name'] . ' » supprimé.');
    } else {
        set_flash('error', 'Client introuvable.');
    }
    redirect('clients.php');
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — GET: load edit data
// ══════════════════════════════════════════════════════════════════════════════

$editClient = null;
if ($action === 'edit' && $clientId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id');
    $stmt->execute([':id' => $clientId]);
    $editClient = $stmt->fetch();

    if (!$editClient) {
        set_flash('error', 'Client introuvable.');
        redirect('clients.php');
    }

    // Pre-fill form with existing values
    $formData = [
        'name'  => $editClient['name'],
        'phone' => $editClient['phone'],
        'email' => $editClient['email'] ?? '',
    ];
}

// If POST edit failed validation, keep $formData as submitted (already set above)

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Fetch clients list (with optional search)
// ══════════════════════════════════════════════════════════════════════════════

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        'SELECT * FROM clients
         WHERE name LIKE :s1 OR phone LIKE :s2
         ORDER BY name ASC'
    );
    $stmt->execute([':s1' => $like, ':s2' => $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM clients ORDER BY name ASC');
}

$clients = $stmt->fetchAll();

// ── Call count per client (shown in table) ─────────────────────────────────
$callCounts = [];
$ccStmt = $pdo->query('SELECT client_id, COUNT(*) AS total FROM calls GROUP BY client_id');
foreach ($ccStmt->fetchAll() as $row) {
    $callCounts[$row['client_id']] = (int) $row['total'];
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients — Call Center CRM</title>
    <meta name="description" content="Gestion des clients du centre d'appels : ajout, modification, suppression et recherche.">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Page-specific styles ── */
        .client-count-pill {
            display: inline-block;
            background: var(--accent-light);
            color: var(--accent);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
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
        .action-col { white-space: nowrap; }
        .call-count-cell {
            text-align: center;
        }
        .no-calls { color: var(--text-muted); font-size: 12px; }
        /* Highlight matched search terms */
        mark {
            background: rgba(37,99,235,.25);
            color: var(--accent);
            border-radius: 2px;
            padding: 0 2px;
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
                <div class="header-title">Gestion des Clients</div>
                <div class="header-meta">
                    <?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?>
                    <?= $search !== '' ? ' trouvé(s) pour « <strong>' . htmlspecialchars($search) . '</strong> »' : ' au total' ?>
                </div>
            </div>
            <div class="header-actions">
                <span class="status-badge-online">En ligne</span>
                <?php if ($action !== 'add'): ?>
                    <a href="clients.php?action=add" class="btn btn-primary btn-sm" id="btn-add-client">
                        ➕ Nouveau client
                    </a>
                <?php else: ?>
                    <a href="clients.php" class="btn btn-ghost btn-sm">✖ Annuler</a>
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
                 Show only when ?action=add or ?action=edit
                 ══════════════════════════════════════════════════════════ -->
            <?php if ($action === 'add' || ($action === 'edit' && $clientId > 0)): ?>
            <div class="form-panel" id="client-form-panel">
                <div class="form-panel-header">
                    <div class="form-panel-title">
                        <?= $action === 'add' ? '➕ Ajouter un nouveau client' : '✏️ Modifier le client' ?>
                    </div>
                    <a href="clients.php" class="btn btn-ghost btn-sm">✖ Annuler</a>
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
                        action="clients.php?action=<?= htmlspecialchars($action) ?><?= $action === 'edit' ? '&id=' . $clientId : '' ?>"
                        novalidate
                        id="client-form"
                    >
                        <div class="form-grid">

                            <!-- Name -->
                            <div class="form-group">
                                <label for="name">Nom complet <span style="color:var(--danger)">*</span></label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="<?= htmlspecialchars($formData['name']) ?>"
                                    placeholder="Ex : Jean Dupont"
                                    maxlength="100"
                                    required
                                    autofocus
                                >
                            </div>

                            <!-- Phone -->
                            <div class="form-group">
                                <label for="phone">Téléphone <span style="color:var(--danger)">*</span></label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    value="<?= htmlspecialchars($formData['phone']) ?>"
                                    placeholder="Ex : 06 01 23 45 67"
                                    maxlength="30"
                                    required
                                >
                            </div>

                            <!-- Email (optional) -->
                            <div class="form-group">
                                <label for="email">Email <span style="color:var(--text-muted);font-weight:400;">(optionnel)</span></label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?= htmlspecialchars($formData['email']) ?>"
                                    placeholder="Ex : jean@example.com"
                                    maxlength="150"
                                >
                            </div>

                        </div><!-- /form-grid -->

                        <div class="form-actions" style="margin-top:18px;">
                            <button type="submit" class="btn btn-primary" id="btn-submit-client">
                                <?= $action === 'add' ? '➕ Ajouter le client' : '💾 Enregistrer les modifications' ?>
                            </button>
                            <a href="clients.php" class="btn btn-ghost">Annuler</a>
                        </div>
                    </form>

                </div>
            </div>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════════════════════
                 SEARCH + TABLE
                 ══════════════════════════════════════════════════════════ -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <span class="panel-icon">👥</span>
                        Liste des clients
                        <span class="client-count-pill"><?= count($clients) ?></span>
                    </div>

                    <!-- Search form -->
                    <form method="GET" action="clients.php" id="search-form" style="display:flex;gap:8px;align-items:center;">
                        <?php if ($action): ?>
                            <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <?php endif; ?>
                        <div class="search-bar">
                            <span class="search-icon">🔍</span>
                            <input
                                type="text"
                                name="search"
                                id="search-input"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Nom ou téléphone…"
                                autocomplete="off"
                            >
                        </div>
                        <button type="submit" class="btn btn-ghost btn-sm">Rechercher</button>
                        <?php if ($search !== ''): ?>
                            <a href="clients.php" class="btn btn-ghost btn-sm">✖ Effacer</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="table-wrapper">
                    <?php if (empty($clients)): ?>
                        <!-- Empty state -->
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <?php if ($search !== ''): ?>
                                <h3>Aucun résultat pour « <?= htmlspecialchars($search) ?> »</h3>
                                <p>Essayez un autre nom ou numéro de téléphone.</p>
                            <?php else: ?>
                                <h3>Aucun client enregistré</h3>
                                <p>Cliquez sur <strong>Nouveau client</strong> pour commencer.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <table id="clients-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nom</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th style="text-align:center;">Appels</th>
                                <th>Ajouté le</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr id="row-client-<?= $client['id'] ?>">

                                <!-- ID -->
                                <td class="td-muted">#<?= $client['id'] ?></td>

                                <!-- Name (with search highlight) -->
                                <td>
                                    <div style="font-weight:600;">
                                        <?php
                                        $name = htmlspecialchars($client['name']);
                                        if ($search !== '') {
                                            $name = preg_replace(
                                                '/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
                                                '<mark>$1</mark>',
                                                $name
                                            );
                                        }
                                        echo $name;
                                        ?>
                                    </div>
                                </td>

                                <!-- Phone (with search highlight) -->
                                <td>
                                    <?php
                                    $phone = htmlspecialchars($client['phone']);
                                    if ($search !== '') {
                                        $phone = preg_replace(
                                            '/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
                                            '<mark>$1</mark>',
                                            $phone
                                        );
                                    }
                                    echo $phone;
                                    ?>
                                </td>

                                <!-- Email -->
                                <td class="td-muted">
                                    <?php if (!empty($client['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($client['email']) ?>"
                                           style="color:var(--accent);font-size:13px;">
                                            <?= htmlspecialchars($client['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="no-calls">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Call count -->
                                <td class="call-count-cell">
                                    <?php $cnt = $callCounts[$client['id']] ?? 0; ?>
                                    <?php if ($cnt > 0): ?>
                                        <a href="calls.php?client_id=<?= $client['id'] ?>"
                                           class="client-count-pill"
                                           title="Voir les appels de ce client">
                                            <?= $cnt ?> appel<?= $cnt > 1 ? 's' : '' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="no-calls">Aucun</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Created at -->
                                <td class="td-muted" style="font-size:12px;">
                                    <?= date('d/m/Y', strtotime($client['created_at'])) ?>
                                </td>

                                <!-- Action buttons -->
                                <td class="action-col" style="text-align:right;">
                                    <!-- Edit -->
                                    <a href="clients.php?action=edit&id=<?= $client['id'] ?>"
                                       class="btn btn-ghost btn-sm"
                                       id="btn-edit-client-<?= $client['id'] ?>"
                                       title="Modifier ce client">
                                        ✏️ Modifier
                                    </a>
                                    <!-- Delete -->
                                    <button
                                        class="btn btn-danger btn-sm"
                                        id="btn-delete-client-<?= $client['id'] ?>"
                                        title="Supprimer ce client"
                                        onclick="confirmDelete(
                                            <?= $client['id'] ?>,
                                            '<?= addslashes(htmlspecialchars($client['name'])) ?>'
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
        <h3 id="modal-title">Confirmer la suppression</h3>
        <p id="modal-message">Êtes-vous sûr de vouloir supprimer ce client ? <br>
            <strong>Tous les appels associés seront également supprimés.</strong>
        </p>
        <div class="modal-actions">
            <button class="btn btn-ghost" id="btn-cancel-delete" onclick="closeModal()">Annuler</button>
            <a href="#" class="btn btn-danger" id="btn-confirm-delete">🗑️ Supprimer</a>
        </div>
    </div>
</div>

<script>
// ── Delete confirmation modal ─────────────────────────────────────────────────
function confirmDelete(clientId, clientName) {
    const modal   = document.getElementById('delete-modal');
    const message = document.getElementById('modal-message');
    const confirm = document.getElementById('btn-confirm-delete');

    message.innerHTML =
        'Êtes-vous sûr de vouloir supprimer <strong>' + clientName + '</strong> ? <br>' +
        '<span style="color:var(--danger);font-size:12px;">Tous les appels associés seront également supprimés.</span>';

    confirm.href = 'clients.php?action=delete&id=' + clientId;
    modal.classList.add('open');
}

function closeModal() {
    document.getElementById('delete-modal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('delete-modal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
});

// ── Auto-dismiss flash message after 5 s ─────────────────────────────────────
const flash = document.querySelector('.flash');
if (flash) {
    setTimeout(() => {
        flash.style.transition = 'opacity .5s ease';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 500);
    }, 5000);
}

// ── Live search (debounced) ───────────────────────────────────────────────────
let searchTimer;
const searchInput = document.getElementById('search-input');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            document.getElementById('search-form').submit();
        }, 500);
    });
}
</script>
</body>
</html>
