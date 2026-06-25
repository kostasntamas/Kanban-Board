<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data    = json_decode(file_get_contents('php://input'), true);
$id      = (int) ($data['id'] ?? 0);
$content = $data['content'] ?? '';

if (!$id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid input']));
}

$stmt = $pdo->prepare("SELECT id, col FROM todo_items WHERE id = ? AND workspace_id = ?");
$stmt->execute([$id, $workspaceId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden']));
}

$pdo->prepare("UPDATE todo_items SET content = ? WHERE id = ?")->execute([$content, $id]);

// Column move
$newCol = $data['col'] ?? null;
$moved  = false;
if ($newCol && $newCol !== $item['col']) {
    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM kanban_columns WHERE col_key = ? AND workspace_id = ?");
    $colCheck->execute([$newCol, $workspaceId]);
    if ((int) $colCheck->fetchColumn()) {
        $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM todo_items WHERE col = ?");
        $posStmt->execute([$newCol]);
        $newPos = (int) $posStmt->fetchColumn();
        $pdo->prepare("UPDATE todo_items SET col = ?, position = ? WHERE id = ?")
            ->execute([$newCol, $newPos, $id]);
        $moved = true;
    }
}

// Assignees
$assignees = isset($data['assigned_to']) && is_array($data['assigned_to']) ? $data['assigned_to'] : null;
$assignedList = [];
if ($assignees !== null) {
    $pdo->prepare("DELETE FROM todo_item_assignees WHERE todo_id = ?")->execute([$id]);
    $ins = $pdo->prepare("INSERT INTO todo_item_assignees (todo_id, user_id) VALUES (?, ?)");
    foreach ($assignees as $uid) {
        $ins->execute([$id, (int) $uid]);
    }
}

$aStmt = $pdo->prepare("
    SELECT u.id, u.name FROM todo_item_assignees tia
    JOIN users u ON u.id = tia.user_id WHERE tia.todo_id = ? ORDER BY u.name
");
$aStmt->execute([$id]);
foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $assignedList[] = ['id' => (int)$u['id'], 'name' => $u['name'], 'initials' => initials($u['name']), 'color' => avatarColor($u['id'])];
}

echo json_encode([
    'success'  => true,
    'assigned' => $assignedList,
    'moved'    => $moved,
    'col'      => $newCol ?: $item['col'],
]);
