# Wedding RSVP

Wedding website service built with the official Laravel Vue starter kit. Modern Classic, guest management and personal RSVP are implemented; Enchanted CMS, immutable publication and hummingbird refinements run behind controlled local/testing gates. Current acceptance and release boundaries are tracked in `docs/checklist.md` and `docs/handoff.md`.

## Windows manual testing — one origin

Authenticated customer CMS integration (D-057): use the existing `dev.cmd` profile below, register at `/register`, create a wedding and open Invitation. New/unconfigured weddings open the full Enchanted editor; saved Modern Classic/v1 remains unchanged. Save Draft before private preview or Publish; edits stay private until Republish. The supplied owner route is `/accounts/3/weddings/3/invitation/edit` and requires its own account membership. [Complete walkthrough, verification and separately approved normal-MySQL setup](docs/tasks/cms_customer_workflow_integration.md). Do not copy synthetic data into MySQL or automatically migrate/seed it.

From this repository, after restarting Windows:

```powershell
.\dev.cmd
```

Keep the terminal open; Ctrl+C stops the preview and Vite started by the launcher. No PowerShell execution-policy change, separate `npm run dev`, migration, seeding or database recreation is needed. The saved private profile resumes the existing synthetic SQLite database/uploads and serves **http://127.0.0.1:8000**, with live current-source Vite internally on 5173. If 8000 is occupied, stop that existing server explicitly; no fallback port is used.

- Login: http://127.0.0.1:8000/login (synthetic owner `editor-preview@example.test`; unchanged password in the private credentials file printed by startup).
- CMS: http://127.0.0.1:8000/accounts/1/weddings/1/cms-preview.
- Published website: http://127.0.0.1:8000/w/synthetic-editor-preview.

**Current MySQL status (2026-10-25):** An unsafe Laravel test invocation accidentally applied the 18 existing migrations to `wedding_rsvp` after the earlier DEV-ENV read showed only an empty ledger. Read-only verification found 20 application tables and zero `users`, `accounts`, `weddings`, or `guests` rows. No seed/import or rollback followed. This was not owner-approved initialization; pause further MySQL writes pending owner review. See `docs/tasks/dev_env_single_origin.md#test-runner-incident-2026-10-25` and RF-032. The retained preview remains separate SQLite and does not change `.env` or MySQL. Do not run tests or fresh-install instructions against this workspace's MySQL connection.

[DEV-ENV diagnosis, first-time profile selection, data/key limitations and exact verification](docs/tasks/dev_env_single_origin.md). Missing retained temporary files fail closed; ordinary restart never manufactures new data. Use a private browser context when switching database profiles. All production/security/5D/DEL-07b acceptance gates remain unchanged.

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

4. Generate an application key if this is a fresh environment. Run migrations only against a newly created disposable database after confirming the target and receiving the required owner approval. The current `wedding_rsvp` schema is under owner review; do not run migrations against it:

    ```powershell
    php artisan key:generate
    php artisan migrate
    ```

## Run locally (Windows PowerShell)

Open two terminals in the project directory:

```powershell
$env:SESSION_PATH = '/'
php artisan serve
```

```powershell
npm.cmd run dev
```

Open `http://127.0.0.1:8000`. `npm.cmd` avoids PowerShell's script-execution policy blocking the `npm.ps1` shim.

Set `SESSION_PATH` in the same PowerShell terminal before starting the server (also before `composer run dev`). An inherited environment variable overrides `.env`; a local process was found supplying `C:/Program Files/Git/` despite `.env` correctly containing `SESSION_PATH=/`, causing cookie creation and dashboard requests to fail. Git Bash/MSYS path conversion can produce this kind of filesystem path. The PowerShell assignment above affects only that terminal and its child processes, preserves the intended root URL path, and makes no machine-wide changes. Restart an affected server after correcting its environment; editing `.env` alone cannot override the inherited value.

