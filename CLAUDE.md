# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

**Syncopa** is a German-language music association management application (Musikvereinsverwaltung). It manages members, instruments, sheet music, events, finances, uniforms, formations, and festival logistics. Version 2.3.9, PHP 8.0+, MySQL/MariaDB, Bootstrap 5.3, vanilla JS.

## Development environment

This runs on **Laragon** (Windows). No build step, no bundler, no test suite — PHP files are served directly. Changes take effect immediately on page reload.

There are no lint or test commands. The `.githooks/` directory contains a pre-commit hook.

## Architecture

### Request flow

Every page follows this pattern:
```
config.php → includes.php → page.php → includes/header.php → [content] → includes/footer.php
```

- `config.php` — environment constants (DB credentials, API keys, paths). **Not in git.** Use `config.example.php` as template.
- `config.app.php` — app-wide constants (`APP_VERSION`, `UPLOAD_DIR`, class autoloader, German date helpers). In git, updated per release.
- `includes.php` — requires all class files, runs all DB migrations as anonymous IIFEs.
- `includes/header.php` — renders the full HTML head + left sidebar navigation + opens `<main>`.
- `includes/footer.php` — closes `<main>`, loads JS libs (Bootstrap, jQuery, DataTables).

### Database layer

Singleton PDO wrapper: `Database::getInstance()`.

```php
$db = Database::getInstance();
$db->fetchAll($sql, $params);   // → array of assoc arrays
$db->fetchOne($sql, $params);   // → single assoc array or null
$db->execute($sql, $params);    // → for INSERT/UPDATE/DELETE
$db->lastInsertId();
$db->beginTransaction(); $db->commit(); $db->rollback();
```

Always use parameterised queries (`?` placeholders). Never interpolate user input into SQL.

### Classes (classes/)

All classes are plain PHP with the DAL pattern — `__construct()` calls `Database::getInstance()`, methods return arrays. No ORM. Key classes:

- `Session` — static methods only. `Session::requireLogin()`, `Session::checkPermission($modul, $aktion)`, `Session::isAdmin()`, `Session::getFormationId()`.
- `Formation` — provides `Formation::getFilterCondition(?int $formationId, string $tableAlias)` which returns `['condition' => '...', 'params' => [...]]` for formation-scoped queries. Used throughout to restrict data to the active formation.
- `Nummernkreis` — sequential number generator for members, invoices etc.
- `ICalendar` — builds `.ics` calendar exports.
- `Fest*` — 8 classes for the festival management subsystem (stations, staff, schedule, purchases, contracts, todos, invoices, copy).

### API endpoints (api/)

Standalone PHP files returning JSON. Pattern:
```php
Session::requireLogin();
Session::checkPermission('modul', 'schreiben');
// ... logic ...
echo json_encode(['success' => true, ...]);
```

Called via `fetch()` or jQuery AJAX from page scripts.

### Migration system

Migrations live as anonymous IIFEs in `includes.php` (no external migration tool). They run on every request and must be idempotent:

```php
(function() {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS ...");
    $db->execute("INSERT IGNORE INTO ...");
})();
```

When adding a new table or column for an existing installation, add it here. For a fresh install, also update `database.sql`.

## Multi-formation & permission system

### Formations

Non-admin users can belong to multiple formations. The active formation is stored in `Session::getFormationId()`. When querying formation-scoped data, use:

```php
$filter = Formation::getFilterCondition(Session::getFormationId(), 'table_alias');
if ($filter['condition']) {
    $where[]  = $filter['condition'];
    $params   = array_merge($params, $filter['params']);
}
```

`formation_id = NULL` in DB means visible to all formations.

### Roles & permissions

Multi-role pivot: `benutzer_rollen(benutzer_id, rolle_id)`. A user can have multiple roles. Permissions are checked against the `berechtigungen(rolle, modul, lesen, schreiben, loeschen)` table. `Session::checkPermission()` returns true if **any** of the user's roles grants the requested permission.

Special role: `extern` (id=12) — guest musicians with read-only access to ausrueckungen, noten, formationen only. They must be assigned to a formation or they are redirected to login.

## Key patterns to follow

**Formation-scoped queries** — whenever a page lists data that belongs to a formation (Ausrückungen, Noten, Finanzen etc.), always apply `Formation::getFilterCondition()`. Do not skip this for non-admin users.

**Migration guards** — all `ALTER TABLE` statements in `includes.php` must be wrapped in `try/catch` because `ADD COLUMN IF NOT EXISTS` isn't supported on older MySQL versions:
```php
try {
    $db->execute("ALTER TABLE `foo` ADD COLUMN IF NOT EXISTS bar INT NULL");
} catch (\Throwable $e) { /* already exists */ }
```

**Flash messages** — use `Session::setFlashMessage('success'|'danger', $text)` before redirecting. Read and display them via `Session::getFlashMessage()` at the top of the target page.

**Dual-direction member↔user link** — `benutzer.mitglied_id` AND `mitglieder.benutzer_id` both exist for legacy reasons. Queries that find formations for a user must check both:
```sql
WHERE mf.mitglied_id IN (
    SELECT id FROM mitglieder WHERE benutzer_id = ?
    UNION
    SELECT mitglied_id FROM benutzer WHERE id = ? AND mitglied_id IS NOT NULL
)
```

## PDF / Noten-Aufteilung

The notes split feature uses:
- **FPDI** (free, `vendor/fpdi/`) — cannot read PDF 1.5+ with compressed cross-references. A `qpdf` preprocessing step is attempted first (`qpdfPreprocess()`), then FPDI.
- **pdftotext** (poppler-utils, system binary) — used for text extraction / instrument recognition.
- Instrument recognition patterns are stored in DB table `noten_instrumente_pattern` (managed via `noten_instrumente.php`), not hardcoded. `matcheStimme()` loads them with a static cache per request.

## File naming conventions

- Pages: `{modul}.php`, `{modul}_bearbeiten.php`, `{modul}_detail.php`, `{modul}_loeschen.php`
- API: `api/{modul}_{aktion}.php`
- Classes: `classes/{Klassenname}.php` (PascalCase)
