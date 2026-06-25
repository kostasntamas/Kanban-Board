<?php
$host   = 'localhost';
$dbname = 'kanbanboard';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => $e->getMessage()]));
}

// ── Schema (idempotent) ────────────────────────────────────────────────────────

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS workspaces (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    owner_id   INT          NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS workspace_members (
    workspace_id INT NOT NULL,
    user_id      INT NOT NULL,
    role         ENUM('owner','member') NOT NULL DEFAULT 'member',
    PRIMARY KEY  (workspace_id, user_id),
    FOREIGN KEY  (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY  (user_id)      REFERENCES users(id)      ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS todo_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    content      TEXT         NOT NULL DEFAULT '',
    col          VARCHAR(50)  NOT NULL DEFAULT 'todo',
    position     INT          NOT NULL DEFAULT 0,
    workspace_id INT          NULL,
    assigned_to  INT          NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS kanban_columns (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    col_key      VARCHAR(50)  NOT NULL UNIQUE,
    label        VARCHAR(100) NOT NULL,
    position     INT          NOT NULL DEFAULT 0,
    workspace_id INT          NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS todo_attachments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    todo_id       INT          NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL DEFAULT '',
    file_size     INT          NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (todo_id) REFERENCES todo_items(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS todo_item_assignees (
    todo_id  INT NOT NULL,
    user_id  INT NOT NULL,
    PRIMARY KEY (todo_id, user_id),
    FOREIGN KEY (todo_id) REFERENCES todo_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)      ON DELETE CASCADE
)");

// ── Migrations for existing installs ──────────────────────────────────────────
try { $pdo->exec("ALTER TABLE kanban_columns ADD COLUMN workspace_id INT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE todo_items ADD COLUMN workspace_id INT NULL");     } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE todo_items ADD COLUMN assigned_to  INT NULL");     } catch (PDOException $e) {}

// Fix todo_items.id missing AUTO_INCREMENT (table created by old schema)
try {
    $pdo->exec("DELETE FROM todo_items WHERE id = 0");
    $pdo->exec("ALTER TABLE todo_items MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
} catch (PDOException $e) {}

// Migrate legacy single assigned_to → junction table
try {
    $pdo->exec("INSERT IGNORE INTO todo_item_assignees (todo_id, user_id)
                SELECT id, assigned_to FROM todo_items WHERE assigned_to IS NOT NULL");
} catch (PDOException $e) {}
