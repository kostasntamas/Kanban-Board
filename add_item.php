<?php
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$content = trim($data['content'] ?? '');
$col = $data['col'] ?? '';

$allowed = ['todo', 'in_progress', 'done', 'backlog', 'others'];
if (!$content || !in_array($col, $allowed, true)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid input']));
}

$stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM todo_items WHERE col = ?");
$stmt->execute([$col]);
$pos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("INSERT INTO todo_items (content, col, position) VALUES (?, ?, ?)");
$stmt->execute([$content, $col, $pos]);

echo json_encode(['id' => $pdo->lastInsertId()]);
