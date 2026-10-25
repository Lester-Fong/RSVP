# DEV-ENV — Single-origin Windows manual testing

Date: 2026-10-25. Manual testing uses **http://127.0.0.1:8000**. The retained synthetic preview remains on its separate SQLite profile; the normal local app configured by `.env` targets MySQL `wedding_rsvp`. D-059 records DEL-09a acceptance and 5D implementation authorization. This update adds test isolation safeguards; it performs no MySQL writes.

## Diagnosis and data boundaries

### Test-runner incident 2026-10-25

The exact command was `php artisan test --compact tests/Feature/PersonalRsvpHttpTest.php tests/Feature/GuestInvitationLifecycleTest.php`, with no process-only isolation overrides. `phpunit.xml` declared `APP_ENV=testing`, `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, but its `<env>` entries omitted `force="true"`. PHPUnit 11.5.57's installed `PhpHandler` only writes an XML environment value when it is forced or `getenv($name) === false`; this made the intended values dependent on inherited process state. More decisively, `bootstrap/cache/config.php` existed and contained the local MySQL configuration. Laravel's `LoadConfiguration` loads that compiled file instead of evaluating `config/database.php` and the test environment. The `artisan test` command launches PHPUnit as a child and clears environment keys declared in `.env`, but it does not invalidate the cached config file. `RefreshDatabase` then used the resolved MySQL default and applied the **18 pending existing migrations** to `127.0.0.1:3306/wedding_rsvp`.

The historical process did not capture every inherited variable, so this record does not claim whether a non-forced env value also contributed. The cached MySQL config was present and was independently sufficient to bypass the XML SQLite settings. `APP_ENV=testing` alone does not select or isolate a database.

This was not owner-approved DEV-ENV initialization. The pre-incident read-only inventory found only an empty InnoDB `migrations` table and no application tables. Post-incident Boost checks find that same ledger with **18 rows (all batch 1)** plus the **20 application tables** created by those migrations. All 20 application, cache and job tables currently have zero rows; there are no database views or triggers. No pre-existing application table/data was present to overwrite; the only pre-existing table gained its migration ledger entries. No test data remained, no synthetic user was copied, no rollback/cleanup was attempted, and no further MySQL operation is authorized by this record. Owner review is required before further writes or normal-MySQL use. See RF-032/QA-DEV-02.

The exact command is preserved above to make the incident auditable. The protections below now make standard PHPUnit runs fail closed and force the default test connection to isolated SQLite. Continue setting the process-only overrides in [`AGENTS.md`](../../AGENTS.md); MySQL `wedding_rsvp` is never a disposable concurrency target.

Initial read-only Boost queries (before the incident above) confirmed `wedding_rsvp` contained only the InnoDB `migrations` table, with zero migration rows and no `users` table. The original login error was an uninitialized database, not a port-8000 issue. The current state is superseded by the incident correction above.

The working Enchanted preview is a separate retained synthetic SQLite environment, originally served on a temporary port. Its database/storage are still present in the private `rsvp-cms-editor-LrKACO` temporary directory. Initial DEV-ENV read-only inventory: **2 users, 2 weddings, 1 draft, 5 revisions, 9 media assets, 2 personal credentials, 0 responses, 18 migration entries**. At the later DEL-05a continuation it already contained **3 users/3 weddings** (the additional records predate this continuation); other counts are unchanged. Existing manual additions were preserved. No synthetic data has been copied to MySQL.

`.env` has `APP_URL=http://127.0.0.1:8000`, `DB_CONNECTION=mysql`, and `DB_DATABASE=wedding_rsvp`; it was not changed. The cached main-app configuration also exists. The normal local Laravel profile (`php artisan serve`) uses MySQL `wedding_rsvp`, subject to owner review after this incident. The retained `dev.cmd` owner workflow uses a separate persistent synthetic SQLite database and explicit SQLite/storage/session/cache/config paths; its realistic registration/CMS route exercise is synthetic, not backed by MySQL. The same origin can serve only one of these profiles at a time.

