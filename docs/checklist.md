# Wedding RSVP SaaS — Milestone Checklist

Updated: 2026-10-16. [x] reflects Codex-reported verification, unless otherwise stated. Local source review and manual browser checks must be marked separately. Milestone acceptance requires the owner to approve its review.

## Phase 0 — Foundation

- [x] Initialize Git repository and Laravel 12 + Vue 3/TypeScript + Inertia + Tailwind + Vite project (Codex report).
- [x] Configure MySQL wedding_rsvp and run default additive migrations (Codex report).
- [x] Run foundation tests/build/typecheck/format checks (27 tests and 64 assertions reported at foundation stage).
- [x] Create initial docs/architecture.md (Codex report; proposed design, not implemented modules).
- [x] Install Laravel Boost v2.10.3 as a Composer development dependency and configure its project-local Codex MCP server with the official MCP-only installer.
- [x] Verify Boost stdio initialization, Application Info, a read-only schema summary (12 tables), and Search Docs tool registration. The schema call used an in-memory cache and did not persist data.
- [x] Verify Boost tool discovery in this Codex session: Application Info, schema reads, Search Docs, and recent error/browser logs worked during Milestone 2B. Earlier managed-profile limitations did not recur.
- [x] Confirm existing Git checkpoints: `115b80e` (Initial Commit) and `783b259` (First Milestone implemented and verified), observed during the 2026-10-16 checkpoint review. New milestone checkpoint still requires explicit commit approval; history alone is not owner acceptance.

## Milestone 1 — Account and wedding foundation

- [x] Add accounts, account_members, weddings tables (Codex report).
- [x] Add Eloquent relationships, owner membership, first-wedding transaction (Codex report).
- [x] Add create/view/edit wedding flows and dashboard (Codex report).
- [x] Add scoped routes, account/wedding policies, validation and slug handling (Codex report).
- [x] Run automated tests and quality checks (37 tests, 102 assertions; frontend/build/lint checks passed per Codex).
- [x] Perform source-level Milestone 1 review and resolve the two IMPORTANT ownership/concurrency findings (Codex review and fix; not owner approval).
- [x] Rerun automated checks after the review fixes (39 tests / 120 assertions; TypeScript, build, Prettier, targeted ESLint, and Pint passed).
- [x] Owner-reported browser verification of core wedding management flow and cross-account access returning 403 (conversation report; not independently re-run here).
- [ ] Owner approves Milestone 1; commit approved implementation and docs (commit only when authorized).

## Milestone 2 — Modern Classic invitation, CMS, and publishing

- [x] Record the approved Modern Classic-only scope and prioritize core functionality before additional designs or template selection/switching (D-010, 2026-10-16).

### Milestone 2A — Private preview

- [x] Select the first proof-of-concept direction: Modern Classic invitation with couple names, celebration date/time, and ceremony/reception venues (explicit task).
- [x] Scope Milestone 2A to one authenticated, account/wedding-scoped preview and acceptance tests; no CMS or public route.
- [x] Build the responsive Modern Classic template from typed wedding facts and add the wedding overview action.
- [x] Verify authorized props, absent optional fields, authentication, account isolation, nested binding, and no public wedding route (45 tests / 168 assertions; isolated SQLite).
- [ ] Owner manually checks the preview at desktop and 390px widths, including long names, missing details, keyboard focus, and the return link.
- [ ] Review and approve the Milestone 2A result; commit only when explicitly authorized.

### Milestone 2B — Basic Modern Classic CMS / invitation editor

