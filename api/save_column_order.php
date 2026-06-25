<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['order'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON']));
}

$stmt = $pdo->prepare("UPDATE kanban_columns SET position = ? WHERE col_key = ? AND workspace_id = ?");
foreach ($data['order'] as $pos => $key) {
    $stmt->execute([(int) $pos, $key, $workspaceId]);
}

echo json_encode(['success' => true]);
