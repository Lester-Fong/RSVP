# Risks and follow-ups

Reviewed: 2026-10-18. This is the issue register; architecture and decisions remain the sources for implemented contracts and approved product behavior. Review relevant entries during each authorized implementation/fix and update evidence, status and next action. No unattended maintenance is configured.

Priorities: **P0** blocks current milestone acceptance; **P1** needs resolution or an explicit deployment decision before real distribution/customer use; **P2** is scoped reliability, usability or maintenance work; **P3** is future product work. Accepted MVP constraints are not bugs or approval to build their proposed alternatives. Unverified risks are not confirmed failures. Automated success does not imply owner acceptance.

## RF-001 — Premature RSVP throttling

- Category: Confirmed bug / test isolation.
- Description: During investigation on 2026-10-16, actual local HTTP returned CSRF 200 followed by RSVP 429, limit 20, remaining 0 and Retry-After 5461 seconds. All links shared the IP-only submission counter. An earlier test run inherited file caching and time-travel tests left future-dated counters in development storage. CSRF setup had a different bucket; it was not consuming the submission counter. No frontend automatic retry was found.
- Impact: A normal first response could be blocked by another group's traffic or leftover test state; the fixed “one minute” message concealed the actual wait.
- Priority: P0.
- Status: Fix verified automatically: 40 focused Laravel tests, 237 full-suite tests and all ten frontend tests pass, including persistent-cache HTTP/browser coverage. Owner retest/5A acceptance pending. Not marked resolved.
- Proposed action: Keep the 20/minute restrictive allowance per hashed invitation credential and an independent 120/minute IP abuse guard. Keep CSRF setup separate at 60/minute/IP. PHPUnit overrides both environment/server cache adapters to array. Only the diagnosed future-dated localhost RSVP/view counters and timers were removed (four entries), not the application cache. Return actual Retry-After in JSON and feedback, including setup failures. Retain the verified persistent-cache regression and complete owner retest.
- Target milestone: 5A acceptance blocker. Evidence: `GuestRsvpTest`, `GuestRsvp.browser.test.mjs`, handoff verification.

## RF-002 — Eight skipped MySQL concurrency cases

- Category: Unverified technical behavior.
- Description: Four draft/photo, two publication and two invitation-link generation/rotation cases remain opt-in and skipped. SQLite success and inspected MySQL schema do not prove InnoDB concurrency.
- Impact: Intended lock/version guarantees have not been empirically verified on the production database engine.
- Priority: P1.
- Status: Unverified; eight tests skipped, no pass claimed.
- Proposed action: Run the existing cases on an approved disposable `CMS_MYSQL_TEST_DATABASE` with current migrations and INNODB_TRX privileges; verify independent workers actually wait on the blocker lock. Never reset development MySQL.
- Target milestone: Before production concurrency sign-off. See `WeddingDraftConcurrencyTest` and README.

## RF-003 — Additional guest/RSVP races

- Category: Unverified technical behavior.
- Description: Guest capacity/final-member changes, deletion versus link issuance, and RSVP writes versus other submissions, rotation, revocation, unpublication or member removal lack actual isolated MySQL race verification.
- Impact: Lock-order reasoning, stale-update tests and simultaneous single-process PHP/SQLite requests cannot exclude engine-specific races/deadlocks.
- Priority: P1.
- Status: Unverified; additional scenarios are not included in the eight existing cases.
- Proposed action: Add/run focused independent-worker MySQL cases using barriers and verify all-or-nothing state, retention and token/publication revalidation.
- Target milestone: 5A security/reliability follow-up before production use; revisit before future lifecycle features.

## RF-004 — Recorded answers cannot return to Pending

- Category: Accepted MVP limitation / product decision.
- Description: Pending means no response row. A recorded answer can change between attending and declined, but cannot be cleared back to pending in 5A (D-016).
- Impact: A link holder cannot withdraw an answer without choosing the other attendance state.
- Priority: P2.
- Status: Confirmed current behavior; future clearing policy not approved.
- Proposed action: Decide whether clearing means withdrawal, deletion or another retained state, including timestamps/versioning, before scoping controls.
- Target milestone: Consider during 5B planning; no implementation authorized by this entry.

## RF-005 — Saving requires JavaScript

- Category: Accepted MVP limitation / accessibility follow-up.
- Description: Server HTML shows saved choices without JavaScript; submitting/updating requires the Vue form and same-origin CSRF setup.
- Impact: JavaScript-disabled or failed-script clients can read but cannot respond.
- Priority: P2.
- Status: Confirmed and documented.
- Proposed action: Assess progressive enhancement if required, preserving bearer privacy, CSRF and stale-save protection; do not add an SSR server merely for this.
- Target milestone: Future accessibility scope; not part of 5A acceptance changes.

