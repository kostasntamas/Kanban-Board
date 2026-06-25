<?php
require 'includes/db.php';
require 'includes/vite.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in, go to workspaces
if (isset($_SESSION['user_id'])) {
    header('Location: workspaces.php');
    exit;
}

// If no users exist yet, go to first-run setup
$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount === 0) {
    header('Location: setup.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: workspaces.php');
        exit;
    } else {
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= vite_assets('src/js/pages.js') ?>
    <title>Sign in — Kanban</title>
</head>

<body class="page-body">
    <div class="auth-wrap">
        <div class="auth-card shadow">
            <p class="auth-logo">Kanban Board</p>
            <p class="auth-subtitle">Sign in to your account</p>

            <?php if ($error): ?>
                <div class="form-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-input" type="email" id="email" name="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autocomplete="email" autofocus required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-input" type="password" id="password" name="password"
                        autocomplete="current-password" required>
                </div>
                <button class="btn-primary" type="submit" style="width:100%;margin-top:0.5rem">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</body>

</html>