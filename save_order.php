<?php
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON']));
}

$allowed = ['todo', 'in_progress', 'done', 'backlog', 'others'];
$stmt = $pdo->prepare("UPDATE todo_items SET col = ?, position = ? WHERE id = ?");

foreach ($data as $col => $ids) {
    if (!in_array($col, $allowed, true)) continue;
    foreach ($ids as $pos => $id) {
        $stmt->execute([$col, $pos, (int) $id]);
    }
}

echo json_encode(['success' => true]);
