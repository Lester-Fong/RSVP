# CMS-INTEGRATION — authenticated wedding workflow

## ONB-01 — Single-wedding onboarding (2026-10-26)

**OWNER-ACCEPTED under D-065/D-066 (2026-10-26):** the owner confirms mandatory onboarding, single-workspace/wedding enforcement, congratulations, returning and existing customer behavior, second-wedding denial and CMS integration. P-009's bounded onboarding direction is approved: one couple/one ordinary owner workspace/one wedding. This section supersedes the earlier creation/dashboard walkthrough below, not its CMS/privacy/immutable-publication contracts. The separate CMS-UX-01 acceptance is recorded below; the complete DEL-10.3 customer journey remains open.

### Enforcement and compatibility

`CustomerWorkspace` owns both creation paths. A transaction locks the current user, then all owned accounts in ID order and their weddings using current locking reads; retry deadlocks up to three attempts. An empty owned account is reused; no owned account creates one account/membership/wedding together. A first-route retry returns the existing sole wedding without mutation or congratulations. An account-route second POST returns 409; its old create GET returns 403. Multiple legacy accounts/weddings return 409 for creation and show a review dashboard with every authorized existing wedding linked. The generated workspace title is bounded to its existing database column even for a valid 255-character registration name; the customer name remains unchanged. No arbitrary oldest-account choice, delete, merge, transfer, archive or replacement. Other-account/non-owner access remains denied; scoped route binding, validated fields, existing publications and memberships remain intact.

`/dashboard` requires onboarding when no owned wedding exists. `/onboarding` is a dedicated responsive page reusing the existing six-field wedding form: only both names are required. Existing single-wedding customers go directly to their authorized overview/dashboard, which already exposes details, Invitation/CMS, saved private preview, Publish/Republish, Guests and Attendance. Optional details remain editable. Authentication essentials and public/personal routes remain accessible outside the onboarding guard. Existing `verified` middleware is retained; `User` currently does not implement `MustVerifyEmail`, so ONB-01 does not newly activate email verification.

Only a newly committed creation produces a session-locked, consumed flash receipt. The congratulations page reads saved names, offers immediate keyboard/mobile dashboard entry and uses one small CSS arrival animation; reduced motion/storage failure stays static. The opaque sessionStorage receipt marker prevents replay through restored client history. Reloads, duplicate/failed creation and returning logins do not produce a new celebration. Session cache must support atomic locks and sessions must not use the cookie driver; existing file/database/array infrastructure supports this boundary. Database row locks, not the browser/session lock, enforce creation across different sessions.

**Schema/data:** no migration, uniqueness index, backfill or normal MySQL connection. Physical `HasMany` relations are preserved. Historical pre-owner ONB-01 read-only retained SQLite inventory: **4 users / 4 accounts / 4 weddings**, zero owners with multiple accounts and zero accounts with multiple weddings. Its full database SHA-256 remained `C351A57605F7DF16D3E289091942893834DC900615A9786727CAA0895C876772` throughout automated verification. Legacy fixtures intentionally contain duplicate cardinalities; normal deployment/MySQL data has not been inspected here. A blanket `UNIQUE(account_id)`/owner uniqueness cannot safely accommodate all supported historical records. Before any future constraint: read-only per-target inventory and backup, explicit owner disposition for violations, disposable InnoDB migration/rollback rehearsal, then separately approved application. Never conditionally skip constraints silently or delete/reassign history. All HTTP creation paths use the locked service; future operator/import paths must enforce their separately approved rules.

### Automated evidence and limits

