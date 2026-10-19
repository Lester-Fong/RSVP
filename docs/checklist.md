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

### Milestone 3A — Saved Modern Classic publication (previously 2C.2; D-013)

**Owner accepted:** The product owner confirms 8/8 manual acceptance tests passed. Recorded from the explicit Milestone 4A instruction on 2026-10-16; the six outstanding MySQL concurrency cases remain unverified.

- [x] Add immutable `website_revisions` with wedding facts/CMS/photo references, unique sequence and fingerprint; add wedding pointer/publication counter. Review MySQL DDL with `--pretend`, apply the additive migration to development, verify schema/indexes with Boost, and clear the stale route cache. Existing invitations remain unpublished until their owner acts; no reset or customer publication was performed.
- [x] Provide authorized, scoped publish/unpublish POST routes with explicit confirmation, saved-draft/schema/name/date/media validation, same-wedding references, parent locking, atomic snapshot/pointer changes, source/counter stale checks and duplicate protection. Keep all draft/previous snapshot data on failure or unpublishing.
- [x] Public `/w/{slug}` and `/w/{slug}/photo/{photo}` resolve only the active supported same-wedding snapshot/reference, never live draft/facts. Return 404 for unpublished/missing/invalid references; retain owner-only draft images. Strip hidden section content and all private metadata from public payloads; escape Blade/Vue/embedded JSON.
- [x] Extend replacement/removal and pruning to retain references in all historical snapshots, including after unpublishing. No public storage links, copied originals or image-processing changes.
- [x] Serve metadata and primary invitation HTML through Laravel, enhanced by the existing Vue renderer and unchanged shared Modern Classic CSS; explicit CSS entries support no-JavaScript styling. No extra SSR process/dependency. Implement the recommended noindex/nofollow/noarchive and private/no-store defaults; owner reviews indexing preference during QA.
- [x] Add restrained overview publication controls: status, pending saved changes, timestamp, confirmation, republish/unpublish, URL copy/fallback and public view. Reuse modal accessibility; optional overlay styling affects only publication confirmations. Existing Modern Classic design is preserved.
- [x] `php artisan test --compact`: 148 passed / 1,126 assertions on isolated SQLite; six MySQL cases skipped. Twenty-two publication cases cover authorization/CSRF, immutable snapshots, source/duplicate/stale protection, validation, rollback, public privacy, photo access and retention/pruning. Original wedding/CMS/photo regression coverage passes.
- [x] `node --test tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/ManualQaRegression.test.mjs tests/Frontend/Publication.browser.test.mjs`: seven passed with explicit opt-in isolated SQLite/storage/session browser targets. Automated browser checks cover publication, copy API/fallback, anonymous access, JavaScript/no-JavaScript content/style parity, private draft separation, republish, unpublish, 404 media revocation, modal keyboard/focus, and no page errors/overflow at 1440/1024/390px in light/dark. Clipboard API is mocked to avoid changing the system clipboard; real device permissions remain part of owner QA.
- [x] TypeScript, ESLint, targeted Prettier, Pint, worker syntax and production build pass. Horizon scoped eval: 60/64 = 94%; applicable hard rules pass. Four font points withheld for the existing fallback. Navigation/tables/empty states/login and church-specific styling are outside this change; Modern Classic is outside the portal palette. Temporary `rsvp-publish-*.png` captures support automated visual review; this is not manual owner sign-off.
- [ ] Execute six opt-in MySQL concurrency cases on an approved existing disposable target with all migrations and `INNODB_TRX` access: the original four draft/photo cases remain pending, plus two new first-publication/republication cases. No database was provisioned/reset and no MySQL concurrency success is inferred from SQLite or the applied schema.
- [x] Owner manually accepts Milestone 3A: 8/8 tests passed, as explicitly reported by the owner. This is distinct from Codex automation and does not resolve MySQL concurrency or external-host preview verification. Milestone 2C.1 acceptance remains recorded separately.
- [ ] Verify external messaging/social previews on an approved reachable deployment; localhost is not public Internet hosting. No production hosting or additional SSR service is included.

## Remaining core platform milestones

Scope and review each area separately before implementation. These capabilities take priority over additional templates.

### Milestone 4A — Guest list and invitation groups (D-014)

**Owner manually accepted on 2026-10-16:** The product owner explicitly confirms manual verification passed and authorizes 4B in the existing working tree. No 4A test-count total is inferred; Milestone 3A's separately reported 8/8 remains distinct.

