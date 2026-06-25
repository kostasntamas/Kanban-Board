<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data       = json_decode(file_get_contents('php://input'), true);
$id         = (int) ($data['id'] ?? 0);
$content    = $data['content'] ?? '';
$assignedTo = array_key_exists('assigned_to', $data)
    ? ($data['assigned_to'] ? (int) $data['assigned_to'] : null)
    : false; // false = not provided, don't update

if (!$id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid input']));
}

// Verify item belongs to current workspace
$stmt = $pdo->prepare("SELECT id FROM todo_items WHERE id = ? AND workspace_id = ?");
$stmt->execute([$id, $workspaceId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}

if ($assignedTo !== false) {
    $pdo->prepare("UPDATE todo_items SET content = ?, assigned_to = ? WHERE id = ?")
        ->execute([$content, $assignedTo, $id]);
} else {
    $pdo->prepare("UPDATE todo_items SET content = ? WHERE id = ?")
        ->execute([$content, $id]);
}

$assigned = null;
$at = $assignedTo !== false ? $assignedTo : null;
if ($at) {
    $s = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $s->execute([$at]);
    $u = $s->fetch(PDO::FETCH_ASSOC);
    if ($u) $assigned = ['id' => (int)$u['id'], 'name' => $u['name'], 'initials' => initials($u['name']), 'color' => avatarColor($u['id'])];
}

echo json_encode(['success' => true, 'assigned' => $assigned]);