- [x] Scope two plain-text messages (500 characters each) and ceremony/reception/closing visibility; no images, styling controls, reordering, publishing, or template switching (D-011).
- [x] Add one schema-versioned JSON draft per wedding, with unique foreign key and stale-save protection; retain wedding facts in `weddings`.
- [x] Add authorized editor/update routes, the overview entry point, reactive live preview, and saved-draft/default loading through the existing Modern Classic renderer.
- [x] Cover defaults, persistence, reopening, multiple weddings, cross-account reads/writes, nested scoping, validation/unsupported keys, stale saves, and unchanged wedding facts in Laravel tests.
- [x] Verify actual renderer section hiding/restoration, missing-details fallbacks, multiline output, and escaping with four Node/Vue tests (no new dependency).
- [x] Run full Laravel suite: 73 tests / 391 assertions passed on isolated in-memory SQLite; TypeScript, ESLint, targeted Prettier, Pint, and production Vite build passed.
- [x] Apply the additive `website_drafts` migration to local MySQL; Boost confirms JSON storage, unique wedding key, and cascading foreign key. No customer records were changed.
- [x] Codex browser QA on a separate temporary SQLite database: live edits, saved-only standalone preview, save/reload, hide/restore, markup rejection/escaping, and cross-account GET/PUT denial passed. No page errors or horizontal overflow at 1440/1024/390px in light/dark; explicit theme persistence, system-theme changes, and keyboard focus passed.
- [x] Inspect desktop/tablet/mobile captures against Horizon Minimal: applicable hard rules 001/004–008 pass; 002/003 are N/A for the dedicated multi-section workspace without overlays. Applicable scored rules: 57/61 = 93/100; HM-TYPE-001's four points withheld because Geist is unavailable and existing Instrument Sans/system mono fallbacks are used. Sidebar/table/modal/empty-state/texture/login/church-specific checks are N/A. Modern Classic is outside the portal palette evaluation by explicit owner instruction.
- [ ] Owner reviews Milestone 2B behavior and visuals and explicitly accepts it. Milestone 2A owner acceptance also remains unrecorded.

### Milestone 2B — Final CMS hardening

- [x] Reject unsupported stored schema versions on editor/preview reads and inside locked saves with a controlled HTTP 409; retain the stored JSON, schema/save versions, and timestamps. Existing version 1 behavior remains covered.
- [x] Add regression coverage for versions 0/2/65535, incompatible future content, production-mode error responses, schema changes after loading the editor, and authorization preceding compatibility checks.
- [x] Add opt-in MySQL concurrency cases for simultaneous first saves and updates using independent PHP request processes/connections. A blocker transaction and `INNODB_TRX` must show both requests waiting before release; assertions require one success/one stale conflict, one draft, one version increment, and the complete winning content.
- [x] Guard concurrency execution with an explicit `CMS_MYSQL_TEST_DATABASE` allowlist (`wedding_rsvp_cms_test` or suffixed variants), actual connection-target checks, and InnoDB/schema prerequisites. No database/schema creation, dropping, resetting, or truncation is performed; cleanup is limited to fixtures created by each test.
- [x] Verification: 78 Laravel tests / 429 assertions passed on isolated SQLite; 2 MySQL cases skipped. Four renderer tests, TypeScript, ESLint, targeted Prettier, Pint, worker PHP syntax check, and production build passed. No frontend/template edits or development MySQL writes were needed.
- [ ] Execute both MySQL concurrency cases against an approved, existing disposable database with project migrations and `INNODB_TRX` inspection privileges. Blocker: no test database was visible on the current server and no test target was configured. Creation/drop/reset was not authorized and was not performed.
- [ ] Obtain manual owner QA and acceptance; retain the existing unchecked owner approval above. Milestone 2C can be scoped, but hardening verification remains pending until the real MySQL cases pass.

### Milestone 2C.1 — Private wedding hero photo

**Owner accepted on 2026-10-16:** The product owner reports successful manual testing, including dashboard name truncation, hero photo upload, and saved-photo persistence after reloading. This acceptance does not mark the four skipped MySQL concurrency cases as verified or infer separate acceptance of earlier milestones.

