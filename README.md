# Wedding RSVP

Wedding RSVP SaaS built with the official Laravel Vue starter kit. Current functionality includes customer workspaces, wedding detail management, and a private Modern Classic invitation editor with saved drafts. Public publishing, guest management, and RSVP features remain deferred; owner milestone acceptance is tracked in `docs/checklist.md`.

## Stack

- Laravel 12.70.0, PHP 8.2.33, Composer 2.10.2
- Vue 3.5.44, Inertia.js 2.0.3 (Vue adapter), Inertia Laravel 2.0.28
- TypeScript 5.7.3, Tailwind CSS 3.4.19, Vite 6.4.4
- Node.js 24.15.0, npm 11.12.1
- MySQL (configure local credentials before database operations)
- Starter kit: `laravel/vue-starter-kit` v1.0.2

The `composer.lock` and `package-lock.json` files record the resolved dependency versions. The `.env` file is local and ignored by Git.

## Prerequisites

PHP 8.2+ with `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `tokenizer`, `xml`, and `zip`; Composer 2; Node.js and npm; and a running local MySQL server.

## Local setup

1. Install PHP and Node dependencies:

    ```powershell
    composer install
    npm.cmd ci
    ```

2. Copy `.env.example` to `.env` if needed, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to your local MySQL details. Never commit `.env`.
3. In MySQL, create the dedicated database if it does not already exist:

    ```sql
    CREATE DATABASE wedding_rsvp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ```

    Use an account permitted to create this database. The project does not create databases or assume a blank root password.

4. Generate an application key if this is a fresh environment, then run default migrations:

    ```powershell
    php artisan key:generate
    php artisan migrate
    ```

## Run locally (Windows PowerShell)

Open two terminals in the project directory:

```powershell
php artisan serve
```

```powershell
npm.cmd run dev
```

Open `http://127.0.0.1:8000`. `npm.cmd` avoids PowerShell's script-execution policy blocking the `npm.ps1` shim.

The project's `App\Console\Commands\Serve` extends Laravel's standard development server and passes `upload_max_filesize=5M`, `post_max_size=8M`, and `memory_limit=256M` directly to the receiving PHP child process, loading EXIF if the parent runtime does not already have it. This applies to normal `php artisan serve` and the server in `composer run dev`, survives subsequent starts, and does not edit global `php.ini`. Restart any already-running development server after updating this command. GD/Fileinfo and the EXIF extension binary must be installed; production web servers still need their own runtime configuration.

## Build, check, and test

```powershell
npm.cmd run build
npm.cmd run typecheck
node --test tests/Frontend/ModernClassicInvitation.test.mjs
php artisan test
vendor\bin\pint --test
```

For an auto-format pass, run `vendor\bin\pint` and `npm.cmd run format`.

Laravel tests must use the isolated SQLite configuration in `phpunit.xml`, never the development MySQL database. In Codex's current Windows environment, load SQLite extensions through the temporary PHP scan directory and set process-only overrides for `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array`, and `SESSION_PATH=/`; point `APP_CONFIG_CACHE` and `APP_ROUTES_CACHE` at unused temporary paths to bypass local caches. See `docs/handoff.md` for verification context. The renderer tests use existing Vue/TypeScript packages and Node's built-in test runner; no additional test dependency is required.

### Optional MySQL concurrency tests

These cases require an existing, dedicated disposable database named `wedding_rsvp_cms_test` (optionally followed by `_` and a lowercase alphanumeric/underscore suffix), with the project migrations already applied and a test user permitted to inspect `information_schema.INNODB_TRX`. They use the configured MySQL host/credentials with the explicitly selected test database, independent request processes, and real InnoDB lock waits.

After setting the isolated testing process overrides above:

```powershell
$env:CMS_MYSQL_TEST_DATABASE='wedding_rsvp_cms_test'
php artisan test --compact --group=mysql-concurrency
```

The tests refuse `wedding_rsvp`, never create/drop/reset databases or provision schema, and clean up only their own fixture records. Without a configured/reachable prepared test database they are skipped, not verified. Creating or resetting a disposable database requires explicit owner approval separately from running the tests.

## Wedding hero photos (Milestone 2C.1)

Before using photo uploads in a new environment, apply the additive `2026_10_16_050354_add_hero_photo_to_website_drafts_table` migration with `php artisan migrate`. It is confirmed already applied to development MySQL; no migration was needed for the upload runtime fix. Do not reset existing databases. If configuration is cached, clear/rebuild it deliberately during deployment so the new private disk is available.

The actual **web PHP runtime** needs GD (JPEG/PNG/WebP), Fileinfo, EXIF, `memory_limit=256M` or greater, `upload_max_filesize=5M` or greater, and POST/proxy body limits above multipart overhead (at least 8M for this form). Have the operator configure/restart the relevant runtime; do not silently alter global PHP settings. A missing processing prerequisite gives an actionable upload error. HEIC is unsupported. PHP/proxy rejections before Laravel may produce an oversized-request response.

