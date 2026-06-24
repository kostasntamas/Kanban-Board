<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');

$user        = apiLogin();
$workspaceId = apiWorkspace($user);

$stmt = $pdo->prepare("
    SELECT u.id, u.name
    FROM workspace_members wm
    JOIN users u ON u.id = wm.user_id
    WHERE wm.workspace_id = ?
    ORDER BY u.name ASC
");
$stmt->execute([$workspaceId]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($members as &$m) {
    $m['id']       = (int) $m['id'];
    $m['initials'] = initials($m['name']);
    $m['color']    = avatarColor($m['id']);
}

echo json_encode($members);
