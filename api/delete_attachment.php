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
    die(json_encode(['error' => 'Invalid input']));
}

$stmt = $pdo->prepare("SELECT filename FROM todo_attachments WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    die(json_encode(['error' => 'Attachment not found']));
}

$path = __DIR__ . '/uploads/' . $row['filename'];
if (file_exists($path)) {
    unlink($path);
}

$pdo->prepare("DELETE FROM todo_attachments WHERE id = ?")->execute([$id]);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
