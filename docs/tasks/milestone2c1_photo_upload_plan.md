# Milestone 2C.1 — Wedding Hero Photo Upload

Approved for implementation by the owner's “Implement the plan” instruction on 2026-10-16. This records the approved plan; observed verification and remaining acceptance work belong in checklist.md and handoff.md.

## 1. Scope and acceptance

One optional private photo in Customize invitation: select, local preview, Save Draft, reopen, replace, remove, and undo a pending photo change. Saved-only preview remains separate from unsaved editor state. Preserve Modern Classic and wedding facts; no gallery, crop editor, original retention, publishing, media library, or template switching.

Owner selected uncropped placement between the existing invitation header and celebration details, preserving natural aspect ratio; 5 MiB uploads with operator-prepared runtime support.

Acceptance requires owner-scoped reads/writes, atomic draft reference/content/version changes, no lost updates, safe cleanup, automated checks, responsive browser review, and separate owner acceptance.

## 2. Existing findings

One unique `website_drafts` row per wedding; schema version 1 JSON; a save counter; parent wedding row locks covering first saves and updates; controlled rejection of unsupported schema versions. Both preview surfaces share ModernClassicInvitation. Wedding facts remain relational.

Strict JSON booleans need special care when uploading multipart forms. Existing local storage is private-rooted but permits signed serving; wedding photos need their own non-serving private disk. CLI GD supports JPEG/PNG/WebP; Fileinfo is enabled, EXIF is not globally enabled. Observed CLI defaults: 2M upload, 8M POST, 128M memory. The S3 adapter is absent. Web-runtime settings must be checked separately. `docs/skills.md` is absent; repository skills supply relevant guidance.

## 3. Storage

Use Laravel Filesystem disk `wedding_photos`, local root `storage/app/private/wedding-photos`, private visibility, `serve=false`, and throwing writes. Keys: `accounts/{account_id}/weddings/{wedding_id}/hero/{uuid}.webp`, entirely server-generated.

An authenticated, verified, nested wedding route streams only the current photo after owner authorization and schema validation. UUID URLs are identifiers, not authorization. Return WebP, private/no-store caching, and nosniff; never expose disk paths, public links, or signed bearer URLs.

Future S3-compatible storage requires separate adapter/configuration approval and private bucket policies. Existing object references remain vendor-neutral. Local production storage must be persistent and backed up.

## 4. Database

Add nullable `hero_photo_disk` and `hero_photo_path` columns to `website_drafts`; set/clear together server-side. No media table. No binary content in JSON. Keep JSON schema version 1 because its contract is unchanged; preserve the unknown-version guard. Use the existing counter for the complete draft.

## 5. Upload/removal workflow

Extend the existing update request with `hero_photo_action` (`keep`, `replace`, `remove`; omitted means keep) and a file required only for replace. Reject other combinations and unknown fields.

Use multipart POST with `_method=put`; encode content as JSON, decode it with bounded depth/size, and retain existing strict validation. Existing JSON submissions remain supported.

Authorize/validate → normalize to temporary bytes → lock wedding/reload draft → schema and counter checks → write immutable replacement → atomically save content/reference/counter → confirmed commit → delete superseded file. Removal clears the reference; keep preserves it. No standalone upload-token workflow.

## 6. Frontend

Extend InvitationEditor with native file selection, format/size/privacy guidance, local object URL preview, replace/remove/undo, upload progress, dirty state, and actionable errors. Revoke object URLs on replacement/reset/unmount. Keep local state on validation/stale failure; explain reselecting after reload when needed.

Use a shared nullable `{ url: string }` photo presentation prop. The editor passes its local blob URL until saving; standalone preview receives only the saved authorized URL. ModernClassicInvitation remains the sole renderer; missing images hide gracefully with editor feedback.

Apply Horizon Minimal only to portal controls, including accessible removal confirmation, focus, light/dark/system themes, and responsive layout. Preserve invitation colors and typography.

## 7. Security and normalization

Verify actual image content: JPEG/PNG/WebP only, maximum 5 MiB, 12 million pixels, and 6,000 pixels per dimension. Bound dimensions before decoding. Require successful decode and re-encode; reject unsupported active formats, arbitrary paths, remote images, and ownership fields.

Native GD produces static WebP at quality 82, long edge at most 1,600 pixels, without upscaling; preserve transparency. Apply all eight JPEG EXIF orientations before dropping metadata. Re-encoding discards EXIF/GPS and appended content; originals are never persisted. Animation is not retained. No Composer/npm dependency is required.

