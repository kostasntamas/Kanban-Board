<?php
require 'db.php'; // connects to 'kanbanboard' database

$pdo->exec("CREATE TABLE IF NOT EXISTS todo_items (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    content  TEXT        NOT NULL,
    col      VARCHAR(50) NOT NULL DEFAULT 'todo',
    position INT         NOT NULL DEFAULT 0
)");

$count = (int) $pdo->query("SELECT COUNT(*) FROM todo_items")->fetchColumn();
if ($count > 0) {
    die("Table already has data — skipping seed. Delete rows first if you want to re-seed.");
}

$items = [
    [1,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam. Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 0],
    [2,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 1],
    [3,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 2],
    [4,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam. Lorem ipsum doab perferendis dolorum quibusdam.", "todo", 3],
    [5,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 4],
    [6,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 5],
    [7,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 6],
    [8,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 7],
    [9,  "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "todo", 8],
    [10, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "in_progress", 0],
    [11, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "in_progress", 1],
    [12, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "done", 0],
    [13, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "done", 1],
    [14, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "backlog", 0],
    [15, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "backlog", 1],
    [16, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "backlog", 2],
    [17, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "backlog", 3],
    [18, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "backlog", 4],
    [19, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "backlog", 5],
    [20, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "others", 0],
    [21, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "others", 1],
    [22, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "others", 2],
    [23, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "others", 3],
    [24, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "others", 4],
    [25, "Lorem ipsum dolor sit amet consectetur adipisicing elit. Fuga libero at, ab perferendis dolorum quibusdam.", "others", 5],
];

$stmt = $pdo->prepare("INSERT INTO todo_items (id, content, col, position) VALUES (?, ?, ?, ?)");
foreach ($items as $row) {
    $stmt->execute($row);
}

echo "Setup complete! 25 items inserted. You can delete this file now.";
