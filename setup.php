<?php
require 'includes/db.php';
require 'includes/vite.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount > 0) {
    header('Location: login.php'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, 1)")
            ->execute([$name, $email, $hash]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO workspaces (name, owner_id) VALUES ('Default', ?)")
            ->execute([$userId]);
        $wsId = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role) VALUES (?, ?, 'owner')")
            ->execute([$wsId, $userId]);

        // Migrate any pre-existing orphaned columns/items to the default workspace
        $pdo->prepare("UPDATE kanban_columns SET workspace_id = ? WHERE workspace_id IS NULL")
            ->execute([$wsId]);
        $pdo->prepare("UPDATE todo_items SET workspace_id = ? WHERE workspace_id IS NULL")
            ->execute([$wsId]);

        $_SESSION['user_id']      = $userId;
        $_SESSION['workspace_id'] = $wsId;
        header('Location: index.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= vite_assets('src/js/pages.js') ?>
    <title>Setup — Kanban</title>
</head>
<body class="page-body">
<div class="auth-wrap">
    <div class="auth-card">
        <p class="auth-logo">Kanban Board</p>
        <p class="auth-subtitle">Create your admin account to get started</p>

        <?php if ($error): ?>
            <div class="form-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="name">Your name</label>
                <input class="form-input" type="text" id="name" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       autocomplete="name" autofocus required>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-input" type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       autocomplete="email" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input" type="password" id="password" name="password"
                       autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm">Confirm password</label>
                <input class="form-input" type="password" id="confirm" name="confirm"
                       autocomplete="new-password" required>
            </div>
            <button class="btn-primary" type="submit" style="width:100%;margin-top:0.5rem">
                Create admin account
            </button>
        </form>
    </div>
</div>
</body>
</html>