## RF-006 — Bearer links authorize the entire group

- Category: Accepted security tradeoff.
- Description: Anyone possessing an active invitation link may view its group's names/answers and respond for every named member. This is intentional under D-015/D-016; it is not individual identity verification.
- Impact: Recipients, forwarded links and preview services holding the URL can access permitted group data. Noindex and generic metadata are not access control.
- Priority: P1.
- Status: Accepted MVP authority model; owner/guest warnings and revocation/rotation exist.
- Proposed action: Retain clear sharing guidance and tested revocation/rotation; reconsider only through an approved identity/access change. Do not infer delivery from copying.
- Target milestone: Before real distribution and ongoing onboarding.

## RF-007 — Group membership is live

- Category: Accepted MVP behavior / lifecycle decision.
- Description: Saved group labels/names, additions and permitted removals change what existing active-link holders see. Wedding content remains a published snapshot; the roster is not snapshotted.
- Impact: Sharing a link does not freeze its guest list. Renaming retains responses; responded people and issued groups are retained.
- Priority: P2.
- Status: Confirmed D-015/D-016 behavior; explained in the link dialog.
- Proposed action: Keep owner guidance; reconsider historical identity/archive semantics before delivery/history features.
- Target milestone: Current guidance; future lifecycle scope.

## RF-008 — Raw invitation credentials in external logs

- Category: Confirmed local exposure / unverified deployment security.
- Description: Standard Artisan/PHP development-server output prints bearer request paths. Application log redaction cannot redact PHP/web-server/proxy/access/APM/analytics streams or independently captured request/response bodies.
- Impact: Anyone obtaining a raw active URL can exercise its group authority; captured recovery bodies also contain credentials.
- Priority: P1.
- Status: Local exposure confirmed; deployment protections remain unverified.
- Proposed action: Require HTTPS and verify exclusion/redaction of `/invite/*`, recovery/submission bodies and URL analytics in every deployed logging layer. Do not modify vendor internals or invent server infrastructure just to conceal local output.
- Target milestone: Before distributing real links. Application redaction tests are not external logging evidence.

## RF-009 — Tracked credential example

- Category: Confirmed security finding.
- Description: The prior checkpoint review found a populated database password in tracked `.env.example`, already in HEAD and previously matching a local credential. No value is reproduced here.
- Impact: Repository sharing/history can disclose a usable credential.
- Priority: P1.
- Status: Unresolved; this fix does not alter credentials, `.env.example` or Git history.
- Proposed action: Replace the example with a placeholder, rotate the affected credential and agree on history/incident handling before sharing. Removing the current text does not erase history.
- Target milestone: Security checkpoint before repository sharing/deployment.

## RF-010 — Historical published media retention

- Category: Product/operations decision.
- Description: Immutable revisions can keep old photo references alive. No approved historical revision/media expiry, cleanup window or owner rollback/history interface exists.
- Impact: Storage and personal-media retention can grow; indiscriminate cleanup could break historical/published snapshots.
- Priority: P2.
- Status: Policy unresolved; existing reference-preserving behavior is implemented.
- Proposed action: Define retention/history rules and clean only safely unreferenced media after approval; preserve active published media when drafts change.
- Target milestone: Prelaunch retention review / separately scoped revision history (P-003).

## RF-011 — Guest/response/wedding data lifecycle

- Category: Product/security decision.
- Description: Issued groups and responded people are retained without a full archive/restore/history system. Future wedding/account deletion, cancellation, export and data-retention behavior is not fully defined.
- Impact: Destructive lifecycle operations could lose customer records or conflict with retained identity/history if added without explicit rules.
- Priority: P1.
- Status: Current guards verified; future policy unresolved.
- Proposed action: Define retention/export/deletion/archive rules before implementing those operations. Keep existing response FKs and authorized pending-member deletion rules intact.
- Target milestone: Before production personal-data lifecycle handling and future delivery/RSVP history.

## RF-012 — Recoverable token encryption and key custody

- Category: Accepted security tradeoff / deployment requirement.
- Description: Recopying links requires encrypted recoverable credentials alongside hashes. Database plus application-key compromise reveals them; losing required keys can prevent recopying while hash-valid links still work.
- Impact: Backup/key compromise or uncoordinated rotation can expose links or disrupt recovery.
- Priority: P1.
- Status: D-015 tradeoff accepted; deployment key/backup/rotation procedures not verified.
- Proposed action: Protect keys separately, test coordinated rotation/re-encryption and backups, or explicitly regenerate links. Never change keys automatically.
- Target milestone: Deployment readiness.