- `php vendor/bin/phpunit --no-progress tests/Feature/CustomerOnboardingTest.php tests/Feature/Weddings/WeddingManagementTest.php tests/Feature/CmsCustomerWorkflowTest.php tests/Feature/DashboardTest.php`: **39 passed / 482 assertions**. Includes registration/mandatory routing, required/optional fields, empty-workspace reuse, stale retries/shared owners, exact cardinality, crafted IDs/status/slug, second-route rejection, foreign/non-owner denial, legacy review/access and transaction rollback with no orphan membership/account/wedding. Existing stale Classic/v2 first-save checks use separate customers rather than illegally creating a second wedding; their conflict/privacy assertions remain enabled.
- Full `php vendor/bin/phpunit --no-progress --log-junit=<isolated-runtime>/onboarding-final.xml`, canonical process-only SQLite overrides, approved PHP scan directory, unique storage/config/routes and test key: **786 cases / 5,142 assertions: 741 passed, zero failures/errors, 45 skipped InnoDB opt-ins**. The guard/bootstrap, existing CMS/publishing, personal RSVP, guest/attendance and security tests remain intact. No genuine new InnoDB execution is claimed.
- Existing browser dependencies/Edge; separate disposable SQLite hosts, never port 8000: the customer/CMS/published-personal workflow passes; all **21 Photobooth cases** pass after updating login navigation to the single-wedding destination (visual/readiness assertions unchanged). Three new onboarding cases cover desktop/mobile, normal/reduced motion, optional/complete facts, keyboard entry, refresh/Back/Forward/returning login, blocked old routes and overlapping first/retry HTTP requests. The existing withdrawal/attendance/RSVP browser flow also passes. **26 distinct browser cases passed, zero final failures/skips.** Overlapping requests against a single-process SQLite host establish endpoint idempotency, not independent InnoDB lock schedules.
- At the pre-publication ONB-01 checkpoint, `npm run typecheck`, fixture `vue-tsc --noEmit -p tests/Frontend/fixtures/tsconfig.json`, `npx eslint . --max-warnings=0`, `npm run format:check`, scoped/dirty Pint, the existing five-file Node contract/renderer selection (**15 passed / zero skips**) and `npm run build` (**2,126 modules**) passed. Other affected legacy/browser files received only registration/login/creation-navigation expectations and long-name containment adaptation; their broader opt-in suites were not claimed as executed. The later combined GitHub checkpoint is recorded in the CMS-UX-01 section below.

Browser commands (existing QA_PLAYWRIGHT_MODULE and QA_BROWSER_EXECUTABLE, no installs): `node --test tests/Frontend/CustomerOnboarding.browser.test.mjs`, the existing `CmsCustomerWorkflow.browser.test.mjs` one-case journey, the full `PhotoboothOpening.browser.test.mjs` 21-case file, and `GuestWithdrawal.browser.test.mjs`. Several were run in serial combined selections; report 26 distinct passes, not a sum of repeated runs. The final three-case onboarding file was rerun after adding explicit dashboard reload and Back/Forward checks.

Initial harness failures were diagnosed, not hidden: the new animation assertion needed Vue's scoped keyframe suffix; the Photobooth login setup still waited for `/dashboard` instead of its new canonical wedding destination. Corrected runs pass without changing invitation visuals or skipping cases. Final desktop/mobile onboarding and congratulations screenshots/videos: `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-GrxMoT/qa-onboarding` and `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-Jramwy/qa-onboarding`. Existing CMS and 21-case Photobooth evidence: `rsvp-cms-editor-8MQPCb/qa-customer-integration` and `rsvp-cms-editor-kZeuoN/qa-photobooth` under the same temporary root. Evidence is local/untracked; it is not customer media or a release artifact.

### Port-8000 owner acceptance

The existing port-8000 listener is verified as this repository's `public`/`cms-editor-router.php` with the retained synthetic SQLite profile; `/up` returns 200. `dev.cmd` correctly refused a second listener; no unknown process was killed or alternate owner port used. Reuse the running server, or run `dev.cmd` after it has been deliberately stopped. It resumes existing data/keys; no initialization/migration is needed for ONB-01.

