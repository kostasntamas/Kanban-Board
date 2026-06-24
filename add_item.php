<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data       = json_decode(file_get_contents('php://input'), true);
$content    = $data['content'] ?? '';
$col        = $data['col'] ?? '';
$assignedTo = isset($data['assigned_to']) && $data['assigned_to'] ? (int) $data['assigned_to'] : null;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM kanban_columns WHERE col_key = ? AND workspace_id = ?");
$stmt->execute([$col, $workspaceId]);
if (!(int) $stmt->fetchColumn()) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid column']));
}

$stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM todo_items WHERE col = ?");
$stmt->execute([$col]);
$pos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("INSERT INTO todo_items (content, col, position, workspace_id, assigned_to) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$content, $col, $pos, $workspaceId, $assignedTo]);

$itemId   = (int) $pdo->lastInsertId();
$assigned = null;
if ($assignedTo) {
    $s = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $s->execute([$assignedTo]);
    $u = $s->fetch(PDO::FETCH_ASSOC);
    if ($u) $assigned = ['id' => (int)$u['id'], 'name' => $u['name'], 'initials' => initials($u['name']), 'color' => avatarColor($u['id'])];
}
echo json_encode(['id' => $itemId, 'assigned' => $assigned]);
