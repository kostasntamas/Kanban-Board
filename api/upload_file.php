<?php
require 'db.php';
require 'auth.php';
$currentUser = apiLogin();
$workspaceId = apiWorkspace($currentUser);

$todoId = isset($_POST['todo_id']) ? (int) $_POST['todo_id'] : 0;
if (!$todoId || !isset($_FILES['file'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid input']));
}

$stmt = $pdo->prepare("SELECT id FROM todo_items WHERE id = ? AND workspace_id = ?");
$stmt->execute([$todoId, $workspaceId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    die(json_encode(['error' => 'Item not found']));
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'Upload error: ' . $file['error']]));
}

$allowedMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(400);
    die(json_encode(['error' => 'File type not allowed']));
}

$ext            = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$blockedExt     = ['php', 'php3', 'php4', 'php5', 'phtml', 'pl', 'py', 'jsp', 'asp', 'sh', 'exe'];
if (in_array($ext, $blockedExt, true)) {
    http_response_code(400);
    die(json_encode(['error' => 'File type not allowed']));
}

$filename  = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
$uploadDir = __DIR__ . '/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    http_response_code(500);
    die(json_encode(['error' => 'Failed to save file']));
}

$stmt = $pdo->prepare(
    "INSERT INTO todo_attachments (todo_id, filename, original_name, mime_type, file_size)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->execute([$todoId, $filename, $file['name'], $mime, (int) $file['size']]);

header('Content-Type: application/json');
echo json_encode([
    'id'            => (int) $pdo->lastInsertId(),
    'filename'      => $filename,
    'original_name' => $file['name'],
    'mime_type'     => $mime,
    'url'           => 'uploads/' . $filename,
]);
