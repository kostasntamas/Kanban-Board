<?php
require 'includes/db.php';
require 'includes/auth.php';
require 'includes/vite.php';
$me = requireLogin();

// ── Actions ────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'enter') {
        handleEnterWorkspace($me);
    }

    if ($action === 'create') {
        $name = trim($_POST['ws_name'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO workspaces (name, owner_id) VALUES (?, ?)")
                ->execute([$name, $me['id']]);
            $wsId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role) VALUES (?, ?, 'owner')")
                ->execute([$wsId, $me['id']]);
        }
        header('Location: workspaces.php');
        exit;
    }

    if ($action === 'delete_ws' && $me['is_admin']) {
        $wsId = (int) ($_POST['workspace_id'] ?? 0);
        // Collect attachment files before cascade-deleting items
        $files = $pdo->prepare("
            SELECT a.filename FROM todo_attachments a
            JOIN todo_items t ON a.todo_id = t.id
            WHERE t.workspace_id = ?
        ");
        $files->execute([$wsId]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) {
            $p = __DIR__ . '/uploads/' . $f;
            if (file_exists($p)) unlink($p);
        }
        $pdo->prepare("DELETE FROM todo_items WHERE workspace_id = ?")->execute([$wsId]);
        $pdo->prepare("DELETE FROM kanban_columns WHERE workspace_id = ?")->execute([$wsId]);
        $pdo->prepare("DELETE FROM workspaces WHERE id = ?")->execute([$wsId]);
        if ((int)($_SESSION['workspace_id'] ?? 0) === $wsId) unset($_SESSION['workspace_id']);
        header('Location: workspaces.php');
        exit;
    }

    if ($action === 'add_member') {
        $wsId  = (int) ($_POST['workspace_id'] ?? 0);
        $email = trim($_POST['member_email'] ?? '');
        // Only owner or admin can add members
        $stmt = $pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $stmt->execute([$wsId, $me['id']]);
        $myRole = $stmt->fetchColumn();
        if ($myRole === 'owner' || $me['is_admin']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($target) {
                $pdo->prepare(
                    "INSERT IGNORE INTO workspace_members (workspace_id, user_id, role) VALUES (?, ?, 'member')"
                )->execute([$wsId, $target['id']]);
            }
        }
        header('Location: workspaces.php');
        exit;
    }

    if ($action === 'remove_member') {
        $wsId   = (int) ($_POST['workspace_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        // Only owner or admin can remove; can't remove the owner themselves
        $stmt = $pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $stmt->execute([$wsId, $me['id']]);
        $myRole = $stmt->fetchColumn();
        if ($myRole === 'owner' || $me['is_admin']) {
            $stmt = $pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
            $stmt->execute([$wsId, $userId]);
            if ($stmt->fetchColumn() !== 'owner') {
                $pdo->prepare("DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ?")
                    ->execute([$wsId, $userId]);
            }
        }
        header('Location: workspaces.php');
        exit;
    }

    header('Location: workspaces.php');
    exit;
}

// ── Data ───────────────────────────────────────────────────────────────────────

$workspaces  = getUserWorkspaces($me);
$membersByWs = getWorkspaceMembers($workspaces, $me);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspaces — Kanban</title>
    <?= vite_assets('src/js/pages.js') ?>
</head>

<body class="page-body">
    <!-- New-Workspace dialog -->
    <dialog id="newWsForm" class="card" closedby="any">
        <p class="card-title" style="font-size:0.95rem">Create workspace</p>
        <form method="POST" style="display:flex;gap:0.5rem">
            <input type="hidden" name="action" value="create">
            <input class="form-input" type="text" name="ws_name" placeholder="Workspace name…"
                maxlength="80" autocomplete="off" required style="flex:1">
            <button class="btn-primary" type="submit">Create</button>
        </form>
    </dialog>
    <nav class="top-nav">
        <a href="index.php" class="top-nav-brand">Kanban Board</a>
        <a href="workspaces.php" <?= $me['is_admin'] ? '' : 'style="margin-right: auto;"' ?> aria-current="page">Workspaces</a>
        <?php if ($me['is_admin']): ?>
            <a href="admin.php" style="margin-right: auto;">Admin</a>
        <?php endif; ?>
        <div class="top-nav-user">
            <span class="top-nav-avatar"
                style="background:<?= avatarColor($me['id']) ?>">
                <?= htmlspecialchars(initials($me['name'])) ?>
            </span>
            <?= htmlspecialchars($me['name']) ?>
        </div>
        <a href="logout.php" class="logout">Sign out</a>
    </nav>

    <div class="page-content wrapper flow">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <h1 class="page-title">Workspaces</h1>
            <button class="btn-primary btn-sm" command="show-modal" commandfor="newWsForm">
                + New workspace
            </button>
        </div>
        <?php if (empty($workspaces)): ?>
            <p style="color:#aaa;text-align:center;margin-top:3rem">
                You're not a member of any workspace yet.
            </p>
        <?php else: ?>
            <div class="workspaces-grid">
                <?php foreach ($workspaces as $ws): ?>
                    <div class="workspace-card">
                        <div style="border-bottom: 1px dotted black; padding-bottom: .5rem;">
                            <div class="workspace-name">
                                <h2><?= htmlspecialchars($ws['name']) ?></h2>
                                <span class="role-badge <?= $ws['role'] === 'owner' ? 'owner' : 'member' ?>">
                                    <?= $ws['role'] ?>
                                </span>
                            </div>
                            <div class="workspace-meta">
                                <span><?= $ws['member_count'] ?> member<?= $ws['member_count'] !== '1' ? 's' : '' ?></span>
                            </div>
                        </div>

                        <div class="workspace-actions">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="enter">
                                <input type="hidden" name="workspace_id" value="<?= $ws['id'] ?>">
                                <button class="btn-primary btn-sm" type="submit">Enter</button>
                            </form>
                            <?php if (isset($membersByWs[$ws['id']])): ?>
                                <button class="members-toggle"
                                    onclick="this.closest('.workspace-card').querySelector('.members-panel').classList.toggle('hidden')">
                                    Manage members
                                </button>
                            <?php endif; ?>
                            <?php if ($ws['role'] === 'owner' && $me['is_admin']): ?>
                                <form method="POST" style="display:inline; margin-inline-start: auto;"
                                    onsubmit="return confirm('Delete workspace &quot;<?= htmlspecialchars(addslashes($ws['name'])) ?>&quot; and all its data?')">
                                    <input type="hidden" name="action" value="delete_ws">
                                    <input type="hidden" name="workspace_id" value="<?= $ws['id'] ?>">
                                    <button class="btn-danger btn-sm" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (isset($membersByWs[$ws['id']])): ?>
                            <div class="members-panel hidden">
                                <?php foreach ($membersByWs[$ws['id']] as $m): ?>
                                    <div class="member-row">
                                        <span class="member-avatar" style="background:<?= avatarColor($m['id']) ?>">
                                            <?= htmlspecialchars(initials($m['name'])) ?>
                                        </span>
                                        <span class="member-name"><?= htmlspecialchars($m['name']) ?></span>
                                        <span class="member-email"><?= htmlspecialchars($m['email']) ?></span>
                                        <?php if ($m['role'] !== 'owner'): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="action" value="remove_member">
                                                <input type="hidden" name="workspace_id" value="<?= $ws['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                                <button class="btn-danger" style="padding:0.15rem 0.4rem;font-size:0.78rem"
                                                    type="submit" title="Remove">×</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <form method="POST" class="add-member-form">
                                    <input type="hidden" name="action" value="add_member">
                                    <input type="hidden" name="workspace_id" value="<?= $ws['id'] ?>">
                                    <input type="email" name="member_email" placeholder="Add by email…"
                                        autocomplete="off" required>
                                    <button class="btn-secondary btn-sm" type="submit">Add</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>