1. Open **http://127.0.0.1:8000/register** in a private window. Use a clearly identified `ONB owner QA` name, a new QA email and a password you choose. Do not use a normal MySQL profile. Existing verification requirements apply; this retained profile does not currently require verification.
2. Confirm automatic arrival at `/onboarding`, without a dashboard collection or modal. Try missing names; check required-field feedback. Enter both names and either leave all optional details blank or supply date/time/venues.
3. Submit once. Confirm a saved-name congratulations page, subtle arrival (static under reduced motion), and an obvious keyboard/touch **Enter your wedding dashboard** action.
4. Enter the single wedding dashboard. Check details, Invitation/CMS, Guests and Attendance. No second-wedding action is present. Optional facts can be edited through More → Edit wedding details.
5. Refresh, use Back/Forward, close/reopen the browser and sign in again. Confirm the same wedding dashboard and no new onboarding/celebration. `/onboarding/congratulations` alone must not replay success.
6. Visit `/weddings/create`: an existing single-wedding owner returns to their dashboard. Using your own account ID from the address, `/accounts/{account}/weddings/create` must deny a second creation. Existing-account crafted POST is 409 (valid CSRF required); retrying `/weddings` reuses the existing wedding without changing facts or celebrating. Automated tests exercise those POSTs; do not copy credentials or disable CSRF.
7. Open Invitation, customize the existing gated Enchanted CMS, Save, reopen the private saved preview and explicitly Publish. Confirm the displayed public URL shows the saved snapshot; subsequent edits/Save stay private until Republish. Test an authorized personal guest link separately. No production feature activation is included.
8. Sign in to an existing retained one-wedding customer: confirm direct dashboard, unchanged Classic or Enchanted content, saved history and public/personal links. Historical multi-record review/access is covered by isolated PHP fixtures; do not manufacture duplicates or delete/reassign retained records for manual QA.
9. **Completed by owner:** QA-ONB-04 is accepted under D-065/D-066. Remaining independent-process InnoDB schedules (same-user/different sessions, shared owners, both creation endpoints and profile deletion), normal-MySQL evidence and existing DEL-05/complete-CMS/security/deployment release gates remain open before final release acceptance. The separate CMS-UX-01 visual check is also accepted under D-066.

## CMS-UX-01 — Full-width compact editor (2026-10-26)

**Separate owner request / D-065; OWNER-ACCEPTED under D-066 (2026-10-26).** The owner approves the final screenshot, editor-only full-width shell, balanced desktop 100% zoom, independent scrolling, Save/Publish, browser and in-editor Back warnings, and mobile/zoom checks. No further CMS editor change is requested. This editor refinement does not reopen accepted onboarding or invitation design.

**Published checkpoint:** while closeout review was in progress, `d7ef078` was committed/pushed as one mixed ONB-01/CMS-UX-01 change. Draft PR #1 targets `master` and includes it. Actual GitHub Linux runs at this SHA: [PR tests](https://github.com/Lester-Fong/RSVP/actions/runs/40290664618), [PR quality](https://github.com/Lester-Fong/RSVP/actions/runs/40290664613), [integration push tests](https://github.com/Lester-Fong/RSVP/actions/runs/40290659916), [integration push quality](https://github.com/Lester-Fong/RSVP/actions/runs/40290659978) all completed successfully. Both test jobs ran the guarded SQLite suite (**786 cases / 5,146 assertions / 741 passed / zero failures or errors / 45 skipped opt-in InnoDB cases**), build (**2,127 modules**) and **15/15 Node cases**. Both quality jobs passed check-only PHP formatting, app/fixture types, ESLint, Prettier and tracked-file cleanliness. This checkpoint does not verify the skipped InnoDB schedules or owner DEL-10.3 release gates.

### Layout and navigation

Only CmsEditorPreview uses CmsEditorLayout, a full-width main landmark without AppLayout/sidebar/header/toggle. Other authenticated pages keep their sidebar. Compact heading/status and section navigation span the workspace above a clamped 340–400px controls column and remaining-width live preview. Desktop controls and the real invitation iframe scroll independently; mobile/tablet retain Edit sections / View website preview with document scrolling. Short desktop windows also use document flow. Inputs below the desktop breakpoint use 16px text; touch controls retain 44px targets. No sticky cover, CSS zoom, transforms or invitation renderer changes.

The native Back to wedding link, beforeunload and Inertia before guards remain. Inertia history popstate cannot be canceled; both ordinary entry paths (Invitation tab and Edit wedding website) now use document navigation so browser Back reaches the existing unsaved guard. Other workspace tabs retain Inertia navigation. Dirty-state, conflicts, Save/Publish, authorization, backend, data/schema and production gates are unchanged.

### Current verification

