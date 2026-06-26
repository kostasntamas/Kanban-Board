<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/vite.php';
$me          = requireLogin();
$workspaceId = requireWorkspace($me);

// ── Workspace data ─────────────────────────────────────────────────────────────

$wsStmt = $pdo->prepare("SELECT name FROM workspaces WHERE id = ?");
$wsStmt->execute([$workspaceId]);
$wsName = $wsStmt->fetchColumn() ?: 'Workspace';

// ── Column + item data ─────────────────────────────────────────────────────────

$colRows = $pdo->prepare("SELECT * FROM kanban_columns WHERE workspace_id = ? ORDER BY position, id");
$colRows->execute([$workspaceId]);
$colRows = $colRows->fetchAll(PDO::FETCH_ASSOC);

$columns = [];
foreach ($colRows as $col) {
    $columns[$col['col_key']] = ['label' => $col['label'], 'items' => []];
}

$rows = $pdo->prepare("
    SELECT t.*, COALESCE(a.cnt, 0) AS attachment_count
    FROM todo_items t
    LEFT JOIN (SELECT todo_id, COUNT(*) AS cnt FROM todo_attachments GROUP BY todo_id) a
        ON a.todo_id = t.id
    WHERE t.workspace_id = ?
    ORDER BY t.col, t.position
");
$rows->execute([$workspaceId]);

$allItems = $rows->fetchAll(PDO::FETCH_ASSOC);
$itemIds  = array_column($allItems, 'id');

$assigneesByItem = [];
if ($itemIds) {
    $ph = implode(',', array_fill(0, count($itemIds), '?'));
    $aStmt = $pdo->prepare("
        SELECT tia.todo_id, u.id, u.name
        FROM todo_item_assignees tia
        JOIN users u ON u.id = tia.user_id
        WHERE tia.todo_id IN ($ph)
        ORDER BY u.name
    ");
    $aStmt->execute($itemIds);
    foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $ar) {
        $assigneesByItem[(int)$ar['todo_id']][] = $ar;
    }
}

foreach ($allItems as $row) {
    $row['assignees'] = $assigneesByItem[(int)$row['id']] ?? [];
    if (isset($columns[$row['col']])) {
        $columns[$row['col']]['items'][] = $row;
    }
}


$stmt = $pdo->prepare("
    SELECT w.id, w.name, wm.role,
           (SELECT COUNT(*) FROM workspace_members WHERE workspace_id = w.id) AS member_count
    FROM workspaces w
    JOIN workspace_members wm ON wm.workspace_id = w.id AND wm.user_id = ?
    ORDER BY w.created_at ASC
");
$stmt->execute([$me['id']]);
$workspaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Members per workspace (for owners)
$membersByWs = [];
foreach ($workspaces as $ws) {
    if ($ws['role'] === 'owner' || $me['is_admin']) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, wm.role
            FROM workspace_members wm
            JOIN users u ON u.id = wm.user_id
            WHERE wm.workspace_id = ?
            ORDER BY wm.role DESC, u.name ASC
        ");
        $stmt->execute([$ws['id']]);
        $membersByWs[$ws['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


$editIconSvg   = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M3 21v-4.25L16.2 3.575q.3-.275.663-.425t.762-.15t.775.15t.65.45L20.425 5q.3.275.438.65T21 6.4q0 .4-.137.763t-.438.662L7.25 21zM17.6 7.8L19 6.4L17.6 5l-1.4 1.4z"/></svg>';
$deleteIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M7 21q-.825 0-1.412-.587T5 19V6H4V4h5V3h6v1h5v2h-1v13q0 .825-.587 1.413T17 21zM17 6H7v13h10zM9 17h2V8H9zm4 0h2V8h-2zM7 6v13z"/></svg>';
$dragIconSvg   = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M9 20q-.825 0-1.412-.587T7 18t.588-1.412T9 16t1.413.588T11 18t-.587 1.413T9 20m6 0q-.825 0-1.412-.587T13 18t.588-1.412T15 16t1.413.588T17 18t-.587 1.413T15 20m-6-6q-.825 0-1.412-.587T7 12t.588-1.412T9 10t1.413.588T11 12t-.587 1.413T9 14m6 0q-.825 0-1.412-.587T13 12t.588-1.412T15 10t1.413.588T17 12t-.587 1.413T15 14M9 8q-.825 0-1.412-.587T7 6t.588-1.412T9 4t1.413.588T11 6t-.587 1.413T9 8m6 0q-.825 0-1.412-.587T13 6t.588-1.412T15 4t1.413.588T17 6t-.587 1.413T15 8"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= vite_assets('src/js/board.js') ?>
    <title><?= htmlspecialchars($wsName) ?> — Kanban</title>
</head>

<body>
    <script>
        window.__KANBAN_COLUMNS__ = <?= json_encode(array_map(fn($k, $v) => ['key' => $k, 'label' => $v['label']], array_keys($columns), $columns)) ?>;
    </script>

    <!-- Item dialog -->
    <dialog id="item-dialog">
        <header class="dialog-header">
            <h3 id="modal-title">Add Item</h3>
            <select id="col-select" class="col-select"></select>
            <button id="modal-close" class="modal-close-btn" aria-label="Close">&times;</button>
        </header>
        <div class="dialog-body">
            <div id="quill-editor"></div>
            <div class="assign-section">
                <p class="attachments-label">Assignees</p>
                <div class="multiselect" id="assign-multiselect">
                    <div class="multiselect-control" id="assign-control">
                        <div class="multiselect-chips" id="assign-chips"></div>
                        <input type="text" class="multiselect-search" id="assign-search"
                            placeholder="Add assignee..." autocomplete="off">
                    </div>
                    <div class="multiselect-dropdown hidden" id="assign-dropdown"></div>
                </div>
            </div>
            <div class="attachments-section">
                <p class="attachments-label">Attachments</p>
                <div class="file-drop-zone" id="file-drop-zone">
                    <input type="file" id="file-input" multiple
                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                    <span>Drop files here or <label for="file-input" class="file-label">browse</label></span>
                </div>
                <div id="attachments-list" class="attachments-list"></div>
            </div>
        </div>
        <footer class="dialog-footer">
            <button id="cancel-btn" class="btn btn-cancel">Cancel</button>
            <button id="save-btn" class="btn btn-save">Save</button>
        </footer>
    </dialog>

    <!-- New-column dialog -->
    <dialog id="col-dialog">
        <div class="col-dialog-content">
            <h3>New Column</h3>
            <input type="text" id="col-label-input" placeholder="Column name…" maxlength="50" autocomplete="off">
            <div class="col-dialog-actions">
                <button type="button" id="col-cancel-btn" class="btn btn-cancel">Cancel</button>
                <button type="button" id="col-create-btn" class="btn btn-save">Create</button>
            </div>
        </div>
    </dialog>

    <aside id="sidebar">
        <!-- User avatar -->
        <a href="workspaces.php" class="sidebar-avatar"
            style="background:<?= avatarColor($me['id']) ?>"
            title="<?= htmlspecialchars($me['name']) ?> — <?= htmlspecialchars($wsName) ?>">
            <?= htmlspecialchars(initials($me['name'])) ?>
        </a>

        <!-- Add column -->
        <button id="add-col-trigger" title="Add column" aria-label="Add column">+</button>

        <!-- Bottom nav -->
        <nav class="sidebar-nav">
            <a href="workspaces.php" class="sidebar-nav-btn" title="Workspaces">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>
            </a>
            <?php if ($me['is_admin']): ?>
                <a href="admin.php" class="sidebar-nav-btn" title="Admin">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </a>
            <?php endif; ?>
            <div class="logout-wrapper">
                <a href="logout.php" class="sidebar-nav-btn logout" title="Sign out">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </a>
            </div>
        </nav>
    </aside>
    <header id="workspace-header">Workspace: <?= htmlspecialchars($wsName) ?></header>
    <main id="drag-lists">
        <?php foreach ($columns as $colKey => $col): ?>
            <div class="container shadow" data-col-key="<?= htmlspecialchars($colKey) ?>">
                <div class="col-header">
                    <button class="col-drag-btn" title="Drag column"><?= $dragIconSvg ?></button>
                    <h2><?= htmlspecialchars($col['label']) ?></h2>
                    <button class="delete-col-btn" title="Delete column">&times;</button>
                </div>
                <div class="add-form">
                    <button class="add-btn"
                        data-col="<?= htmlspecialchars($colKey) ?>"
                        data-label="<?= htmlspecialchars($col['label']) ?>">+ Add</button>
                </div>
                <ul class="drag-list" data-column="<?= htmlspecialchars($colKey) ?>">
                    <?php foreach ($col['items'] as $item): ?>
                        <li class="drag-item" data-id="<?= (int) $item['id'] ?>">
                            <div class="item-content"><?= $item['content'] ?></div>
                            <?php if ((int) $item['attachment_count'] > 0): ?>
                                <div class="item-attachment-badge">
                                    <span>&#128206;</span> <?= (int) $item['attachment_count'] ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['assignees'])): ?>
                                <div class="assigned-badges">
                                    <?php foreach ($item['assignees'] as $assignee): ?>
                                        <div class="assigned-badge"
                                            style="background:<?= avatarColor((int)$assignee['id']) ?>"
                                            title="<?= htmlspecialchars($assignee['name']) ?>">
                                            <?= htmlspecialchars(initials($assignee['name'])) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="interactions">
                                <button class="edit-btn" title="Edit"><?= $editIconSvg ?></button>
                                <button class="delete-btn" title="Delete"><?= $deleteIconSvg ?></button>
                                <button class="drag-btn" title="Drag"><?= $dragIconSvg ?></button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </main>
</body>

</html>