The project's `App\Console\Commands\Serve` extends Laravel's standard development server and passes `upload_max_filesize=5M`, `post_max_size=8M`, and `memory_limit=256M` directly to the receiving PHP child process, loading EXIF if the parent runtime does not already have it. This applies to normal `php artisan serve` and the server in `composer run dev`, survives subsequent starts, and does not edit global `php.ini`. Restart any already-running development server after updating this command. GD/Fileinfo and the EXIF extension binary must be installed; production web servers still need their own runtime configuration.

## Build, check, and test

### GitHub Actions (DEL-10.2)

`tests.yml` and `lint.yml` run for pull requests targeting `master` (including drafts), and pushes to `master` or `integration/**`. Both use PHP 8.2, Node 22, `composer install`/`npm ci` from the committed lockfiles, read-only repository permissions and checkout without persisted credentials. They do not need production secrets, run a MySQL service, deploy or push formatter changes.

The test job sets the process-only testing/in-memory SQLite, blank database URLs, array cache/session and temporary config/routes/storage paths before any Laravel boot. It generates an ephemeral CI key, verifies the existing bootstrap/resolved-connection guard plus actual in-memory PDO, and checks GD/EXIF support before tests. PHP prerequisites are installed only on the disposable runner. No `.env` copy, key-writing Artisan command, Ziggy regeneration or application migration is needed. The full PHP suite runs with disposable MySQL opt-ins disabled; their skips remain unverified engine coverage. Fifteen explicitly listed Node contract/renderer cases run alongside the build; browser/physical-device suites remain separate prerequisites, not silent CI passes.

