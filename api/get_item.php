<?php
require 'db.php';
require 'auth.php';
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

$assigned = null;
if ($item['assigned_to']) {
    $s = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $s->execute([$item['assigned_to']]);
    $u = $s->fetch(PDO::FETCH_ASSOC);
    if ($u) $assigned = ['id' => (int)$u['id'], 'name' => $u['name'], 'initials' => initials($u['name']), 'color' => avatarColor($u['id'])];
}

echo json_encode([
    'id'          => (int) $item['id'],
    'content'     => $item['content'],
    'col'         => $item['col'],
    'assigned_to' => $item['assigned_to'] ? (int) $item['assigned_to'] : null,
    'assigned'    => $assigned,
    'attachments' => $attachments,
]);