- Guarded in-memory SQLite: `php vendor/bin/phpunit --no-progress --filter='CustomerOnboardingTest|WeddingManagementTest|DashboardTest|CmsCustomerWorkflowTest|CmsEditorPreviewTest|CmsV2PublishingTest|WeddingPublicationTest|WeddingImageUploadTest'` — **125 passed / 1,161 assertions**, zero failures/skips. Used the canonical process-only prefix/bootstrap from the existing environment guide; no normal MySQL.
- `node --test tests/Frontend/CmsWorkspace.browser.test.mjs` — **2 passed**, zero failures/skips: five viewport layouts, sidebar isolation, native Back/link cancellation preserving edits, Save/private saved preview/Publish, image-picker keyboard/focus, mobile switching and genuine 200% browser zoom. Zoom uses only a disposable Edge profile preference: 1440 physical pixels → 720 CSS pixels / DPR 2. Application styles do not change. Final zoom screenshots use the browser protocol to avoid Playwright full-page capture clipping under native zoom.
- `node --test --test-concurrency=1 tests/Frontend/CustomerOnboarding.browser.test.mjs tests/Frontend/CmsCustomerWorkflow.browser.test.mjs` — **4 passed**, zero failures/skips, including fresh registration/CMS uploads, immutable publication/personal RSVP and all three onboarding cases.
- Existing full browser regressions: **CmsEditor 39/39**, **CmsPolish 7/7**, **CmsPublication 5/5**, **PhotoboothOpening 21/21**. Together with the 2 workspace and 4 onboarding/customer cases above: **78 distinct passes, zero failures/skips**. Each opt-in file ran with its required fixture environment. The publication fixture's optional personal-only branch is not claimed from that file; the separate full customer journey and Photobooth suite exercise personal entry.
- Full-suite logs/results are retained under the temporary root: `rsvp-cms-editor-f8sLw5` (editor), `rsvp-cms-editor-QDCAUr` (polish), `rsvp-cms-editor-AG4Nzn` (publication), each with `cms-ux-regression.log` / `cms-ux-result.json`. Photobooth screenshots/motion: `rsvp-cms-editor-RnCH5U/qa-photobooth`; fresh customer evidence: `rsvp-cms-editor-e1Y19K/qa-customer-integration`. Final workspace evidence adds the short-window 1366×500 fallback.
- `npm.cmd run typecheck`, fixture `vue-tsc --noEmit -p tests/Frontend/fixtures/tsconfig.json`, `npx.cmd eslint . --max-warnings=0`, `npm.cmd run format:check` and production build pass. The 15 existing Photobooth/Classic/Countdown/Guest Guide/CMS-v2 Node contracts pass. No PHP changes in CMS-UX-01; the earlier full ONB suite remains dated evidence: 741 passed / 45 InnoDB skips, not new engine proof.

Live iframe dimensions at genuine 100% zoom (CSS pixels):

| Viewport | Before | After | Controls after |
| --- | --- | --- | --- |
| 1366 × 768 | 632 × 427 | 949 × 546 | 369 |
| 1440 × 900 | 706 × 559 | 1003 × 678 | 389 |
| 1920 × 1080 | 1130 × 739 | 1472 × 858 | 400 |

Before screenshots/metrics: `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-lL2NrB/cms-ux-before`.
After screenshots/metrics: `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-7cd8LA/cms-ux-after`.
Both include 1366×768, 1440×900, 1920×1080, tablet 820×1180 and mobile 390×844, plus tablet/mobile preview switching. After includes saved/published controls at 1366×768. Final 200% artifacts: `rsvp-cms-editor-eNFGTd/cms-ux-zoom` under the same temporary root. Local synthetic artifacts remain untracked, with no customer media or credentials.

Broader-suite initial results were 39/39 editor, 4/7 polish and 2/5 publication. The six older public checks assumed entry without opening the accepted Photobooth; they now explicitly select Open Invitation. Two ordinary website-loader checks additionally disable the optional intro through the real CMS before saving/publishing their disposable fixture: the accepted Photobooth Open deliberately bypasses network gates, while the non-intro loader still waits for images/fonts. Original loader, publication, privacy and accessibility assertions remain. The final complete 7/7 and 5/5 reruns pass; the unchanged 21-case Photobooth suite independently proves immediate Open/Skip and readiness. No invitation code was edited.

Other initial harness issues were corrected: canceled browser Back intentionally produces no load event, so the test verifies the native dialog, cancellation and retained field/URL; the saved-story check now supplies real paragraph content because empty stories are omitted. The existing pane regression now locates shared navigation in the workspace rather than its former controls column. No publication/privacy/authorization assertions were removed.

### Changed files and delivery boundary

