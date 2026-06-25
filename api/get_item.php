<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid input']));
}

$stmt = $pdo->prepare("SELECT * FROM todo_items WHERE id = ? AND workspace_id = ?");
$stmt->execute([$id, $workspaceId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    die(json_encode(['error' => 'Not found']));
}

$stmt = $pdo->prepare(
    "SELECT id, original_name, mime_type, file_size, filename
     FROM todo_attachments WHERE todo_id = ? ORDER BY created_at ASC"
);
$stmt->execute([$id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($attachments as &$att) {
    $att['id']  = (int) $att['id'];
    $att['url'] = 'uploads/' . $att['filename'];
}

$aStmt = $pdo->prepare("
    SELECT u.id, u.name FROM todo_item_assignees tia
    JOIN users u ON u.id = tia.user_id WHERE tia.todo_id = ? ORDER BY u.name
");
$aStmt->execute([$id]);
$assignees = [];
foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $assignees[] = ['id' => (int)$u['id'], 'name' => $u['name'], 'initials' => initials($u['name']), 'color' => avatarColor($u['id'])];
}

echo json_encode([
    'id'          => (int) $item['id'],
    'content'     => $item['content'],
    'col'         => $item['col'],
    'assignees'   => $assignees,
    'attachments' => $attachments,
]);