- [x] Implement one private photo per draft: select/local preview/save/reopen/replace/remove/undo; reuse Modern Classic below its existing header, without cropping or changing its identity.
- [x] Add nullable server-controlled draft reference columns in an additive migration; retain version-1 JSON, relational wedding facts, owner policies, scoped routes, and the existing save counter/parent lock. No media table or dependency added.
- [x] Validate JPEG/PNG/WebP, 5 MiB, 12 MP/6,000px bounds; normalize to static metadata-free WebP with native GD/EXIF, all eight JPEG orientations, transparency, and no upscaling. Serve only current saved references through authenticated owner-only, private/no-store/nosniff delivery.
- [x] Cover multipart strict booleans, unauthorized/nested access, formats/bounds, stale/schema rejection, persistence/replacement/removal, missing files, rollback/storage failure, uncertain commit reference protection, and dry-run-first pruning. Extend the opt-in MySQL harness with simultaneous first photo saves/replacements and isolated shared temporary storage.
- [x] Automated verification: 118 Laravel tests / 778 assertions pass on isolated SQLite; four MySQL cases skip without a configured disposable target. Five Node/Vue renderer tests, TypeScript, ESLint, targeted Prettier, Pint, worker syntax, and production build pass. Temporary PHP scan settings enable SQLite/EXIF and 256M memory; no global runtime settings or development MySQL schema/data changed.
- [x] Isolated browser QA: select/preview/save/reload, multipart messages/toggles, replacement, stale failure retaining local selection, remove/undo/save, invalid type, and cross-account image denial pass. At 1440/1024/390px in light/dark: no page overflow; dialog fits; Escape, focus trapping, and focus restoration pass; no page errors. Evidence lives in temporary `rsvp-2c1-*.png` captures, not repository assets.
- [x] Horizon Minimal review: applicable hard rules pass; dedicated editor workspace retains the page exception, with an accessible removal dialog. Portal remains monochrome; uploaded content and Modern Classic are outside its palette rules. Rubric: 60/66 applicable weight = 91/100; font points (4) and shared dialog overlay styling points (2 of 5) withheld because the reused overlay is heavier than the preferred dim/blur. Existing UI font fallback retained; no unrelated shared-component redesign. Motion is disabled for the editor/dialog in reduced-motion mode. Owner acceptance is recorded above; no exhaustive physical-device/format matrix is inferred.
- [x] Confirm development MySQL readiness for the additive schema: Boost reports `wedding_rsvp`, the hero-photo migration in its migration ledger, and both nullable reference columns. Already applied before this fix; no development migration or record changes were needed.
- [x] Fix manual QA dashboard overflow: constrain card/title flex sizing, clamp names to two lines with ellipsis, wrap unbroken words, preserve the full accessible text/title and saved values, and keep the status badge from shrinking. Modern Classic is unchanged.
- [x] Diagnose the active web runtime directly: `cli-server` on port 8000 loads `C:\php\php.ini` with 2M upload / 8M POST / 128M memory, EXIF disabled, working GD JPEG/PNG/WebP and Fileinfo, and a writable fallback temporary directory. A 3 MiB multipart probe returned `UPLOAD_ERR_INI_SIZE` (1), reproducing rejection before processing. Temporary diagnostic endpoint removed. POST with `_method=put` was already correct; private storage and the additive migration are not the cause.
- [x] Improve failed-upload field messages for PHP size/interruption/temp/write/extension errors and application size rejection. Six focused failure cases assert HTTP 422, unchanged saved photo/content/version, and reload persistence. Existing normalization/orientation/privacy/authorization/stale/storage tests still pass.
- [x] Bugfix verification: full isolated SQLite suite passes 125 tests / 924 assertions, with four opt-in MySQL concurrency cases skipped. After final message changes, the affected photo suite passes 43 tests / 462 assertions. Five renderer tests, the opt-in browser regression, TypeScript, ESLint, targeted Prettier, Pint, and production build pass. No dependencies or global PHP settings changed.
- [x] Real HTTP/browser regression (`tests/Frontend/ManualQaRegression.test.mjs`): synthetic 3,785,568-byte PNG saves/reloads through process-only configured PHP; original PHP limits reject the same file with an actionable editor field error and preserve unsaved selection and saved state. Malformed/oversized replacements preserve saved content/photo; subsequent text-only/toggle saves work. Separate temporary SQLite, photo storage and sessions keep development data isolated. The opt-in harness uses existing external Playwright/Edge; default frontend runs skip it unless explicitly configured.
- [x] Dashboard browser QA covers spaced names and unbroken words as the leading name, 1440/1024/390px light/dark, two-line clipping, card/document containment, full title/text, visible focus, and no page errors. Screenshots: temporary `rsvp-bugfix-{width}-{theme}.png`. Scoped Horizon eval: 46/50 = 92%; applicable hard rules pass (001/004/005/006/007), overlays and new animation N/A. Existing font fallback accounts for four withheld points; shared navigation and other unchanged surfaces are outside this fix.
- [x] Fix standard development startup rather than requiring another port: `App\Console\Commands\Serve` extends the existing Laravel command and supplies 5M upload / 8M POST / 256M memory and missing EXIF directly to the PHP child. Normal `php artisan serve` and `composer run dev` now retain the fix on subsequent starts. Restarted the original port-8000 server; its receiving PHP command line confirms all four overrides. No global PHP settings, customer records, application validation limits, or dependencies changed. README uses the normal startup command again.
- [x] Startup regression launches real `php artisan serve` from restrictive parent settings (2M upload / 3M POST / 128M memory, no extension scan), posts a valid 2.24 MiB PNG, and asserts actual receiving-runtime settings, enabled EXIF, upload error 0 and complete byte count. Fixtures/processes are cleaned up. Full isolated suite: `php artisan test --compact` passes 126 tests / 927 assertions; four disposable-MySQL concurrency cases skip. Six frontend/browser tests pass, including a 3,785,568-byte PNG saved/reloaded through standard Artisan startup with isolated SQLite/storage and an assertion that the browser POST stays on its original origin. Pint, ESLint, targeted Prettier and diff checks pass. Owner acceptance was subsequently reported on 2026-10-16; MySQL concurrency remains pending.
- [ ] Run all four opt-in concurrency cases on an approved disposable MySQL database with the new migration and inspection privileges. No target is configured; no provisioning/reset was attempted.
- [x] Product owner manually tests and accepts Milestone 2C.1 on 2026-10-16, explicitly confirming dashboard truncation, hero photo upload, and persistence after reloading. This is the owner's direct report, distinct from Codex browser tests. Separate Milestones 1/2A/2B acceptance remains unrecorded; no additional device/format coverage is claimed.