## Startup after restarting Windows

From the repository, run in PowerShell or Command Prompt:

```powershell
.\dev.cmd
```

Keep that terminal open. Ctrl+C stops the preview and Vite started by this launcher. An existing working project Vite instance is reused and is not owned/stopped by this command. Windows currently disallows `.ps1` scripts; this `.cmd`/Node entry point requires no execution-policy change or bypass.

The private profile has already been selected once and is stored in ignored `storage/app/private/dev-preview/manual-preview.json`. It points to the **existing** retained preview manifest; it does not contain or initialize a database. It survives service/laptop restarts. Ordinary startup never migrates, seeds, resets, recreates data or rotates credentials. Missing/invalid retained files fail closed rather than generating a replacement.

To select an existing approved preview on another checkout/workstation, use once:

```powershell
.\dev.cmd --preview-info "C:\path\to\existing\rsvp-cms-editor-XXXXXX\preview.json"
```

The source must pass the existing isolated temporary-directory/SQLite guards. Do not manufacture a manifest pointing to valuable data. Windows temporary-file cleanup can remove retained QA data: retain/back up this directory before cleanup; any relocation, restore, initialization or credential replacement requires its own approved step. A physical laptop reboot was not performed during verification; cold service stop/restart was tested.

| Manual surface | Stable URL |
| --- | --- |
| Login | http://127.0.0.1:8000/login |
| Registration form | http://127.0.0.1:8000/register |
| Synthetic owner CMS | http://127.0.0.1:8000/accounts/1/weddings/1/cms-preview |
| Published Enchanted wedding | http://127.0.0.1:8000/w/synthetic-editor-preview |

Synthetic owner email remains `editor-preview@example.test`; its unchanged password is in the private `active-preview.json` printed by startup. Never commit the credentials, runtime key or profile data. Use a fresh/private browser context after changing between MySQL and synthetic profiles; they share the required browser origin but not user IDs or authorization state.

Vite may listen internally on 5173. The default command uses current-source Vite assets for the existing Laravel editor/public renderer, including the accepted SVG and latest guided-flight refinements. It creates no new public preview route or separate website implementation. Explicit `--built` uses existing compiled assets instead; rebuild deliberately if source changed, and do not use that option to claim current-source verification without checking the build.

If port 8000 is occupied, startup fails with a clear message. Stop its existing server explicitly and retry; it never chooses 8001/random ports or kills an unknown process. Future owner reports must provide port-8000 URLs. Automated suites may create separate disposable internal services only when technically necessary and must never target the retained owner-edited manual profile with fixture-writing tests.

## Reuse and security

- `dev.cmd` delegates to the existing `tests/Frontend/cms-editor-preview.mjs` harness. Automated `createCmsEditorPreview()` remains opt-in and isolated; manual CLI resumes retained data on 8000. Its new fixtures retain their original private runtime key/cookie for future resumption.
- The existing PHP router and `cms-editor-target.php` guard still require the verified temporary SQLite/storage root, local environment, personal RSVP mode and explicit CMS feature flags. Normal production configuration/routes gain no bypass or fixture endpoint. The live-assets flag affects this test router only; existing automated built-asset behavior remains default.
- Current legacy preview metadata did **not** retain its original generated encryption key. No key/token was rotated or rewritten here. Existing bearer-link reads and owner encrypted credential recovery must be distinguished: recovery/recopy is unverified without the original key. Do not silently regenerate personal credentials to hide this limitation; RF-012 and QA-OPS-04 remain open. New runtime persistence does not prove production key custody/ACLs or restore.
- Existing no-store, tenancy, owner policy, CSRF and publishing controls remain unchanged. Permission/public-media/key/log/retention/device/release gates are not waived by local availability.

## MySQL schema — owner review required