Photos use the `wedding_photos` disk at `storage/app/private/wedding-photos`, with automatic serving disabled. **Do not create a public symlink or use `Storage::url()` for these files.** Keep storage writable, private, persistent, and backed up. Draft URLs require owner authentication; only the active published snapshot's matching photo is served anonymously through its wedding's public route. Private S3 adoption later needs a separately approved Flysystem adapter and configuration; no dependency was added now.

Replacement/removal deletes superseded objects after commit only if neither a draft nor any historical revision references them. Crash/deletion failures can leave private orphans. Inspect `php artisan wedding-photos:prune` (dry run), then explicitly execute `php artisan wedding-photos:prune --delete` to remove generated, unreferenced objects older than 24 hours. No automatic schedule or historical-snapshot deletion policy is installed.

Photo tests need the same PHP extensions/memory as processing. Codex used a **temporary process-only** PHP scan directory containing SQLite/EXIF extension loading and 256M memory, then ran `php artisan test --compact` with the isolated testing overrides above. No global PHP settings changed. MySQL photo concurrency cases additionally require the additive migration and share only a uniquely named temporary photo directory; they still refuse development MySQL.

## Publishing saved invitations (Milestone 2C.2)

The additive `2026_10_16_075658_create_website_revisions_table` migration is applied to development MySQL. In another environment, run `php artisan migrate --no-interaction`, refresh its route cache deliberately, and run `npm.cmd run build` for the separate public page/CSS entries. No reset, new package or SSR service is needed. A stale pre-publication route cache must be cleared with `php artisan route:clear --no-interaction` (and rebuilt if deployment uses route caching).

Set a wedding date, customize and save the draft, preview, then use **Publish invitation** on the overview and confirm. Copy the `/w/{slug}` URL and open it signed out. Editing wedding facts or saving another draft leaves that snapshot unchanged until **Republish changes**. **Unpublish** returns page/photo 404 while retaining drafts, history and media references. Required names/date/saved supported CMS/media are checked again inside the publication transaction.

Laravel delivers the public document, sharing metadata and styled no-JavaScript invitation; the existing Modern Classic Vue component enhances it. Public pages do not use the portal Inertia root or its authentication props. The default asks crawlers not to index/follow/archive, and page/photo responses are private/no-store. Third-party caches and previously downloaded copies cannot be recalled. Localhost URLs work locally without login; sharing over the Internet and real messaging previews require separate deployment to a reachable host.

The opt-in browser tests use an isolated SQLite server, storage and sessions with `QA_ISOLATED_SQLITE=1`, `QA_BASE_URL`, `QA_PLAYWRIGHT_MODULE` and an optional `QA_BROWSER_EXECUTABLE`; they reuse existing browser tooling and do not install dependencies. With those configured, run `node --test tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/ManualQaRegression.test.mjs tests/Frontend/Publication.browser.test.mjs`. Never point fixture-writing browser tests at development MySQL. Six MySQL concurrency cases (four draft/photo, two publication) still require the separately approved disposable target above and its latest migrations.

## Laravel Boost for Codex

Laravel Boost is a Composer development dependency. The project-local MCP server is configured in `.codex/config.toml` and starts with `php artisan boost:mcp`. Open and trust this project in Codex, then restart or reopen the project so Codex loads its MCP configuration.

The Codex MCP process uses an in-memory cache and bypasses the local cached config file. This keeps Boost's schema metadata cache out of the MySQL database and does not change the web application's runtime configuration. Boost provides application information, database schema and connection inspection, Laravel documentation search, and supporting browser/log tools. Documentation search needs outbound access to Laravel's hosted docs service.

## Structure

- `app/`, `routes/`, `database/`, `tests/`: Laravel application, customer accounts, wedding management, routes, migrations, and tests
- `resources/js/pages/`: Inertia Vue pages
- `resources/js/components/`, `layouts/`, `composables/`, `types/`: shared frontend code
- `resources/css/`, `vite.config.ts`: Tailwind and Vite setup

## Common setup issues

- **`npm` is blocked in PowerShell:** use `npm.cmd` / `npx.cmd`; this does not change machine policy.
- **MySQL access denied or connection refused:** ensure the MySQL service is running and `.env` contains the correct host, port, username, and password.
- **Unknown database `wedding_rsvp`:** create it using the SQL above, then run `php artisan migrate`.
- **Missing PHP extension:** enable it in the active PHP configuration (`C:\php\php.ini` in the inspected environment) and restart terminal commands.
- **Tests report `could not find driver` for SQLite:** enable `pdo_sqlite` and `sqlite3` in the active PHP configuration, then rerun `php artisan test`.
- **Configuration changes appear ignored:** run `php artisan config:clear`.