CMS-UX-01 UI files: `resources/js/layouts/CmsEditorLayout.vue` (new), `resources/js/pages/weddings/CmsEditorPreview.vue`, `resources/css/cms-editor.css`, `resources/js/pages/weddings/Show.vue`, `resources/js/components/wedding/WeddingNavigation.vue`. Browser support: `tests/Frontend/CmsWorkspace.browser.test.mjs` (new), `CmsEditor.browser.test.mjs`, `CmsPolish.browser.test.mjs`, `CmsPublication.browser.test.mjs`. Existing ONB-01 changes, including the earlier login-route adaptations in those tests, are preserved. No backend/schema/dependency or global AppLayout/AppSidebar/CmsPreviewFrame/invitation-renderer edits in this refinement.

D-065 implementation results were synchronized in the existing documentation before owner review. That pre-acceptance checkpoint had an empty staged diff/deletion list and no GitHub execution; the later D-066 owner acceptance and publication evidence supersede only those dated status statements. Protected ONB-01 tracked implementation fingerprints matched, build transformed 2,127 modules and local synthetic visual artifacts stayed untracked. No competing plan or generated evidence was added to Git.

The retained preview was resumed with dev.cmd on port 8000, without initialization/migrations. Its SQLite SHA-256 stayed `25737649F608D98634F71667AC91985A01072A6F34A467D4CBF53265DA6055B7` before/after this resume and verification; the earlier ONB hash above is historical before owner edits. No browser automation wrote fixtures into that preview.

### Final port-8000 visual acceptance

Resume the retained synthetic preview with dev.cmd, preserving its existing data/keys; no initialization, migration or fixture-writing tests against it. Open **http://127.0.0.1:8000**, sign into your existing QA customer, then Invitation / Edit wedding website.

1. At 100% zoom, review 1366×768, 1440×900 and 1920×1080: full-width editor, compact section navigation, useful preview, visible Back/Save/Publish, reachable control-pane bottom and independent invitation scroll. Return to the wedding and confirm its sidebar remains.
2. Make an unsaved edit; cancel both Back to wedding and browser Back warnings and confirm the edit survives. Save, review the private saved draft and explicitly Publish/Republish only if desired using QA content.
3. Check Gallery/image picker and keyboard focus/Escape; tablet/mobile Edit/Preview switching, readable inputs and no horizontal overflow. At 200% zoom, use normal document scrolling to reach all controls.
4. **Completed by owner:** QA-CMSUX-02 passes under D-066. The final screenshot and 100% desktop balance, independent scrolling, Save/Publish, native Back warnings, mobile/responsive and zoom checks are accepted. ONB-01 is independently accepted; DEL-10.3's complete journey and engine/security/release gates remain open.

## Earlier CMS-INTEGRATION delivery record

2026-10-25 / D-057. Implementation delivered for owner end-to-end acceptance; production activation remains denied. DEL-07b remains owner-accepted; DEL-05a/b remains implemented, awaiting visual acceptance.

## Cause and bounded resolution

Registration already creates a real user. First-wedding creation already creates the owner's workspace/membership and wedding in the existing transaction. Invitation already redirects saved Enchanted v2 drafts into the complete CMS, but weddings without a draft fell back to Modern Classic. This was a dispatch gap, not missing CMS functionality.

The existing `CmsEditorPreview::shouldOpen` dispatcher now also selects Enchanted for an unconfigured wedding: no draft, no active publication and no revision history. It runs only with the existing local/testing editor gate. Existing saved v1/Modern Classic and revision history retain legacy behavior; unsupported documents retain existing rejection. No conversion, template switching, generated customer data, new API, routes, schema or publisher.

Opening the editor does not create a draft or revision. The existing starter uses actual relational wedding facts, nine sections (including Countdown and Guest Guide), empty Gallery/library/photo slots and no invented story. The first explicit Save persists v2, stable content IDs and the existing lock version. A competing legacy/v2 first-save tab cannot overwrite the selected template. Private preview requires a saved version; overview does not advertise a saved preview before Save. Existing validated private uploads, hidden-content retention, stale errors, dirty Publish protection and immutable publication remain shared.

The end-to-end test also found an optional-photo removal defect: Vue casts an omitted Boolean prop to false. `CmsImageField` now explicitly defaults `clearable` to true. Gallery still passes false and retains its own item-removal confirmation. Removing an assignment does not delete the uploaded asset or alter live content before republish.

