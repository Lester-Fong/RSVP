# CMS-INTEGRATION — authenticated wedding workflow

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
