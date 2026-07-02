# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## About This Project

Lukaisu Server is the **headless backend** for Lukaisu, a language-learning-by-reading
app. This is a third-party community-maintained fork of the original SourceForge
LWT project. It exposes `/api/v1` (and, still server-rendered, the two OAuth
account-link-confirm pages) — nothing else. The reading/learning UI is a
connected client: the [`lukaisu`](https://github.com/lukaisu/lukaisu) mobile
app, or any other `/api/v1` consumer.

**Tech Stack (today):**

- Backend: PHP 8.2+ with MySQLi
- Database: MySQL/MariaDB with InnoDB engine
- Build Tools: Composer (PHP); a small NPM/Vite build for the server's own CSS
  + service worker (`server-src/`) — see [Asset Building](#asset-building)

**Direction (target architecture).** Lukaisu is a local-first app with an
*optional* server, and the PHP backend is **frozen** and being hollowed out
further: what stays server-side becomes an optional **Python (FastAPI)** edge
for sync + NLP. See `BRIEFING.md` and `docs-src/server/local-first.md`.

The **frontend migration is done** (Phase M of
[frontend-relocation.md](docs-src/server/frontend-relocation.md)): all
reading/learning UI (Alpine → Svelte 5, IndexedDB/Dexie client data, the whole
`src/frontend/` tree) moved to the `lukaisu` app repo. This repo no longer owns
or builds any of it.

So: **don't add new PHP rendering or client UI here** — build the Python edge
(this repo) or a Svelte island (the `lukaisu` repo) instead.

## Development Setup

### Initial Setup

```bash
git clone https://github.com/lukaisu/lukaisu-server
cd lukaisu-server
composer install --dev
npm install
```

### Database Configuration

Copy `.env.example` to `.env` and update the database credentials:

```bash
cp .env.example .env
# Edit .env with your database credentials
```

The `.env` file contains:

- `DB_HOST` - Database server (default: localhost)
- `DB_USER` - Database username (default: root)
- `DB_PASSWORD` - Database password
- `DB_NAME` - Database name (default: lukaisu)
- `DB_SOCKET` - Optional database socket
- `MULTI_USER_ENABLED` - Enable user_id-based data isolation (default: false)

## Common Commands

### Running the Application

```bash
# Docker (recommended for quick setup)
docker compose up                # Start app at http://localhost:8010/

# PHP built-in server (for development)
php -S localhost:8000            # Start at http://localhost:8000/
```

### Testing

```bash
# PHP tests
composer test                    # Run PHPUnit tests with coverage
composer test:no-coverage        # Run PHPUnit tests without coverage (faster)

# Run a single test file
./vendor/bin/phpunit tests/backend/Services/TextServiceTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testMethodName

# Integration tests (requires test database)
composer test:setup-db           # Create test database and apply migrations
composer test:db-status          # Show test database status
composer test:reset-db           # Drop and recreate test database
composer test:integration        # Run integration tests (sets up DB automatically)

# Live-server API smoke test (Vitest): tests/api.test.ts. Needs a running
# instance and is excluded from `npm test` (see vitest.config.ts) — run it by
# temporarily removing it from that exclude list.

# E2E tests (requires server on localhost:8000)
npm run e2e                      # Run Cypress E2E tests
npm run cy:open                  # Interactive Cypress test runner
```

**Integration Tests:** Some tests require a MySQL database with FK constraints. Run `composer test:setup-db` once to create the test database (`test_<dbname>` from your `.env`). The integration test suite includes FK cascade tests, tag service tests, and other database-dependent tests.

**Unit Tests and Database Guards:** CI runs PHPUnit without a MySQL service, so any unit test that reaches a database call (directly or via static methods like `Settings::getWithDefault()`, `TagsFacade::*`, `QueryBuilder::table()`) will fail on CI. When writing unit tests, add a skip guard to any test method that may hit the database:

```php
if (!defined('LUKAISU_TEST_DB_AVAILABLE') || !LUKAISU_TEST_DB_AVAILABLE) {
    $this->markTestSkipped('Database connection required');
}
```

Prefer mocking or restructuring code to avoid DB calls in unit tests. Use the skip guard only when static/global DB calls cannot be avoided (e.g., deeply nested static method calls).

**When to run E2E tests:** Run `npm run e2e` after making changes to:

- Routes or URL handling (`src/backend/Router/`)
- Controllers (`src/backend/Controllers/`)
- Form handling or navigation
- REST API endpoints
- Fix the test failures, even if they are unrelated to the current changes.

### Code Quality

```bash
./vendor/bin/psalm                                   # Static analysis (default level)
composer psalm:level1                                # Strictest static analysis
npm run lint                                         # ESLint for TypeScript/JS
npm run lint:fix                                     # Auto-fix lint issues
npm run typecheck                                    # TypeScript type checking
./vendor/bin/phpcs [file]                            # PHP code style check
./vendor/bin/phpcbf [file]                           # PHP code style auto-fix
```

**After every PHP file change**, always run these checks and fix any issues before committing:

1. `./vendor/bin/psalm --threads=1` — Psalm static analysis must pass with 0 errors (multi-thread crashes due to amphp bug; always use `--threads=1`)
2. `./vendor/bin/phpcs --standard=PSR12 [changed files]` — PHP CodeSniffer must have 0 errors and 0 warnings
3. `composer test:no-coverage` — PHPUnit tests must all pass (run after any important PHP change)

### Asset Building

```bash
npm run dev                      # Start Vite dev server with HMR
npm run build                    # Build the server's CSS + service worker
composer build                   # Alias for npm run build
```

This builds only what the server itself ships: a CSS-only bundle for the two
OAuth account-link pages (`server-src/styles.ts`) and the service worker
(`server-src/sw.ts`). The reading/learning frontend moved to the `lukaisu` app
repo (Phase M) — see
[frontend-relocation.md](docs-src/server/frontend-relocation.md) and that
repo's own `npm run build` / `npm run build:themes`.

### Documentation Generation

```bash
composer doc                     # Regenerate all documentation (VitePress + JSDoc + phpDoc)
composer clean-doc               # Clear all generated documentation
```

## Architecture Overview

### Request Flow (v3 Front Controller)

All requests route through `index.php` → `Router` → `Controller` → `Service` → `View`:

1. `index.php` bootstraps the application and invokes the Router
2. `src/backend/Router/routes.php` maps URLs to controller methods
3. Controllers in `src/backend/Controllers/` handle request/response
4. Services in `src/backend/Services/` contain business logic
5. Views in `src/backend/Views/` render HTML output

### Dual Codebase: Legacy vs Modules

The codebase has two parallel structures. New feature work should target `src/Modules/` when the relevant module exists; `src/backend/` is the legacy layer being incrementally migrated.

- **`src/Modules/`** — New modular architecture with bounded contexts, DI containers, and repository pattern
- **`src/backend/`** — Legacy MVC layer (Controllers/Services/Views) still handling most routes
- **`src/Shared/`** — Cross-cutting infrastructure used by both (Database, Http, Container, UI helpers)

Both share the `Lukaisu\` PSR-4 root, with explicit mappings: `Lukaisu\` → `src/backend/`, `Lukaisu\Shared\` → `src/Shared/`, `Lukaisu\Modules\` → `src/Modules/`.

### Key Directories

```text
src/Shared/                          # Cross-cutting infrastructure
├── Infrastructure/
│   ├── Database/                    # Connection, DB, QueryBuilder, PreparedStatement, etc.
│   ├── Http/                        # InputValidator, SecurityHeaders, UrlUtilities
│   ├── Container/                   # DI Container, ServiceProviders
│   └── Globals.php                  # Type-safe global state access
├── Domain/
│   └── ValueObjects/                # UserId (cross-module identity)
└── UI/
    ├── Helpers/                     # FormHelper, IconHelper, PageLayoutHelper, etc.
    └── Assets/                      # ViteHelper

src/Modules/                         # Feature modules (bounded contexts)
├── Admin/                           # Admin/settings module
├── Dictionary/                      # Dictionary lookup/translation module
├── Feed/                            # RSS feed module
├── Home/                            # Home page/dashboard module
├── Language/                        # Language configuration module
├── Review/                          # Spaced repetition testing module
├── Tags/                            # Tagging module
├── Text/                            # Text reading/import module
├── User/                            # User authentication module
└── Vocabulary/                      # Terms/words module

# Each module follows this structure:
├── Application/                     # Use cases and application services
├── Domain/                          # Entities, value objects, repository interfaces
├── Http/                            # Controllers, request handling
├── Infrastructure/                  # Repository implementations, external integrations
├── Views/                           # Module-specific view templates
└── [Module]ServiceProvider.php      # DI container registration

src/backend/                         # Legacy MVC (being migrated to src/Modules/)
├── Controllers/                     # MVC Controllers
├── Services/                        # Business logic layer
├── Views/                           # PHP templates organized by feature
├── Router/                          # URL routing (Router.php, routes.php)
├── Api/V1/                          # REST API handlers
│   ├── Handlers/                    # Endpoint handlers by resource
│   ├── ApiV1.php                    # Main API router
│   └── Endpoints.php                # Endpoint registry
└── View/Helper/                     # StatusHelper (business logic dependency)

server-src/                          # The server's own tiny Vite build
├── styles.ts                        # CSS-only entry (Bulma + assets/css/styles.css)
└── sw.ts                            # Service worker (offline cache for the bundled client)
```

The reading/learning frontend (TypeScript, Svelte, CSS themes) lives in the
[`lukaisu`](https://github.com/lukaisu/lukaisu) repo's `webapp/`, not here.

### Database Architecture

Key tables (InnoDB engine):

- `languages` - Language configurations (parsing rules, dictionaries)
- `texts` / `archivedtexts` - User texts for reading
- `words` - User vocabulary with status tracking
- `sentences` - Parsed sentences from texts
- `textitems2` - Word occurrences linking words to sentences
- `settings` - Application settings (key-value pairs)

**Word Status Values:** 1-5 (learning stages), 98 (ignored), 99 (well-known)

### Global State Access

Use `Lukaisu\Shared\Infrastructure\Globals` class instead of PHP globals:

```php
use Lukaisu\Shared\Infrastructure\Globals;

// Database operations
$db = Globals::getDbConnection();
$tableName = Globals::table('words');  // Returns table name

// Query builder
$words = Globals::query('words')->where('WoLgID', '=', 1)->get();

// User context (for multi-user mode)
$userId = Globals::getCurrentUserId();
$userId = Globals::requireUserId();  // Throws if not authenticated
```

### REST API

Base URL: `/api/v1` (also supports legacy `/api.php/v1`)

Key endpoint groups (see `src/backend/Api/V1/Endpoints.php` for full list):

- `languages` - Language CRUD and definitions
- `texts` - Text management and statistics
- `terms` - Vocabulary CRUD, status changes, bulk operations
- `feeds` - RSS feed management
- `review` - Spaced repetition test interface
- `settings` - Application configuration
- `tags` - Term and text tagging

## Working with the Codebase

### Creating New Features

1. **Add route** in `src/backend/Router/routes.php`
2. **Create/extend controller** in `src/backend/Controllers/`
3. **Extract business logic** to `src/backend/Services/`
4. **Create view templates** in `src/backend/Views/[Feature]/`

### Modifying PHP Code

- Controllers extend `BaseController` which provides helper methods for input validation, rendering, and database access
- Use prepared statements for database queries: `Connection::preparedFetchAll($sql, [$param1, $param2])`
- For IN clauses with arrays of IDs: `Connection::buildPreparedInClause($ids, $bindings)` returns `(?,?,?)` and appends values to `$bindings`; returns `(NULL)` for empty arrays
- Use `Globals::table('tablename')` for table names
- Use `getSettingWithDefault()` for application settings
- Use `InputValidator` for request parameter validation (accessed via `$this->param()`, `$this->paramInt()` in controllers)
- Use `forTablePrepared()` instead of legacy `forTable()` for parameterized queries in module code

**Key Namespaces:**
- Database: `Lukaisu\Shared\Infrastructure\Database\{Connection, DB, QueryBuilder}`
- HTTP: `Lukaisu\Shared\Infrastructure\Http\{InputValidator, SecurityHeaders}`
- Container: `Lukaisu\Shared\Infrastructure\Container\Container`
- UI Helpers: `Lukaisu\Shared\UI\Helpers\{FormHelper, PageLayoutHelper, IconHelper}`

### Modifying TypeScript

`server-src/` is the entire TS surface here — `styles.ts` (CSS imports only)
and `sw.ts` (the service worker). Both are self-contained; there's no
component/module system, Alpine, or Svelte to reach for. For anything beyond
these two files (reader UI, vocabulary, forms, themes, …), that's the
[`lukaisu`](https://github.com/lukaisu/lukaisu) repo's `webapp/`, a separate
checkout with its own `npm run dev` / `typecheck` / `build`.

## Important Conventions

- **Character Encoding:** UTF-8 throughout
- **Namespaces:** PSR-4 autoloading with `Lukaisu\` prefix
- **ID Columns:** `LgID` (language), `TxID`/`AtID` (text/archived), `WoID` (word)
- **Database Queries:** Always use prepared statements (`Connection::preparedFetchAll()`, `preparedExecute()`, `preparedFetchValue()`). Never interpolate variables into SQL strings. Use `buildPreparedInClause()` for IN clauses.
- **Test Namespaces:** `Lukaisu\Tests\` maps to `tests/backend/`

## Database Migrations

Migration files in `db/migrations/` with format `YYYYMMDD_HHMMSS_description.sql`. The `_migrations` table tracks applied migrations.

## Version Bumping

The version must be updated in these files before tagging a release:

| File | What to update |
| --- | --- |
| `src/Shared/Infrastructure/ApplicationInfo.php` | `VERSION` constant (semver, e.g. `'0.2.0'`) and `RELEASE_DATE` |
| `package.json` | `version` field, matching `ApplicationInfo` (e.g. `"0.2.0"`) |
| `CHANGELOG.md` | Move `[Unreleased]` items to a new version section with the release date |

`ApplicationInfo.php` is the **authoritative** version — it's what the app displays. Always update it.

## Contributing Workflow

Branches:

- `main` - Stable releases
- `develop` - Development branch

Before committing:

1. Run `composer test` and `./vendor/bin/psalm`
2. Run `npm run typecheck` and `npm run lint`
3. If you modified `server-src/`, run `npm run build`