## Local walkthrough and remaining owner action

All owner browser URLs use `http://127.0.0.1:8000`. From this repository run `dev.cmd` after reboot to resume the retained isolated profile. Never initialize it automatically or run fixture-writing tests against it. Current source is served through Vite internally on 5173; this is the same authenticated application and existing CMS, not a separate customer editor.

1. Open `/register` in a private window, register, and choose Create your first wedding. Alternatively sign in at `/login` with the account that owns wedding 3.
2. Create the wedding with its actual names/date/time/timezone/venues; from Dashboard open its Invitation tab. The supplied existing example is `/accounts/3/weddings/3/invitation/edit`, redirecting to `/accounts/3/weddings/3/cms-preview` when owned and unconfigured. Other accounts must receive denial.
3. Customize Welcome/photos, Countdown, Story milestones, Gallery, Entourage, Guest Guide, RSVP copy and Closing. Save Draft; refresh/reopen and check the saved preview. Unsaved edits cannot publish.
4. Explicitly Publish and open the wedding-specific public URL shown by the editor. Before publication it correctly returns 404. Editing/saving stays private; Republish updates the live snapshot. Create a guest through Guests and generate its personal invitation to test authorized RSVP; the generic page must contain no real form or private guest data.
5. Existing retained visual reference: `/w/synthetic-editor-preview`. It is not the newly registered customer's website. Do not copy its users/uploads into MySQL. Read-only inspection confirms wedding 3's public route is `/w/shelby-cotton-rowan-greer-u22ptwni`; it remains unpublished (404) until its owner saves and explicitly publishes. New weddings have their own generated public URL displayed in the editor.

The earlier statement that development MySQL was uninitialized is superseded by the 2026-10-25 test-runner incident: `wedding_rsvp` now has the 18 existing migrations recorded and 20 application tables, but zero rows across users, workspaces, weddings, guests, responses, uploads, cache and job tables. No application data existed in the pre-incident read-only inventory; no synthetic records were imported. Do not run migrations, rollback, seed, reset, repair or normal-MySQL registration against this schema until the owner reviews the incident. Preserve existing credentials/APP_KEY.

If the owner later approves normal MySQL use after reviewing the incident, keep `APP_ENV=local`, `APP_URL=http://127.0.0.1:8000`, the intended MySQL settings and enable only these existing local gates in `.env`:

```dotenv
CMS_EDITOR_PREVIEW_ENABLED=true
CMS_V2_PUBLISHING_ENABLED=true
```

Stop the owned `dev.cmd` services first, then run `php artisan config:clear` and use two terminals:

```powershell
php artisan serve --host=127.0.0.1 --port=8000 --tries=1
npm.cmd run dev -- --host=127.0.0.1 --port=5173 --strictPort
```

These are alternative profiles, never concurrent port-8000 servers. Do not kill an unidentified listener or fall back to random ports. Normal MySQL login/registration cannot be claimed verified until initialization is approved and performed; the isolated real registration workflow is verified separately.

## Verification and evidence

The QA register's QA-CMS-15 owns owner acceptance; QA-DEV-02 now tracks owner review of the accidental empty-schema initialization and later normal login verification. `dev.cmd` serves the retained synthetic SQLite profile; it is separate from the `.env` MySQL profile. Exact current execution results and artifacts are appended below. Existing production hard denial, composite InnoDB races, deployed privacy/cache/ACL/log/key/retention/rights/device/performance gates remain independent; no new cross-engine evidence is inferred from SQLite.

Affected implementation files:

- `app/Services/CmsEditorPreview.php`
- `app/Http/Controllers/WeddingInvitationController.php`
- `app/Http/Controllers/WeddingController.php`
- `resources/js/pages/weddings/Show.vue`
- `resources/js/pages/weddings/CmsEditorPreview.vue`
- `resources/js/components/cms/CmsImageField.vue`
- `tests/Feature/CmsCustomerWorkflowTest.php`
- `tests/Frontend/CmsCustomerWorkflow.browser.test.mjs`
- `tests/Frontend/CmsPublication.browser.test.mjs` (overview link label regression selector)