- [x] Add `invitation_groups` and `guests` only, with required parent foreign keys, tenant-leading indexes, optional group contacts and named guests. Guest wedding ownership is derived through its group; no duplicated wedding key, RSVP status or invitation token. Review MySQL DDL, apply the additive migration to development without resets, and verify both tables/indexes/FKs with Boost. No existing wedding/CMS/snapshot records changed.
- [x] Owner-authorized, fully scoped CRUD with strict Form Requests and immutable parent membership. Create group/members transactionally; serialize membership changes/deletion on the parent group to preserve at least one guest and a 100-person group safety limit. Names/labels up to 255 characters, email 254 and phone 50; duplicate names are allowed. No wedding-wide guest quota is introduced.
- [x] Add wedding overview navigation and Horizon Minimal management page: distinct global group/person totals, literal label/member search, 25-group pagination with all members, optional contacts, row menus, modal create/edit/add/rename and explicit permanent deletion confirmations. Search does not change global totals. Final-person removal is blocked; deleting the whole group remains possible.
- [x] Focused guest suite: 27 tests / 300 assertions pass. Full isolated SQLite suite: 175 passed / 1,426 assertions, six existing MySQL cases skipped. Covers CRUD/persistence, limits, empty-group rejection, rollback, search/pagination, literal wildcards, tenant/nested/role boundaries, manipulated membership fields and public snapshot privacy. Existing wedding/CMS/photo/publication regressions pass.
- [x] All eight frontend tests pass with isolated SQLite/storage/session HTTP browser opt-in, including actual guest CRUD, server validation/focus, reload, totals/search, cancel/delete confirmations, final-person guard, fresh create-form defaults, 250-character names, 1440/1024/390px light/dark containment, trapped keyboard focus, Escape and focus restoration. Existing publishing/photo/renderer browser checks pass. No new dependency or invitation styling change.
- [x] TypeScript, ESLint, targeted Prettier, Pint and production build pass. Scoped Horizon eval: 72/76 = 94.7%; all eight applicable hard rules pass. Existing fallback font accounts for four withheld points; unchanged navigation/theme preferences/login, texture and church-specific content are outside the scope. Temporary `rsvp-guests-*.png` screenshots were inspected at desktop and mobile, including dark modal and empty states.
- [x] Owner manually verifies and accepts Milestone 4A, confirmed in the explicit authorization to proceed with 4B. Outstanding MySQL concurrency checks remain unverified.
- [ ] MySQL simultaneous membership changes have not been exercised; the six pre-existing opt-in draft/photo/publication cases remain pending on an approved disposable database. SQLite success and development schema verification are not MySQL concurrency evidence.
- [x] Reconsider deletion for 5A: responded guests are protected by model guard and restrictive response FK; issued groups are retained. Pending members remain removable subject to nonempty-group rules. Future delivery/history/archive/wedding deletion requires separate retention decisions.

### Milestone 4B — Secure personalized invitation links (D-015)

**Owner manually accepted on 2026-10-16:** explicitly confirmed in the Milestone 5A request. Outstanding MySQL and deployment logging verification remains separate.

