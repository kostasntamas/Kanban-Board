<?php
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = (int) ($data['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid id']));
}

$stmt = $pdo->prepare("DELETE FROM todo_items WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true]);