Documentation files changed: `docs/tasks/cms_customer_workflow_integration.md`, `docs/decisions.md`, `docs/architecture.md`, `docs/product_spec.md`, `docs/checklist.md`, `docs/handoff.md`, `docs/mvp_roadmap.md`, `docs/risks_and_followups.md`, `docs/qa/verification_register.md`, `docs/README.md`, `README.md`. No new dependency, development data operation, production activation, commit or push. Other pre-existing uncommitted/untracked work is preserved and is not part of this batch.

### Exact execution

PHP tests use process-only isolated SQLite (`DB_DATABASE=:memory:`, `APP_CONFIG_CACHE` set to `%TEMP%/rsvp-dev-env-phpunit-config.php`, `SESSION_PATH=/`, `PHP_INI_SCAN_DIR=%TEMP%/rsvp-cms-editor-LrKACO/ini`), never development MySQL:

```powershell
php artisan test --compact tests/Feature/CmsCustomerWorkflowTest.php tests/Feature/CmsEditorPreviewTest.php tests/Feature/CmsDraftTest.php tests/Feature/CmsPersistenceTest.php tests/Feature/CmsPublicationTest.php tests/Feature/CmsPersonalRsvpTest.php tests/Feature/CmsV2AvailabilityTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Weddings/WeddingManagementTest.php tests/Feature/Weddings/WeddingInvitationTest.php
```

**156 passed / 1,448 assertions, zero skipped.** Includes eight new real customer lifecycle/dispatch/authorization/template-race cases. No new schema or engine-dependent transaction code; no new InnoDB test executed. Existing uncompleted engine obligations stay open.

Installed Edge/Playwright are explicitly enabled with `QA_PLAYWRIGHT_MODULE=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/horizon-ministry-qa/node_modules/playwright-core/index.mjs` and `QA_BROWSER_EXECUTABLE=C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe`; no install:

```powershell
node --test tests/Frontend/CmsCustomerWorkflow.browser.test.mjs
node --test tests/Frontend/CmsGuestGuide.test.mjs tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/WeddingCountdown.test.mjs tests/Frontend/ModernClassicInvitation.test.mjs
node --test tests/Frontend/RomanticRsvp.browser.test.mjs tests/Frontend/StoryReveal.browser.test.mjs
```

New customer journey: **1 passed**, actual registration/authenticated workspace and wedding creation, desktop/mobile/reduced-motion editor, real processed uploads/replacement/removal, saved section/milestone/hidden Gallery data, private preview, dirty publish denial, live immutability, republish media denial/retention, no-JS public page and real guest link/attending submission/reload/private-data absence. Zero browser page errors. Contract/countdown/Guide/Classic selection: **13 passed**. Romantic RSVP/Story: **15 passed / 1 optional connected Story skip**; the connected case was then explicitly enabled and passed separately:

```powershell
$env:QA_CMS_EDITOR_INFO=Join-Path $env:TEMP 'rsvp-cms-editor-LrKACO/active-preview.json'
node --test --test-name-pattern='Port-8000' tests/Frontend/StoryReveal.browser.test.mjs
```

That read-only case verifies real retained published/personal/saved previews on 8000, one bird and moving wings. The first attempt mistakenly used historical `preview.json` (which still records the old port) and failed before navigation; corrected to the startup-owned `active-preview.json`. Do not rewrite historical credentials/manifests to fix this. Existing editor/publication regression results follow below.

The regression runner uses `createCmsEditorPreview({publishing:true})`, launches `node --test tests/Frontend/CmsEditor.browser.test.mjs` with `QA_CMS_EDITOR_INFO=join(host.root,'preview.json')`, awaits completion and closes that host. It then creates a **different fresh host** and runs `node --test tests/Frontend/CmsPublication.browser.test.mjs` the same way. Never use the retained active manifest for these fixture-writing suites. Exact orchestration (Node ES module, with the installed browser variables above):

**Final results: 39/39 editor and 5/5 publication cases pass**, zero final failures/skips. Publication suite was rerun after the link-label correction. Total distinct frontend/browser cases for this batch: **74 passed** (1 customer journey + 39 editor + 5 publication + 13 unit/contracts + 7 romantic RSVP + 8 isolated Story + 1 connected port-8000 Story). The initially skipped connected case is covered by its explicit rerun; no engine execution or owner acceptance is implied.

