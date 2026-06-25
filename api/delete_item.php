<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int) ($data['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid id']));
}

// Collect physical files before cascade deletes the DB rows
$stmt = $pdo->prepare("SELECT filename FROM todo_attachments WHERE todo_id = ?");
$stmt->execute([$id]);
$filenames = $stmt->fetchAll(PDO::FETCH_COLUMN);

$pdo->prepare("DELETE FROM todo_items WHERE id = ? AND workspace_id = ?")->execute([$id, $workspaceId]);

foreach ($filenames as $filename) {
    $path = __DIR__ . '/uploads/' . $filename;
    if (file_exists($path)) {
        unlink($path);
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true]);
