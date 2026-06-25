# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A multi-user Kanban board application built with PHP, ES module JavaScript, and MySQL. Runs on WampServer at `http://localhost/Kanban-Board/`. Uses Vite for frontend asset bundling.

## Development Commands

- `npm run dev` — start Vite dev server (serves JS/CSS on port 5173 with HMR)
- `npm run build` — production build to `dist/` with hashed filenames + manifest

In dev mode, PHP pages load assets from the Vite dev server. In production (when `dist/.vite/manifest.json` exists), they load from `dist/`. This is handled by `includes/vite.php`.

## Development Environment

- **Stack**: PHP 8.x + MySQL (via WampServer/WAMP64), ES modules + Vite, plain CSS
- **Database**: MySQL `kanbanboard` — schema auto-created on first `require 'includes/db.php'` (idempotent CREATE TABLE IF NOT EXISTS statements)
- **First run**: Visit `setup.php` — creates the admin user and a default workspace. If users already exist, it redirects to `login.php`.
- **File uploads**: Stored in `uploads/` directory (gitignored), referenced in `todo_attachments` table
- **Rich text**: Quill.js editor (npm package, imported in `src/js/dialog.js`) — stored as raw HTML in `todo_items.content`

## Architecture

### File Organization

- **Root**: PHP page files (index.php, login.php, setup.php, workspaces.php, admin.php, logout.php)
- **`api/`**: JSON API endpoints (add_item.php, update_item.php, delete_item.php, etc.)
- **`includes/`**: Shared PHP (db.php, auth.php, vite.php)
- **`src/js/`**: ES module source files, compiled by Vite
- **`src/css/`**: CSS source files, bundled by Vite
- **`dist/`**: Vite build output (gitignored)

### Authentication & Authorization

Session-based auth via `includes/auth.php`. Two sets of guard functions:
- **Page guards** (`requireLogin`, `requireWorkspace`, `requireAdmin`): redirect to login/workspaces on failure
- **API guards** (`apiLogin`, `apiWorkspace`): return 401/403 JSON on failure

All API endpoints and pages include `includes/db.php` then `includes/auth.php`. The JS `apiFetch` wrapper (`src/js/api.js`) auto-redirects to `login.php` on 401.

### Multi-tenancy: Workspaces

Everything is scoped to a workspace stored in `$_SESSION['workspace_id']`. Columns (`kanban_columns`) and items (`todo_items`) both have a `workspace_id` FK. Users are linked to workspaces via `workspace_members` (role: owner/member).

### Frontend Modules

Two Vite entry points:
- **`src/js/board.js`** — board page: imports `global.css` + `board.css` + all JS modules
- **`src/js/pages.js`** — other pages: imports `global.css` + `pages.css` (CSS only)

JS modules:
- `api.js` — `apiFetch` wrapper with 401 redirect
- `drag.js` — HTML5 drag-and-drop handlers and `saveOrder`
- `dialog.js` — item dialog (Quill editor, file handling, save logic)
- `columns.js` — column dialog (add/delete columns)
- `dom.js` — item DOM creation (`createItemEl`, `attachItemListeners`, badges)

### API Endpoints (in `api/`)

| File | Purpose |
|------|---------|
| `add_item.php` | Create a kanban item in a column |
| `update_item.php` | Update item content and assignee |
| `delete_item.php` | Delete a kanban item |
| `get_item.php` | GET — fetch item with attachments for edit modal |
| `save_order.php` | Persist drag-and-drop column/position changes |
| `add_column.php` | Create a new kanban column |
| `delete_column.php` | Delete column and its items |
| `upload_file.php` | Multipart file upload for item attachments |
| `delete_attachment.php` | Delete a single attachment |
| `get_workspace_members.php` | GET — list members for the assignee dropdown |

### Database Tables

`users`, `workspaces`, `workspace_members`, `kanban_columns`, `todo_items`, `todo_attachments` — all defined in `includes/db.php` with inline migrations for schema evolution (try/catch ALTER TABLE blocks).