```js
import { createCmsEditorPreview } from './tests/Frontend/cms-editor-preview.mjs';
import { spawn } from 'node:child_process';
import { join } from 'node:path';
for (const file of ['CmsEditor.browser.test.mjs', 'CmsPublication.browser.test.mjs']) {
    const host = await createCmsEditorPreview({ publishing: true });
    try {
        const child = spawn(process.execPath, ['--test', 'tests/Frontend/' + file], {
            env: { ...process.env, QA_CMS_EDITOR_INFO: join(host.root, 'preview.json') },
            stdio: 'inherit', windowsHide: true,
        });
        const code = await new Promise(resolve => child.once('exit', resolve));
        if (code) { process.exitCode = code; break; }
    } finally { await host.close(); }
}
```

Quality commands: `npm.cmd run typecheck`; `node node_modules/vue-tsc/bin/vue-tsc.js --noEmit -p tests/Frontend/fixtures/tsconfig.json`; scoped ESLint/Prettier on the three changed Vue pages/components and new browser test; scoped `php vendor/bin/pint ... --dirty --format agent` on the four affected PHP files; `php -l` on those files; `npm.cmd run build` (**2,115 modules**); `git diff --check`. All pass. Final test-only screenshot-readiness waits were format-checked again. Build uses installed tooling, no temporary dependency workaround.

```powershell
node node_modules/eslint/bin/eslint.js resources/js/components/cms/CmsImageField.vue resources/js/pages/weddings/Show.vue resources/js/pages/weddings/CmsEditorPreview.vue tests/Frontend/CmsCustomerWorkflow.browser.test.mjs
node node_modules/prettier/bin/prettier.cjs --check resources/js/components/cms/CmsImageField.vue resources/js/pages/weddings/Show.vue resources/js/pages/weddings/CmsEditorPreview.vue tests/Frontend/CmsCustomerWorkflow.browser.test.mjs
php vendor/bin/pint app/Services/CmsEditorPreview.php app/Http/Controllers/WeddingInvitationController.php app/Http/Controllers/WeddingController.php tests/Feature/CmsCustomerWorkflowTest.php --dirty --format agent
php -l app/Services/CmsEditorPreview.php
php -l app/Http/Controllers/WeddingInvitationController.php
php -l app/Http/Controllers/WeddingController.php
php -l tests/Feature/CmsCustomerWorkflowTest.php
```

Read-only port-8000 HTTP checks: `/login` 200, `/register` 200, `/w/synthetic-editor-preview` 200, wedding 3 public route 404 (unpublished), and its unauthenticated Invitation route 302 to login. Connected authenticated saved/personal checks pass above; no ownership bypass was used to sign into account 3.

Initial browser test corrections: choose the first of two legitimate Create-wedding CTAs; reload before inspecting initial Inertia HTML props; wait for enabled upload input; explicitly select Welcome before editing; wait for live iframe readiness before capturing desktop evidence. The unavailable photo-removal button exposed and fixed the actual Boolean-default defect described above. An initial attempt to run two fixture-writing regression files against one shared disposable target was stopped; the final run gives each file a fresh target. The first separate publication run passed four cases but its immutable-publish case still looked for the old overview link label; update that exact selector to “Edit wedding website” and rerun on a fresh target. No retained/manual database was involved in those attempts.

### Review artifacts and safeguards

Actual new-customer desktop/mobile editor and published captures, personal RSVP confirmation and guest video: `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-XbNnXg/qa-customer-integration/`.

- `desktop-customer-editor.png`, `mobile-customer-editor.png`
- `desktop-customer-published.png`, `mobile-customer-published.png`
- `customer-personal-rsvp.png`, `customer-guest.webm`

Desktop editor capture was inspected after adding iframe-readiness waits; earlier premature blank-preview capture is superseded. Uploaded images are generated flat-color test files, not stock/default wedding photos. No bearer link, password or private guest props are copied into documentation.

Final read-only MySQL inspection still finds only `migrations`. Retained SQLite SHA-256 remains `af2693788265c44fa748de8720cd571b4aafecab6340e567adf8f29035b50733`. All five pre-task approved hummingbird SVG, loading CSS, waypoint helper and bird/loader component hashes match. No credentials/encryption key or production flags changed; no reset, seed, migration, commit or push. Physical-device/assistive/performance and owner acceptance remain separate from desktop Edge automation.