- [x] Preserve all uncommitted 4A work and record its owner acceptance. Add only hash/ciphertext/version columns to invitation groups; review target/MySQL DDL, apply the additive migration normally and verify its unique hash index through Boost. Existing groups default to not generated; no customer links were issued by Codex.
- [x] Generate 32-byte random tokens, store SHA-256 lookup hashes plus Laravel-encrypted recoverable TEXT, hide both fields from serialization and reject customer field injection. Explicit owner-scoped CSRF-protected JSON actions return no-store URLs; ordinary list props expose status/version only. No session flash, remembered form, delivery state, expiry, analytics or new dependency.
- [x] Lock the same parent group and check the expected version for every action. Fresh repeated generation/copy returns the same link; stale requests fail without changes. Revoke clears ciphertext/hash, regeneration replaces them, and the monotonic counter records prior issuance. Recovering unreadable ciphertext gives a safe error and regeneration can replace it. Rollback retains prior valid state on failure.
- [x] Anonymous stateless `/invite/{token}` resolves only its group and the latest supported published snapshot through shared `PublishedWedding`. Saved label/member changes are intentionally live; wedding drafts/facts remain private until publication. Greeting is outside the unchanged Modern Classic Vue renderer. Unpublished, revoked, malformed and invalid links return generic 404s; photo visibility retains existing publication rules.
- [x] Protect group hard deletion after any issuance, even after revocation, under the existing group lock/model guard. Preserve never-issued deletion and existing member CRUD. Explain revocation, current saved-name visibility and manual sharing in the link dialog; no RSVP is inferred.
- [x] Apply private/no-store, no-referrer, noindex/nofollow/noarchive, generic personalized metadata and IP throttling at 60 requests/minute. Remove session/cookie/Inertia/CSRF-cookie middleware only from the read-only personalized route; private POST actions retain normal CSRF. Public failures omit debug details. Standard application log channels redact invitation paths and long hexadecimal credentials; public exception reporting sanitizes details before logging, including fallback paths. PHP/web-server/proxy/analytics logging remains separately controlled.
- [x] Focused invitation suite: 22 tests / 303 assertions pass. Full isolated Laravel suite: 197 tests / 1,729 assertions pass; eight opt-in MySQL cases skip. Coverage includes encrypted/hash association, authorized repeat recovery, stale lifecycle operations, revocation/rotation, tenant and payload boundaries, live saved names, snapshot isolation/republish/unpublish, contacts/metadata/XSS/session privacy, photo rules, CSRF, rate limits, rollback, decryption recovery and application log redaction.
- [x] Frontend/browser verification uses only isolated SQLite/storage/sessions and synthetic accounts. The new link interaction test verifies copy/fallback/reopen, anonymous and no-JavaScript rendering, current saved names, stale-window errors, cancellation/rotation/revocation/regeneration, deletion menu protection, publication gating and no page errors. Existing renderer/photo/publication/guest tests are also run; final combined result is recorded in handoff.md.
- [x] TypeScript, ESLint, targeted Prettier, Pint, worker PHP syntax and production build pass. Horizon scoped review: 62/66 = 93.9%, all eight applicable hard rules pass. Existing font fallback accounts for four withheld points; unchanged header width/navigation/empty state/preferences/login/texture and church-specific content are N/A. 1440/1024/390px light/dark dialogs, containment, keyboard trapping/Escape/focus restoration and anonymous mobile greeting were inspected from temporary screenshots. Owner subsequently accepted 4B in the 5A request.
- [ ] Run original six MySQL draft/photo/publication cases plus two new simultaneous link-generation/regeneration cases on an approved disposable target with latest migrations and INNODB_TRX access. Both workers must wait on the group lock before release; worker output omits recovered URLs. No target was provisioned/reset and no actual MySQL concurrency pass is claimed. Additional guest capacity/final-person and delete-versus-issuance races remain unverified against MySQL.
- [x] Owner manually tests and accepts Milestone 4B, explicitly confirmed in the authorization to proceed with 5A. No additional test-count total is inferred.
- [ ] Deployment prerequisite: HTTPS and confirmed redaction/exclusion of `/invite/*` in PHP/web-server/proxy/access/monitoring logs, with no capture of recovery response bodies. Normal `artisan serve` still prints bearer paths; Laravel redaction does not control those streams. External social preview services holding a link can read permitted names despite generic metadata. No server infrastructure/vendor/PHP configuration was modified to hide logs.

### Local dashboard cookie-path follow-up (2026-10-16)

- [x] Reproduce the dashboard HTTP 500 and identify inherited process `SESSION_PATH=C:/Program Files/Git/` overriding `.env`'s correct `/`. Restart only the affected project server with process-only `SESSION_PATH=/`; preserve application logic, data, upload settings and uncommitted 4A/4B work. Document the corrected PowerShell startup command in README.
- [x] Real HTTP: signed-out dashboard redirects to login (302), login returns 200, and both set cookies with `Path=/`. Isolated dashboard/authentication/development-server tests pass: seven tests / 14 assertions, including authenticated dashboard access and the existing real multipart upload check.
- [ ] Owner explicitly confirms the dashboard follow-up reload. Milestone 4B was subsequently accepted in the 5A request; existing MySQL concurrency/security findings remain pending.

### Milestone 5A — Guest RSVP submission (D-016)

