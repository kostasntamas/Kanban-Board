<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data = json_decode(file_get_contents('php://input'), true);
$key  = $data['key'] ?? '';

if (!$key) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid input']));
}

$stmt = $pdo->prepare("SELECT id FROM kanban_columns WHERE col_key = ? AND workspace_id = ?");
$stmt->execute([$key, $workspaceId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    die(json_encode(['error' => 'Column not found']));
}

// Collect physical files before cascade deletes DB rows
$stmt = $pdo->prepare("
    SELECT a.filename FROM todo_attachments a
    JOIN todo_items t ON a.todo_id = t.id
    WHERE t.col = ?
");
$stmt->execute([$key]);
$files = $stmt->fetchAll(PDO::FETCH_COLUMN);

$pdo->prepare("DELETE FROM todo_items WHERE col = ?")->execute([$key]);

foreach ($files as $filename) {
    $path = __DIR__ . '/uploads/' . $filename;
    if (file_exists($path)) unlink($path);
}

$pdo->prepare("DELETE FROM kanban_columns WHERE col_key = ?")->execute([$key]);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