The quality job uses `php vendor/bin/pint --test --format agent`, `npx --no-install eslint . --max-warnings=0`, TypeScript checks, `npm run format:check` and workflow-only Prettier `--check`; it asserts tracked files stayed unchanged. Do not substitute the local fix-style `npm run lint`/`npm run format`, mask exit codes or reformat unrelated files to make CI green. A failing application assertion, dependency or unrelated style issue must be reported for its own scoped approval. See the [canonical DEL-10 execution record and actual run evidence](docs/tasks/premium_delight_experience.md#del-101102--execution-record).

```powershell
npm.cmd run build
npm.cmd run typecheck
node --test tests/Frontend/ModernClassicInvitation.test.mjs
php artisan test
vendor\bin\pint --test
```

For an auto-format pass, run `vendor\bin\pint` and `npm.cmd run format`.

Laravel tests must use isolated SQLite and the complete process-only overrides in AGENTS.md, never `.env` MySQL. `phpunit.xml` forces SQLite; `tests/bootstrap.php` fails closed unless the test environment is isolated, applies a unique uncached Laravel config path, and `Tests\TestCase` checks the resolved connection before fixture-writing traits run. Do not bypass `phpunit.xml` or this bootstrap. Load extensions through the approved temporary PHP scan directory and point storage/config/route caches at isolated temporary paths. Renderer/browser tests use existing packages; do not add dependencies for verification.

For a focused test selection in PowerShell, set the process environment first (the temporary PHP scan directory below is the approved Windows extension-loading directory for this checkout):

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:DATABASE_URL=''
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:SESSION_PATH='/'
$env:APP_CONFIG_CACHE=Join-Path $env:TEMP ('rsvp-tests-'+[guid]::NewGuid().ToString('N')+'.php')
$env:APP_ROUTES_CACHE=Join-Path $env:TEMP ('rsvp-tests-routes-'+[guid]::NewGuid().ToString('N')+'.php')
$env:PHP_INI_SCAN_DIR=Join-Path $env:TEMP 'rsvp-cms-editor-LrKACO\ini'
php artisan test --compact tests/Feature/PersonalRsvpHttpTest.php
```

### Optional MySQL concurrency tests

These cases require an existing, dedicated disposable database named `wedding_rsvp_cms_test` (optionally followed by `_` and a lowercase alphanumeric/underscore suffix), with the project migrations already applied and a separate test user permitted to inspect `information_schema.INNODB_TRX`. They keep PHPUnit's default connection on SQLite and use dedicated `CMS_MYSQL_TEST_HOST`, `CMS_MYSQL_TEST_PORT`, `CMS_MYSQL_TEST_USERNAME`, and `CMS_MYSQL_TEST_PASSWORD` variables for the named test connections and worker processes. The helper rejects `wedding_rsvp`, non-loopback targets and reused application credentials. No schema is provisioned or reset by these tests.

After setting the isolated testing process overrides above:

```powershell
$env:CMS_MYSQL_TEST_ENABLED='true'
$env:CMS_MYSQL_TEST_HOST='127.0.0.1'
$env:CMS_MYSQL_TEST_PORT='3306'
$env:CMS_MYSQL_TEST_DATABASE='wedding_rsvp_cms_test'
$env:CMS_MYSQL_TEST_USERNAME='<dedicated-disposable-test-user>'
$env:CMS_MYSQL_TEST_PASSWORD='<dedicated-test-user-password>'
php artisan test --compact --group=mysql-concurrency
```

The tests refuse `wedding_rsvp`, never create/drop/reset databases or provision schema, and clean up only their own fixture records. Without all explicit `CMS_MYSQL_TEST_*` values and a reachable prepared test database they are skipped, not verified. The database must be provisioned separately with a dedicated non-application user; approval to run tests is not permission to provision or reset it.

## Wedding hero photos (Milestone 2C.1)

Before using photo uploads in a new environment, apply the additive `2026_10_16_050354_add_hero_photo_to_website_drafts_table` migration with `php artisan migrate`. It is confirmed already applied to development MySQL; no migration was needed for the upload runtime fix. Do not reset existing databases. If configuration is cached, clear/rebuild it deliberately during deployment so the new private disk is available.

The actual **web PHP runtime** needs GD (JPEG/PNG/WebP), Fileinfo, EXIF, `memory_limit=256M` or greater, `upload_max_filesize=5M` or greater, and POST/proxy body limits above multipart overhead (at least 8M for this form). Have the operator configure/restart the relevant runtime; do not silently alter global PHP settings. A missing processing prerequisite gives an actionable upload error. HEIC is unsupported. PHP/proxy rejections before Laravel may produce an oversized-request response.

Photos use the `wedding_photos` disk at `storage/app/private/wedding-photos`, with automatic serving disabled. **Do not create a public symlink or use `Storage::url()` for these files.** Keep storage writable, private, persistent, and backed up. Draft URLs require owner authentication; only the active published snapshot's matching photo is served anonymously through its wedding's public route. Private S3 adoption later needs a separately approved Flysystem adapter and configuration; no dependency was added now.

Replacement/removal deletes superseded objects after commit only if neither a draft nor any historical revision references them. Crash/deletion failures can leave private orphans. Inspect `php artisan wedding-photos:prune` (dry run), then explicitly execute `php artisan wedding-photos:prune --delete` to remove generated, unreferenced objects older than 24 hours. No automatic schedule or historical-snapshot deletion policy is installed.

Photo tests need the same PHP extensions/memory as processing. Codex used a **temporary process-only** PHP scan directory containing SQLite/EXIF extension loading and 256M memory, then ran `php artisan test --compact` with the isolated testing overrides above. No global PHP settings changed. MySQL photo concurrency cases additionally require the additive migration and share only a uniquely named temporary photo directory; they still refuse development MySQL.

## Publishing saved invitations (Milestone 3A, previously 2C.2)

The additive `2026_10_16_075658_create_website_revisions_table` migration is applied to development MySQL. In another environment, run `php artisan migrate --no-interaction`, refresh its route cache deliberately, and run `npm.cmd run build` for the separate public page/CSS entries. No reset, new package or SSR service is needed. A stale pre-publication route cache must be cleared with `php artisan route:clear --no-interaction` (and rebuilt if deployment uses route caching).

Set a wedding date, customize and save the draft, preview, then use **Publish invitation** on the overview and confirm. Copy the `/w/{slug}` URL and open it signed out. Editing wedding facts or saving another draft leaves that snapshot unchanged until **Republish changes**. **Unpublish** returns page/photo 404 while retaining drafts, history and media references. Required names/date/saved supported CMS/media are checked again inside the publication transaction.

Laravel delivers the public document, sharing metadata and styled no-JavaScript invitation; the existing Modern Classic Vue component enhances it. Public pages do not use the portal Inertia root or its authentication props. The default asks crawlers not to index/follow/archive, and page/photo responses are private/no-store. Third-party caches and previously downloaded copies cannot be recalled. Localhost URLs work locally without login; sharing over the Internet and real messaging previews require separate deployment to a reachable host.

The opt-in browser tests use an isolated SQLite server, storage and sessions with `QA_ISOLATED_SQLITE=1`, `QA_BASE_URL`, `QA_PLAYWRIGHT_MODULE` and an optional `QA_BROWSER_EXECUTABLE`; they reuse existing browser tooling and do not install dependencies. With those configured, run `node --test tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/ManualQaRegression.test.mjs tests/Frontend/Publication.browser.test.mjs`. Never point fixture-writing browser tests at development MySQL. Six MySQL concurrency cases (four draft/photo, two publication) still require the separately approved disposable target above and its latest migrations.

## Guest management (Milestone 4A)

The additive `2026_10_16_090634_create_invitation_groups_and_guests_tables` migration creates invitation groups and named guests; it is applied to development MySQL. Other environments need normal migrations and a production build. No reset, seed, new dependency or PHP setting change is required. Open **Guest Management** from the wedding overview; create/edit in modals, select a guest name to rename/remove, and use the group actions menu to add people or delete a group. A group requires 1–100 named guests. Removal is permanent and confirmed; the final guest can only be removed by deleting its group. There is no RSVP or delivery behavior.

For the focused isolated backend checks, run `php artisan test --compact tests/Feature/Weddings/WeddingGuestManagementTest.php` with the SQLite/testing overrides described above. The opt-in real-browser checks use the same isolated server/tooling variables as publication: `node --test tests/Frontend/GuestManagement.browser.test.mjs`. The full frontend set is the three files listed above plus this guest file. Never point browser fixtures at development MySQL.

## Personalized invitation links (Milestone 4B)

The additive `2026_10_16_095519_add_invitation_links_to_invitation_groups` migration is applied to development MySQL. Other environments need normal migrations, refreshed configuration/route caches and a production build. Local configuration cache was cleared to activate the new application-log taps. No new dependency or global PHP change.

In **Guest Management → group actions → Invitation link**, generate/copy/revoke/regenerate explicitly. A group with any issued link cannot be hard deleted, including after revocation; never-issued group deletion remains available. Saved group labels/member names change what existing holders see immediately. Wedding drafts remain private until publication. Unpublished weddings return 404; republishing updates existing active links. Delivery remains outside this scope; 5A adds responses below.

Tokens are random bearer credentials with SHA-256 lookup and encrypted recoverability. Protect the application encryption key independently from database/backups, and coordinate key rotation or explicitly regenerate links. Owners retrieve URLs only through no-store JSON actions; list props have status/version, not credentials. Browser clipboard/history and recipients can retain shared URLs.

**Before distributing real links:** use HTTPS and verify `/invite/*` path redaction/exclusion in every PHP/web-server/proxy/access/APM/analytics layer, and disable recovery-response body capture. Standard `php artisan serve` still prints paths to console. Application log taps and sanitized public exceptions do not control those external streams. No vendor or server-infrastructure workaround was added. Generic metadata/noindex does not prevent messaging-preview services holding a link from reading permitted names.

Focused checks: `php artisan test --compact tests/Feature/Weddings/InvitationLinkTest.php` with the isolated SQLite overrides above. Browser checks use the same isolated opt-in tooling: `node --test tests/Frontend/InvitationLink.browser.test.mjs`. The full frontend run includes this fifth file alongside the preceding renderer/three browser files. MySQL concurrency has eight opt-in cases: the original six plus new link generation/regeneration, all pending an approved disposable target; additional guest/deletion-race verification is not claimed.

## Guest RSVP submission (Milestone 5A)

Run normal `php artisan migrate` before serving this code in another environment. The additive `2026_10_16_104215_create_rsvp_responses_table` migration creates one response per guest and adds a group stale-save counter. It is applied to development MySQL without resets or customer response creation. Do not roll it back against valuable response data.

Open an existing active personalized link for a published wedding. The separate RSVP section lets its bearer save attending/declining choices for one or more named people and update them later; omitted answers are preserved and no record means pending. Generic public wedding pages have no RSVP. Renaming keeps answers; responded guests cannot be deleted. No attendance dashboard, clearing to pending, deadlines or delivery is added. Saving requires JavaScript; static HTML shows saved states.

The invitation GET remains stateless. Same-origin CSRF setup uses a token-free URL, followed by an ordinary CSRF-protected submission. Privacy headers/redaction remain in effect, but external access logs and request/response capture still require deployment protection. Use a persistent shared limiter cache in production. RSVP is limited to 20/minute per hashed invitation with a separate 120/minute/IP abuse guard; CSRF setup remains independently 60/minute/IP. Errors display the actual Retry-After interval. Browser HTTP tests must use `CACHE_STORE=file` with a fresh temporary `LARAVEL_STORAGE_PATH`; an array cache would hide cross-request throttling. PHPUnit's own isolated tests use array cache.

Focused check: `php artisan test --compact tests/Feature/GuestRsvpTest.php` with the isolated overrides above. Full frontend regression with the existing isolated browser setup: `node --test --test-concurrency=1 tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/ManualQaRegression.test.mjs tests/Frontend/Publication.browser.test.mjs tests/Frontend/GuestManagement.browser.test.mjs tests/Frontend/InvitationLink.browser.test.mjs tests/Frontend/GuestRsvp.browser.test.mjs tests/Frontend/Attendance.browser.test.mjs tests/Frontend/PortalUx.browser.test.mjs`. Real HTTP simultaneous requests test stale rejection, not InnoDB concurrency; the eight opt-in MySQL cases plus guest/RSVP races remain pending.

## Attendance monitoring (Milestone 5B)

Open **Wedding overview → Attendance** for wedding-wide totals, individual responses and invitation-group breakdowns. Search/status filters affect results, not summary cards. Group members load on expansion with pagination. Refresh loads latest saved responses; timestamps use your device's time zone. This is read-only: manage names in Guest Management and update responses through active invitation links. No new migration or environment setting is required. Owner acceptance remains pending.

Focused check: `php artisan test --compact tests/Feature/Weddings/WeddingAttendanceTest.php` with the isolated SQLite overrides above. The attendance browser test is included in the full frontend command above, using the existing isolated HTTP and external Playwright/Edge setup; no application dependency was added.

## Laravel Boost for Codex

Laravel Boost is a Composer development dependency. The project-local MCP server is configured in `.codex/config.toml` and starts with `php artisan boost:mcp`. Open and trust this project in Codex, then restart or reopen the project so Codex loads its MCP configuration.

The Codex MCP process uses an in-memory cache and bypasses the local cached config file. This keeps Boost's schema metadata cache out of the MySQL database and does not change the web application's runtime configuration. Boost provides application information, database schema and connection inspection, Laravel documentation search, and supporting browser/log tools. Documentation search needs outbound access to Laravel's hosted docs service.

## Structure

- `app/`, `routes/`, `database/`, `tests/`: Laravel application, customer accounts, wedding management, routes, migrations, and tests
- `resources/js/pages/`: Inertia Vue pages
- `resources/js/components/`, `layouts/`, `composables/`, `types/`: shared frontend code
- `resources/css/`, `vite.config.ts`: Tailwind and Vite setup

## Individual RSVP - active development workflow

Individual links and household delegation are active. config/rsvp.php defaults to RSVP_MODE=personal. Only that exact value enables RSVP; any other configured value (including legacy) fails closed. Required personal tables/counters must exist or relevant operations return 503. Legacy /invite/{token}, its RSVP POST and owner group-link actions always return 404, regardless of configuration. There is no mode that restores group authority. See architecture Section 7I and D-022.

Historical setup notes are superseded by the 2026-10-25 DEV-ENV incident: the earlier read-only inventory found only an empty migrations ledger, then an unsafe Laravel test run applied 18 migrations to `wedding_rsvp`. It now has 20 application tables and no user, account, wedding, guest, RSVP, cache or job records. No rollback or repair was performed. Owner review is required before any further MySQL write or normal-MySQL login verification; see [DEV-ENV](docs/tasks/dev_env_single_origin.md#test-runner-incident-2026-10-25). Do not follow setup/migration instructions against this existing schema until that review is complete.

Use the canonical local origin http://127.0.0.1:8000; trusted APP_URL must match the served host/port for copied links. Recovery URLs exist only after explicit authorized actions and are cleared from component memory. No raw tokens belong in normal props/history/logs. External access-log/APM exclusions and key/backup protection remain release requirements.

Run all browser regressions against a separately prepared disposable SQLite server with QA_ISOLATED_SQLITE=1, QA_RSVP_MODE=personal, QA_BASE_URL, QA_PLAYWRIGHT_MODULE, QA_BROWSER_EXECUTABLE, then node --test --test-concurrency=1 tests/Frontend/\*.test.mjs. Verify target isolation; flags alone do not prove it. Tests never reset the development database. See [handoff](docs/handoff.md) for exact verification and owner QA.

## Isolated CMS drafts and controlled publishing (DEL-03b.1)

The opt-in local/testing workflow supports persistent CMS-v2 drafts, saved private preview and controlled Enchanted publication through the existing wedding publisher. Both `CMS_EDITOR_PREVIEW_ENABLED` and `CMS_V2_PUBLISHING_ENABLED` default off; production always denies premium activation even if these flags are set. Missing required schema fails closed. Do not enable these flags against the unmigrated development MySQL database.

```powershell
npm.cmd run build
node tests/Frontend/cms-editor-preview.mjs
```

The Windows harness uses existing `C:/php` GD/EXIF/SQLite extensions via process-only configuration. It verifies a new temporary SQLite database/storage root before migrating/seeding **only that disposable target**, starts an independent loopback PHP host, and prints its preview URL and `preview.json` path. That file contains the synthetic owner's login. Use a private browser window, sign in, and open the printed URL. The normal `.env`, Vite hot file and development database are unchanged. Ctrl+C stops this host; rerunning creates a fresh isolated workspace.

Uploads are private assets stored in that temporary workspace. Explicit Save Draft persists complete source and assignments across reload within the same target; unsaved edits stay local. Removing a section image keeps its asset in the private library. If an upload is interrupted, reopen Photos before retrying because the server may have committed it. Restarting the harness creates a fresh disposable target rather than reopening the previous database.

For controlled publication QA, launch a new target with `$env:QA_CMS_PUBLISHING='1'` before `node tests/Frontend/cms-editor-preview.mjs`. Omit/unset this variable for the default private-editor-only target. Save, review the saved website and canonical facts/timezone, then confirm Publish directly in the editor. Republish appears after saved changes; dirty/pending-save state cannot publish. Wedding overview still provides status and Unpublish. View the public link in a private window. Later saved edits stay private until explicit Republish; Unpublish denies the page and current media while retaining source/history. DEL-04 supports personal Enchanted RSVP for the exact validated template/version under these controlled local/testing gates. Publication never creates/rotates/revokes credentials; authorized guests use existing 5C permissions and response storage. Production remains hard denied. [Contracts, exact verification and walkthrough](docs/tasks/del03a_draft_persistence_contract.md#9-del-03b--immutable-publishing-and-public-wedding-website).

For its actual browser tests set `QA_CMS_EDITOR_INFO` to that preview.json, `QA_PLAYWRIGHT_MODULE` to an existing Playwright/Playwright Core module and `QA_BROWSER_EXECUTABLE` to the installed browser, then run `node --test tests/Frontend/CmsEditor.browser.test.mjs`. The broader existing suite additionally requires the isolated-host flags above; set `QA_FLORAL_ARTIFACTS` to a temporary output folder to preserve earlier Floral screenshot evidence. Flags do not replace target verification. No MySQL provisioning/test authorization follows from this command.

The publication-enabled target additionally supports `node --test tests/Frontend/CmsPublication.browser.test.mjs`. On a separate **fresh** publication-enabled target, `node --test tests/Frontend/CmsPolish.browser.test.mjs` covers empty initial media, editor publishing and delayed-resource/reduced-motion loading. Its initial-empty check must precede test uploads. [DEL-03b.1 readiness contract and exact commands](docs/tasks/del03a_draft_persistence_contract.md#10-del-03b1--focused-preview-publishing-and-loader-polish). Test-only InnoDB cases are in `CmsPublicationMySqlTest`; they require the verified prepared target and PROCESS inspection described above and never create/reset schema themselves. Windows build QA used the previously approved temporary matching Rollup WASM parser without project dependency changes; its exact process-only invocation is in the DEL-03b evidence.

For a disposable DEL-04 walkthrough, set both `$env:QA_CMS_PUBLISHING='1'` and `$env:QA_CMS_PERSONAL='1'` before starting the preview harness. Synthetic Maya represents Nico; Lia is independent; Mateo has no initial credential. Login/password/guest URLs exist only in the temporary preview.json output. Gallery/library remain empty and all answers initially Pending. Default launches stay private-editor-only without personal fixtures. Run `node --test --test-concurrency=1 tests/Frontend/EnchantedPersonal.browser.test.mjs` against a separate fresh personal-enabled target using QA_CMS_EDITOR_INFO and the existing browser prerequisites. No-JS renders saved states/guidance; saving requires JavaScript as in 5C. [DEL-04 exact evidence and owner steps](docs/tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

For the database-free DEL-07a artwork/motion showcase, run `node tests/Frontend/enchanted-editorial-preview.mjs` and open `http://127.0.0.1:5196/enchanted-editorial.html?case=hummingbird`. This existing loopback fixture exposes the same shared bird with five heading controls, a finite flower flight, pause/resume and reduced-motion fallback; it does not create an application route or CMS setting. On Windows, retain the previously approved temporary WASM loader option when native Rollup is blocked. [Design, tests, owner evidence and current running URL](docs/tasks/premium_delight_experience.md#del-07a--layered-svg-hummingbird-and-contained-motion-prototype). DEL-07b scroll following remains deferred.

## Common setup issues

- **`npm` is blocked in PowerShell:** use `npm.cmd` / `npx.cmd`; this does not change machine policy.
- **MySQL access denied or connection refused:** ensure the MySQL service is running and `.env` contains the correct host, port, username, and password.
- **Unknown database `wedding_rsvp`:** stop and confirm the active local profile and owner-approved initialization target first. Do not create or migrate `wedding_rsvp` automatically; this existing database is under owner review after the 2026-10-25 incident.
- **Missing PHP extension:** enable it in the active PHP configuration (`C:\php\php.ini` in the inspected environment) and restart terminal commands.
- **Tests report `could not find driver` for SQLite:** enable `pdo_sqlite` and `sqlite3` in the active PHP configuration, then rerun `php artisan test`.
- **Configuration changes appear ignored:** run `php artisan config:clear`.