- [x] Record 4B owner acceptance and preserve all uncommitted work. Add unique guest-linked relational responses (attending/declined, response timestamp, restrictive deletion FK) and a group RSVP/roster version. Inspect the development target and MySQL DDL before the normal additive migration; verify schema through Boost. No development customer responses were created.
- [x] Read/write only through active bearer links to supported published weddings. Transaction order wedding → group → ordered members revalidates token/publication/membership, applies a whole group's changes atomically and preserves omitted answers. Identical retries are no-ops; stale changed submissions fail without overwriting data. Token-keyed opaque guest references avoid exposing database IDs.
- [x] Keep personalized GET stateless; add a token-free same-origin CSRF setup endpoint and a normal CSRF-protected JSON POST. Strict allowlists reject unsupported states, duplicate/foreign/unknown references, manipulated fields and excessive lists. Private/no-store, no-referrer, noindex and generic errors cover setup/submission failures; throttle setup and submissions independently. Existing external access-log protections remain deployment requirements.
- [x] Add a separate responsive RSVP section below the unchanged Modern Classic renderer, only on personalized invitations. Saved states, partial changes, success/error feedback and reload after stale rejection are supported. Static HTML shows saved states; saving requires JavaScript. No attendance dashboard or new dependency.
- [x] Renaming retains responses; added people start pending. Model and database guards prevent deleting responded guests. Portal removal is disabled with an explanation; pending removal/nonempty-group and issued-group protections remain.
- [x] Initial 5A implementation verification (2026-10-16): 34 focused tests / 295 assertions; full isolated Laravel suite 231 passed / 2,024 assertions, eight existing opt-in MySQL tests skipped. Coverage includes lifecycle/publication/tenant boundaries, validation/privacy, partial persistence/updates, idempotency/stale rejection, atomic failure, deletion/rename safeguards, CSRF/session history and rate limits. Final verification is recorded in handoff.md.
- [x] All ten frontend tests pass, including the full isolated HTTP RSVP flow, real CSRF rejection, same-version simultaneous requests (one 200/one 422), stale-window feedback and responded-person removal guards. 1440/1024/390px RSVP, light/dark portal, visible focus, keyboard and containment checks pass; temporary screenshots inspected. TypeScript, ESLint, Prettier, Pint, production build and diff checks pass. Horizon scoped portal eval: 62/66 = 93.9%, all applicable hard rules pass; existing font fallback noted in handoff.md.
- [ ] Owner manually accepts 5A after checking partial attendance, reload/update, stale windows, invalid/revoked/unpublished access, mobile/keyboard and retention. 4B acceptance does not imply 5A acceptance.
- [ ] MySQL RSVP-versus-RSVP, token rotation/revocation, unpublication and member-removal races remain unverified. The original six draft/photo/publication plus two link concurrency cases and additional guest races remain pending; SQLite and single-process HTTP do not prove InnoDB behavior.

### 5A RSVP rate-limit owner finding — RF-001 (2026-10-18)

- [x] Reproduce actual local CSRF 200 → RSVP 429 with limit 20/remaining 0 and Retry-After 5461 seconds. Diagnose a future-dated development file-cache counter from tests plus restrictive IP-wide sharing. Confirm setup/submission buckets are distinct and frontend has no automatic retry.
- [x] Isolate the 20/minute allowance by hashed invitation, retain a separate 120/minute IP abuse guard and independent setup throttle, and surface the server retry interval for both setup/submission failures. Preserve all RSVP integrity/privacy rules.
- [x] Force array caching through both PHPUnit environment/server adapters; test with inherited file caching under temporary storage. Remove only four diagnosed legacy localhost cache entries, without flushing the application cache or changing customer data.
- [x] Laravel: 237 passed / 2,283 assertions, eight existing MySQL skips. Focused RSVP: 40 passed / 554 assertions, including group separation, setup allowance, expiry/retry, broad/per-link abuse and saved-state protection. Persistent-file-cache real HTTP browser test passes: one POST under duplicate submit events, helpful setup 429, normal saves, genuine 429 and another group unaffected.
- [x] All ten frontend tests pass on isolated HTTP with persistent file cache, including existing photo/publication/guest regressions; TypeScript, ESLint, Prettier, Pint, production build and diff checks pass.
- [ ] Owner retests RF-001 and explicitly accepts 5A. **5A remains blocked pending that confirmation.**
- [x] Create [risks_and_followups.md](risks_and_followups.md) as the structured issue register, link it from handoff/documentation map, and add narrow review/maintenance rules in AGENTS.md. Accepted limitations and unverified risks remain distinct from confirmed bugs.

### Milestone 5B and later — Attendance tracking

- [x] Scope and implement named guests and invitation groups under Milestone 4A; anonymous plus-ones remain unsupported.
- [ ] Scope automated delivery, plus-one policy, deadlines and attendance management separately. Personalized access and bounded response editing are implemented in 4B/5A.
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

- [risks_and_followups.md](risks_and_followups.md) owns the issue inventory and proposed actions. RF-001/RF-017 block 5A owner acceptance pending retest; RF-002/RF-003 retain eight skipped MySQL cases and additional unverified races. RF-008/RF-009 remain unresolved pre-distribution logging/credential findings. Other MVP, retention, environment and maintenance items are tracked there rather than duplicated here.
- Checkpoint: publishing is in `bb0c3b3`; accepted 4A/4B and pending-owner-acceptance 5A/fix work remain uncommitted. Existing work is preserved; no staging, commit or push by Codex.

## Milestone close-out rule

A task is implemented when code is in place; verified when required checks were actually run; approved only after code review, manual QA where applicable, and explicit owner sign-off. Never collapse these stages into one checkbox.