## RF-013 — Hosting, image runtime and public previews

- Category: Unverified deployment behavior / product decision.
- Description: Local publication and server metadata work, but localhost is not Internet hosting. Production private storage, PHP upload/image extensions/limits, backup restore, public social previews and indexing preference still need deployment verification.
- Impact: Real recipients or preview services may not reach the site; incorrect runtime/storage setup can break photos or privacy.
- Priority: P1.
- Status: Local automated coverage exists; production evidence is pending. Conservative noindex/nofollow/noarchive remains the default.
- Proposed action: Scope hosting separately, configure receiving PHP for the advertised 5 MiB support, keep media private with authorized delivery, test external previews/restore and confirm indexing policy.
- Target milestone: Deployment/launch review; no infrastructure approval implied.

## RF-014 — Shared networks, proxies and limiter cache

- Category: Confirmed configuration / suspected deployment fairness risk.
- Description: Restrictive RSVP allowances are now per invitation, but broader submission (120), setup (60) and invitation-view (60) limits remain per IP/minute. No project-specific trusted-proxy configuration was found; direct localhost traffic shares 127.0.0.1. Deployment proxy behavior is not verified.
- Impact: Large shared networks or incorrectly trusted proxies can aggregate legitimate visitors or permit forged client addresses. An array cache cannot enforce limits across PHP requests.
- Priority: P2.
- Status: Configuration confirmed; deployment impact unverified. Investigation confirmed CSRF setup and submissions use distinct buckets.
- Proposed action: Use persistent shared cache, trust only intended proxies, test real client IPs and review broad thresholds using expected traffic. Do not blindly trust forwarded headers or remove abuse controls.
- Target milestone: Before production load/proxy sign-off.

## RF-015 — Dependency advisories and platform upgrades

- Category: Previously observed maintenance finding.
- Description: Nine npm development-tool advisories were previously reported; the earlier production-only audit passed. That is historical evidence, not a fresh audit. PHP 8.2/Laravel 12 compatibility remains the approved baseline.
- Impact: Toolchain exposure and future major-version compatibility need deliberate review.
- Priority: P2.
- Status: Advisories tracked; no dependency changes or new audit claimed in this fix.
- Proposed action: Rerun audits during maintenance, assess exploitability and approve scoped updates. Do not run forced/breaking automatic upgrades.
- Target milestone: Maintenance/security checkpoint.

## RF-016 — Inherited local environment and isolated testing

- Category: Confirmed environment failure / workflow safeguard.
- Description: Inherited SESSION_PATH previously replaced `/` with a Git filesystem path and broke cookie creation. Inherited file caching also escaped PHPUnit defaults until both cache adapters were forced. SQLite extensions are temporarily loaded for test processes; cached app configuration can bypass intended test settings.
- Impact: Local startup failures or tests touching development state can recur if the documented isolated configuration is bypassed.
- Priority: P2.
- Status: Session runtime correction verified; cache isolation regression now passes even with inherited file caching. Safe test/start commands remain required.
- Proposed action: Follow README process-only SESSION_PATH and isolated SQLite/storage/cache/config-path setup. Do not change machine-wide PHP settings or run destructive development-database commands.
- Target milestone: Ongoing development/CI setup.

## RF-017 — Manual acceptance evidence

- Category: Pending verification / documentation debt.
- Description: 5A owner acceptance is blocked pending the RF-001 retest; earlier 2A/2B explicit sign-off remains tracked. 2C.1, 3A (8/8), 4A and 4B acceptance are preserved.
- Impact: Automated checks cannot establish owner acceptance or close older review questions.
- Priority: P0 for 5A; P2 for older sign-off reconciliation.
- Status: Pending owner confirmation; no acceptance inferred.
- Proposed action: Run the handoff's concise 5A retest and record only explicit owner results; reconcile earlier milestones separately.
- Target milestone: 5A acceptance / checkpoint review.

## RF-018 — Deferred product work and open launch decisions

- Category: Future enhancement / product decision.
- Description: Attendance summaries/management belong to 5B. Additional templates remain deferred under D-010. Delivery, deadlines, plus-one rules, seating, prices/payment workflow and lifecycle policies require separately approved scope; proposals in decisions.md are not approvals.
- Impact: Building these opportunistically would expand the MVP or silently decide product rules.
- Priority: P3; launch-critical commercial/policy decisions need review before use.
- Status: Deferred/open; no new feature implemented by this register.
- Proposed action: Use product_spec.md and the decisions proposals as the backlog source, then scope one milestone at a time after acceptance. Do not duplicate that complete backlog here.
- Target milestone: 5B next; other targets require owner decisions.
