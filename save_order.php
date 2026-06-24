<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON']));
}

$stmt = $pdo->prepare("SELECT col_key FROM kanban_columns WHERE workspace_id = ?");
$stmt->execute([$workspaceId]);
$validCols = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt = $pdo->prepare("UPDATE todo_items SET col = ?, position = ? WHERE id = ?");

foreach ($data as $col => $ids) {
    if (!isset($validCols[$col])) continue;
    foreach ($ids as $pos => $id) {
        $stmt->execute([$col, $pos, (int) $id]);
    }
}

echo json_encode(['success' => true]);
