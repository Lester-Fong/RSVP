# DEL-03a — persistent drafts and private preview

**Current DEL-04 / D-045:** DEL-03b.1 owner-accepted 14/14. Personalized Enchanted integration implemented/automatically verified; owner acceptance QA-UI-21 pending. [Current contract, exact verification and fresh owner/guest walkthrough](#11-del-04--personalized-enchanted-rsvp-integration). Earlier closeouts are historical.

**Latest DEL-03b.1 / D-044:** focused empty-photo, desktop navigation, direct saved-state editor Publish/Republish and loading-bird/Hero handoff implemented; owner review pending. [Exact behavior, changed files, verification and fresh empty review](#10-del-03b1--focused-preview-publishing-and-loader-polish).

**Latest: DEL-03a.2 owner-accepted (17/17); DEL-03b implemented and verified under D-043, awaiting its own owner acceptance.** [Current contract, exact verification, preview and DEL-04 boundaries](#9-del-03b--immutable-publishing-and-public-wedding-website). Earlier sections preserve historical evidence and restrictions at their execution dates.

Reviewed: **2026-10-22**. DEL-02e has passed owner acceptance, explicitly confirmed in the DEL-03a request. Its visual/editor acceptance does not approve full CMS-v2 publication or all premium-template/rights gates.

**Historical DEL-03a.2 closeout: final UX polish/waypoint foundation implemented under D-042; automated verification complete, owner acceptance pending.** [Current changes, exact verification, preview and remaining acceptance](#8-del-03a2--final-ux-polish-and-hummingbird-preparation). Sections 6–7 retain their original evidence.

**Historical DEL-03a status: DP-01–03 owner-approved under D-040; DEL-03a implemented and automatically verified. The owner confirms basic Save/reload/saved-preview tests passed. DEL-03a.1 polish under D-041 is implemented and pending owner review (Section 7).** Durable owner drafts and saved private preview remain inside the default-off local/testing boundary. Public CMS-v2 publication remains disabled. Development MySQL was neither migrated nor reset. Section 6 preserves original implementation evidence.

## 1. Historical pre-implementation findings against actual code

| Evidence                                                                                                                                                                                                                        | Consequence                                                                                                                                                                                                                                                                |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [website_drafts migration](../../database/migrations/2026_10_16_010000_create_website_drafts_table.php), [WebsiteDraft](../../app/Models/WebsiteDraft.php)                                                                      | One unique wedding draft, existing `schema_version`, `lock_version`, JSON content and template metadata. Reuse this row; do not create a parallel premium document/store. Model's current schema-v1 guard must remain valid for Modern Classic.                            |
| [WeddingInvitationController::update](../../app/Http/Controllers/WeddingInvitationController.php)                                                                                                                               | Existing wedding-first transaction, expected draft version (`0` before first save), counter increment and safe stale validation feedback can be reused. It intentionally accepts only schema-v1; removing that guard globally would break isolation between workflows.     |
| [CmsV2Contract](../../app/Services/CmsV2Contract.php), [CmsV2Document](../../app/Services/CmsV2Document.php)                                                                                                                    | Strict schema-v2 content has only `theme`, `experience`, `sections`; no durable hero/details/story-cover slot fields. Complete source is distinct from its visibility/capability projection. Gallery max 10, distinct references, all hidden content still validated.      |
| [CmsEditorPreviewController](../../app/Http/Controllers/CmsEditorPreviewController.php), [CmsEditorPreviewRequest](../../app/Http/Requests/CmsEditorPreviewRequest.php), [editor types](../../resources/js/types/cms-editor.ts) | Every editor visit receives a fresh starter document; `{document,slots}` is a validation-only envelope. Slots are separate local state, not a persisted document. There is no saved-draft adapter to connect unchanged.                                                    |
| [editorPresentation](../../resources/js/lib/cms-editor.ts), [Wedding](../../app/Models/Wedding.php), migrations                                                                                                                 | The preview hardcodes `Asia/Manila`; Wedding has date/time but no canonical timezone column. Persisting that fixture assumption or duplicating it in template JSON would violate the approved relational-facts direction.                                                  |
| [WeddingImageResolver](../../app/Services/WeddingImageResolver.php)                                                                                                                                                             | Existing owner authorization, owned bytes and complete-source reference checks are reusable. New durable slots must be included; pagination of the picker cannot determine which saved media is resolved on reload. Current helper reads do not lock assets by themselves. |
| [CreateWebsiteRevision](../../app/Services/CreateWebsiteRevision.php), [PublishedWedding](../../app/Services/PublishedWedding.php)                                                                                              | Internal revision creation already supports schema-v2 records, but active public dispatch remains v1. Do not assume there can be no durable v2 data merely because the editor never saved it. No revision creation/publication-pointer change belongs in DEL-03a.          |
| [config/cms_editor.php](../../config/cms_editor.php), [EnforceCmsEditorPreview](../../app/Http/Middleware/EnforceCmsEditorPreview.php)                                                                                          | Retain the explicit default-off, local/testing, owner-scoped boundary for incomplete premium integration. Missing required schema must fail closed. No production activation or normal development configuration change is needed.                                         |

These findings justified the original approval stop. D-040 subsequently approved the following minimal contracts; the historical table describes the state before this implementation, not outstanding approval blockers. [Current CMS architecture](../architecture.md) and [RF-026](../risks_and_followups.md#rf-026--cms-v2-schema-publication-and-media-privacy-boundaries) distinguish implemented private persistence from pending publication/security gates.

## 2. Minimal decisions — owner-approved DP-01–03 (D-040)

### DP-01 — shared durable photo slots

Recommend an optional **`content.photo_slots`** object within the existing schema-v2 JSON:

```text
photo_slots?: {
  hero: ImageReference | null,
  details: ImageReference | null,
  story: ImageReference | null
}
ImageReference = { asset_id, alt, focal, crop }
```

Use existing UUID/plain-text/bounded focal/crop validation. All three keys are required when the object exists; no path/disk/URL keys. New editor documents explicitly include the object. Old v2 documents without it remain valid and unchanged; presentation treats absence as no explicit assignment. Preserve existing Story milestone-photo fallback. Selecting/removing one slot never mutates another; missing images use the existing template fallbacks.

This is a proposed **additive optional v2 contract extension**, not a silent reinterpretation or automatic data conversion. A schema bump is unnecessary if the owner accepts that compatibility rule: old documents retain their exact source shape and all existing keys retain meaning. If schema-v2 is required to be permanently frozen, use a new document schema instead; that has broader manifest/reader/snapshot testing cost. Do not choose that policy silently.

Why: one complete source, reusable photo roles across templates and existing snapshot JSON; no extra SQL columns, duplicate slot store or Enchanted-specific form. Validate/resolve slots even when hidden or unsupported. Store only references/settings, never temporary preview URLs. The current top-level request `slots` becomes editor state mapped to this source, not a second durable representation.

### DP-02 — canonical wedding timezone

Approved additive **`weddings.timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Manila'`**, with server-validated supported IANA names. The migration is implemented and exercised only in isolated SQLite tests. Future application gives existing weddings that explicit default without changing date/time values; development MySQL application remains unauthorized in this batch.

Expose timezone through the canonical wedding-details workflow and display/reuse it in the CMS editor, rather than storing a second editable value in CMS JSON. Keep CMS draft save content-only; changing canonical facts remains a wedding operation. No per-template timezone field or new facts-version counter is proposed. Read fresh canonical facts for saved private preview; DEL-03b must snapshot timezone/date/time under the wedding lock and include them in the fingerprint.

An alternative is a nullable column requiring explicit timezone confirmation for existing weddings; safer for unknown historical geography but adds setup friction. Recommend the stated default for this Philippines-focused pre-launch product **only with approval**. Default does not mean silently accepting invalid zones. Missing date/time yields no invented instant/countdown; ambiguous/nonexistent local times must be rejected or left without a countdown rather than guessed. No migration execution against development is authorized here.

### DP-03 — existing Modern Classic draft coexistence

Recommend preserving the one-draft-per-wedding invariant and **refusing premium saves over an existing schema-v1 draft** with the existing controlled unsupported-schema 409 behavior. Existing v2 drafts and weddings with no draft can use DEL-03a. V1 editor/saves and published revisions remain operational for their existing records. Do not create a parallel draft table or silently overwrite/convert a Modern Classic draft/hero.

Trade-off: testing the premium persisted editor uses a synthetic wedding without a v1 draft; moving an existing Modern Classic wedding to premium requires a separately approved explicit conversion/template-switch workflow. If that transition is required in DEL-03a, approve its content/photo mapping first; it cannot be inferred safely from a request to preserve all existing content. A future conversion must retain published snapshots and private files; public v2 still waits for DEL-03b.

**Approved:** DP-01's optional v2 photo-slot extension, DP-02's relational timezone/default and DP-03's fail-closed v1 draft policy. The owner additionally requires blocking unresolved template transitions rather than discarding source, preserving current immutable/live snapshots, validating photo ownership and retaining optimistic concurrency. No parallel draft tables or template-specific forms are introduced.

## 3. Approved implementation contract

- Reuse the existing scoped editor/adapters, `website_drafts` row, template metadata and wedding policy. Keep the server boundary local/testing and off by default. Owner-only GET editor/retrieval, PUT draft save and GET saved private preview beneath the existing wedding-scoped prefix; exact route names follow sibling conventions. CSRF on saves, normal sessions, separate appropriate throttles and private/no-store/nosniff/no-referrer/noindex headers on success and errors.
- Save request allowlist: `{draft_version, document}`. All slots live in validated document content. Reject ownership IDs, arbitrary media URLs/paths, unknown keys and unsupported versions. Capture a cloned request source plus a local edit generation; edits made while a save is pending remain dirty after that save completes. No autosave/localStorage, background persistence or silent overwrite/reload.
- Reauthorize inside a transaction: **Wedding → current draft → referenced assets ordered by UUID**. Lock the wedding even when the draft does not exist, compare expected version and compatible schema/template, validate full source and private owned bytes, then insert/update the complete source and advance the existing draft counter once. Unique wedding FK is the first-save backstop. Reuse existing bounded transaction retry practice; do not introduce a second counter. Asset-pruning/publication writers must share the wedding-first contract before future activation.
- Every save uses the current expected draft version; stale saves return existing safe **422** semantics and preserve local edits. A repeated request after an uncertain response must not be silently replayed as a newer save: retrieve the authorized current version/source and offer explicit reload/reconciliation. No merge algorithm or force-save action in this batch. Rollback leaves both source and counter unchanged; counter overflow fails safely.
- Store full known validated content, including hidden/unsupported sections and media references. Never save the visible projection. Enforce gallery 10 max/distinct refs, all existing list IDs/order and bounds, introduction disabled state, crop/focus and separate slots. No guest identities/RSVP authority enter CMS.
- Reload resolves **all referenced owned assets**, including slots and references outside the first library page, through existing safe metadata/private-image adapters; never persist resolved URLs or private storage paths. Missing/foreign/corrupt assets fail safely with a repairable owner error, not fallback to another wedding's assets or loss of saved data. No cleanup/deletion job is introduced.
- Keep live in-tab preview for unsaved edits, clearly distinguished from saved private preview. Save feedback means confirmed server commit only. Standalone saved preview reads durable content and fresh canonical facts, is owner-authorized on every request and does not contain editor empty-state coaching. No public share token or unauthenticated preview route. Opening it must not save local edits.
- DEL-03a never creates a revision, changes `published_revision_id`/publication counters, reuses private URLs as public URLs or enables the Enchanted public manifest. Existing public/personal pages remain pinned to their existing immutable supported snapshot. Modern Classic's schema guard must not be globally relaxed just to load premium content.

## 4. Small verification batches

1. Approved contract/timezone foundation: shared PHP/TS fixtures for optional slots, exact old-v2 round trips, hidden-slot ownership, independent assignments, valid/invalid zone values, additive migration upgrade/default and v1 preservation. Run only on disposable migrated SQLite; identify MySQL-specific DDL checks separately. Do not apply development migrations.
2. Save/load/private-preview service and HTTP: first save/version zero, durable reload, full-source visibility/order/IDs/media preservation, gallery 10/11, safe missing schema, cross-account/wedding denial, CSRF, missing/foreign/private bytes, stale/invalid/rollback/counter behavior and private headers. Capture before/after immutable publication fingerprint/content/media/pointer to prove no changes. Include existing v1 guarded paths and RSVP/Attendance regressions.
3. Connected editor browser: actual authenticated upload → assign independent slots/milestone/gallery → Save → reload → saved private preview; edits during save, dirty/success/error states, two-tab stale rejection retaining edits, failed/uncertain saves, assets outside library page one, invalid/hidden sections, keyboard/mobile and unchanged split panes/design. No mocked-only proof of commit or authorization.
4. InnoDB schedules requiring independent workers/barriers: concurrent first save, competing same-version saves, save versus canonical wedding facts/timezone update, save versus future publication/media mutation/parent deletion. Add only implemented writer schedules; future writers remain Planned. Use an explicitly verified disposable target under separate engine execution authorization. Historical H23 does not verify these new schedules.

Select QA-CMS-01/02/05/08/09/10/11/12, QA-UI-19, QA-MY-24 and applicable QA-RACE rows. Split draft/save evidence from still-Planned DEL-03b publication checks rather than closing a composite gate prematurely. Permissions/data integrity/private-media tests precede affected activation. No tests ran at the original documentation-only stop; the subsequent approved implementation evidence is below.

## 5. DEL-03b prerequisites, deliberately not implemented here

Immutable v2 snapshot creation derived from complete validated source; deduplicated retention references (including hidden/history) versus a separate current-visible public-delivery allowlist; canonical timezone/facts fingerprinting; safe template/intro/version dispatch and escaped server fallback; public/personal renderer parity without identity leaks; publish/republish/unpublish races and failure rollback; current publication media visibility. Saving a premium draft must not make these available.

Existing RF-026/030, external access logs, key custody/credential examples, media retention/rights, real-device/performance and engine verification findings remain open. 5D is unchanged and still required before pilot; Seating/S01–S06 remain postponed/unapproved. Stop after DEL-03a for owner acceptance; DEL-03b requires separate authorization.

## 6. DEL-03a implementation and verification closeout (2026-10-22)

### Delivered code and HTTP contract

- [CmsDraft](../../app/Services/CmsDraft.php) reuses the existing unique wedding draft and counter. Inside a retry-bounded transaction it locks **Wedding → draft → assets ordered by UUID**, reauthorizes the owner, compares the expected version, validates complete source and verifies every referenced private owned image. First save expects version zero; successful saves advance once. Stale/counter-bound saves return safe 422; schema-v1 or template-key/version transitions return 409 without mutation. There is no conversion, force save, second draft store or publication write.
- [SaveCmsDraftRequest](../../app/Http/Requests/SaveCmsDraftRequest.php) permits only `document` and integer `draft_version`. [Controller](../../app/Http/Controllers/CmsEditorPreviewController.php) and [routes](../../routes/web.php) add **GET/PUT `/accounts/{account}/weddings/{wedding}/cms-preview/draft`** and **GET `/saved-preview`** under that same prefix. Existing scoped bindings, owner policy, CSRF and private error/header middleware apply. Save has a distinct 30/user/minute bucket; reads use the existing private-read bucket. Retrieval resolves all referenced assets independently of picker pagination. Saved preview is 404 until first save.
- [Contract](../../app/Services/CmsV2Contract.php), [document](../../app/Services/CmsV2Document.php), [TS types](../../resources/js/types/cms-v2.ts) and [shared corpus](../../tests/Fixtures/cms-reusable-content.json) support optional independent hero/details/story `photo_slots`. Old v2 source without the field round-trips unchanged. All hidden source, IDs/order, visibility, ten-photo gallery, milestones, entourage and crop/focal settings are retained. Capability projection hides unsupported slots without deleting saved references. [Resolver](../../app/Services/WeddingImageResolver.php) rechecks slot/milestone/gallery ownership and bytes, including hidden references.
- [Timezone migration](../../database/migrations/2026_10_21_162436_add_timezone_to_weddings_table.php), [Wedding](../../app/Models/Wedding.php), [canonical edit request](../../app/Http/Requests/UpdateWeddingRequest.php), [controller](../../app/Http/Controllers/WeddingController.php) and [form](../../resources/js/components/WeddingForm.vue) implement the approved relational timezone and wedding-first edit transaction. The existing unmigrated application omits the optional field; no model default writes an absent column. The guarded premium workflow requires the column and fails 503 when missing. IANA validation is server-side. Expected MySQL DDL is `ALTER TABLE weddings ADD timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Manila'`; this is migration intent, **not new MySQL execution evidence**. No date/time values or old snapshot/fingerprint fields were rewritten.
- [Editor](../../resources/js/pages/weddings/CmsEditorPreview.vue), [adapter](../../resources/js/lib/cms-editor.ts) and [saved preview](../../resources/js/pages/weddings/CmsSavedPreview.vue) provide Save Draft, confirmed feedback, source-based dirty state, explicit reload/discard confirmation and unsaved-navigation protection. A cloned request snapshot prevents edits during a save from being falsely marked saved. Double clicks cannot submit twice. A lost acknowledgment never displays success or force-overwrites; explicit current-source review/reload resolves uncertainty. Reverting edits clears dirty state. Valid empty section lists remain empty. The saved view reads durable source and fresh canonical facts, independently of local unsaved edits; it omits editor-only empty coaching.
- The Enchanted details renderer displays the actual named timezone when non-Manila, retaining its existing Manila wording. No visual redesign, personal RSVP authority, public renderer, published pointer, revision, media bytes or cleanup behavior changed. Source/private paths and recovered credentials never enter draft JSON. Existing Modern Classic/publication/RSVP/Attendance regressions pass.

### Exact executed verification

All PHP tests used isolated in-memory SQLite, synthetic factories, array sessions/cache and temporary extension loading; browser HTTP used verified newly created temporary SQLite/storage/session roots. No development database operations or new InnoDB run occurred.

| Command / selection                                                                                                                                                                                                                                                                   | Observed result                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit --no-progress --stop-on-error --stop-on-failure` with process `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, array cache/session and `SESSION_PATH=/` | **645 passed, 24 opt-in MySQL skips; 3,911 assertions**. This full run completed outside the sandbox because its development-server test could not start reliably inside it.                                                                                                                                                                                                                                                                                                                                         |
| Same isolated invocation restricted to `tests/Feature/CmsDraftTest.php tests/Feature/CmsEditorPreviewTest.php`, after the final read limiter and save-limiter regression                                                                                                              | **30 passed, 320 assertions, zero skips**; includes 13 new draft tests and 17 editor HTTP cases. This adds verification after the full run; overlapping cases are not added to its total.                                                                                                                                                                                                                                                                                                                            |
| Same isolated invocation: `CmsReusableContentTest.php CmsV2ContractTest.php WeddingImageUploadTest.php CmsV2AvailabilityTest.php`                                                                                                                                                     | **199 passed, 581 assertions, zero skips**.                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| Original-editor selection (exact command below); separate `--test-name-pattern=DEL02e` selection against the same browser file                                                                                                                                                        | Existing **17 + 5 passed**, zero skips.                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `node --test --test-name-pattern=DEL03a tests/Frontend/CmsEditor.browser.test.mjs`, followed by targeted corrected/added cases                                                                                                                                                        | **Six distinct new cases verified**: actual upload/save/reload/private preview/CSRF; two-window stale handling; edits during save/revert/double-click; lost acknowledgment and explicit reload; canonical timezone/responsive captures; valid empty-source round trip. Initial combined run was four passed/one failed due an exact photo-control label selector; corrected partial region-scoped label then passed that case. The sixth case was added and passed separately. No failed app assertion was bypassed. |
| `node --test tests/Frontend/EnchantedEditorial.browser.test.mjs` with `QA_ENCHANTED_PHOTOS=1`; `node --test tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/ModernClassicInvitation.test.mjs`; `node --test tests/Frontend/WeddingCountdown.test.mjs`                           | **15 + 7 + 4 passed**, zero skips. Together with editor selections: **54 distinct affected frontend/browser/contract cases verified**; overlapping reruns are excluded.                                                                                                                                                                                                                                                                                                                                              |
| `npm.cmd run typecheck`; `npx.cmd vue-tsc --noEmit -p tests/Frontend/fixtures/tsconfig.json`; scoped ESLint/Prettier; `npm.cmd run build`                                                                                                                                             | Passed. Build/isolated Vite tests required the existing approved sandbox escape for esbuild filesystem access, not configuration changes.                                                                                                                                                                                                                                                                                                                                                                            |
| `php vendor/bin/pint --dirty --format agent` scoped to intended changed PHP paths; `php -l` on those 15 paths; `git diff --check`                                                                                                                                                     | Passed, including all 15 PHP syntax checks. No framework/vendor edits or unrelated PHP formatting retained.                                                                                                                                                                                                                                                                                                                                                                                                          |

The first sandboxed full run was interrupted at development-server startup and is **not** a successful run. Its exact generated public test probe was removed; the direct server test then passed (one test/three assertions) and the complete isolated suite above passed. Initial fresh/default fixture snapshots and migration-test teardown were corrected before passing focused runs. These were test setup issues, not suppressed failures. Browser screenshots were refreshed after private image decoding; no additional broad suite rerun was needed.

Exact original-editor selection, with the alternation passed as one argument:

```powershell
node --test --test-skip-pattern='DEL02e|DEL03a' tests/Frontend/CmsEditor.browser.test.mjs
```

### Owner preview and acceptance gate

Launch/restart with `npm.cmd run build`, then `node tests/Frontend/cms-editor-preview.mjs`. The loopback-only harness prints the isolated editor URL and a temporary `preview.json` containing its synthetic login. **Each launch deliberately creates a new disposable database**; persistence survives reload within that target, but a new harness target starts afresh. This is not development MySQL activation or a customer production workflow.

Current review host: `http://127.0.0.1:58099/accounts/1/weddings/1/cms-preview`. Login information: `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-J5HQpY/preview.json`. Use a private window. Screenshots in its `qa/` directory: `del03a-editor-{1440,1024,390}-{light,dark}.png` and `del03a-saved-private-{1440,390}.png`. Uploaded demonstration gradients are synthetic; they verify private image persistence rather than final photography fidelity. Earlier family-photo visual-trial rights remain unchanged.

- [ ] Edit story, independent photo slots, crop/focus, gallery order/captions and entourage; Save, reload and verify exact persistence, including hidden section data.
- [ ] Make unsaved edits; confirm live preview changes while a separately opened saved preview remains at the last save. Reload confirmation must allow cancellation without loss.
- [ ] Open two windows; save one, then verify the second rejects a stale save and retains its local edits. Check dirty/loading/success feedback and keyboard focus.
- [ ] Change the canonical wedding timezone through wedding details; reopen private preview and check the displayed zone/countdown source. No CMS JSON duplicate is created.
- [ ] Review 1440/1024/390 light/dark editor, independent pane scrolling, mobile Edit/Preview, confirmation dialog and saved-view return navigation. Existing Horizon evaluation: 64/68 (94.1%), eight hard gates met; this is scoped portal QA, not complete premium visual acceptance.

**DEL-03a owner acceptance remains pending (QA-UI-19).** Public CMS-v2 publishing remains off. Missing/corrupt saved asset bytes fail closed without erasing the source; this batch adds no asset-repair UI. Future DEL-03b must define/snapshot canonical timezone and UTC instant/DST behavior, connect immutable publication/media visibility and escaped public fallback, and verify public/personal privacy/dispatch/races. QA-MY-24 and new draft/media/timezone independent-worker races remain unverified; H23 remains historical evidence only. No new approval decision is needed to finish this batch; those later gates are not waived.

## 7. DEL-03a.1 — editor UX and wedding design polish

2026-10-22 / D-041: owner confirms the preceding basic Save/reload/saved-preview manual checks passed and authorizes these refinements. **Implementation/automated QA complete; polish owner acceptance pending.** Existing Enchanted layout, complete source, hidden preferences, single draft/version and private-media/owner boundary are retained. No development database/schema/configuration operation, publication activation, real credential, RSVP/5D or dependency change. Test-only uploads and draft writes use verified disposable targets.

### Findings and implemented corrections

- [Editor](../../resources/js/pages/weddings/CmsEditorPreview.vue) and [shared error helper](../../resources/js/lib/cms-editor.ts): the validation-only display used friendly messages, but Save errors joined raw PHP messages. Both now use friendly section/group/field labels. A prominent named summary links to the corresponding tab/control, opens invalid collections, focuses/marks controls with `aria-invalid` and linked descriptions, and keeps server validation authoritative. [Story](../../resources/js/components/cms/StorySectionEditor.vue), [Entourage](../../resources/js/components/cms/EntourageSectionEditor.vue) and image controls expose bounded field references/indicators. Fresh error responses may expand the affected collection; changing tabs alone no longer reopens a completed item from old error state. Ordinary typing does not move preview scroll/focus.
- Save success, dirty state, stale conflict, failure and information now have distinct labels/iconography/border treatments in [portal CSS](../../resources/css/cms-editor.css), supporting light/dark without routine accent colors or intrusive normal-editing alerts. Validation summary is a region; focused contextual errors retain existing alert semantics.
- Newly generated documents **already** initialize Gallery enabled in `CmsEditorPreview::document()`. A new persistence regression explicitly proves this and that a saved hidden Gallery is not overwritten. No read-time normalization/forced re-enable was added.
- [PHP contract](../../app/Services/CmsV2Contract.php), [TS contract](../../resources/js/types/cms-v2.ts), [projection adapter](../../resources/js/lib/cms-editor.ts), [image field](../../resources/js/components/cms/CmsImageField.vue) and [photo renderer](../../resources/js/components/wedding/enchanted-editorial/EditorialPhoto.vue): optional reusable image `frame: fit|fill`. Fit uses `contain`; Fill uses `cover` plus existing focal positions. Explicit natural framing fills the template's frame; bounded crop presets still define their selected aspect frame. Original documents without `frame` retain prior geometry and offer an accurately labeled original-framing option until edited. New picker selections default Fill. No stretching, reprocessing or physical asset mutation.
- [Gallery editor](../../resources/js/components/cms/GallerySectionEditor.vue) and [renderer](../../resources/js/components/wedding/enchanted-editorial/EditorialGallery.vue): optional reusable `data.slideshow: {autoplay: boolean, interval_seconds: 5|8|12}`. Missing settings resolve to on/5 seconds, but automatic playback requires multiple visible photos. Defaults are presentation-only, not read-time source rewrites; stored off/hidden preferences persist. Manual arrows/keyboard/swipe remain. Pause/play is visible; manual interaction latches pause, control focus temporarily pauses, and intersection/document visibility/reduced-motion checks stop timers. Focused Play arms playback until focus leaves; explanatory copy makes that state clear. Reduced motion disables auto-start/Play while manual navigation works. Automatic slides do not announce every change. Unmount removes interval/observers/listeners.
- Font diagnosis: local variable serif uses a noticeably lighter stroke than the temporary system serif; `font-display: swap` revealed that transition. [Enchanted CSS](../../resources/css/enchanted-editorial.css) now uses preload, font blocking and deliberate 600 display weights/responsive balanced wrapping; body typography remains lighter. The [iframe](../../resources/js/components/cms/CmsPreviewFrame.vue) waits for its declared fonts before mounting, catches failed loads, prevents duplicate asynchronous mounts and cleans up safely. Actual decoded-font screenshots are captured. Existing large TTF payloads/slow real-device performance are not claimed resolved.
- [Original static SVG ornament](../../resources/js/components/wedding/enchanted-editorial/EditorialOrnament.vue): restrained flowers/plane in negative spaces and interlocking footer rings. Decorative, `aria-hidden`, pointer-transparent, no external asset license or animation dependency. Stable `data-landing-section` values reference existing visible `invitation`, `our-story`, `our-photos`, `closing` sections. Hidden optional sections remove their markers; mobile uses smaller ornaments. These are only landing references; **no hovering, flight, simulated bird or shared motion runtime** exists.

### Executed verification

Same isolated CLI SQLite/runtime conditions as Section 6; new loopback browser hosts each verify their disposable target before migrations. No development MySQL touched. **315 focused PHP passes / 1,750 assertions / zero skips**:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsDraftTest.php tests/Feature/CmsReusableContentTest.php tests/Feature/CmsEditorPreviewTest.php --no-progress --stop-on-failure
# 120 passed / 504 assertions
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsV2ContractTest.php tests/Feature/CmsV2AvailabilityTest.php tests/Feature/CmsMediaPersistenceTest.php tests/Feature/Weddings/WeddingPublicationTest.php tests/Feature/PersonalRsvpHttpTest.php tests/Feature/Weddings/WeddingAttendanceTest.php --no-progress --stop-on-failure
# 195 passed / 1,246 assertions
```

[CmsDraftTest](../../tests/Feature/CmsDraftTest.php) adds two meaningful cases: settings/hidden preference/save/load/private-preview preservation with no publication, and strict invalid-frame/interval/type/unknown-field rejection without replacing the prior draft. [Shared corpus](../../tests/Fixtures/cms-reusable-content.json) adds valid optional settings and invalid enum/interval cases exercised by both PHP and TypeScript. Existing old-document round trips remain unchanged.

**58 distinct affected frontend/browser/contract cases verified, zero final unresolved failures/skips**, excluding overlapping reruns:

- `CmsEditor.browser.test.mjs`: 17 existing editor + five DEL02e + six DEL03a + four new DEL03a1 cases = **32**. Selections used `--test-skip-pattern='DEL02e|DEL03a'`, `--test-name-pattern=DEL02e`, `--test-name-pattern='DEL03a '` and `--test-name-pattern=DEL03a1` on separate verified fixtures. Targeted reruns cover corrected cases only.
- New cases: friendly actual-422 summary/marked focus/expanded collections/no raw visible paths; real upload → Fit/Fill/interval/off → Save/reload/saved private preview; actual automatic advancement, focus/manual/offscreen/reduced-motion pause and instrumented active-interval removal on unmount; hidden Gallery retention and marker disappearance/responsive screenshots.
- `EnchantedEditorial.browser.test.mjs` with private photo fixture enabled + `CmsV2Contracts.test.mjs` + `ModernClassicInvitation.test.mjs` + `WeddingCountdown.test.mjs`: **26 distinct cases**. Existing licensed local fonts, no-JS content, real permitted private-photo presentation, long names/missing assets, responsive/touch/keyboard and Modern Classic remain covered.
- `npm.cmd run typecheck`; fixture `vue-tsc --noEmit -p tests/Frontend/fixtures/tsconfig.json`; scoped ESLint/Prettier; scoped `php vendor/bin/pint --dirty --format agent`; two `php -l` checks; `npm.cmd run build`: passed. No new full-suite/MySQL run; Section 6's full suite and H23 remain historical evidence, not rerun claims.

Initial browser failures were diagnosed, not skipped: tests toggled off an already automatically selected upload; wrapping labels included option text; old selectors assumed a collapsible error summary, an unlabeled success paragraph or a single outline-style link. New controls use explicit label associations, selectors target their semantic region, and source/HTTP assertions remain. An existing crop case depended on earlier uploads/empty state; it now creates its own synthetic image and checks explicit Fill's natural frame geometry. One screenshot decode waited indefinitely for lazy hidden images; that test process was stopped, then captures force eager decoding with a bounded wait. The completed-milestone reopening regression was an actual editor-state issue corrected above. Corrected/added cases passed targeted reruns; no timeout or security assertion was weakened to force a result.

### Preview, screenshots and owner review

Use `http://127.0.0.1:58290/accounts/1/weddings/1/cms-preview` with a private window and synthetic login from `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-mDBKmj/preview.json`. Its `qa/` directory contains `del03a1-saved-{1440,390}.png` and error/focus captures. This target contains synthetic saved Gallery photographs and explicit slideshow preferences; those saved choices correctly override defaults. Original empty-editor 1440/1024/390 light/dark captures are in `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-Xxlb7n/qa/`. Complete wedding visual snapshots with the owner's permitted private photos are in `storage/app/private/dev-preview/enchanted-editorial/qa-b2b/default-{1440,390}.png`; no photos were moved into public assets/bundles.

Restart instructions and disposable-target lifetime remain Section 6. Current owner preview is private/local-only, not public CMS activation. Visual review confirms restrained marks/semibold balanced headings and no document overflow. Horizon portal review retains the existing approved UI font, monochrome distinctions, visible focus, responsive panes and accessible dialogs: **64/68 applicable weighted points (94.1%), all eight hard gates pass**. New decorative template styling remains isolated from portal/Modern Classic. This is Chromium/emulated-mobile evidence, not a physical-device/screen-reader or slow-network certification.

- [ ] Submit an invalid collapsed Entourage group; follow each summary link, correct fields, switch tabs and confirm friendly contextual feedback/focus.
- [ ] Review saved/unsaved/stale/info treatments; recheck two-window save behavior without losing local edits.
- [ ] Use portrait/landscape photos, Fit/Fill/crop/focus; Save/reload and compare saved preview. Original drafts remain intact until choices change.
- [ ] Gallery on/off, 5/8/12 seconds, manual arrows, visible pause/play, keyboard focus, scrolling away and reduced motion; Save/reload off/hidden preferences. Newly initialized content is enabled by default, saved hidden preferences stay hidden.
- [ ] Review desktop/mobile headings, static decorations/footer rings and marker placement with optional sections hidden. No hummingbird is expected.

Remaining gates: polish/complete template acceptance, physical mobile/assistive technology/slow-font performance, DEL-03b publication/visible-media/UTC-instant/privacy integration and QA-MY-24/new media races. Historical 23 InnoDB passes are preserved, not newly executed. No public publishing, real RSVP, 5D, Seating, commit or push. Stop after DEL-03a.1.

## 8. DEL-03a.2 — final UX polish and hummingbird preparation

2026-10-22 / D-042. Implemented and automatically verified; owner review remains QA-UI-19. Stop after this batch. Persistent drafts, complete saved source, Enchanted composition and RSVP/5D remain intact. No production PHP behavior, migration, development database/reset, public publication, project dependency, commit or push changed.

### Changes and findings

- [Editor](../../resources/js/pages/weddings/CmsEditorPreview.vue) now shows one linked content-error summary. Removed the duplicate contextual alert and repeated Save error text; corrected fields clear the obsolete Save error. The warning summary has a subtle background and retains accessible descriptions, focus and collapsed-group expansion. Success/info/conflict notices have distinct restrained backgrounds; stale versions still reject saves and retain edits. [Scoped styling](../../resources/css/cms-editor.css).
- The actual starter already had `gallery.items: []`, and the picker already queried `$wedding->mediaAssets()` only. The previous review target's synthetic photos were real test uploads/saved selections. Preserve those targets and all stored media. Strengthened empty-start HTTP/browser assertions and friendly Gallery/picker guidance; no deletion, backfill or demo data added to production. Synthetic content stays in tests and disposable test storage.
- [Enchanted navigation](../../resources/js/components/wedding/enchanted-editorial/EnchantedEditorialInvitation.vue) uses an enhanced mobile disclosure at widths ≤820px. Enter/Space opens it, focus enters the links, Escape restores the trigger, outside click/focus departure closes it, and section selection closes/focuses/scrolls to its destination. It uses the iframe's own document/media query. Hidden/omitted sections have no link. Desktop and no-JS navigation remain usable.
- [Gallery](../../resources/js/components/wedding/enchanted-editorial/EditorialGallery.vue) dissolves one decorative, inert/aria-hidden outgoing photograph over the newly selected one for 420ms. Only opacity animates; no control duplicates or additional timers. Rapid changes replace the overlay, animation completion removes it, reduced motion skips/removes it, and source changes/unmount remove stale state. Existing autoplay intervals/manual/swipe/focus/visibility/reduced-motion behavior stays intact.
- Every rendered major section and visible Story milestone has a botanical landing marker. [Discovery helper](../../resources/js/lib/wedding-waypoints.ts) returns fresh stable IDs/elements in document order, with no coordinates or animation engine. Section/milestone markers disappear with hidden/removed content. The [DEL-07 plan](premium_delight_experience.md#del-03a2-waypoint-and-orientation-foundation-d-042-2026-10-22) records the exact DOM contract and FRONT/LEFT/RIGHT orientation requirements. No bird artwork, sprite, wing animation or scroll-follow behavior was implemented.

### Exact verification

All PHP tests used process-only `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `SESSION_PATH=/` and unused temporary config/route cache paths. Browser targets were independently created/verified disposable SQLite/storage/session roots. Development MySQL and customer media/credentials were not used.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsDraftTest.php tests/Feature/CmsReusableContentTest.php tests/Feature/CmsEditorPreviewTest.php --no-progress --stop-on-failure
```

**120 passed / 508 assertions / zero skips.** Existing cross-account/wedding/CSRF/private-media denial, source-preserving old drafts, settings validation and hidden-source round trips pass. The updated starter-editor assertion proves empty gallery/initial images. `php vendor/bin/pint --dirty --format agent tests/Feature/CmsEditorPreviewTest.php` and `php -l tests/Feature/CmsEditorPreviewTest.php` pass.

Browser environment: `QA_CMS_EDITOR_INFO=<verified target>/preview.json`, `QA_PLAYWRIGHT_MODULE=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/horizon-ministry-qa/node_modules/playwright-core/index.mjs`, `QA_BROWSER_EXECUTABLE=C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe`. Renderer additionally uses `QA_ENCHANTED_PHOTOS=1` and `QA_ENCHANTED_ARTIFACTS=C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-renderer`; prior private photo evidence was preserved.

```powershell
node --test tests/Frontend/CmsEditor.browser.test.mjs
node --test --test-name-pattern='validation feedback|one linked validation|DEL02d collapsed|DEL03a2' tests/Frontend/CmsEditor.browser.test.mjs
node --test --test-name-pattern='DEL03a2 (all|mobile|waypoint)' tests/Frontend/CmsEditor.browser.test.mjs
node --test --test-name-pattern='DEL03a2 fresh|DEL03a1 friendly' tests/Frontend/CmsEditor.browser.test.mjs
node --test --test-concurrency=1 tests/Frontend/EnchantedEditorial.browser.test.mjs tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/WeddingCountdown.test.mjs
node --test --test-name-pattern='keyboard skip' tests/Frontend/EnchantedEditorial.browser.test.mjs
node --test --test-name-pattern='complete invitation|optional/reordered' tests/Frontend/EnchantedEditorial.browser.test.mjs
```

**63 distinct affected frontend/browser/contract cases verified: 37 connected editor + 26 renderer/contract/countdown cases**, excluding overlapping reruns. All selected cases ultimately passed; no final unresolved failures/skips. New editor cases cover empty gallery/library, all 5/8/12-second settings with on/off and saved hidden preferences, mobile menu in live/saved previews, milestone waypoint identity/order/reordering/hiding/removal/mobile discovery, and dissolve cleanup/reduced motion/unchanged stage height/focus. Existing actual upload, Fit/Fill/crop/focus, old-source contracts, stale/uncertain saves, autoplay/manual/pause/offscreen/unmount and escaped/no-JS content remain covered. Renderer assertions now check a marker for every rendered major section, including Countdown, and absence for hidden sections.

Initial failures were resolved explicitly: three old editor cases targeted the removed duplicate `alert`; the mobile touch-target case measured links while the new menu was closed. New cases initially read iframe content before mount, expected a zero scroll offset instead of the existing 28px margin, tried the interval control while autoplay was off, and selected an empty Story omitted by the saved renderer. Updated tests exercise the replacement summary/menu and wait for actual rendering; they retain the original focus/security/persistence assertions. One login encountered a transient missing Vite manifest because a rebuild overlapped that browser run; final targeted runs occurred after build completion. No application failure or acceptance gate was skipped to obtain a pass.

These final quality commands pass:

```powershell
npm.cmd run typecheck
node node_modules/vue-tsc/bin/vue-tsc.js --noEmit -p tests/Frontend/fixtures/tsconfig.json
node node_modules/eslint/bin/eslint.js resources/js/pages/weddings/CmsEditorPreview.vue resources/js/components/cms/CmsImagePicker.vue resources/js/components/cms/GallerySectionEditor.vue resources/js/components/wedding/enchanted-editorial resources/js/lib/wedding-waypoints.ts tests/Frontend/CmsEditor.browser.test.mjs tests/Frontend/EnchantedEditorial.browser.test.mjs
node node_modules/prettier/bin/prettier.cjs --check resources/js/pages/weddings/CmsEditorPreview.vue resources/js/components/cms/CmsImagePicker.vue resources/js/components/cms/GallerySectionEditor.vue resources/js/components/wedding/enchanted-editorial resources/js/lib/wedding-waypoints.ts resources/css/cms-editor.css resources/css/enchanted-editorial-sections.css tests/Frontend/CmsEditor.browser.test.mjs tests/Frontend/EnchantedEditorial.browser.test.mjs
npm.cmd run build
```

Windows App Control blocked the installed Rollup native parser even outside the sandbox. The owner approved `npm.cmd install --prefix C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-tools --no-package-lock --ignore-scripts --no-audit --no-fund @rollup/wasm-node@4.64.7`. Build/renderer commands use process-only `NODE_OPTIONS=--import=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-tools/rollup-wasm-loader.mjs` to select that matching WASM parser. The install/build/renderer needed outside-sandbox execution for network/esbuild filesystem access. Project package files and Windows security settings are unchanged. Plain build may still need this temporary environment until the native-tool policy is resolved.

No broad Laravel/full application browser/MySQL run was needed or claimed. Historical full SQLite and 23 InnoDB passes remain historical; QA-MY-24 and new CMS/media races remain unverified.

Documentation closeout: Prettier passes for all nine affected documents, 258 local file links resolve, 91 QA IDs remain unique and `git diff --check` passes. Existing tracked/untracked work and all earlier migration/package/runtime files are preserved; no new migration or package-file change.

### Preview, screenshots and remaining acceptance

Fresh manual review: `http://127.0.0.1:49675/accounts/1/weddings/1/cms-preview`. Synthetic email `editor-preview@example.test`; its generated password is in `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-9sUgND/preview.json` and supplied directly to the owner. Use a private window. This target has no saved draft, gallery selections or uploaded assets; Save creates its first persistent draft. Restart still creates a different disposable target; reload/save within this target persists normally.

Desktop/mobile screenshots reviewed: fresh target `qa/del03a2-empty-editor-{1440,390}.png` and empty-picker capture; corrected-test target `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-pekpD0/qa/` contains `del03a2-live-mobile-menu.png`, `del03a2-saved-mobile-menu.png`, `del03a2-saved-desktop.png` and `del03a2-waypoints-mobile.png`. Full approved-photograph renderer captures are under `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-renderer/`; family originals are unchanged/private. Initial regression target was `rsvp-cms-editor-rvfbK5`. Keyboard/focus, 1440/1024/390 layouts, light/dark, touch, no-overflow and reduced-motion evidence are browser/emulated-device checks.

- [x] Outstanding automated settings-persistence check: on/off, 5/8/12 seconds and saved hidden Gallery preferences survive Save/reload; full source is compared unchanged and saved preview respects visibility. Existing Fit/Fill/crop/focus/old-draft tests pass.
- [ ] Owner accepts the final summary/notice backgrounds, empty/uploaded galleries, mobile menu, dissolve and marker placement (QA-UI-19); test with owner photographs as desired.
- [ ] Physical mobile browsers, assistive technology and slow-network/font performance remain unverified. Cross-engine/deployment/media/race/rights gates remain open in RF-026/027 and the QA register.
- [ ] DEL-03b public publishing/current-visible media/UTC-instant integration and DEL-07 original/licensed bird/prototype need separate authorized batches. No activation follows from these private previews. Stop after DEL-03a.2.

## 9. DEL-03b — immutable publishing and public wedding website

2026-10-22 / D-043. DEL-03a.2 is **owner-accepted: 17/17 manual checks passed**. DEL-03b implementation and scoped integrated verification are complete; **DEL-03b owner acceptance is pending (QA-UI-20)**. Stop at this milestone. No production activation, development MySQL migration/reset, new migration, package change, commit or push.

### Delivered contract and code

- Reuse WeddingPublication, CreateWebsiteRevision, website_revisions, revision-media bindings and the existing publication pointer/counter. EnchantedWebsite is a bounded template/facts/display adapter, not another publisher. Complete CMS-v2 source is validated, including hidden content and all owned image bytes, before an immutable revision and retained references are committed.
- Under the wedding-first lock, lock the saved draft, recheck owner membership and expected draft/publication versions, resolve canonical wedding facts/timezone and media, and update the existing pointer atomically. CMS-v2 hashes include template/version, canonical named timezone, resolved UTC instant and complete source. Associative keys are canonicalized while section/milestone/photo list order is preserved. The existing Modern Classic hash is unchanged. No-op/stale and three-attempt transaction retry behavior use the existing service.
- Canonical date and time remain relational. Publication stores timezone and wedding_instant_utc in the existing facts JSON. A missing optional time yields a null instant; invalid timezone, ambiguous DST time and nonexistent DST time reject publication with an actionable message. Snapshot facts never reread mutable wedding facts at public render time. No new CMS countdown section was added.
- Public template dispatch requires exact schema 2 / enchanted-editorial / template version 1. Full validation, disabled introductions and supported snapshot metadata are required; unknown/unsupported/corrupt combinations fail closed. V1 Modern Classic still uses its existing renderer and escaped Blade fallback.
- Public Enchanted uses the accepted Vue composition through public-enchanted.ts and an escaped, meaningful Blade fallback. Both receive only visible display values, never full source, private paths, credentials, contacts, guests or RSVP answers. Hidden ceremony/reception facts are omitted from the display projection. No-JS Story/milestones/photos/navigation remain readable. Fit/Fill/crop/focus agree with Vue within two pixels in the checked mobile fixture.
- GET /w/{slug}/media/{asset} serves verified bytes only when the UUID is owned by the current wedding, bound to its current revision, and present in that revision's visible projection. Hidden, draft-only, history-only, foreign, missing and corrupt assets fail closed. Full hidden/history bindings remain retained and private; no files or saved selections are deleted. Delivery holds a shared wedding lock through authorization and byte reading, so publish/unpublish serialize with it. Already delivered bytes cannot be recalled. Existing no-store/private/noindex/nosniff/no-referrer headers remain; images add same-origin resource policy.
- Reuse the Wedding overview PublicationPanel, its confirmation dialogs, saved-state fingerprint, errors and status. CMS editor links to the overview; overview links to editor and saved preview. Publication reads the server's saved draft only. Unsaved local fields and later saved edits cannot affect live content until explicit Republish. Unpublish removes access without deleting drafts, revisions or retained files. Rollback means transaction rollback on failure; this batch does not add a revision-history restoration UI or template conversion.
- Controlled activation requires both CMS_EDITOR_PREVIEW_ENABLED=true and CMS_V2_PUBLISHING_ENABLED=true, plus local/testing environment and the existing schema readiness check. Both default false; production is denied even if flags are true. WebsiteDesignRegistry's general customer availability remains false. The isolated harness enables publishing only with QA_CMS_PUBLISHING=1. Actual .env and dependency manifests/lockfile were unchanged.
- Until DEL-04, a wedding with any active personal credential cannot publish Enchanted; a published Enchanted wedding cannot issue/recover/rotate personal credentials. Personal read/write dispatch is unavailable for v2. Revocation remains possible; existing credentials are never silently revoked. Modern Classic personal/legacy RSVP and Attendance/5D behavior remain preserved by regression tests.
- Mobile menu selection closes, focuses the visible destination, updates the hash and scrolls smoothly with existing scroll-margin plus any sticky/fixed header offset. Reduced motion navigates instantly. Editor iframe documents avoid an invalid about:blank history update. Escape, disclosure focus, keyboard, direct hashes/back navigation and desktop behavior remain accessible. No scrolling dependency, bird assets or animation engine.

### Executed verification

**427 distinct focused SQLite tests / 2,470 assertions; 31 real InnoDB cases / 661 assertions; 47 distinct frontend/browser/contract cases. All final selected runs passed, zero final failures/skips.** These are scoped bundles, not a new full application-suite pass. Overlapping strengthened reruns are excluded.

PHP process overrides: APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array, SESSION_DRIVER=array and separate APP_CONFIG_CACHE/APP_ROUTES_CACHE paths under Temp/rsvp-del03b-php. Temporary CLI extensions only; no global PHP setting changes.

```text
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsPublicationTest.php tests/Feature/CmsDraftTest.php tests/Feature/CmsEditorPreviewTest.php tests/Feature/CmsV2AvailabilityTest.php tests/Feature/CmsReusableContentTest.php tests/Feature/CmsPersistenceTest.php tests/Feature/CmsMediaPersistenceTest.php tests/Feature/Weddings/WeddingPublicationTest.php tests/Feature/Weddings/WeddingInvitationTest.php tests/Feature/PersonalRsvpHttpTest.php tests/Feature/PersonalRsvpServicesTest.php tests/Feature/Weddings/WeddingAttendanceTest.php --no-progress --stop-on-failure --log-junit C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03b-php/results.xml
# PASS: 363 tests, 2,367 assertions
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsV2ContractTest.php --no-progress --stop-on-failure
# PASS: 64 tests, 103 assertions
php vendor/bin/phpunit --group=mysql-concurrency --group=mysql-schema --no-progress --stop-on-failure --log-junit C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03b-mysql/final-engine-results.xml
# PASS: 31 tests, 661 assertions, zero skips
```

New CmsPublicationTest covers complete/hidden source retention, current-visible media, order-only fingerprints, immutability/republish/unpublish, stale versions, owner/viewer/cross-account denial, strict gate/production denial, named zones/missing time/DST ambiguity/gaps, old slot-less source, unsupported versions/hidden invalid content, binding-failure rollback, missing/foreign/corrupt media, active-credential guards/personal read/write/issuance denial/revocation and owner links. Real browser POST without CSRF returns 419.

**InnoDB environment and cleanup:** separately provisioned disposable local schema wedding_rsvp_cms_test_20261022_del03b_d209623e on 127.0.0.1:3306 / LAPTOP-2129KHGH / MySQL 26.7.0, InnoDB, REPEATABLE-READ. Strict SQL mode: ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION. Dedicated principal had only this schema plus temporary PROCESS for observed independent-worker lock waits; development SELECT permission was explicitly denied without reading rows. Existing 18 migrations were applied only to this schema. The 23 historical engine cases, previously unexecuted CMS constraint and seven CmsPublicationMySqlTest cases all passed. New schedules: publish/publish, save/publish, republish/unpublish, credential issuance/publish, media/unpublish, canonical facts/publish and a queued failing binding transaction versus successful publish. The failure worker actually waited first, rolled back and left one complete winner. The disposable database, user and environment.json credential file were removed after verification. Metadata/JUnit files remain in Temp/rsvp-del03b-mysql. No development migration/reset.

```text
node --test --test-concurrency=1 tests/Frontend/CmsPublication.browser.test.mjs
# PASS: 4 new connected cases; selected strengthened reruns also pass
node --test --test-concurrency=1 --test-name-pattern='mobile section navigation|escaped Blade|publication controls' tests/Frontend/CmsPublication.browser.test.mjs tests/Frontend/Publication.browser.test.mjs
# PASS: 2 overlapping new cases + 1 distinct Modern Classic publication UI regression
node --test --test-name-pattern='DEL03a1|DEL03a2|actual authenticated|all focal/crop|keyboard photo|DEL03a real|DEL03a two|DEL03a uncertain|DEL02d timeline' tests/Frontend/CmsEditor.browser.test.mjs
# PASS: 16 selected connected editor cases on a separate default-off publication target
node --test --test-concurrency=1 tests/Frontend/EnchantedEditorial.browser.test.mjs tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/WeddingCountdown.test.mjs
# PASS: 26 renderer/contract/countdown cases
npm.cmd run typecheck
node node_modules/vue-tsc/bin/vue-tsc.js --noEmit -p tests/Frontend/fixtures/tsconfig.json
npm.cmd run build
# PASS: app/fixture types and production build (2,097 modules)
```

Browser environment used the existing temporary playwright-core module at C:/Users/UNI-PRESIDENT/AppData/Local/Temp/horizon-ministry-qa/node_modules/playwright-core/index.mjs and installed Microsoft Edge, QA_CMS_EDITOR_INFO for disposable targets, QA_ENCHANTED_PHOTOS=1 and a separate Temp/rsvp-del03b-renderer artifact folder for renderer cases. Editor default-off target: Temp/rsvp-cms-editor-Z2oNtQ. Public target: Temp/rsvp-cms-editor-hfTqb6. All images are synthetic uploaded test fixtures; customer uploads/family originals were not altered.

Scoped ESLint and Prettier --check passed for: resources/js/public-enchanted.ts; resources/js/components/wedding/enchanted-editorial/{EnchantedEditorialInvitation,EditorialRsvpClosing}.vue; resources/js/components/wedding/PublicationPanel.vue; resources/js/pages/weddings/{Show,CmsEditorPreview}.vue; resources/js/types/{cms-editor,index}.ts; tests/Frontend/{cms-editor-preview,CmsPublication.browser.test}.mjs; vite.config.ts. Commands: node node_modules/eslint/bin/eslint.js followed by those exact paths, and node node_modules/prettier/bin/prettier.cjs --check with the same paths.

Scoped PHP formatting command passed: php vendor/bin/pint --dirty --format agent followed by app/Services/{EnchantedWebsite,CmsV2Document,WeddingPublication,PublishedWedding,PersonalInvitationLink,PersonalRsvp,WebsiteDesignRegistry}.php; app/Http/Controllers/{WeddingPublicationController,PublicWeddingController,WeddingController,WeddingInvitationController,CmsEditorPreviewController}.php; config/cms_editor.php; routes/web.php; tests/Feature/{CmsPublicationTest,CmsPublicationMySqlTest}.php; tests/Fixtures/save-wedding-draft.php. php -l passed on the same 17 paths. php artisan route:list --path=w/ --except-vendor confirmed existing/public/media routes. git diff --check passed.

Windows App Control still blocks the installed native Rollup parser. Owner-approved temporary matching @rollup/wasm-node@4.64.7 is reused through process NODE_OPTIONS=--import=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-tools/rollup-wasm-loader.mjs for builds and Vite-based browser/renderer checks. No package install/project dependency or Windows security-setting change in this batch.

**Corrected execution issues:** initial new upload browser checks selected files before the async picker finished loading; the helper now waits for an enabled upload input. Initial PHP fixture use of RefreshDatabase conflicted with existing upload transaction ownership; switched to the established DatabaseMigrations convention. JSON escaping and fresh DB attribute comparisons were corrected without weakening security assertions. The broad InnoDB retry initially lacked EXIF in child workers; a temporary process-only PHP_INI_SCAN_DIR loads EXIF/memory settings, and the affected eight legacy cases then all 31 engine cases passed. Final review caught and fixed list-order canonicalization before final backend runs. The targeted no-JS photo check exposed fallback crop-size mismatch; pure CSS sizing was corrected and actual Vue/fallback fit/focus/dimensions now agree. No final unresolved failure or selected-test skip.

### Isolated owner walkthrough and screenshots

The running disposable publication target is **http://127.0.0.1:63326**. Use a private browser window. Login: editor-preview@example.test; the synthetic password is supplied directly to the owner and recorded only in C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-hfTqb6/preview.json. The preview helper creates a new target on restart; it does not reopen this one.

1. Open /accounts/1/weddings/1/cms-preview. The synthetic saved draft has two uploaded Gallery photos, shared hero/details/story slots and one Story milestone. Open Wedding overview and Review saved website.
2. Open /w/synthetic-editor-preview anonymously: it is already published for this walkthrough. Check desktop/mobile gallery, Story and menu; no personal RSVP form is supplied.
3. Change a Story paragraph in the editor without saving: public content stays unchanged. Save, then reload the public site: it still shows the prior snapshot. Overview reports changes available to republish.
4. Confirm Republish in Wedding overview: the public site now shows the saved changes. Reorder-only changes can also be republished. Canceling the dialog changes nothing.
5. Hide Gallery, Save and Republish: its old public photo URLs deny access when no other visible slot uses them, while private source/history retain them. Unpublish: public page/media return 404; saved private preview stays available. Publish again to restore the saved visible version.
6. Mobile: choose a visible section, confirm close/focus/hash/smooth scrolling and sticky offset; reduced-motion instant behavior and desktop navigation remain. Repeat with JavaScript disabled to review escaped readable fallback.

Screenshots (synthetic only), under C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-hfTqb6/qa-del03b/: published-1440.png, published-390.png, no-js-390.png and owner-published-1440.png. Automated owner/public sequence, saved-vs-live distinction, multipart uploads, hide/republish/unpublish, no-CSRF denial, mobile hashes/history/sticky offset/reduced motion and no-JS crop parity all passed.

### Outstanding acceptance and DEL-04 dependencies

- QA-UI-20: owner accepts this isolated generic publication workflow, Enchanted public desktop/mobile/Blade fallback and error/status handling. No uncontrolled activation before acceptance; production remains hard denied.
- DEL-04 must connect the existing PersonalRsvp service, holder/dependent authorization, greeting and submit/version/transaction protocol to an Enchanted-specific adapter. Keep guest state separate from immutable display props; generic HTML/JSON/media must never contain guest data. Preserve bearer privacy, trusted origin, private/no-store/noindex metadata and log/token protections. Only lift current active-credential/publication/issuance guards after connected HTTP/browser/privacy/concurrency coverage and explicit acceptance. No introduction/envelope/personalized animation in this batch.
- QA-CMS-06 template conversion/history restoration remains separately scoped; no source discard or new rollback UI. QA-CMS-07 personal Enchanted parity remains Planned. Production ACL/log/key/backups, final photo rights/quotas/retention, physical Safari/Android/screen-reader/slow-network checks and incomplete upload/cleanup/crash/capacity/parent-deletion race obligations remain as labeled in the QA register and RF-026/027/030. These are not opt-in skips or claimed passes. No cleanup scheduler, deployment or new migration was introduced.

## 10. DEL-03b.1 — focused preview, publishing and loader polish

2026-10-22 / D-044. Owner authorizes this focused follow-up only. DEL-03a.2 acceptance remains 17/17; this request does not independently record acceptance of all DEL-03b/release gates. DEL-03b.1 is implemented and automatically verified; owner review remains QA-UI-20. No unrelated RSVP/5D, DEL-04, Seating, full scroll-follow motion, database schema, dependency or deployment work. No commit/push.

### Exact behavior

1. **Empty photographs:** code-owned fresh documents and owner-scoped library were already empty. Prior populated previews contained actual synthetic uploads made by browser tests, not product defaults. This batch removes the rendered photo placeholders for null/unassigned slots and their empty hero backdrop/caption. Failed photographs that an owner really assigned still get an accessible unavailable-image fallback. Gallery/library start empty, no demo seed/fallback asset is substituted, and no existing uploads/source/history are removed. A fresh saved/published preview is explicitly verified to contain zero photograph elements/assets.
2. **Desktop navigation:** visible desktop section links now use the same smooth close/focus/hash/sticky-offset navigation as mobile. Reduced motion navigates instantly. Hidden sections, direct hashes, back navigation, desktop layout and landing IDs/order remain intact.
3. **Editor publishing:** CmsPublishAction replaces the global Reload saved draft button with Publish, Republish or disabled Published state plus a link to the current published website. Publication is unavailable until a draft is saved and supported; dirty, pending-save or mismatched saved-version state disables it. A confirmation posts only the existing expected draft/publication versions and fingerprint to the existing publish endpoint. No unsaved source is submitted, no autosave or bypass is added. The existing overview/unpublish workflow remains available. Failed/stale/uncertain operations keep local edits; a conditional Review saved version dialog retains explicit discard-and-reload recovery. Save refreshes publication status; successful publish stays in the editor. HTML/Inertia publication requests keep the existing overview redirect; JSON requests now return the current owner-only summary. Publisher, revision/pivot transaction and personal guards are unchanged.
4. **Readiness overlay:** the live editor iframe, saved private preview and generic published Vue page use the same EnchantedLoading wrapper. It waits for complete presentation data already supplied by the authenticated/server snapshot response, document fonts, first-screen/eager photographs to settle/decode, Vue updates and two layout frames. It then removes inert/hidden state, fades the veil and transfers the bird to the existing section:invitation Hero marker. Offscreen photos retain lazy loading; this is not a promise to download every hidden/offscreen image before first display. A failed image settles into its normal fallback instead of trapping the loader. A server bootstrap mask covers published HTML until module mount; script failure reveals escaped Blade content. No-JS Blade and Vue SSR remain readable without a client-only hidden state. No artificial minimum wait.
5. **Bird foundation:** EnchantedHummingbird is a small reusable, decorative component with FRONT/LEFT/RIGHT poses from original code-authored SVG. Loading uses a transform hover and bounded wing movement; one 720ms transform-only handoff selects LEFT/RIGHT from actual horizontal displacement and returns to FRONT on landing. A ResizeObserver recalculates the Hero destination on layout changes. Coordinates are transient DOM measurements, never stored in drafts/snapshots. Existing section/milestone hooks are untouched. It rests at Hero and does not follow scrolling or visit other sections. Reduced motion suppresses hover, wings, veil and flight transitions and places a static FRONT bird directly at Hero. It intercepts no clicks or focus. Observer/timer/image listeners are cleaned up on unmount.

### Changed files in this batch

- Controllers: app/Http/Controllers/CmsEditorPreviewController.php; app/Http/Controllers/WeddingPublicationController.php. Add owner publication summary to editor/save JSON and a JSON success response on the existing publisher; authorization/validation/locking stay in the existing services.
- Editor: resources/js/pages/weddings/CmsEditorPreview.vue; resources/js/types/cms-editor.ts; resources/js/components/cms/CmsPublishAction.vue (new); resources/js/components/cms/CmsPreviewFrame.vue.
- Website: resources/js/components/wedding/enchanted-editorial/EnchantedEditorialInvitation.vue; EditorialPhoto.vue; EnchantedHummingbird.vue (new); EnchantedLoading.vue (new), all in that same directory. Also resources/js/public-enchanted.ts; resources/css/enchanted-loading.css (new); resources/assets/enchanted-editorial/hummingbird.svg (new); resources/views/public/enchanted.blade.php; enchanted-photo.blade.php.
- Tests: tests/Feature/CmsPublicationTest.php; tests/Frontend/CmsPolish.browser.test.mjs (new); tests/Frontend/CmsEditor.browser.test.mjs; tests/Frontend/EnchantedEditorial.browser.test.mjs. Existing JSON-success assertions follow the new explicit JSON response; HTML redirect checks remain.
- Affected documentation: this contract, handoff, checklist, QA register, risks, decisions, architecture, CMS/Delight plans, README and asset provenance. No product requirements/schema change or AGENTS/skill policy change.

### Exact focused verification

**87 isolated SQLite tests / 820 assertions; 51 distinct frontend/browser/contract cases verified. Final affected selections pass without unresolved failures/skips.** Not a new full suite or fresh InnoDB run. D03B's 31-case engine evidence remains historical for the unchanged transaction implementation; this batch changes UI/readiness and controller response representation only.

```text
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsPublicationTest.php tests/Feature/CmsDraftTest.php tests/Feature/CmsEditorPreviewTest.php tests/Feature/Weddings/WeddingPublicationTest.php tests/Feature/CmsV2AvailabilityTest.php --no-progress --stop-on-failure --log-junit C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03b1-php.xml
# PASS: 87 tests / 820 assertions; 0 skips
node --test --test-reporter=tap --test-concurrency=1 tests/Frontend/CmsPolish.browser.test.mjs
# PASS: 7 cases on fresh publication-enabled Temp/rsvp-cms-editor-xWwm9R
node --test --test-name-pattern='all focal/crop|keyboard photo|DEL03a real|DEL03a two|DEL03a edits made|DEL03a uncertain|DEL03a1 photo|DEL03a1 hidden|DEL03a2' tests/Frontend/CmsEditor.browser.test.mjs
# PASS: 13 selected editor cases on default-off Temp/rsvp-cms-editor-PZ1Uwx
node --test --test-concurrency=1 tests/Frontend/CmsPublication.browser.test.mjs tests/Frontend/Publication.browser.test.mjs
# PASS: 4 Enchanted cases on fresh Temp/rsvp-cms-editor-UGVTtv; Modern Classic initially skipped for missing host flags
node --test tests/Frontend/Publication.browser.test.mjs
# PASS: 1 Modern Classic case with QA_ISOLATED_SQLITE=1, QA_RSVP_MODE=personal, QA_BASE_URL=http://127.0.0.1:54208
node --test --test-concurrency=1 tests/Frontend/EnchantedEditorial.browser.test.mjs tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/WeddingCountdown.test.mjs
# PASS: 26 renderer/contract/countdown cases
npm.cmd run typecheck
node node_modules/vue-tsc/bin/vue-tsc.js --noEmit -p tests/Frontend/fixtures/tsconfig.json
npm.cmd run build
# PASS: app/fixture types and production build, 2,107 modules
```

PHP environment: APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array, SESSION_DRIVER=array, SESSION_PATH=/, separate unused config/routes cache paths under Temp/rsvp-del03b1-\*. SQLite/EXIF/memory extensions are process-only. No normal .env change, MySQL migration/reset or new migration.

Browser environment: QA_CMS_EDITOR_INFO points to the respective disposable preview.json; QA_PLAYWRIGHT_MODULE=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/horizon-ministry-qa/node_modules/playwright-core/index.mjs; QA_BROWSER_EXECUTABLE=C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe. Renderer run additionally sets QA_ENCHANTED_PHOTOS=1 and QA_ENCHANTED_ARTIFACTS=C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03b1-renderer. Existing private supplied photographs remain confined to their existing test fixture allowlist; new CMS/publication photos are synthetic owner uploads.

Build/Vite checks reuse the previously owner-approved process NODE_OPTIONS=--import=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-tools/rollup-wasm-loader.mjs. Native Windows App Control limitation persists; no new install, dependency manifest/lockfile or security-setting change.

Scoped ESLint and Prettier --check pass on the changed Vue/TS/MJS paths listed above (excluding PHP/Blade; Prettier additionally checks resources/css/enchanted-loading.css). Exact commands: node node_modules/eslint/bin/eslint.js and node node_modules/prettier/bin/prettier.cjs --check followed by those changed paths. The standalone SVG has no inferred Prettier parser in this project and was excluded from that formatting claim; actual SVG rendering/poses are browser-verified. php vendor/bin/pint --dirty --format agent followed by the two changed controllers and tests/Feature/CmsPublicationTest.php passes (ordered import fixed). php -l passes on all three PHP files. git diff --check passes.

Coverage includes: fresh empty library/document/publication, real owner upload, null-versus-failed photo behavior, Publish disabled before save/while dirty, cancel, same-editor publish/republish, saved-vs-live immutability, stale publication/local retention, existing tenant/auth/unsupported/personal guards, existing hidden/history/unpublish media delivery, Fit/Fill/crop/focus, all stored slideshow intervals/hidden preferences, lost-save acknowledgment/recovery, menu/mobile/desktop/hash/focus/sticky offset, waypoint identity/order/hide/remove, paused modules/fonts/images, reduced motion, private live/saved previews, no-JS and script-failure fallback, and Hero reanchoring at 1440/390.

**Corrected initial checks:** the first PHP run omitted SESSION_PATH=/ and inherited an invalid Windows path; the established process override fixed it. New browser helpers initially omitted the Inertia asset-version header, passed relative endpoint URLs without an API base, and used an outdated paragraph label; corrected against actual props/controls. A media pause was initially registered after the first request, so the barrier moved before navigation. An existing publication suite was mistakenly pointed at an already published target; it was rerun successfully on a fresh target without weakening its initial 404 assertion. Regression found and fixed the Vue SSR mask issue; the absent-photo assertion was intentionally updated to no placeholder, while failed-photo assertions remain. The paused countdown clock now advances 64ms for the two readiness frames; no countdown logic change. Screenshot review caught the loading label overlapping the bird; spacing was corrected before the final seven-case run. One Modern Classic opt-in skip was rerun successfully with its required isolated-host flags. A later targeted repeat on an already populated default-off target hit its non-idempotent fixture assumptions (Choose versus Change photo, unchanged Save disabled); those affected cases were rerun on a fresh target, without weakening assertions. Earlier failures/skips are not counted as final passes. No source edits/builds were made during final browser runs.

### Fresh manual target and remaining follow-up

Fresh empty, saved and published review: **http://127.0.0.1:55083/accounts/1/weddings/1/cms-preview**; public **http://127.0.0.1:55083/w/synthetic-editor-preview**. Login editor-preview@example.test; synthetic password supplied directly to owner, stored only in Temp/rsvp-cms-editor-APL95A/preview.json. The fresh-empty test was rerun there and passed; no media was uploaded to this manual target. Its Gallery/library remain empty. Screenshots: qa-del03b1/fresh-published-1440.png and fresh-published-390.png. Test-target loader, editor and handoff captures: Temp/rsvp-cms-editor-xWwm9R/qa-del03b1/{bootstrap-loader,image-loader,editor-ready,editor-published}.png. A harness restart creates a new workspace rather than restoring this one.

Owner review QA-UI-20 remains. DEL-07 still needs its approved visual prototype, more natural artwork/hover/wing refinement, full forward/backward scroll travel across visible document-ordered destinations, direction/orientation coordination, optional controls and measured physical-device/assistive/performance budgets. This scoped loader does not approve that whole animation system. No scroll listener/engine, persistent screen coordinates or saved draft fields were introduced. DEL-04 and the existing public-media/rights/recovery/deployment gates remain unchanged. Stop after DEL-03b.1.

## 11. DEL-04 — personalized Enchanted RSVP integration

2026-10-22 / D-045. **DEL-03b.1 owner-accepted, 14/14 manual checks. DEL-04 implemented and automatically verified; owner acceptance remains QA-UI-21.** This supersedes the temporary DEL-03b personal-Enchanted restriction only for the exact supported version under the existing dual local/testing gates. Production remains hard denied; 5D remains inactive.

### Delivered contract and changed files

- PublishedWedding validates/dispatches the existing immutable revision for authorized personal requests; the generic display projection stays free of guest state. Personal greeting/responses are separate operational data with private/no-store/noindex/referrer controls, neutral metadata and a trusted generic canonical URL. Bearer credentials are resolved by the existing personal controller/service, never emitted into CMS props or snapshots.
- PersonalInvitationLink validates supported publication inside its existing wedding-first credential transaction. WeddingPublication removes only the temporary active-personal-link prohibition. Publication never rotates, revokes or creates credentials. Existing lock order, saved-draft/version/fingerprint checks, rollback, immutable revisions and current-visible-only media delivery remain.
- New EnchantedPersonalInvitation.vue is a thin adapter around the accepted website, existing PersonalRsvpForm and existing personalRsvpTransport. Hero has a private greeting; self/current authorized dependents use the existing response endpoints, statuses, per-person versions and access checks. Pending means unanswered; selecting Pending to reset a saved response remains unsupported by accepted 5C. Partial submissions preserve omitted responses, stale batches fail atomically, and success appears only after a server acknowledgment. Revocation removes private UI after the next denied request; already delivered bearer content cannot be remotely erased.
- EnchantedEditorialInvitation.vue / EditorialRsvpClosing.vue provide scoped private slots. CMS RSVP content remains informational: hiding it does not disable existing 5C operational RSVP. An authorized visitor still gets a separate personal-rsvp section and stable botanical landing marker; generic/editor pages never get this form. The exact old code-owned preview-only sentence is suppressed visually only when the private form is present, without rewriting saved source/history. New starter copy in CmsEditorPreview is suitable for personal links.
- public-enchanted.ts selects the adapter only when the separate allowlisted personal payload exists. resources/views/public/enchanted.blade.php and new enchanted-rsvp.blade.php provide escaped saved-state/name summaries and clear JavaScript-required saving guidance. No-JS editing remains the accepted 5C limitation, rather than a second response implementation. resources/css/enchanted-personal.css scopes the responsive private form/greeting; PublicationPanel.vue removes obsolete blocking guidance. Existing Modern Classic, loader, Hero handoff and section/milestone identities remain.
- Tests: new CmsPersonalRsvpTest.php, CmsPersonalRsvpMySqlTest.php and EnchantedPersonal.browser.test.mjs. Updated CmsPublicationTest.php, CmsPublicationMySqlTest.php, PersonalRsvpConcurrencyTest.php and WeddingDraftConcurrencyTest.php for supported personal publication and the already accepted JSON publication success contract. Existing core response logic/storage was not changed. tests/Fixtures/cms-editor-seed.php and tests/Frontend/cms-editor-preview.mjs add an explicit disposable personal-fixture option only.

### Exact execution evidence

Final selected runs pass with **zero failures/skips**. Counts below are distinct cases within each layer; repeated targeted runs are excluded.

| Layer | Observed result | Command / environment |
| --- | --- | --- |
| Laravel SQLite | **427 tests / 2,589 assertions** | Command below; in-memory SQLite, isolated paths, SESSION_PATH=/ |
| Real InnoDB | **45 cases / 953 assertions** | php vendor/bin/phpunit --group=mysql-concurrency --group=mysql-schema --no-progress --stop-on-failure; independent worker LOCK WAIT assertions |
| New connected Enchanted | **9 cases** | node --test --test-reporter=tap --test-concurrency=1 tests/Frontend/EnchantedPersonal.browser.test.mjs |
| Existing 5C UI/connected Modern Classic | **14 cases** | node --test --test-concurrency=1 tests/Frontend/PersonalRsvpIntegration.browser.test.mjs tests/Frontend/PersonalRsvpUi.browser.test.mjs; QA_ISOLATED_SQLITE=1 QA_RSVP_MODE=personal |
| Renderer/contracts/countdown | **26 cases** | node --test --test-concurrency=1 tests/Frontend/EnchantedEditorial.browser.test.mjs tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/ModernClassicInvitation.test.mjs tests/Frontend/WeddingCountdown.test.mjs |
| Default-off editor regressions | **4 cases** | node --test --test-name-pattern='DEL03a uncertain\|DEL03a1 photo Fit\|DEL03a2 mobile menu\|DEL03a2 waypoint' tests/Frontend/CmsEditor.browser.test.mjs |
| Publish/loader regressions | **5 cases** | node --test --test-name-pattern='editor publish/republish\|bootstrap and image\|reduced motion disables\|live and saved private\|fonts settle' tests/Frontend/CmsPolish.browser.test.mjs |

Total frontend/browser/contract cases: **58**. New connected checks cover public/personal separation and neutral metadata, representative/independent targets, partial save and reload, real CSRF419, delayed acknowledgment without false success, stale/forged batch denial, recover/generate/rotate/revoke/delegation changes, save versus explicit republish, hidden informational RSVP, keyboard/mobile/no-JS/reduced motion, unpublish/re-publish, per-key 20/21 rate limiting and Retry-After. Existing editor/loader checks retain Fit/Fill/slideshow preferences, uncertain-save recovery, waypoint order/hide/remove and delayed script/font/image readiness.

Exact Laravel selection (process overrides APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array, SESSION_DRIVER=array, SESSION_PATH=/; temporary config/routes paths):

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsPersonalRsvpTest.php tests/Feature/CmsPublicationTest.php tests/Feature/PersonalRsvpHttpTest.php tests/Feature/PersonalRsvpServicesTest.php tests/Feature/CmsDraftTest.php tests/Feature/CmsEditorPreviewTest.php tests/Feature/CmsV2AvailabilityTest.php tests/Feature/CmsV2ContractTest.php tests/Feature/CmsMediaPersistenceTest.php tests/Feature/CmsReusableContentTest.php tests/Feature/Weddings/WeddingPublicationTest.php tests/Feature/Weddings/WeddingInvitationTest.php tests/Feature/Weddings/WeddingAttendanceTest.php --no-progress --stop-on-failure
```

Engine target: local 127.0.0.1:3306 / LAPTOP-2129KHGH, MySQL 26.7.0, InnoDB, REPEATABLE-READ; strict ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION. Disposable schema wedding_rsvp_cms_test_20261022_del04_b628bbef and a dedicated schema-only principal plus PROCESS inspection were verified; development SELECT was denied. Only the existing 18 migrations were applied to that disposable schema. All31 existing engine schedules were rerun plus 14 exact-Enchanted personal schedules (issuance, independent, same-guest, designation, revoke, rotate, reassign, representative-batch, rename, retention, delegation-revoke, unpublish, republish, rotate-revoke). Schema/principal/PROCESS grant and temporary credential file were removed and absence verified. Evidence: Temp/rsvp-del04-mysql/{metadata.json,personal-results.xml,final-engine-results.xml}; SQLite JUnit: Temp/rsvp-del04-php.xml. No development database reset/migration or new migration.

Static checks pass: npm.cmd run typecheck; node node_modules/vue-tsc/bin/vue-tsc.js --noEmit -p tests/Frontend/fixtures/tsconfig.json; scoped ESLint and Prettier on affected Vue/TS/CSS/MJS; php vendor/bin/pint --dirty --format agent on the eleven affected PHP files; PHP syntax and git diff --check. npm.cmd run build passes, **2,110 modules**, using previously owner-approved temporary Rollup4.64.7 WASM loader via NODE_OPTIONS=--import=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-tools/rollup-wasm-loader.mjs. Browser uses installed Edge and external existing playwright-core via QA_PLAYWRIGHT_MODULE; no project dependency or OS security change. Fixture Vite allowlist403 is an intentional successful private-file probe.

Corrected initial verification issues: test UTF-8 BOM removed; refreshed newly published test models to hydrate publication pointers and DB-default timezone before fingerprinting; restored test environment before migration teardown. Two historical engine assertions expected302 although JSON publishing now correctly returns200 under accepted DEL-03b.1; only status expectations were aligned, preserving all concurrency/state assertions. Screenshot tests now foreground inactive tabs before viewport capture. No-JS guidance uses ordinary fallback text, which Vue replaces after successful mounting. Final selections were rerun successfully; earlier failures are not counted as passes.

### Fresh isolated owner and guest walkthrough

Manual target: **http://127.0.0.1:50782/accounts/1/weddings/1/cms-preview**. Public: http://127.0.0.1:50782/w/synthetic-editor-preview. Owner email: editor-preview@example.test. Synthetic password and bearer links are supplied directly to the owner and stored only in Temp/rsvp-cms-editor-zda747/preview.json; do not copy them into tracked documentation. The test fixture is entirely synthetic, gallery/library empty, saved and published, all responses initially Pending. The final read-only privacy/screenshot case passes there; no answers or credentials were changed on the manual target.

1. Sign in in a separate owner window; overview/Guests are under /accounts/1/weddings/1. Santos Family contains Maya, Nico, Lia and Mateo Santos.
2. Open Maya's personal link: greeting Maya; form only Maya + authorized dependent Nico. Submit just one answer, refresh, then complete/change the other. Omitted answers stay Pending or retain saved responses. Lia's independent link shows only Lia. Generic public page has no private names/answers/form.
3. In Guests > RSVP Access, Recover preserves Maya's existing URL. Generate Mateo's first link after publication. Explicit Regenerate/ Revoke or dependent reassignment invalidates prior authority as in existing 5C; recovery/publish alone never changes credentials.
4. Edit/save CMS text: live generic and personal pages retain the immutable prior snapshot. Explicit Republish updates both website presentations while preserving guest credentials/answers. Hide informational RSVP and republish: private operational form remains available without exposing hidden copy. Unpublish denies public/personal access; publishing again restores access to retained responses.
5. Check mobile hamburger/keyboard focus, saved responses, no-JS summary/clear saving guidance and reduced-motion loader. With a saved answer, Pending is a display state, not a reset action.

Desktop/mobile screenshots: Temp/rsvp-cms-editor-zda747/qa-del04/{personal-1440.png,personal-390.png}. Additional owner-access/no-JS synthetic screenshots: Temp/rsvp-cms-editor-tOgmCd/qa-del04/{owner-access-1440.png,personal-no-js-390.png}. Automated mutation target tOgmCd is separate from this fresh manual target. To recreate: set QA_CMS_PUBLISHING=1 and QA_CMS_PERSONAL=1, then node tests/Frontend/cms-editor-preview.mjs; every run creates a new guarded disposable SQLite/storage target. Run the nine-case personal suite on a separate fresh target, not the owner's review data.

### Remaining gates and stop

QA-UI-21 owner acceptance remains. QA-OPS-02 external web/proxy/APM/analytics/body-capture token inspection, key custody/restore, shared deployed limiter/proxy behavior, storage ACL/retention/commercial rights, physical iOS/Android/assistive/performance and the enumerated composite crash/upload/cleanup/parent/capacity races remain open. Existing engine passes do not imply these different schedules were tested. PHP development-server logs can contain synthetic bearer paths; this target is local QA only. No customer credentials/data were used. Full DEL-07 flight/orientation coordinator, new intro/music/celebration and unfinished 5D remain deferred. No production activation/deployment, development migration/reset, package install, commit or push. **Stop after DEL-04 for manual acceptance.**

## DEL-04.1 — Wedding Countdown and editor navigation polish

### Scope and contract

D-046 records the owner's explicit DEL-04 acceptance (20/20) and authorizes this focused batch. CMS-v2 Enchanted now accepts an optional `countdown` section between event details and story. The new Enchanted starter has it enabled with editable heading/subtitle only. It does not duplicate wedding date, time, timezone or a calculated deadline in CMS JSON. The private editor uses current Wedding facts; generic published and authorized personal views use immutable revision facts. The section retains hidden authored content, participates in strict validation, draft Save/reload, projection and publication/republish, and exposes `section:countdown`. Other templates reject it unless their manifest opts in. There is no version bump, migration or new storage. Existing documents/snapshots are not rewritten.

Vue resolves the canonical local wedding date/time in the supplied IANA zone, shows days/hours/minutes/seconds, stops at the wedding instant and presents the arrival message. Invalid/missing date, time or zone does not fabricate numbers. The Blade fallback shows only safely parsed date/time and a confirmation note; it has no fake countdown digits. Existing timer, reduced-motion, welcome/landing, mobile menu and personal RSVP behavior remains unchanged. The editor header uses the compact **Back to wedding** link while existing Save and confirmed Publish/Republish keep the saved-version and immutable-snapshot checks.

DEL-04 is owner-accepted 20/20 under D-046. DEL-04.1 is implemented and automatically verified; QA-UI-22 manual visual acceptance remains. No Guest Guide, RSVP/5D changes, new bird art/flight/wing behavior, deployment or activation is included.

### Files changed for this batch

- Contract/default/editor types and presentation: `CmsV2Contract`, `CmsEditorPreview`, `EnchantedWebsite`, `cms-v2.ts`, `cms-editor.ts`, `CmsPreviewFrame.vue`, `CmsEditorPreview.vue`.
- Published static fallback: `resources/views/public/enchanted.blade.php`.
- Regression coverage: `CmsV2ContractTest`, `CmsEditorPreviewTest`, `CmsDraftTest`, `CmsPublicationTest`, `CmsPersonalRsvpTest`, `CmsPublicationMySqlTest`, the shared TS CMS contract test, editor browser test and publication browser test. Countdown/timezone helper and component tests were reused.
- Handoff and acceptance records: `docs/architecture.md`, `docs/checklist.md`, `docs/decisions.md` (D-046), `docs/handoff.md`, `docs/mvp_roadmap.md`, `docs/qa/verification_register.md`, `docs/risks_and_followups.md`, and this closeout. The DEL-07 plan still records the FRONT/LEFT/RIGHT states and document-ordered, visible-only landing-point contract.

### Exact verification

All Laravel tests used isolated in-memory SQLite with process-only extensions and overrides `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `SESSION_PATH=/`. No development DB was reset or migrated.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsV2ContractTest.php tests/Feature/CmsEditorPreviewTest.php tests/Feature/CmsPublicationTest.php
```

Result: **113 tests / 568 assertions passed**. This includes new-vs-legacy contract behavior, hidden Countdown retention, snapshot facts, hidden-section media and publish/republish immutability.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsDraftTest.php tests/Feature/CmsPersonalRsvpTest.php
```

Result: **27 tests / 291 assertions passed**; existing RSVP and draft behavior stays intact.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsV2AvailabilityTest.php tests/Feature/Weddings/WeddingPublicationTest.php tests/Feature/GuestRsvpTest.php
```

Result: **28 tests / 243 assertions passed**, including Modern Classic publication and legacy RSVP regression coverage.

```powershell
node --test tests/Frontend/WeddingCountdown.test.mjs tests/Frontend/CmsV2Contracts.test.mjs
node --test --test-name-pattern='countdown uses Manila facts' tests/Frontend/EnchantedEditorial.browser.test.mjs
```

Results: **6 unit/contract tests plus 1 connected Edge clock case passed**. The browser case starts before the event, advances through zero to the arrival message, checks the Manila/UTC conversion from another browser zone, and covers past/missing facts.

Connected editor and publication cases on verified disposable publication-enabled SQLite/Edge targets:

```powershell
node --test --test-name-pattern='DEL04.1 Countdown' tests/Frontend/CmsEditor.browser.test.mjs
node --test --test-name-pattern='DEL03b saved draft publishes' tests/Frontend/CmsPublication.browser.test.mjs
```

Both selected cases passed. The editor case verifies default order/enabled state, heading/subtitle Save/reload, hidden choice retention and the stable landing marker. The publication case verifies saved snapshot immutability until explicit republish. A separate connected Edge run published from the editor, verified an unsaved edit remained private, saved, confirmed Republish and verified the live snapshot changed only afterward. A fresh empty-media target confirmed the starter Gallery and private library are empty, published the countdown from the editor, and checked four live units, the marker, the no-JavaScript date/time fallback, mobile width and `Back to wedding`.

The standalone selection `node --test --test-name-pattern='no JavaScript' tests/Frontend/CmsPublication.browser.test.mjs` stalled before a result twice and is **not counted as a pass**. Direct connected Edge assertions and screenshot on the same published fallback passed: page status 200, heading/date/time visible, no fabricated `.ee-countdown-units`, section navigation available. The fallback screenshot is preserved below.

Static checks:

```powershell
npm.cmd run typecheck
npx.cmd prettier --check resources/js/types/cms-v2.ts resources/js/lib/cms-editor.ts resources/js/pages/weddings/CmsEditorPreview.vue resources/js/components/cms/CmsPreviewFrame.vue resources/js/components/wedding/enchanted-editorial/WeddingCountdown.vue tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/CmsEditor.browser.test.mjs tests/Frontend/CmsPublication.browser.test.mjs
npx.cmd eslint resources/js/types/cms-v2.ts resources/js/lib/cms-editor.ts resources/js/pages/weddings/CmsEditorPreview.vue resources/js/components/cms/CmsPreviewFrame.vue resources/js/components/wedding/enchanted-editorial/WeddingCountdown.vue tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/CmsEditor.browser.test.mjs tests/Frontend/CmsPublication.browser.test.mjs
vendor/bin/pint --format agent app/Services/CmsV2Contract.php app/Services/CmsEditorPreview.php app/Services/EnchantedWebsite.php tests/Feature/CmsV2ContractTest.php tests/Feature/CmsEditorPreviewTest.php tests/Feature/CmsPublicationTest.php
php -l resources/views/public/enchanted.blade.php
git diff --check
$env:NODE_OPTIONS='--import=file:///C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-del03a2-tools/rollup-wasm-loader.mjs'; npm.cmd run build
```

Results: typecheck, formatting, ESLint, Pint and syntax checks passed; build passed with 2,110 modules using the previously owner-approved temporary matching Rollup WASM loader. There is no project dependency change. No new InnoDB schedule was run because no DB/schema or locking behavior changed.

### Isolated owner preview and screenshots

Fresh synthetic owner/editor preview: **http://127.0.0.1:54357/accounts/1/weddings/1/cms-preview**. Published Enchanted sample: **http://127.0.0.1:54357/w/synthetic-editor-preview**. Login: `editor-preview@example.test`. The synthetic password is supplied directly to the owner and held only in `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-Y0k20J/preview.json`; do not copy it into tracked documentation. Both CMS preview/publishing gates are enabled only for this isolated local/testing target. The draft and gallery/library contain no uploaded or demonstration photographs.

Desktop/mobile editor and published/no-JavaScript captures: `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-Y0k20J/qa-del04-1/{editor-desktop.png,editor-mobile.png,published-desktop.png,published-mobile.png,published-no-js-mobile.png}`. Live captures wait for the hummingbird first-Hero handoff to reach its landed state. Current published title is “Our favorite day is almost here”; an explicit editor Republish updated it after the saved snapshot check.

QA-UI-22 owner visual acceptance remains. Production activation, 5D, full DEL-07 scroll-flight animation, mobile physical-device/assistive-tech/performance checks and existing external privacy/race/release gates remain out of scope. No development DB reset/migration, unrelated changes, commit or push. **Stop after DEL-04.1.**

## DEL-04.2 — Guest Guide

Owner acceptance recorded 2026-10-22 under D-048: **DEL-04.2 OWNER-ACCEPTED**; no check count supplied. The implementation-time review state below is historical. DEL-07a's separate artwork/motion prototype now awaits visual approval; Guest Guide contracts and evidence are preserved.

### Scope and contract

D-047 records the owner's acceptance of DEL-04.1 (14/14) and authorizes this batch. New Enchanted CMS-v2 starter documents receive one Guest Guide section between Entourage and RSVP information. The section has independently optional Attire, Gifts and FAQs blocks. Attire accepts bounded dress-code/description text and up to five UUID-labeled six-digit hex swatches; Gifts accepts bounded heading/message and an optional credential-free HTTPS registry URL; FAQs accept at most seven bounded UUID question/answer pairs. New FAQs start with three editable neutral suggestions: “Are my kids welcome?”, “Can I bring a plus one?” and “How do I RSVP?”. No starter language assumes child or plus-one restrictions.

The section reuses the current validator, editor, draft Save/reload, private preview, immutable publish/republish and generic/personal Enchanted renderers. All free text is escaped; no arbitrary HTML, payment fields, RSVP answers or bearer URLs are accepted. Hidden authored content remains in saved source/snapshots and is excluded from public projection. Older drafts and published revisions are not silently rewritten: an owner can add the section explicitly to an older draft. Generic invitations show only neutral RSVP instructions; the personal invitation's RSVP FAQ link resolves to the already-authorized active RSVP anchor. Native disclosure semantics provide keyboard and no-JavaScript behavior. There is one section navigation item and one stable `section:guest-guide` landing marker; no bird flight or new animation was added.

### Changed files

- Contract/defaults and renderer projection: `app/Services/CmsV2Contract.php`, `app/Services/CmsEditorPreview.php`, `app/Services/CmsV2Document.php`, `app/Services/EnchantedWebsite.php`, `resources/views/public/enchanted.blade.php`.
- Frontend source/presentation: `resources/js/types/cms-v2.ts`, `resources/js/types/enchanted-editorial.ts`, `resources/js/lib/cms-editor.ts`, `resources/js/lib/cms-guest-guide.ts`, `resources/js/pages/weddings/CmsEditorPreview.vue`, `resources/js/components/wedding/enchanted-editorial/EditorialGuestGuide.vue`, `resources/js/components/wedding/enchanted-editorial/EnchantedEditorialInvitation.vue`, `resources/js/components/cms/CmsPreviewFrame.vue`, `resources/css/cms-editor.css`, `resources/css/enchanted-editorial-sections.css`.
- Focused regression coverage: `tests/Feature/CmsV2ContractTest.php`, `tests/Feature/CmsEditorPreviewTest.php`, `tests/Feature/CmsPublicationTest.php`, `tests/Feature/CmsPersonalRsvpTest.php`, `tests/Fixtures/cms-v2-contracts.json`, `tests/Frontend/CmsGuestGuide.test.mjs`, `tests/Frontend/CmsV2Contracts.test.mjs`, `tests/Frontend/CmsEditor.browser.test.mjs`, `tests/Frontend/CmsPublication.browser.test.mjs`.
- Acceptance/evidence updates: `docs/architecture.md`, `docs/checklist.md`, `docs/decisions.md` (D-047), `docs/handoff.md`, `docs/mvp_roadmap.md`, `docs/qa/verification_register.md`, `docs/risks_and_followups.md`, and this closeout.

### Exact verification

The focused PHP suite used process-only SQLite extensions/environment overrides (`APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, array cache/session). It did not use or reset the development database:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit tests/Feature/CmsV2ContractTest.php tests/Feature/CmsEditorPreviewTest.php tests/Feature/CmsDraftTest.php tests/Feature/CmsPublicationTest.php tests/Feature/CmsPersonalRsvpTest.php tests/Feature/CmsV2AvailabilityTest.php tests/Feature/Weddings/WeddingPublicationTest.php tests/Feature/GuestRsvpTest.php
```

Result: **171 tests / 1,152 assertions passed.** This includes new versus legacy source, section/block visibility and retention/projection, safe URL/token/markup rejection, FAQ and swatch bounds, immutable snapshots, personal RSVP anchor and generic privacy, plus Modern Classic/RSVP regressions.

```powershell
node --test tests/Frontend/CmsGuestGuide.test.mjs tests/Frontend/CmsV2Contracts.test.mjs tests/Frontend/WeddingCountdown.test.mjs
```

Result: **8 tests passed.** Runtime frontend checks cover valid schema, upper/lower bounds and unsafe links/content. Connected browser checks ran against an explicitly publication/personal-enabled disposable SQLite target:

```powershell
node --test --test-name-pattern='DEL04.2 Guest Guide' tests/Frontend/CmsEditor.browser.test.mjs
node --test --test-name-pattern='DEL04.2 published Guest Guide' tests/Frontend/CmsPublication.browser.test.mjs
```

Both selected Edge tests passed without skips. The editor case verifies explicit opt-in for an old draft, section ordering, independent visibility, color picker/five-swatch bound, FAQ add/remove/reorder/seven-item bound, private preview, Save/reload and hidden data retention. The publication case verifies safe generic guidance, personal `#rsvp`, one navigation item, mobile menu and width, keyboard accordion, and native no-JavaScript fallback. Fresh screenshots were separately captured after these mutating tests, on a clean isolated owner target.

`npm.cmd run typecheck`, scoped ESLint, scoped Prettier, scoped `vendor/bin/pint --format agent`, PHP/Blade syntax checks, `git diff --check`, and `npm.cmd run build` pass. The build used the previously owner-approved temporary matching Rollup WASM loader (no dependency/project change). No new InnoDB schedule was run because no database schema, persistence, lock or publication transaction changed.

### Isolated owner review

Fresh synthetic editor: **http://127.0.0.1:54900/accounts/1/weddings/1/cms-preview**. Generic published sample: **http://127.0.0.1:54900/w/synthetic-editor-preview**. Login: `editor-preview@example.test`. The one-time synthetic password is supplied directly in the owner review response and stored only in `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-LrKACO/preview.json`; keep it out of tracked documentation. This target uses disposable SQLite/storage/session data, with CMS preview and publication enabled only for the local review fixture; a synthetic personal invitation is available only from the same target.

Desktop/mobile editor, public published and mobile no-JavaScript screenshots are in `C:/Users/UNI-PRESIDENT/AppData/Local/Temp/rsvp-cms-editor-LrKACO/qa-del04-2/` (`editor-1440.png`, `editor-390.png`, `published-1440.png`, `published-390.png`, `published-no-js-390.png`). The clean published sample has one Guest Guide, all three starter FAQs, and no Guest Guide image elements. QA-UI-23 owner visual acceptance remains. Physical Safari/Android/screen-reader/slow-network and existing deployment/privacy/race/media-rights gates remain separately open; no public activation, migration, DB reset, commits or pushes. **Stop after DEL-04.2 for manual acceptance.**
