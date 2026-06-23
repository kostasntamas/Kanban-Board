<?php
require 'db.php';

$rows = $pdo->query("SELECT * FROM todo_items ORDER BY col, position")->fetchAll(PDO::FETCH_ASSOC);

$columns = [
    'todo'        => ['label' => 'To do',      'items' => []],
    'in_progress' => ['label' => 'In Progress', 'items' => []],
    'done'        => ['label' => 'Done',         'items' => []],
    'backlog'     => ['label' => 'Backlog',      'items' => []],
    'others'      => ['label' => 'Others',       'items' => []],
];

foreach ($rows as $row) {
    if (isset($columns[$row['col']])) {
        $columns[$row['col']]['items'][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Kanban Board</title>
</head>

<body>
    <main id="drag-lists">
        <?php foreach ($columns as $colKey => $col): ?>
            <div class="wrapper">
                <h2><?= htmlspecialchars($col['label']) ?></h2>
                <div class="add-form">
                    <input type="text" name="addnew" placeholder="New item..." data-col="<?= $colKey ?>">
                    <button class="add-btn" data-col="<?= $colKey ?>">Add</button>
                </div>
                <ul class="drag-list" data-column="<?= $colKey ?>">
                    <?php foreach ($col['items'] as $item): ?>
                        <li class="drag-item" draggable="true" data-id="<?= (int) $item['id'] ?>">
                            <span><?= htmlspecialchars($item['content']) ?></span>
                            <button class="delete-btn" title="delete">x</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </main>
</body>

<script src="script.js"></script>

</html>