Operator prerequisites: GD/Fileinfo/EXIF and JPEG/PNG/WebP support in the web runtime; upload limit at least 5M; POST/proxy limits above multipart overhead; memory at least 256M. Missing processing capabilities produce a controlled field error. Do not change system-wide settings automatically.

## 8. Consistency and cleanup

Normalize before locking, but create durable objects only after locked stale/schema checks. Rejected stale uploads leave no durable replacement. Storage failure leaves the database unchanged. Rollback cleanup checks references before deleting candidates; uncertain commit outcomes must never delete referenced files. Delete the old file only after confirmed commit. Cleanup failures retain files for retry, not a false save failure.

Provide `wedding-photos:prune`, dry-run by default and destructive only with `--delete`: generated hero keys older than 24 hours, rechecked under the save's wedding lock, excluding referenced/recent/unknown-schema objects. Recover crash and deleted-wedding orphans. Initial execution is operator-driven; no new scheduler infrastructure.

Publishing must later snapshot references and extend retention checks before published assets coexist with mutable drafts. Do not implement publication now.

## 9. Testing

Storage fake + isolated SQLite: formats/normalization, orientation/metadata, bounds/malformed files, multipart JSON/strict booleans, unauthorized/cross-account/nested access, keep/replace/remove/reopen, private delivery/missing files, stale/schema rejection, unchanged wedding facts, storage failure, rollback, deferred cleanup, and dry-run pruning safety. Existing renderer tests cover photo/no-photo and previous CMS behavior.

Extend the opt-in MySQL harness for simultaneous first photo saves and replacements using independent connections and shared test-only storage. Require one winner/one conflict, one row/increment, the winning photo/content, and no surviving loser. Never use `wedding_rsvp`; unavailable approved disposable MySQL means pending verification, not permission to provision/reset databases.

Browser: local-versus-saved preview, multipart submission, persistence, replacement/removal/undo, stale recovery, validation, and cross-account delivery. Review 1440/1024/390px, both themes, keyboard dialog behavior, focus, no overflow, reduced motion, and Horizon Minimal rubric. Owner acceptance is separate.

## 10. Implementation sequence

Confirm runtime/test prerequisites → additive schema/private disk → processing/validation → locked save/private delivery → shared photo props/editor → cleanup/tests → checks/browser review → affected documentation sync. Do not disturb existing manual QA data or servers.

Checks: relevant/full isolated Laravel suite; approved opt-in MySQL group; `node --test tests/Frontend/ModernClassicInvitation.test.mjs`; TypeScript; ESLint without unrelated autofixes; targeted Prettier; Pint; Vite build. Record exact outcomes and pending work.

## 11. Files

Create: additive hero-photo migration; `app/Services/WeddingHeroPhotoProcessor.php`; `app/Console/Commands/PruneWeddingHeroPhotos.php`; photo/cleanup feature tests; synthetic JPEG fixture.

Modify: existing WebsiteDraft, invitation request/controller, WeddingController preview, web routes, filesystem config, shared TypeScript types, InvitationEditor/Preview/ModernClassicInvitation, existing renderer/concurrency tests and worker. Update affected setup/product/architecture/tracking docs after verification. Never overwrite unrelated work or commit/push.

## 12. Approvals and implementation prompt

The owner approved implementation and the selected placement/upload approach. Still pending operationally: actual web-runtime readiness, disposable MySQL access, additive migration deployment, and owner QA. Database provisioning, global PHP changes, publishing, and new dependencies are not implicitly authorized.

### Implementation-ready Codex prompt

Implement only this approved Milestone 2C.1. Read AGENTS.md, handoff, relevant docs and skills; preserve dirty work. Load Horizon Minimal before portal UI changes. Add one private normalized photo to the existing Save Draft flow with two nullable reference columns, authorized streaming, shared Modern Classic rendering, unchanged JSON schema/strict validation, locked concurrency protection, local preview, replacement/removal, and safe orphan cleanup. Add meaningful isolated/failure/security/renderer/MySQL coverage, run checks and browser QA, and synchronize affected docs with observed outcomes. Never test against development MySQL, provision/reset databases or change global PHP settings without explicit approval. No publishing, gallery, template engine, dependencies, unrelated refactors, commit, push, or assumed owner acceptance.