All **18 existing migrations are already applied** as described in the incident correction; together they define **20 application tables**. Their `up()` bodies create/alter schema and do not create users, guest or RSVP rows. They include CMS metadata/media, invitation credentials/delegation/version fields, the withdrawal timestamp and canonical timezone. This does not activate the 5D runtime feature or customer/production CMS availability. MySQL DDL may commit independently. Do not rerun, roll back or reset these migrations without a separate owner decision. No command is supplied here because no database-changing action is currently authorized.

| Group | Tables created |
| --- | --- |
| Authentication/session | users, password_reset_tokens, sessions |
| Cache/jobs | cache, cache_locks, jobs, job_batches, failed_jobs |
| Tenancy/weddings | accounts, account_members, weddings |
| Website | website_drafts, website_revisions, wedding_media_assets, website_revision_media |
| Guest/RSVP | invitation_groups, guests, rsvp_responses, guest_invitation_links, rsvp_delegations |

After owner review, a fresh owner account could be created through normal registration if that path is deliberately selected; no existing user is available and none was imported. The synthetic published preview remains separate. Use `dev.cmd` for manual QA. Do not switch profiles or run bare `artisan serve` against MySQL until the owner reviews the incident; never change browser origins to work around a busy port.

## PHPUnit database safeguards and commands

`phpunit.xml` now forces `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, blank `DB_URL`/`DATABASE_URL`, array cache/session, and `SESSION_PATH=/`. `tests/bootstrap.php` validates those values before Laravel boots, reasserts them in `getenv`, `$_ENV`, and `$_SERVER`, and chooses a unique nonexistent config-cache path under `bootstrap/cache`. `Tests\TestCase::createApplication()` then checks Laravel's resolved environment and SQLite config before Pest's feature `RefreshDatabase` trait or test code can run. A bad driver, on-disk SQLite path, URL override, or cached-config resolution aborts before fixtures/migrations.

Standard test command (run from PowerShell; substitute the desired selection). The temporary scan directory is the approved SQLite/EXIF PHP extension setup used on this workstation:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:DATABASE_URL=''
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:SESSION_PATH='/'
$env:APP_CONFIG_CACHE=Join-Path $env:TEMP ('rsvp-test-'+[guid]::NewGuid().ToString('N')+'.php')
$env:APP_ROUTES_CACHE=Join-Path $env:TEMP ('rsvp-test-routes-'+[guid]::NewGuid().ToString('N')+'.php')
$env:PHP_INI_SCAN_DIR=Join-Path $env:TEMP 'rsvp-cms-editor-LrKACO\ini'
php artisan test --compact tests/Feature/PersonalRsvpHttpTest.php tests/Feature/GuestInvitationLifecycleTest.php
```

Disposable InnoDB suites remain opt-in and keep the PHPUnit application's default connection on SQLite. They skip unless every `CMS_MYSQL_TEST_*` value is supplied; when enabled, the helper accepts only a loopback host, a prepared `wedding_rsvp_cms_test` or suffixed database, and dedicated username/password variables distinct from the application's `DB_USERNAME`/`DB_PASSWORD`. The schema must already be prepared and the test account granted only the required test privileges. Tests neither migrate nor create/reset it. Example invocation after such a separately approved target exists:

```powershell
$env:CMS_MYSQL_TEST_ENABLED='true'
$env:CMS_MYSQL_TEST_HOST='127.0.0.1'
$env:CMS_MYSQL_TEST_PORT='3306'
$env:CMS_MYSQL_TEST_DATABASE='wedding_rsvp_cms_test'
$env:CMS_MYSQL_TEST_USERNAME='<dedicated-disposable-test-user>'
$env:CMS_MYSQL_TEST_PASSWORD='<dedicated-test-user-password>'
php artisan test --compact --group=mysql-concurrency
```

