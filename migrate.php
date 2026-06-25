<?php
require 'includes/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS todo_attachments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    todo_id       INT NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL DEFAULT '',
    file_size     INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (todo_id) REFERENCES todo_items(id) ON DELETE CASCADE
)");

$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
    echo "Created uploads/ directory.<br>";
}

echo "Migration complete: todo_attachments table ready.";
