<?php
ini_set('display_errors', '0');
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Page helpers (redirect on failure) ────────────────────────────────────────

function requireLogin(): array
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    return $u;
}

function requireWorkspace(array $user): int
{
    if (!isset($_SESSION['workspace_id'])) {
        header('Location: workspaces.php');
        exit;
    }
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ?"
    );
    $stmt->execute([$_SESSION['workspace_id'], $user['id']]);
    if (!$stmt->fetch()) {
        unset($_SESSION['workspace_id']);
        header('Location: workspaces.php');
        exit;
    }
    return (int) $_SESSION['workspace_id'];
}

function requireAdmin(array $user): void
{
    if (!$user['is_admin']) {
        header('Location: index.php');
        exit;
    }
}

// ── API helpers (JSON 4xx on failure) ─────────────────────────────────────────

function apiLogin(): array
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
    return $u;
}

function apiWorkspace(array $user): int
{
    if (!isset($_SESSION['workspace_id'])) {
        http_response_code(403);
        die(json_encode(['error' => 'No workspace selected']));
    }
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ?"
    );
    $stmt->execute([$_SESSION['workspace_id'], $user['id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        die(json_encode(['error' => 'Not a member of this workspace']));
    }
    return (int) $_SESSION['workspace_id'];
}

// ── Workspace actions ─────────────────────────────────────────────────────

function handleEnterWorkspace(array $user): void
{
    global $pdo;
    $wsId = (int) ($_POST['workspace_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
    $stmt->execute([$wsId, $user['id']]);
    if ($stmt->fetch()) {
        $_SESSION['workspace_id'] = $wsId;
        header('Location: index.php');
        exit;
    }
}

function getUserWorkspaces(array $user): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT w.id, w.name, wm.role,
               (SELECT COUNT(*) FROM workspace_members WHERE workspace_id = w.id) AS member_count
        FROM workspaces w
        JOIN workspace_members wm ON wm.workspace_id = w.id AND wm.user_id = ?
        ORDER BY w.created_at ASC
    ");
    $stmt->execute([$user['id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWorkspaceMembers(array $workspaces, array $user): array
{
    global $pdo;
    $membersByWs = [];
    foreach ($workspaces as $ws) {
        if ($ws['role'] === 'owner' || $user['is_admin']) {
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
    return $membersByWs;
}

// ── Utility ───────────────────────────────────────────────────────────────────

function initials(string $name): string
{
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 3) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 3));
}

function avatarColor(int $userId): string
{
    $colors = ['#e07b54', '#54a0e0', '#54c077', '#a054e0', '#e0c454', '#e05490', '#54e0d4', '#7b54e0'];
    return $colors[$userId % count($colors)];
}