### Planned next — Publishing (separately scoped)

- [ ] Decide public routing, rendering, hosting, and link-preview metadata for the existing design.
- [ ] Implement publishing with versioned draft/published snapshots; expose only published content on public pages.
- [ ] Review and verify draft/public separation, privacy, accessibility, and responsive behavior for each scoped milestone.

## Remaining core platform milestones

Scope and review each area separately before implementation. These capabilities take priority over additional templates.

### Guest management and RSVP tracking

- [ ] Scope guest fields, invitation groups, plus-one policy, response editing, and deadlines.
- [ ] Invitation groups and individual guests.
- [ ] Per-guest RSVP, dashboard counts, and exports.
- [ ] Verify invitation privacy, account/wedding isolation, and response consistency.

### Seating arrangements

- [ ] Scope attendee eligibility, table capacity, and arrangement/layout controls.
- [ ] Implement saved tables and guest seating assignments with capacity and ownership constraints.

### Internal administration

- [ ] Define explicit, auditable staff access to customer accounts and weddings.
- [ ] Implement assisted onboarding using the same Modern Classic CMS and manual order/payment verification tools.

### Core release readiness

- [ ] Production safeguards: policies, backups, privacy/retention, performance, monitoring, launch QA.

## Deferred until core functionality is developed

- [ ] Revisit multiple-template support, template selection/switching, and additional visual designs after core platform development.
- [ ] Obtain the product owner's Figma or Canva references before scoping additional wedding templates that reuse the same data and business logic.
- [ ] Assess whether a registry or generic template engine is justified by those future designs; do not build one as a prerequisite for current CMS or publishing work.

## Blockers / watch items

- Checkpoint review (2026-10-16): `.env.example:28` contains a populated database password matching the active local credential and already present in `HEAD`. Replace it with a placeholder and rotate the credential before sharing the repository; removing it from the current file does not remove prior history. No value is recorded here. Review-only finding: no credential, environment, history, or business-logic edits were made.
- Checkpoint scope: include approved milestone source/tests/migrations, synchronized docs, and approved Boost manifests/configuration with canonical `.agents/skills`. Exclude the 72 identical skill copies under `.claude/skills` and `.github/skills` from the proposed checkpoint unless separately desired. No runtime artifacts or pasted customer data were found among Git candidates; environment/private storage/log/build/dependency/cache paths are ignored. No staging, commit, or push is authorized by this acceptance task.
- PHP CLI is 8.2 and may need upgrade for future Laravel majors; remain compatible with Laravel 12 until explicitly changed.
- pdo_sqlite was enabled only temporarily for test processes; ordinary php artisan test may still fail until a persistent safe configuration is set.
- Nine npm audit development-tool advisories were previously reported, while npm audit --omit=dev passed; track deliberate upgrades separately.
- Milestones 2A/2B manual owner QA and explicit acceptance remain pending; Codex browser QA does not constitute owner sign-off. No commit has been requested.

## Milestone close-out rule

A task is implemented when code is in place; verified when required checks were actually run; approved only after code review, manual QA where applicable, and explicit owner sign-off. Never collapse these stages into one checkbox.