Keep the password in the local process environment; do not place it in docs, committed files, or logs. Worker processes recheck the explicit target and matching host, port and test credentials. No disposable MySQL target was available or used for this safety task.

## Focused verification

Final safety-task verification: `php artisan test --compact tests/Unit/TestDatabaseGuardTest.php tests/Feature/TestingDatabaseIsolationTest.php tests/Feature/PersonalRsvpHttpTest.php tests/Feature/GuestInvitationLifecycleTest.php` passed **100 tests / 690 assertions** under the documented SQLite process overrides. With inherited `APP_ENV=local`, `DB_CONNECTION=mysql`, `DB_DATABASE=wedding_rsvp`, a database URL for unreachable loopback port 1, and file cache/session values, the guard/isolation selection passed **11 tests / 14 assertions** because PHPUnit forced its safe values. Separately, running `php -r "require 'tests/bootstrap.php';"` under that unsafe environment exited nonzero with `Unsafe PHPUnit environment: APP_ENV must use the isolated test value.` before Laravel boot/database access. The five existing opt-in MySQL files skipped **29 tests / 0 assertions** with no disposable target configured. Changed PHP files pass Pint and PHP syntax; `git diff --check` passes. No MySQL test target was contacted.

Exact execution evidence and remaining gates are tracked as QA-DEV-01/02/03 in the [QA register](../qa/verification_register.md). No rollback/reset, data import, package install, system PHP/security-policy change, commit or push was performed.

- `node --test tests/Frontend/LocalPreviewEnvironment.test.mjs`: **5 passed**. Isolates inherited MySQL/URL/cache/storage, fixed 8000, retains bytes, denies missing/outside/invalid profiles and preserves/refuses replacement runtime keys. Initial short-versus-long Windows temp-path assertion was corrected to arrange a canonical fixture; final selection passes.
- With process-only `APP_CONFIG_CACHE` pointing to a nonexistent temporary file, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_PATH=/` and `PHP_INI_SCAN_DIR` pointing to the retained preview's `ini` directory: `php artisan test --compact tests/Feature/CmsV2AvailabilityTest.php tests/Feature/CmsEditorPreviewTest.php`: **20 passed / 212 assertions**. Initial parent-only `php -d extension=... artisan test` did not propagate extensions to the child runner; 20 driver errors/zero assertions were resolved by the documented scan-directory environment, without global PHP changes or MySQL access.
- Connected installed Edge on 8000: existing synthetic-owner login → dashboard, CMS HTTP 200/one live iframe, another account's CMS HTTP 403, registration form HTTP 200 without submission, current published snapshot HTTP 200; one Hero bird, both wing animations running, 11 markers, travel to Countdown, mobile one bird/no overflow, reduced-motion preference and readable no-JS fallback. No save/publish/RSVP/registration submission. No page errors; current `app.ts`, `public-enchanted.ts`, `EnchantedHummingbird.vue`, SVG and other source modules loaded through Vite. Private detailed evidence: `Temp/rsvp-dev-env-browser.json`.
- `npm run typecheck`, scoped ESLint, scoped Prettier, `php vendor/bin/pint tests/Fixtures/cms-editor-router.php --dirty --format agent`, PHP syntax and `git diff --check`: pass. DEV-ENV changes no frontend application source; separately authorized DEL-05a subsequently changes only Story presentation and passes a new production build. Manual current-source verification uses live Vite.
- Busy-port check: a second `dev.cmd` exits 1 with an explicit occupied-8000 error; no alternate server starts. The retained SQLite checksum remains the separately documented owner-preview value. The initial MySQL read-only snapshot and the subsequent migration incident are recorded above; final read-only counts show zero application rows.

Current manual preview remains available through `dev.cmd`; legacy encryption-key recovery remains unverified. The MySQL schema incident remains pending owner review, and normal login was not verified. D-059 separately authorizes 5D implementation; use only the retained preview profile for manual review until the owner decides the MySQL state.
