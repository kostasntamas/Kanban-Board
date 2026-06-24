<?php
require 'db.php';
require 'auth.php';
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
    SELECT t.*, COALESCE(a.cnt, 0) AS attachment_count,
           u.name AS assigned_name, u.id AS assigned_user_id
    FROM todo_items t
    LEFT JOIN (SELECT todo_id, COUNT(*) AS cnt FROM todo_attachments GROUP BY todo_id) a
        ON a.todo_id = t.id
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE t.workspace_id = ?
    ORDER BY t.col, t.position
");
$rows->execute([$workspaceId]);

foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($columns[$row['col']])) {
        $columns[$row['col']]['items'][] = $row;
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
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="style.css">

    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js" defer></script>
    <script src="script.js" defer></script>
    <title><?= htmlspecialchars($wsName) ?> — Kanban</title>
</head>

<body>

    <!-- Item dialog -->
    <dialog id="item-dialog">
        <header class="dialog-header">
            <h3 id="modal-title">Add Item</h3>
            <button id="modal-close" class="modal-close-btn" aria-label="Close">&times;</button>
        </header>
        <div class="dialog-body">
            <div id="quill-editor"></div>
            <div class="assign-section">
                <p class="attachments-label">Assignee</p>
                <select id="assign-select" class="assign-select">
                    <option value="">Unassigned</option>
                </select>
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
            <a href="logout.php" class="sidebar-nav-btn logout" title="Sign out">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </a>
        </nav>
    </aside>

    <main id="drag-lists">
        <?php foreach ($columns as $colKey => $col): ?>
            <div class="container shadow" data-col-key="<?= htmlspecialchars($colKey) ?>">
                <div class="col-header">
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
                            <?php if ($item['assigned_name']): ?>
                                <div class="assigned-badge"
                                    style="background:<?= avatarColor((int)$item['assigned_user_id']) ?>"
                                    title="<?= htmlspecialchars($item['assigned_name']) ?>">
                                    <?= htmlspecialchars(initials($item['assigned_name'])) ?>
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