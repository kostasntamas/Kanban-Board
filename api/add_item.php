<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data      = json_decode(file_get_contents('php://input'), true);
$content   = $data['content'] ?? '';
$col       = $data['col'] ?? '';
$assignees = isset($data['assigned_to']) && is_array($data['assigned_to']) ? $data['assigned_to'] : [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM kanban_columns WHERE col_key = ? AND workspace_id = ?");
$stmt->execute([$col, $workspaceId]);
if (!(int) $stmt->fetchColumn()) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid column']));
}

$stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM todo_items WHERE col = ?");
$stmt->execute([$col]);
$pos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("INSERT INTO todo_items (content, col, position, workspace_id) VALUES (?, ?, ?, ?)");
$stmt->execute([$content, $col, $pos, $workspaceId]);
$itemId = (int) $pdo->lastInsertId();

$assignStmt = $pdo->prepare("INSERT INTO todo_item_assignees (todo_id, user_id) VALUES (?, ?)");
$assignedList = [];
foreach ($assignees as $uid) {
    $uid = (int) $uid;
    $assignStmt->execute([$itemId, $uid]);
    $s = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $s->execute([$uid]);
    $u = $s->fetch(PDO::FETCH_ASSOC);
    if ($u) $assignedList[] = ['id' => (int)$u['id'], 'name' => $u['name'], 'initials' => initials($u['name']), 'color' => avatarColor($u['id'])];
}

echo json_encode(['id' => $itemId, 'assigned' => $assignedList]);
