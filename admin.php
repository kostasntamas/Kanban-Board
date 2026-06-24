<?php
require 'db.php';
require 'auth.php';
$me = requireLogin();
requireAdmin($me);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $isAdmin  = !empty($_POST['is_admin']) ? 1 : 0;

        if (!$name || !$email || !$password) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)")
                    ->execute([$name, $email, $hash, $isAdmin]);
                $success = "User \"{$name}\" created.";
            } catch (PDOException $e) {
                $error = 'Email already in use.';
            }
        }
    }

    if ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId === $me['id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            $success = 'User deleted.';
        }
    }
}

$users = $pdo->query("SELECT id, name, email, is_admin, created_at FROM users ORDER BY created_at ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="pages.css">
    <title>Admin — Kanban</title>
</head>

<body class="page-body">

    <nav class="top-nav">
        <a href="index.php" class="top-nav-brand">Kanban Board</a>
        <a href="workspaces.php" <?= $me['is_admin'] ? '' : 'style="margin-right: auto;"' ?>>Workspaces</a>
        <?php if ($me['is_admin']): ?>
            <a href="admin.php" aria-current="page" style="margin-right: auto;">Admin</a>
        <?php endif; ?>
        <div class="top-nav-user">
            <span class="top-nav-avatar" style="background:<?= avatarColor($me['id']) ?>">
                <?= htmlspecialchars(initials($me['name'])) ?>
            </span>
            <?= htmlspecialchars($me['name']) ?>
        </div>
        <a href="logout.php" class="logout">Sign out</a>
    </nav>

    <div class="page-content flow">
        <h1 class="page-title">User Management</h1>

        <?php if ($error):   ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="flash-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <!-- Create user form -->
        <div class="card">
            <p class="card-title">Add User</p>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Name</label>
                        <input class="form-input" type="text" name="name" required>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Email</label>
                        <input class="form-input" type="email" name="email" required>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Password</label>
                        <input class="form-input" type="password" name="password" required>
                    </div>
                    <div class="form-group" style="margin:0;justify-content:flex-end">
                        <label class="form-check" style="margin-top:1.5rem">
                            <input type="checkbox" name="is_admin" value="1">
                            Admin
                        </label>
                    </div>
                </div>
                <button class="btn-primary" type="submit" style="margin-top:1rem">Create user</button>
            </form>
        </div>

        <!-- Users table -->
        <div class="card">
            <p class="card-title">All Users (<?= count($users) ?>)</p>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="display:flex;align-items:center;gap:0.5rem">
                                <span class="top-nav-avatar" style="background:<?= avatarColor($u['id']) ?>;width:28px;height:28px">
                                    <?= htmlspecialchars(initials($u['name'])) ?>
                                </span>
                                <?= htmlspecialchars($u['name']) ?>
                                <?php if ($u['id'] === $me['id']): ?>
                                    <span style="font-size:0.75rem;color:#aaa">(you)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= $u['is_admin'] ? '<span class="admin-badge role-badge">admin</span>' : '<span class="user-badge role-badge">user</span>'; ?></td>
                            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['id'] !== $me['id']): ?>
                                    <form method="POST" style="display:inline"
                                        onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button class="btn-danger" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>