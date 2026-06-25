<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$data  = json_decode(file_get_contents('php://input'), true);
$label = trim($data['label'] ?? '');

if (!$label) {
    http_response_code(400);
    die(json_encode(['error' => 'Label required']));
}

$base = preg_replace('/[^a-z0-9]+/', '_', strtolower($label));
$key  = trim($base, '_') . '_' . substr(uniqid(), -6);

$stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM kanban_columns WHERE workspace_id = ?");
$stmt->execute([$workspaceId]);
$pos = (int) $stmt->fetchColumn();

$pdo->prepare("INSERT INTO kanban_columns (col_key, label, position, workspace_id) VALUES (?, ?, ?, ?)")
    ->execute([$key, $label, $pos, $workspaceId]);

echo json_encode(['key' => $key, 'label' => $label]);
