# Risks and follow-ups

Current D-074 / 2026-10-28: The owner accepted the revised CMS-UX-03 density on a real 13-inch laptop; the first rejected candidate remains historical evidence. Commercial name clearance, functional/engine/security/device/release gates, the DEL-10.3 full-editor disposable-host stall and the separate 1024px public-RSVP browser failure remain open. Hostinger shared hosting is an evaluation candidate only; see RF-013/RF-042 and the [DEPLOY-00 audit](tasks/deploy00_hostinger_readiness.md).

At D-071 / 2026-10-27, CMS-UX-02 and GUEST-HUB-01 were implemented for owner review; D-072 above records their subsequent UX approval. UX-02 owner acceptance, the DEL-10.3 disposable CMS editor stall and all independent release gates remain open. See RF-041 and the QA register.

Reviewed: 2026-10-28 (D-072 laptop-density owner-review candidate; UX-02 and DEL-10 gates unchanged). This is the issue register; architecture and decisions remain the sources for implemented contracts and approved product behavior. Review relevant entries during each authorized implementation/fix and update evidence, status and next action. No unattended maintenance is configured.

The [QA verification register](qa/verification_register.md) owns executable checks, missing coverage, prerequisites, gate timing and last execution evidence. It preserves historical MySQL execution, including DEL-04's 45 enumerated cases, separately from Planned races and new ALB obligations. Maintain findings here and checks there; ALB-00 ran no application or engine tests and closes no existing security gate.

Priorities: **P0** blocks current milestone acceptance; **P1** needs resolution or an explicit deployment decision before real distribution/customer use; **P2** is scoped reliability, usability or maintenance work; **P3** is future product work. Accepted MVP constraints are not bugs or approval to build their proposed alternatives. Unverified risks are not confirmed failures. Automated success does not imply owner acceptance.

## RF-001 — Premature RSVP throttling

- Category: Confirmed bug / test isolation.
- Description: During investigation on 2026-10-16, actual local HTTP returned CSRF 200 followed by RSVP 429, limit 20, remaining 0 and Retry-After 5461 seconds. All links shared the IP-only submission counter. An earlier test run inherited file caching and time-travel tests left future-dated counters in development storage. CSRF setup had a different bucket; it was not consuming the submission counter. No frontend automatic retry was found.
- Impact: A normal first response could be blocked by another group's traffic or leftover test state; the fixed “one minute” message concealed the actual wait.
- Priority: P0 at discovery; resolved acceptance blocker.
- Status: Resolved — automated verification plus explicit owner 5A manual acceptance on 2026-10-19. The owner confirms persistence, partial submissions, updates, stale handling, revocation and publication protections; no new test-count total is inferred.
- Proposed action: Retain the verified per-invitation limiter, separate CSRF allowance, actual Retry-After feedback and persistent-cache regression. PHPUnit forces isolated array cache. The owner retest is complete; no additional cache reset is required.
- Target milestone: 5A acceptance blocker closed with historical owner evidence. Active personal limiter/CSRF/Retry-After regression coverage is now in PersonalRsvpHttpTest and PersonalRsvpIntegration.browser.test.mjs; old group-authority tests were replaced after D-022.

## RF-002 — Original eight MySQL concurrency cases verified

DEL-03b D03B (2026-10-22): all original cases rerun as part of 31 real InnoDB passes/661 assertions, zero skips. New isolated local strict REPEATABLE-READ target was removed afterward. Historical 2026-10-20 evidence below is preserved; new publication schedules are QA-MY-25–31.

- Category: Unverified technical behavior.
- Description: Four draft/photo, two publication and two personal-link generation/rotation cases were previously skipped. All eight now pass on isolated MySQL 26.7.0/InnoDB. The last two use the current individual credential HTTP adapters; group-wide authority is not exercised or restored.
- Impact: Intended lock/version guarantees have not been empirically verified on the production database engine.
- Priority: P1.
- Status: Closed for these eight scenarios on 2026-10-20: independent processes, distinct connection IDs and actual two-worker LOCK WAIT barriers verified. See the per-case inventory below. Production configuration/load and additional races remain separate findings.
- Proposed action: Retain the opt-in cases and rerun against the selected release MySQL version/configuration. No further execution is pending for these original eight cases in this audit.
- Target milestone: Original eight-case execution complete; M8 still verifies deployment-specific behavior and remaining RF-003 scenarios.

## RF-003 — Additional guest/RSVP races

DEL-03b D03B (2026-10-22): historical personal lifecycle/RSVP schedules rerun successfully in the 31-case isolated engine bundle; seven new CMS schedules verify selected publication/credential/media/facts overlaps. This does not cover last-person/capacity/parent-deletion or upload/cleanup/crash schedules; remaining composite QA-RACE obligations stay Planned.

- Category: Unverified technical behavior.
- Description: Original 19 cases plus four added cases now verify current draft/photo/publication/personal issuance/rotation, independent/same-guest/representative saves, designation/reassignment/removal, credential revocation, guest rename/deletion, and publication changes versus in-flight responses. Guest capacity/final-member and broader group/parent/membership/withdrawal/seating combinations still lack dedicated real-engine coverage.
- Impact: Lock-order reasoning, stale-update tests and simultaneous single-process PHP/SQLite requests cannot exclude engine-specific races/deadlocks.
- Priority: P1.
- Status: Partially verified on 2026-10-20: all 23 implemented engine cases pass; no surfaced deadlock errors, partial writes or stale authorization in those tested schedules. Internal retried deadlock events were not separately attributed on the shared server. Remaining combinations/load/production settings are unverified; this is not a universal race-safety claim.
- Proposed action: Keep current barrier tests; add focused capacity/final-member and group/parent cases, then future 5D/M6 races as those rules are implemented. Test release-version/configuration behavior before real-customer use.
- Target milestone: 5C implemented engine cases verified; additional lifecycle and future withdrawal/seating races remain M8/5D/M6 work.

## RF-004 — Recorded answers cannot return to Pending

- Category: Accepted MVP limitation / product decision.
- Description: Pending means no response row. A recorded answer can change between attending and declined, but cannot be cleared back to pending (D-016). The 5B owner dashboard is deliberately read-only: no owner correction, reset or bulk override.
- Impact: A link holder cannot withdraw an answer without choosing the other attendance state; owners cannot correct responses from Attendance.
- Priority: P2.
- Status: Confirmed current behavior; future clearing policy not approved.
- Proposed action: Separately scope owner corrections/reset and its authorization, timestamps, retention and versioning. Keep answer clearing distinct from D-021/5D invitation withdrawal, which preserves the saved response; 5D does not authorize reset-to-Pending.
- Target milestone: After 5B; no implementation authorized by this entry.

## RF-005 — Saving requires JavaScript

- Category: Accepted MVP limitation / accessibility follow-up.
- Description: Server HTML shows saved choices without JavaScript; submitting/updating requires the Vue form and same-origin CSRF setup.
- Impact: JavaScript-disabled or failed-script clients can read but cannot respond.
- Priority: P2.
- Status: Confirmed and documented.
- Proposed action: Assess progressive enhancement if required, preserving bearer privacy, CSRF and stale-save protection; do not add an SSR server merely for this.
- Target milestone: Future accessibility scope; not part of 5A acceptance changes.

## RF-006 - Personal links remain bearer credentials

DEL-04 / D-045, 2026-10-22: DEL-04 preserves bearer semantics and the existing private/no-store/token redaction boundary; only synthetic local links were used. Already delivered content cannot be remotely erased. External distribution/transport controls remain open. [Exact evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

- Category: Implemented authority replacement / accepted residual MVP security tradeoff.
- Description: D-022 retires group-wide HTTP authority. Active links authorize only their independent holder and explicit same-group dependents. Possession does not establish real-world identity; anyone holding the URL can perform those permitted actions. OTP remains outside MVP.
- Impact: Forwarded links or preview services can access the permitted personal/dependent information. Noindex and generic metadata are not access control.
- Priority: P1 before real distribution; personal replacement is owner-accepted (D-023), residual bearer risk remains.
- Status: Individual replacement and old-route denial automatically verified, active in development and manually accepted under D-023; historical 4B/5A acceptance remains preserved. Residual bearer-distribution/privacy risk is not resolved by narrower permissions.
- Proposed action: Preserve accepted personal scope; retain private-sharing guidance, no unnecessary recovery/logging and immediate revocation. Copying is not delivery. RF-021 tracks production rollback, RF-008 external logs and RF-024 withdrawal.
- Target milestone: 5C accepted; M7 onboarding/M8 release safeguards/M9 pilot.

## RF-007 — Group membership is live

- Category: Accepted MVP behavior / lifecycle decision.
- Description: Saved names of the respondent and explicitly authorized dependents are live, not snapshotted. Owner delegation changes immediately change the permitted roster. Other independent household members remain private. Wedding content uses the immutable published snapshot.
- Impact: Sharing a link does not freeze its guest list. Renaming retains responses; responded people and issued groups are retained.
- Priority: P2.
- Status: Approved C02 behavior is active after D-022; scope/privacy and permission changes are automatically verified and owner-accepted (D-023). Live-name/bearer-sharing implications remain.
- Proposed action: Keep live-name guidance and review minimum personal/dependent roster visibility (RF-022). No automatic household authority. Reconsider historical identity/archive semantics before delivery/history features.
- Target milestone: 5C privacy/UX review; future lifecycle scope.

## RF-008 — Raw invitation credentials in external logs

DEL-04 / D-045, 2026-10-22: DEL-04 application HTML/JSON/metadata/privacy and core redaction tests pass. External server/proxy/APM/analytics/body-capture inspection remains unverified (QA-OPS-02); local PHP development-server logs can contain synthetic bearer paths. Do not treat local success as deployed logging remediation. [Exact evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

- Category: Confirmed local exposure / unverified deployment security.
- Description: Standard Artisan/PHP development-server output prints bearer request paths. Application log redaction cannot redact PHP/web-server/proxy/access/APM/analytics streams or independently captured request/response bodies.
- Impact: Anyone obtaining a raw active URL can exercise personal self/explicit-dependent authority; legacy group-wide authority is retired. Captured recovery bodies also contain credentials.
- Priority: P1.
- Status: Local exposure confirmed; 5C.3a extends application redaction to personal paths and tests real nested logger/exception contexts. Deployment protections remain unverified; application tests do not certify upstream logs.
- Proposed action: Require HTTPS and verify exclusion/redaction of `/invite/*`, any approved 5C personal route namespace, recovery/submission bodies and URL analytics in every deployed logging layer. Preserve tested application redaction for both namespaces; separately verify every upstream logging/body-capture layer. Do not modify vendor internals or invent server infrastructure just to conceal local output.
- Target milestone: Before distributing real links. Application redaction tests are not external logging evidence.

## RF-009 — Tracked credential example

- Category: Confirmed security finding.
- Description: The prior checkpoint review found a populated database password in tracked `.env.example`, already in HEAD and previously matching a local credential. No value is reproduced here.
- Impact: Repository sharing/history can disclose a usable credential.
- Priority: P1.
- Status: Partially resolved. Current .env.example uses placeholders (verified without printing values); PR #1 records prior owner-confirmed rotation, rejection of the old credential and retained history. This task does not rerun credential tests or verify every external consumer/history artifact.
- Proposed action: Reconcile protected rotation/history and remaining consumer evidence under QA-OPS-03. Do not rotate credentials, rewrite history or treat removal from the current file as erasing history automatically.
- Target milestone: Security checkpoint before repository sharing/deployment.

## RF-010 — Historical published media retention

- Category: Product/operations decision.
- Description: Immutable revisions can keep old photo references alive. No approved historical revision/media expiry, cleanup window or owner rollback/history interface exists.
- Impact: Storage and personal-media retention can grow; indiscriminate cleanup could break historical/published snapshots.
- Priority: P2.
- Status: Policy unresolved; existing reference-preserving behavior is implemented.
- Proposed action: Define retention/history quotas and clean only safely unreferenced media after approval; preserve active and retained revision media when drafts change. CMS v2 proposes immutable multi-asset references/pinning and current-visible-only public delivery; it does not resolve retention policy or implement cleanup changes.
- Target milestone: Prelaunch retention review / separately scoped revision history (P-003).

## RF-011 — Guest/response/wedding data lifecycle

- Category: Product/security decision.
- Description: Issued groups and responded people are retained without a full archive/restore/history system. Future wedding/account deletion, cancellation, export and data-retention behavior is not fully defined.
- Impact: Destructive lifecycle operations could lose customer records or conflict with retained identity/history if added without explicit rules.
- Priority: P1.
- Status: Current guards verified; future policy unresolved.
- Proposed action: Amended C04 approves preserving issued/delegated/responded guest records and separately requires 5D withdrawal (RF-024). 5C restrictive FKs and friendly guest/parent guards are active and owner-accepted; normal-app withdrawal remains unavailable despite the default-off internal 5D.1b service. [5D W05](tasks/milestone5d_invitation_withdrawal.md), now approved by D-036, requires retaining withdrawn people and evaluating deletion after reinstatement without claiming a permanent withdrawal audit. Ordinary active never-issued/never-delegated pending removal remains subject to nonempty groups. Define broader retention/export/archive/parent deletion separately.
- Target milestone: Before production personal-data lifecycle handling and future delivery/RSVP history.

## RF-012 — Recoverable token encryption and key custody

DEV-ENV / 2026-10-25: the retained legacy synthetic preview manifest omitted its original generated encryption key. Port-8000 owner login/public rendering are verified, but encrypted credential recovery is not; do not silently revoke/rotate existing links. Newly created automated preview fixtures now persist their private runtime key/cookie for later resume. No current credential or key was rewritten. Production key custody/restore and QA-OPS-04 remain unverified; local runtime-file persistence is not an ACL/backup guarantee. [DEV-ENV limitations](tasks/dev_env_single_origin.md#reuse-and-security).

DEL-04 / D-045, 2026-10-22: DEL-04 preserves credential encryption/recovery and publication does not rotate/recreate credentials. Coordinated key custody, backup/restore and rotation drills remain open (QA-OPS-04/07). [Exact evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

- Category: Accepted security tradeoff / deployment requirement.
- Description: Recopying links requires encrypted recoverable credentials alongside hashes. Database plus application-key compromise reveals them; losing required keys can prevent recopying while hash-valid links still work.
- Impact: Backup/key compromise or uncoordinated rotation can expose links or disrupt recovery.
- Priority: P1.
- Status: D-015 tradeoff accepted; deployment key/backup/rotation procedures not verified.
- Proposed action: Protect keys separately, test coordinated rotation/re-encryption and backups, or explicitly regenerate links. Never change keys automatically.
- Target milestone: Deployment readiness.

## RF-013 — Hosting, image runtime and public previews

ALB-00 follow-up / 2026-10-25 (D-053) named Hostinger KVM 2 as a preliminary initial-host evaluation candidate. D-074 supersedes that initial-host preference with affordable Hostinger shared hosting for protected private-staging evaluation; KVM 2 remains a possible later option. The existing Laravel/Vue/Inertia monolith is unchanged. Current MVP code has no required persistent worker/scheduler, but Hostinger account-specific PHP extensions, MariaDB InnoDB behavior, secure nonpublic source layout, capacity/cost, private-media/cache isolation and backup/restore remain unverified. Enchanted CMS/v2 hard-deny online `APP_ENV=staging|production`; a separately approved activation change is required before full staging. No SPA/API split or purchase/deployment is authorized. [DEPLOY-00 audit and gates](tasks/deploy00_hostinger_readiness.md).

- Category: Unverified deployment behavior / product decision.
- Description: Local publication and server metadata work, but localhost is not Internet hosting. Production private storage, PHP upload/image extensions/limits, backup restore, public social previews and indexing preference still need deployment verification.
- Impact: Real recipients or preview services may not reach the site; incorrect runtime/storage setup can break photos or privacy.
- Priority: P1.
- Status: Local automated coverage exists; production evidence is pending. A.2 private disk/root/no-serving configuration and anonymous storage denial are tested in isolated temporary storage. Windows Flysystem visibility reporting did not prove private filesystem ACLs; verify actual deployment ownership/permissions separately. Conservative noindex/nofollow/noarchive remains default.
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

CMS-INTEGRATION / 2026-10-25: normal MySQL still contains only the migration ledger; no initialization was run. Real new-user registration/wedding/CMS/publishing/guest flow is verified using a fresh disposable SQLite environment, independently of retained manual data. [Exact owner migration impact, local gate settings and alternative port-8000 startup](tasks/cms_customer_workflow_integration.md#local-walkthrough-and-remaining-owner-action). QA-DEV-02 remains Blocked; this is not evidence of initialized MySQL login or production readiness.

DEV-ENV / 2026-10-25: read-only inspection confirms development MySQL currently contains only an empty migration ledger; the saved manual preview is separate retained SQLite. `dev.cmd` resumes that existing profile on 8000, isolates cached MySQL configuration, uses live Vite internally and fails rather than auto-initializing/falling back. Five configuration tests, 20 Laravel cases/212 assertions and connected read-only owner/public/mobile checks pass; existing SQLite bytes and MySQL schema/data remain unchanged. QA-DEV-02 is blocked on explicit approval for 18 existing migrations/20 application tables; no migration or synthetic import. Windows blocks `.ps1`, so startup uses `.cmd`/Node without changing execution policy. Temporary-file cleanup/key loss remain risks; do not recreate missing data. [Exact commands, restart evidence and boundaries](tasks/dev_env_single_origin.md). QA-DEV-01 is the bounded local pass; this closes no production/release/key gate.

**Superseding correction, later 2026-10-25:** one unisolated test command then applied the 18 pending migrations. Post-incident read-only checks now show the 18-entry ledger, 20 app tables and zero rows in app/cache/job tables. No pre-existing application table/data was available to overwrite. The safeguards below were added after this state change; this correction supersedes the prior statements above that MySQL remained uninitialized.

- Category: Confirmed environment failure / workflow safeguard.
- Description: Inherited SESSION_PATH previously replaced `/` with a Git filesystem path and broke cookie creation. Inherited file caching also escaped PHPUnit defaults until both cache adapters were forced. SQLite extensions are temporarily loaded for test processes; cached app configuration can bypass intended test settings.
- Impact: Local startup failures or tests touching development state can recur if the documented isolated configuration is bypassed.
- Priority: P2.
- Status: Session runtime correction verified; cache isolation regression now passes even with inherited file caching. Safe test/start commands remain required.
- Proposed action: Follow README process-only SESSION_PATH and isolated SQLite/storage/cache/config-path setup. Do not change machine-wide PHP settings or run destructive development-database commands.
- Target milestone: Ongoing development/CI setup.

## RF-017 — Manual acceptance evidence

- Category: Acceptance evidence / reconciled documentation debt.
- Description: In the roadmap request, the owner explicitly confirms Milestones 1–5B completed/manually accepted and confirms 5B plus portal refinements passed manual acceptance. This reconciles the prior 1/2A/2B/5B sign-off gaps. Preserve 3A's existing 8/8 count without inventing new manual totals.
- Impact: Feature acceptance is now recorded consistently; it does not close MySQL, security, deployment or future milestone gates.
- Priority: P2 at discovery; owner-sign-off gap resolved through 5C.
- Status: Resolved through Milestone 5C: 1–5B/portal acceptance under D-018, then all 5C manual tests and RSVP Access bug-fix retests explicitly confirmed 2026-10-20 (D-023). No new manual total inferred. 5D and Milestones 6–9 are not accepted; automatic/23-case engine evidence is recorded separately.
- Proposed action: Maintain the canonical roadmap and record later acceptance only from explicit owner confirmation. Keep technical/release evidence separate.
- Target milestone: Completed acceptance reconciliation through 5C; future gates in mvp_roadmap.md.

## RF-018 — Deferred product work and open launch decisions

- Category: Future enhancement / product decision.
- Description: D-051 establishes premium wedding websites/secure personal RSVP plus separate paid Our Wedding Memories planning. Advanced drag-and-drop Seating is outside MVP priorities and indefinitely deferred pending real customer demand; historical S01–S06 remain unapproved. DEL-07a is accepted and DEL-07b refinements await visual acceptance. 5D W01–W06 are approved, with default-off foundations only; full withdrawal acceptance, M7/M8/M9 and P-009 remain independent. General attendance export scope remains open; album contracts are RF-031.
- Impact: Building these opportunistically would expand the MVP or silently decide product rules.
- Priority: P3; launch-critical commercial/policy decisions need review before use.
- Status: Deferred/open; no new feature implemented by this register.
- Proposed action: Use product_spec.md and the decisions proposals as the backlog source, then scope one milestone at a time after acceptance. Do not duplicate that complete backlog here.
- Target milestone: Scoped website/ALB approvals and independent required 5D/M7/M8/M9 gates. Advanced Seating only if real demand warrants separate reauthorization; no replacement planner commitment.

## RF-019 — Portal branding and inherited shell polish

- Category: Confirmed UX/branding follow-up.
- Description: The authenticated shell still contains Laravel Starter Kit branding and generic GitHub/Documentation links. Existing Instrument Sans/system fallbacks are retained rather than introducing font dependencies. Wedding-scoped navigation and the requested overview/search/group-entry refinements are implemented; public Modern Classic styling is unchanged.
- Impact: The workspace is functional but inherited shell copy and external links do not yet communicate a finished customer product.
- Priority: P2; review before launch.
- Status: Confirmed in desktop/mobile visual review; branding decisions and broader shell changes are deferred.
- Proposed action: Approve product name/logo, support/help destinations and any font changes in a separate branding pass. Preserve keyboard/mobile navigation, current architecture and invitation styling. Do not install fonts or redesign unrelated shell pages opportunistically.
- Target milestone: M8/pilot-readiness polish under a separately approved branding scope; 5B/refinement manual acceptance is complete.

## RF-020 — Registration, email verification and activation policy

- Category: Confirmed configuration / unresolved launch policy.
- Description: routes/auth.php exposes registration and verification routes, but app/Models/User.php does not implement MustVerifyEmail. The inspected Laravel middleware enforces verification only for that interface, so a verified route label is not proof of email-ownership enforcement. No order/payment activation control exists in the current implementation.
- Impact: The desired consultation/paid-access journey lacks an explicit technical activation boundary; identity/contact verification and account-abuse handling are not established for launch. This is a confirmed configuration gap, not evidence of a customer breach or a new change in accepted behavior.
- Priority: P1 before real-customer pilot/release.
- Status: Source/framework inspection confirmed during the documentation audit; intended verification/registration/activation rules remain unapproved and unchanged.
- Proposed action: Approve the minimal registration/email/password-recovery and payment-activation policies, including existing accounts, in M7; implement and verify the approved controls and production mail in M7/M8. Do not silently close registration or disable existing weddings/public links.
- Target milestone: M7 product/access decisions; M8 security/deployment gate before M9.

## RF-021 — Legacy authority and mixed-version cutover

- Category: Development retirement verified / unresolved production deployment risk.
- Description: Current code permanently denies old group GET/RSVP POST/owner lifecycle routes, including with legacy configuration. D-022 authorizes a verified reset of disposable local data instead of customer migration/backfill. Deploying/restoring an older binary remains capable of restoring its old authorization model.
- Impact: A partial rollout could leave wider legacy writes active alongside restricted personal links, violating D-019 and exposing responses to unintended changes.
- Priority: P1 before real deployment/customer distribution; not a requirement for pre-launch data migration tooling.
- Status: Development retirement implemented: only personal mode enables RSVP, old routes always 404, no UI group workflow. Actual runtime and isolated HTTP/browser tests verify denial. Local MySQL wedding_rsvp was deliberately reset; no historical customer responses required preservation. Production old-binary/worker/backup restore protections remain unverified, not resolved by the reset.
- Proposed action: No pre-launch backfill, maintenance simulation or old-data rollback command. In M8 define deployment/all-instance drain, prohibit unsafe old-code restore, protect keys/backups and verify release rollback/restore against the intended personal authorization model before M9.
- Target milestone: 5C development activation implemented/manually accepted. M8 production deployment/recovery evidence before M9.

## RF-022 — Personal invitation household visibility

DEL-04 / D-045, 2026-10-22: DEL-04 controlled Enchanted now shows only the holder/current authorized dependents, with separate operational payload and generic metadata. Scoped PHP/connected browser cases and14 Enchanted engine schedules pass. Owner acceptance QA-UI-21 and external deployment privacy gates remain open; no production activation. [Exact evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

- Category: Personal privacy verified in development / bearer-sharing limitation.
- Description: Only self/authorized dependent names and answers are presented by active personal links. Unrelated independent names/contacts/IDs remain absent from HTML and JSON. A valid bearer URL sent to a preview service can disclose permitted names even with generic metadata.
- Impact: Keeping full-group HTML/JSON while merely hiding other response controls exposes more personal information than necessary.
- Priority: P1 for 5C privacy design.
- Status: Scoped fallback HTML/JSON/metadata and connected personal HTTP/UI are verified in isolation; D-022 activates the same model in development; D-023 records manual owner acceptance. Preview-service/bearer distribution risks remain; external logging is RF-008.
- Proposed action: Prefer server-side self/authorized-dependents-only projection in greeting, fallback HTML and JSON, generic OG/canonical public URL, no contacts/IDs. Explain scoped live-name visibility to owners; test crawler/body/metadata surfaces separately.
- Target milestone: 5C privacy owner-accepted; M7/M9 private-sharing guidance; M8 logging/release safeguards.

## RF-023 — Permission and per-guest version races

DEL-04 / D-045, 2026-10-22: DEL-04 reruns all 31 existing real-engine cases and adds 14 Enchanted schedules:45/953 assertions, independent workers/observed waits. Per-person conflicts/delegation/revoke/rotate/publish boundaries pass. Broader capacity/parent deletion, crash/cleanup and future5D races remain distinct unverified obligations. [Exact evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

- Category: Unverified technical behavior / integration risk.
- Description: Current personal versions and wedding-first integration have now been exercised under real InnoDB contention: first issuance, independent/same-target changes, competing designations, revoke/rotate/reassignment/removal, atomic dependent batches, rename/deletion and publication changes. The original 19 plus four additional cases pass on this local engine.
- Impact: An incomplete migration could allow stale authority, lost answers, partial dependent saves or deadlocks; simply introducing guest counters is insufficient.
- Priority: P1; M8 engine integrity/release sign-off, with additional 5D/M6 integration scenarios.
- Status: Implemented scenarios verified on isolated MySQL 26.7.0, strict SQL mode, REPEATABLE-READ, independent processes and real barriers. Prior 19 are no longer skipped/unverified. Additional lifecycle combinations, stress and release deployment configuration remain open through RF-003/M8. D-023 separately records manual owner acceptance; it does not close these remaining engine scenarios.
- Proposed action: Retain the expanded opt-in suite; extend it for remaining group/parent operations and separately approved 5D/M6 integration. Do not gate RSVP on seating versions. Recheck against the chosen release MySQL configuration.
- Target milestone: Current 5C engine scenarios verified; remaining lifecycle/withdrawal/seating and deployment evidence stays in RF-003 and M8.

## RF-024 — Retained guests need invitation withdrawal and active eligibility

- Category: Approved MVP requirement / incomplete lifecycle implementation.
- Description: Amended C04/D-021 preserve issued/delegated/responded guests and require 5D withdrawal before Seating. D-059 completes the default-off authenticated owner lifecycle, retention states, RSVP access guards and active-only Attendance reads. The owner accepted the manual functional walkthrough under D-060; real InnoDB race evidence remains outstanding and production stays disabled.
- Impact: Without a defined withdrawal workflow owners cannot safely remove an invitation while preserving answers, deny all self/delegated authority or distinguish active attendance/seating eligibility from retained history.
- Priority: P1; required gate before any M6 development.
- Status: Partially verified; 5C accepted, W01–W06 approved under D-036, and the 5D manual functional walkthrough owner-accepted under D-060. Owner HTTP/Attendance implementation is verified on isolated SQLite and browser fixture; genuine InnoDB schedules, deployment readiness and activation remain unverified. Do not infer pilot readiness.
- Proposed action: Implement/run the approved withdrawal races against an explicitly verified disposable InnoDB target before any production or future Seating activation. Preserve response history, tenant boundaries and old-token revocation. See the [5D readiness handoff](tasks/milestone5d_implementation_readiness.md) for exact evidence and remaining gates.
- Target milestone: Real 5D disposable-InnoDB verification before customer pilot and any resumed M6; manual acceptance is recorded under D-060. CMS planning does not waive this gate. M8 engine/release verification and RF-011 retention questions remain.

## RF-025 — RSVP Access copy and sibling-action failures

- Category: Confirmed UI bugs / resolved owner acceptance blocker.
- Description: Reproduced before fixing on an isolated HTTP/browser server. A valid canonical recovered URL was rejected when its origin differed from the portal alias; fail('unavailable') cleared it and set the dialog-wide blocked flag after successful credential issuance. Copy also awaited HTTP recovery before invoking the clipboard, losing the direct click context and reporting denial as ordinary status. Backend mutation snapshots/current per-member versions were correct.
- Impact: No usable copied URL; siblings' mutation controls disabled until manual Refresh access, preventing consecutive owner management.
- Priority: P0 at discovery; acceptance blocker closed.
- Status: Closed 2026-10-20: fixed, automated/visual regressions verified, and the owner explicitly confirms clipboard and multi-member state retests passed with all 5C manual acceptance tests (D-023).
- Evidence: New RsvpAccessRegression.browser.test.mjs reproduced canonical alias rejection and asynchronous copy before the patch; PersonalRsvpHttpTest verifies consecutive mutations using response versions and genuine stale rejection. Final stable browser suite 28 passes/0 failures/0 skips; native clipboard write/read, fallback, sibling/delegation/version states, 404/409/422 and late-result/privacy checks pass. Current Laravel focused 84 passes/596 assertions; full isolated SQLite 309 passes/2,616 assertions/23 opt-in MySQL skips. 1440/1024/390 light/dark and keyboard/overflow checks passed. See handoff for exact commands, unsuccessful sandbox/build-overlap attempts and visual evidence. No development data, token cryptography or server access/version contract changed; prior MySQL audit remains separate.
- Action: Retain explicit recover-then-copy, success only after clipboard resolution, selectable fallback, full credential-free snapshot synchronization, valid selection and stale-state safeguards. Secret-lifetime checks reject delayed reappearance after clearing. Owner confirms the RSVP Access bug-fix retests passed; retain those sequential-member, delegation, clipboard and privacy regressions for future changes.
- Target milestone: 5C accepted; unrelated existing engine/release findings remain separate and open.

## RF-026 — CMS v2 schema, publication and media privacy boundaries

UX-02 / 2026-10-27: new/replacement CMS photos now use Natural crop while saved crop/frame values and immutable publications remain untouched; focused disposable editor/published checks pass. Owner visual acceptance is pending. The DEL-10.3 full-editor disposable-host stall below is unaffected; two broader Editorial fixture failures involve absent optional private-photo originals and do not establish a CMS application defect.

DEL-10.3 / 2026-10-27: fresh disposable-SQLite customer/legacy browser selections and the guarded full PHP suite pass the affected public/private, immutable revision, media, guest and RSVP checks; [exact execution and owner queue](tasks/premium_delight_experience.md#del-103--isolated-execution-and-owner-acceptance-queue-2026-10-26). CMS polish has one complete 7/7 pass and an intermittent failed run. The editor's first-failure timeline confirms login/session/wedding routing succeeds before queued build/media requests and `/up` stall on its single-process disposable PHP host; the exact blocked request remains unproven. A full 39-case diagnostic executed 36 passes/3 experimental cleanup failures; temporary fixture changes were removed, so the unmodified file remains unverified as a complete pass. Priority P1 before QA-CMS-15 closeout: prove the fixture stall and repeat both complete files on fresh hosts. No application authentication behavior was changed. QA-CMS-15 remains owner pending; composite InnoDB races, normal MySQL, deployed ACL/privacy/recovery and media retention gates remain open.

DEL-09a / D-058: one opt-in photobooth uses the existing experience branch, strict preset/version validation and immutable visible-media projection. Saving does not enable a live intro; explicit republish does. Old disabled documents/history, tenant checks, generic/personal privacy and production hard denial stay intact. No new media grants, upload pipeline, migration or transaction lock order. [Exact checks and acceptance](tasks/premium_delight_experience.md#del-09a--enchanted-editorial-photobooth-opening). Composite engine/operational release gates remain open; isolated SQLite is not new InnoDB evidence.

CMS-INTEGRATION / D-057, 2026-10-25: real customer Invitation routing now defaults only unconfigured local/testing weddings to the existing Enchanted CMS; reads do not persist defaults, and v1/history are preserved. Focused first-save template races, cross-account denial and immutable saved/live/media visibility are verified; production hard denial and existing publication services are unchanged. [Evidence and QA-CMS-15 owner acceptance](tasks/cms_customer_workflow_integration.md). This bounded integration does not close composite engine, privacy, operational or public-release gates.

DEL-04.2 / D-047, 2026-10-22: DEL-04.1 is owner-accepted (14/14). The Guest Guide adds optional Attire/Gifts/FAQs blocks under the existing strict Enchanted contract; old documents and immutable snapshots remain unchanged absent explicit opt-in. FAQ, swatch and HTTPS bounds, hidden source/public projection, generic/personal RSVP guidance and keyboard/no-JS rendering pass **171 Laravel tests/1,152 assertions, 8 frontend checks and 2 connected Edge cases**. No migration/customer activation or new bird motion; QA-UI-23 visual acceptance remains. [Exact evidence](tasks/del03a_draft_persistence_contract.md#del-042--guest-guide).

DEL-04 / D-046, 2026-10-22: DEL-04 completes the controlled personal Enchanted adapter/privacy/publication guard reconciliation, with 427 SQLite/45 InnoDB/58 frontend cases. Owner explicitly accepts 20/20. Generic display data/snapshots remain guest-free; current-visible media rules remain. Composite upload/ownership/cleanup/crash/parent schedules and deployed ACL/retention/restore/release gates remain open. Production hard denial unchanged. [Exact evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

DEL-03b.1 / D-044: saved-state editor publishing reuses unchanged publisher/ownership/version/transaction guards; owner JSON summary now returns on successful JSON publish and Save. Empty fresh/public media, dirty/stale prevention and immutable saved/live behavior pass focused tests. Existing default-off production/personal boundaries stay intact. 87 PHP/820 assertions and 51 distinct frontend/browser cases pass; this adds no new InnoDB execution or upload/crash/release claim. [Exact evidence](tasks/del03a_draft_persistence_contract.md#10-del-03b1--focused-preview-publishing-and-loader-polish).

DEL-03b / D-043 (2026-10-22): partial resolution verified by 427 focused SQLite tests/2,470 assertions, 31 real InnoDB cases/661 assertions and 47 distinct frontend/browser/contract cases. Complete validated CMS-v2 snapshots, named timezone/UTC instant/DST rejection, list-order fingerprints, immutable saved-vs-live behavior, safe generic Enchanted/Blade display and current-visible-only owned media are implemented under default-off dual local/testing flags; production hard denied. Hidden/history source retained; no cleanup. Owner accepts DEL-03a.2 (17/17); DEL-03b QA-UI-20 pending. Seven actual new engine schedules and the previously pending CMS constraint pass; temporary database/principal removed. Personal Enchanted remains deliberately unsupported until DEL-04 and guarded on publication/credential issuance/read/write. Remaining composite upload/cleanup/crash/ownership/parent races, production privacy/ACL/log/key/retention/rights/device gates stay open. This risk is partially resolved for isolated generic publication, not closed for customer rollout. [Exact evidence](tasks/del03a_draft_persistence_contract.md#9-del-03b--immutable-publishing-and-public-wedding-website).

DEL-03a.2/D-042: private editor error consolidation/backgrounds, empty gallery/library assertions, mobile disclosure navigation, reduced-motion-aware dissolve and stable section/milestone discovery are verified by 120 isolated PHP passes/508 assertions and 63 distinct affected frontend/browser/contract cases. On/off, every interval and hidden Gallery settings now have actual Save/reload/private-preview evidence. Existing uploads/source/ownership/private-media/publication boundaries are preserved; no cleanup/data migration/public grant was added. DEL-07 FRONT/LEFT/RIGHT and waypoint contract are recorded, but artwork/flight/wing/engine remain absent. Owner visual acceptance, cross-engine/physical-device/assistive/slow-network checks and existing public-media/UTC-instant/race/release gates remain open; no new MySQL evidence. [Exact execution and corrected initial checks](tasks/del03a_draft_persistence_contract.md#8-del-03a2--final-ux-polish-and-hummingbird-preparation).

DEL-03a.1/D-041: optional reusable frame/slideshow settings pass strict PHP/TS, isolated save/load/private preview and browser checks. Raw Save error paths are replaced by friendly linked validation; hidden/old source preferences remain intact. Default-on motion is restricted to multiple visible photos with focus/manual/offscreen/hidden-document/reduced-motion guards and tested unmount interval cleanup. Static original SVG markers do not activate DEL-07 motion. Basic owner persistence tests passed; polish acceptance, physical-device/slow-font tests and all public snapshot/media/engine/release gates remain open. No new SQL/DDL, development database operation, new MySQL result or public activation.

DEL-03a/D-040 (2026-10-22): DP-01–03 approved and implemented behind the default-off local/testing owner boundary. Single-row optimistic draft saving/reload, optional independent photo slots, canonical IANA timezone and saved private preview pass isolated HTTP/browser/full SQLite checks (QA-CMS-12/D03A). V1/template transitions reject without erasing source; complete hidden references are ownership/byte validated. Published snapshots/live websites remain unchanged. Owner acceptance QA-UI-19, DEL-03b public dispatch/current-visible media/escaped fallback, UTC-instant/DST snapshot rules, QA-MY-24 and new draft/media/zone races remain open. Development MySQL was not migrated; no new InnoDB evidence. This partial resolution does not approve production delivery or final rights/template acceptance.

DEL-02b / D-035 adds real owner upload/list/private-image/validation HTTP and a reusable local editor, verified only behind a default-off local/test boundary with disposable SQLite/storage/sessions. Production/invalid configuration denies routes; missing media schema fails closed. Functional CSRF, ownership, reference privacy, retry and local-only behavior pass; no permanent v2 draft or public delivery exists. Hero/story-cover slots remain typed local presentation state, requiring an approved durable shared contract before DEL-03. QA-CMS-11 records this partial integration; QA-CMS-05–09/QA-MY-24 and media races are still gates, not waived by isolated HTTP success.

D-033 / DEL-02a: reusable image bytes/MIME/extension/bounds/orientation/metadata, internal owner-scoped resolution, hidden-reference ownership and injected upload/cleanup failures pass isolated tests (QA-CMS-10). No new HTTP endpoint, draft assignment or public delivery exists; this does not satisfy connected snapshot/current-visible media gates. Full SQLite: 555 passed/24 opt-in MySQL skips. New collision/cleanup/crash races are Planned QA-RACE-09–11, not additional skipped tests. See RF-030 for the non-atomic filesystem boundary.

D-032 / DEL-01: internal Story/Gallery/Entourage, explicit disabled intro, retained source and nested capability projection pass PHP/TS checks and the isolated suite. Read-only inspection found no durable local v2 documents before evolution; no migration/conversion. This resolves the internal contract gap only. Connected snapshots/public delivery, timezone, HTTP owner adapters and media races remain unimplemented/unverified; this P1 finding stays open before customer activation. QA-DEL-02/QA-CMS-10 are current; QA-CMS-05–09/QA-MY-24 and relevant races remain gates.

D-031 planning baseline: A.1 mandated an intro and rejected known sections unsupported by a selected template. Main-CMS-first and preserve-on-switch direction require explicit contract/version evolution, not silently enabling reserved manifests. [Premium Delight](tasks/premium_delight_experience.md) proposes global validation, retained private content/media and a separate visible public projection. No runtime change or verification is claimed; QA-DEL-02 and existing CMS/race gates must pass before affected activation.

CMS-B.2b (D-030): complete trial uses proposed display-only story/gallery/entourage sections and a fixture named timezone. No active CMS validator/schema/customer paths expanded. PHP content bounds, unique IDs, owned visible media, preserved hidden content and canonical timezone/snapshot resolution are planned in QA-CMS-09 and required before activation. Countdown is client-clock presentation, not an RSVP deadline.

- Category: Proposed architecture / unverified integration risk, not a confirmed v1 vulnerability.
- Description: Future typed sections/templates and multiple media assets expand content projection, validation, preview and cleanup paths. Hidden content or historically pinned assets could become public if delivery checks only ownership/bindings rather than the current visible published projection.
- Impact: Cross-wedding media exposure, accidental hidden-content disclosure, broken snapshots or a v1/personal RSVP regression.
- Priority: P1 before enabling any new v2 public delivery.
- Status: Partially resolved for isolated CMS/public/personal delivery; historical D04 verifies 45 named InnoDB cases, not this task or all composite races. DEL-07b is bounded-owner-accepted under D-055 and Photobooth readiness under D-061. DEL-05/CMS full customer acceptance, remaining upload/ownership/cleanup/crash/parent races, production/privacy/rights gates remain open.
- Proposed action: Preserve verified A.1/A.2/DEL-01/DEL-02a boundaries; approve and test customer dispatch, locked ownership/byte revalidation, safe public/personal DTOs, current-visible-only delivery, authenticated preview HTTP and transactional upload/prune/publish races. A.2 restrictive FKs are not same-wedding enforcement or proof of complete immutable SQL storage. Keep RSVP authority outside CMS; no untested cleanup/deployment claim.
- Target milestone: Proposed CMS-A/B/C/E implementation and owner acceptance; new engine/deployment evidence in CMS/M8.

## RF-027 — Rich-media runtime, licensing and mobile accessibility

DEL-10.3 / 2026-10-26: current disposable browser runs pass 21 Photobooth, seven connected Story/RSVP, twelve Story/bird and two published/personal bird cases; one optional retained-preview Story case was skipped. Accepted artwork/motion source was not edited. Combined DEL-05 Story/RSVP owner visual acceptance, physical-device/assistive review, rights and measured cold/slow-network budgets remain open. [Walkthrough and limits](tasks/premium_delight_experience.md#del-103--isolated-execution-and-owner-acceptance-queue-2026-10-26).

DEL-09a final feedback (2026-10-25): the confirmed Hero focus rectangle and inconsistent strip framing are corrected in presentation only. Eleven browser cases, mixed-orientation full-frame measurements and actual desktop/mobile Open/Skip/printing/settled captures verify the fix; interactive keyboard focus remains visible. [Exact evidence](tasks/premium_delight_experience.md#final-owner-feedback--hero-outline-and-strip-framing-2026-10-25). Final owner acceptance and all broader device/rights/performance obligations remain open; this does not resolve RF-027 as a whole.

DEL-09a / D-058: finalized opening uses finite masked CSS printing, immediate Open/Skip, honest sparse/failure fallbacks, static reduced motion and bounded/no-module direct entry. Existing bird is hidden until entry; same component/geometry resumes afterward. Three already authorized photo renditions are the maximum additional eager picture demand; no external origins, library or artwork asset. Targeted desktop Edge captures do not certify a physical midrange phone, assistive technology, measured budgets or asset redistribution rights. These gates remain open. [Final evidence](tasks/premium_delight_experience.md#del-09a--enchanted-editorial-photobooth-opening).

DEL-05 / D-056, 2026-10-25: Story and finite personal RSVP botanical feedback are implemented; combined owner visual acceptance remains pending. Seven new connected RSVP journeys plus focused existing tests verify privacy, stale/error/retry/no-op handling, reduced motion, no-JS, mobile/keyboard and unobstructed new confirmation/control geometry. Actual inspection moved the confirmation above editable responses to avoid focus-induced overlap with the existing bird path. Bird/loader/waypoint source hashes remain unchanged. No new dependency or engine; broader physical-device, competing effects, rights and measured budgets remain open. [Exact evidence and limitations](tasks/premium_delight_experience.md#del-05--romantic-guest-experience-closeout).

Current 2026-10-25 / D-055: the owner explicitly accepts DEL-07b, including Hero/flower contact and flight scaling. DEL-05a adds finite progressive Story reveals only and awaits visual acceptance. One observer/finite native animations preserve default-visible semantic content and stationary flower geometry; failures, reduced motion, reorder/removal and unmount cancel cleanly. Nine new browser cases, eleven accepted bird/flight regressions and three existing Editorial keyboard/no-JS cases pass; desktop/mobile before/after recordings retain identical row sizes and one bird. Approved bird/engine source hashes are unchanged. Physical-device/assistive, competing effects, rights and measured whole-delight performance remain open; this is not a broad RF-027 closure. [Exact Story evidence](tasks/premium_delight_experience.md#del-05a--interactive-our-story-progressive-reveals). Earlier pending DEL-07b statuses below preserve their execution-date context and are superseded by D-055.

DEL-07b owner-feedback refinement / D-050, 2026-10-24: Owner reported inaccurate nectar contact, low/offscreen first Hero stop and barely visible travel scale. The causes were generic SVG-container centres, segment-derived resting headings and a 0.94× mid-flight shrink. Runtime now uses marked nectar centres plus calibrated bill tips, one upper-right Hero flower with short-screen lift, unit-scale dwell and 1.18×/1.10× desktop/mobile travel. Actual per-section/milestone contact, initial viewport and read-only connected browser tests pass; before/after desktop/mobile WebMs and samples are captured. Owner visual acceptance remains pending; physical devices, assistive technology and measured performance budgets remain open. PHP CLI/Boost checks are currently blocked by Windows App Control, including PHPUnit/Pint/syntax; preserve historical PHP evidence without claiming a new pass. No security policy, dependencies, development DB/schema or production activation change. [Exact refinement evidence](tasks/premium_delight_experience.md#del-07b-owner-feedback-refinement--nectar-contact-hero-and-travel-zoom).

DEL-07b / D-049, 2026-10-23: DEL-07a is owner-accepted. The same shared layered SVG now follows visible section/milestone markers after Hero handoff through bounded viewport-space curves, safe clamps, five-heading updates, flower-facing rest offsets and reduced-motion static fallback. Three new guided-flight cases and four prototype cases pass; connected published smoke confirms one bird and no page errors. Owner visual acceptance of scroll rhythm/clearance remains pending; physical-device/assistive/performance and competing-effect gates remain open. No filters/dependencies/schema/publishing/production activation. [Exact design/evidence](tasks/premium_delight_experience.md#del-07b--guided-hummingbird-flight-integration).

DEL-07a / D-048, 2026-10-22: Guest Guide is owner-accepted. The original shared layered SVG now provides five smooth heading projections, independent wings/body/tail, first-Hero nectar alignment and a contained two-flower prototype. Four motion cases, three connected loader cases, four personal regressions, 13 frontend unit/contracts and 72 Laravel tests/658 assertions pass. Actual WebM/decoded frames and desktop/mobile captures were visually inspected; QA-DEL-06 is subsequently owner-accepted. Three-quarter views are interpolated stylizations rather than separately rendered 3D assets. No filters/dependencies/full scroll following or production activation; physical-device/assistive/performance and existing media/font/music/ACL gates remain open. [Exact design/evidence](tasks/premium_delight_experience.md#del-07a--layered-svg-hummingbird-and-contained-motion-prototype).

DEL-04 / D-045, 2026-10-22: DEL-03b.1 owner acceptance is confirmed14/14. DEL-04 mobile/keyboard/no-JS/reduced-motion and existing loader/Hero readiness regressions pass installed Chromium; physical iOS/Android, assistive technology, measured performance, commercial rights and full DEL-07 prototype remain open. [Exact evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

DEL-03b.1 / D-044: original decorative SVG loader, FRONT/LEFT/RIGHT and one Hero handoff are implemented; delayed module/font/image, failure/no-JS, mobile/desktop/reduced-motion and layout reanchoring pass Chromium checks. Null photo slots no longer imply placeholder images. Owner visual review, physical iOS/Android, assistive technology, measured performance and the full DEL-07 prototype remain open; no supplied GIF art copied. Existing font payload/production rights/ACL gates remain. [Exact evidence](tasks/del03a_draft_persistence_contract.md#10-del-03b1--focused-preview-publishing-and-loader-polish).

DEL-03b / D-043: generic published Vue and escaped no-JS narrative/milestones/photos tested in Chromium/Edge at desktop/mobile sizes; actual Fit/Fill/crop/focus parity, menu hash/history/focus/sticky offset/smooth/reduced-motion navigation and accepted gallery regressions pass. DEL-03a.2 has owner 17/17 acceptance; DEL-03b owner review remains separate. Physical Safari/Android, assistive technology, slow-network/final fonts, production ACLs and commercial image rights remain open. All new public/preview media are synthetic uploaded fixtures. Previously approved temporary matching WASM build tooling reused; dependency manifests/security settings unchanged.

DEL-03a.2: Chromium/Edge emulated mobile verifies menu keyboard/focus/Escape/selection in saved and live previews, stable gallery geometry and opacity-only dissolve with reduced-motion suppression. Fresh-gallery and private real-upload regressions pass; synthetic assets remain test-only and supplied family originals remain private. Physical iOS/Android, assistive technology, slow-network/font payload and production rights/ACL acceptance are still unverified. Windows App Control blocked the installed native Rollup parser; owner-approved matching WASM tooling in temporary storage allowed build/renderer verification without project dependency or OS-security changes. Reusing plain native build still depends on resolving that local policy or the documented temporary process option; this is a tooling limitation, not customer media activation.

DEL-02b exercises actual multipart PNG upload/private WebP delivery through an isolated PHP web runtime, reusing DEL-02a's full codec/orientation tests. Desktop/1024/390 light/dark, keyboard and bounded photo presets have browser evidence; photographs are synthetic. This does not verify production PHP/proxy/storage policy, real iOS/Android performance or commercial photo rights. DEL-02e owner editor acceptance is QA-UI-18 confirmed; DEL-03a adds tested shared-slot persistence/private preview but needs its own QA-UI-19 acceptance. Customer public delivery remains unresolved before activation.

The picker pages 12 assets and lazy-loads photographs, but thumbnails currently reuse the processed image, not a separately sized derivative. Worst-case image/font payload, large repeaters and low-bandwidth/physical-device behavior remain unverified. Review these before customer sharing (QA-UI-10); upload rate limits are not a total storage quota. Quota/retention decisions remain RF-010 before normal customer uploads.

DEL-02a uses the existing GD/EXIF runtime with real synthetic JPEG/PNG/WebP fixtures, including all JPEG orientations and PNG/WebP EXIF. This verifies the isolated CLI processor, not deployment PHP upload limits/codecs/storage ACLs or actual new HTTP uploads. Family photographs were neither accessed nor moved; their existing permission/licensing limits remain. D-034 adds future default-on gallery playback, requiring pause/reduced-motion/device/timer coverage (QA-UI-17), not current visual acceptance.

D-031 planning: optional music is approved in direction; catalogue-only MP3 caps/delivery/rights and motion budgets remain proposals. A.2 stays image-only. The private hummingbird GIF is primary anatomical/hover reference, **not rights-cleared production art**. Original/licensed colorful botanical SVG and later animated Enchanted prototype approval must precede shared integration. QA-DEL-04–08/10 cover future delivery, mobile/playback failure, animation cleanup and provenance; no new tests or rights clearance here. Video processing is deferred from this cycle.

CMS-B.2b extends local family-permitted photography to story and all six .jpg gallery files; originals/captures remain ignored/private and unchanged. No stock-distribution claim. Native manual gallery/timer/no-JS/reduced-motion checks pass in Chromium; production font payload/network/device/assistive-technology checks remain open.

CMS-B.2a local-rights evidence: owner confirms the four supplied family prenup photos are used with their sister's permission. Originals and photo-bearing screenshots remain ignored/private; no stock redistribution or photographer copyright-transfer claim. New original botanical SVG and self-hosted OFL Cormorant Garamond/Roboto have [provenance](../resources/assets/enchanted-editorial/ASSET_SOURCES.md). Local trial permission is confirmed; commercial demonstration-image rights, source fidelity, production font delivery and the broader AV/media/runtime requirements below remain open.

- Category: Proposed capability / unresolved runtime, rights and performance requirements.
- Description: Current GD pipeline supports images only. Safe custom audio/video needs codec/duration/metadata processing and seek delivery; reference artwork/fonts/music are not licensed merely because linked. Browser playback can fail and animation/video must remain optional.
- Impact: Unsupported uploads, excessive processing/storage/mobile downloads, rights exposure or a blocked/inaccessible invitation.
- Priority: P1 before accepting or distributing new rich media.
- Status: Open for remaining media/runtime/rights/device/performance evidence. Bounded original hummingbird/flight and single Photobooth visuals are owner-accepted under D-048/D-055/D-061; this does not approve music/AV delivery, all reference artwork, customer-photo rights or the complete real-device experience.
- Proposed action: Approve an original design and licensed self-hosted assets; begin with images/envelope, choose curated media versus explicitly approved processing dependencies, validate caps/runtime, retain poster/quiet/no-JS/reduced-motion fallbacks and test actual mobile browsers. Track quotas/retention in RF-010 rather than duplicating policy.
- Target milestone: Proposed CMS-B/D/E; production capability/rights checks M8 and final pilot scope.

## RF-028 — Figma references, five-intro completeness and fidelity evidence

DEL-09a / D-058: owner's finalized Photobooth brief supersedes the first envelope concept. One original existing-system variant is implemented and awaits **QA-DEL-15 owner visual acceptance**. Older reserved style identifiers stay readable for compatibility; they are not newly rendered/activated. This does not finish the future catalogue/Replay QA-DEL-09 or certify other designs/physical devices. No Figma/reference imagery, burgundy styling or literal envelope is copied. [Current storyboard and evidence](tasks/premium_delight_experience.md#del-09a--enchanted-editorial-photobooth-opening).

DEL-07b / D-049: DEL-07a is owner-accepted. Original layered SVG and the actual five-view/short-flight showcase now underpin a one-bird guided section/milestone route with screenshots and connected published smoke. DEL-07b owner visual acceptance remains pending for scroll rhythm, marker/RSVP clearance and responsive size; this does not certify the five introductions, physical-device fidelity or measured performance. [Concrete design/evidence](tasks/premium_delight_experience.md#del-07b--guided-hummingbird-flight-integration).

DEL-07a / D-048: Guest Guide is owner-accepted. Original layered SVG and an actual five-view/short-flight showcase now exist, with screenshots and recorded motion; QA-DEL-06 was subsequently owner-accepted. This is original code-authored vector work rather than an extracted Figma/GIF asset. It does not certify the five introductions or physical-device fidelity. [Concrete design/evidence](tasks/premium_delight_experience.md#del-07a--layered-svg-hummingbird-and-contained-motion-prototype).

D-031: owner approves Enchanted's overall visual direction and Premium Delight direction; full-template, each intro and hummingbird prototype acceptance remain separate. Finish the main CMS before the five introductions; use future INTRO-01–05 labels rather than the historical unimplemented B.2–B.5 intro proposal. No new visual implementation or acceptance is inferred.

CMS-B.2b: owner approves B.2a desktop visual direction only. Complete responsive story/countdown/gallery/entourage/RSVP trial is delivered with real private captures and fourteen passing browser cases; final full-template/mobile acceptance remains pending. New sections are source-inspired adaptations, not measured Figma frames. ED-07–ED-10 record remaining differences.

CMS-B.2a addendum (D-029): the preferred Enchanted Editorial first slice is implemented only in a loopback fixture. Official Figma MCP is rate-limited; verified 1920 × 7936 owner screenshot supplies estimates, not layer geometry. Actual browser comparisons/responsive/alternate-crop/long-name/fallback/keyboard/no-JS evidence and ED-01–ED-06 are in [design findings Section 9](tasks/figma_first_trial_design_spec.md#9-cms-b2a--enchanted-editorial-source-and-browser-comparison). Seven new cases pass. Owner visual approval, exact typography/artwork fidelity and mobile adaptation acceptance remain pending. No extra intro or customer CMS activation. Existing Floral findings below remain evidence, not Enchanted measurements.

- Category: Verified source gaps / pending design approval and implementation evidence.
- Description: Official MCP reads verified the first trial file's mobile floral cover and wedding-app frames. No desktop wedding composition, five distinct intro designs or envelope motion specification was found. Source attendance/messages/registration cannot become public guest features. Low-contrast token pairs and clipped fixed layers need documented adaptations. Asset/font rights remain unverified (RF-027).
- Impact: Invented desktop/motion details, coupled intro/template selection, private guest leakage or unsupported claims that five high-fidelity designs exist.
- Priority: P1 before final design acceptance/customer activation.
- Status: Open, partially verified. D-028 delivers only isolated CMS-B.1a Floral Trial/Botanical Bloom with actual 414/390/768/1024/1440px browser captures and source comparisons. Official source was re-read; geometry/type/palette and interaction tests pass. Significant original substitute artwork/hero differences, desktop/motion/navigation proposals and owner design acceptance remain pending. Other four intro visuals are unimplemented. Customer v2 stays unavailable; no five-design/final-fidelity claim.
- Evidence: [First trial source specification and FD-01–FD-06](tasks/figma_first_trial_design_spec.md); [CMS independent intro contract/batches](tasks/cms_v2_premium_wedding_website_plan.md); D-025 approves direction only.
- Proposed action: Approve final architecture/first exact frames, resolve source gaps, obtain each variant's reference and licensed assets; implement separately and compare matching browser screenshots. Preserve private personal RSVP boundaries and v1; resolve material discrepancies before each owner acceptance.
- Target milestone: CMS-0/A/B.1–B.5/C/E; rights/runtime RF-027 and release M8 remain separate.

## RF-029 — RSVP feedback needs submit-specific commit evidence

- Category: Resolved implementation gap for the bounded DEL-05b effect; owner visual/release acceptance remains separate. No observed RSVP data-loss defect.
- Description: Previously the shared `saved` event covered both submit and reload without an operation receipt. D-056 now returns a private submit-only boolean from the existing transaction-local changes, preserves state-only internal callers and keeps GET/reload free of effect metadata. Enchanted's optional slot consumes only successful typed receipts; malformed/unknown outcomes fail safely.
- Impact: Prevents false celebration on reload, identical retry, errors or uncertain acknowledgements. Current authorized saved rows determine explicit attending/declining/pending counts, with neutral mixed/partial feedback. No operational data enters generic pages or immutable snapshots.
- Priority: P1 before activating RSVP confirmation effects.
- Status: Resolved for the implemented submit-only effect by 262 PHP passes/1,607 assertions and 72 distinct frontend/browser passes, including actual lost-acknowledgement/no-op retry, stale two-window submission and rollback. Fourteen opt-in InnoDB cases remain skipped without a prepared target; no new engine result. Combined DEL-05 owner visual acceptance is pending QA-DEL-03/12.
- Action: Preserve receipt/private-data tests and existing transaction rules; obtain combined visual acceptance, then separately satisfy existing customer/production security and device gates. No audit table or stored celebration state. [Exact tests, corrections and evidence](tasks/premium_delight_experience.md#del-05--romantic-guest-experience-closeout).
- Target milestone: DEL-05b automated/owner acceptance; not a deferrable release-only check once the feature is enabled.

## RF-030 — Image upload filesystem/database recovery

DEL-03b: injected revision-binding failure proves complete publication SQL rollback in SQLite and real waiting InnoDB schedules; current-publication files/source remain retained. Save/publish, facts/publish and media/unpublish contention pass. These SQL-only publisher checks do not establish upload filesystem crash atomicity, collision/uncertain-compensation races or process-kill reconciliation. QA-RACE-09–11 remain Planned; no cleaner/deletion introduced. [D03B execution](tasks/del03a_draft_persistence_contract.md#9-del-03b--immutable-publishing-and-public-wedding-website).

DEL-03a adds SQL-only draft saving, not filesystem mutation or deletion. An actual HTTP commit followed by a lost response is browser-tested: no false success, automatic overwrite or silent retry; local edits remain and explicit reload/review reconciles the stored version. This is distinct from upload idempotency or process-crash reconciliation, which remain open below. Missing saved private bytes fail closed without deleting source; there is no new repair UI or cleanup scheduler. New save/asset/timezone concurrency schedules remain Planned; no MySQL execution.

DEL-02e evidence: bounded sequential multi-upload and explicit selection use the same private single-image endpoint. A controlled failed response is retryable without reuploading successful files; repeated confirmation/reopening cannot duplicate gallery asset references. This does **not** deduplicate file bytes or guarantee upload idempotency after a lost commit acknowledgement. An interrupted request may already have registered an asset; inspect/reload the private library before retrying an ambiguous upload. Existing per-file upload throttles still apply to batches, with partial successes retained and safe errors. Engine/process-kill/crash-reconciliation and production retention gates remain open; see QA-CMS-11/D02E and QA-RACE-09–11. No development MySQL operation or new InnoDB evidence.

- Category: Confirmed architectural limitation / unverified crash and concurrent recovery behavior; no observed customer data-loss incident.
- Description: DEL-02a reserves immutable asset metadata under a wedding-first SQL transaction, then writes/verifies private bytes. SQL and filesystem changes cannot commit atomically. Compensation retains any committed UUID row's file and defers deletion when the database outcome is unknown. A process kill or failed deletion can leave private unregistered bytes; no general cleanup scheduler is implemented.
- Impact: Storage leakage/orphan accumulation and ambiguous retry outcomes; an unsafe future cleaner could delete registered or revision-retained files. Repeated successful uploads intentionally create distinct assets.
- Priority: P1 before activating customer uploads/reconciliation; retention policy remains RF-010.
- Status: Open. Isolated synthetic tests pass for partial writes, registration/pre-commit failures, lost commit acknowledgment, cleanup failure, UUID collision and pinned-asset protection. DEL-02b adds private HTTP upload/error/retry tests; a canceled browser upload may already have committed, so the UI directs owners to reopen the library before retrying. Independent InnoDB writers and real process-kill/recovery schedules are unverified (QA-RACE-09–11 Planned); D03B?s 31 InnoDB passes include publication SQL contention, not the remaining upload filesystem collision/compensation/crash schedules.
- Proposed action: Before upload activation, implement/execute controlled collision and uncertain-compensation race coverage on a separately approved disposable target. Before sharing, rehearse a crash/recovery procedure using private isolated storage; any future reconciliation must lock the wedding and verify primary committed asset/revision references, never delete on unknown outcomes. Keep file deletion out of customer CRUD until separately designed/approved.
- Target milestone: DEL-02b upload/private-preview adapters and DEL-03 media publication gates; production retention/recovery and storage policy in M8 before pilot.

## RF-031 — Paid album contracts and private media isolation

- Category: Product/security/operations design gates; not a confirmed deployed album defect.
- Description: D-051 approves separate paid Our Wedding Memories direction, not implementation. Existing CMS lists all wedding assets, the processor discards originals and outputs bounded WebP, and model deletion protects revision references rather than future album references. Reusing these unchanged could expose Pending guest images through invitation selection, fail download-quality expectations or permit unsafe cleanup. No entitlement/staff grant/album lifecycle implementation exists.
- Impact: Unauthorized collection/viewing, cross-tenant or cross-purpose media disclosure, premature release after approval, retention/capacity cost or data loss if contracts are guessed.
- Priority: P1 before album implementation/affected upload or media activation; commercial/retention acceptance before real collection.
- Status: Open, album planning only; no album implementation/application/engine evidence or activation is inferred. D-055 subsequently accepted bounded DEL-07b, independently of album entitlement, private collection and reveal/access/retention contracts.
- Evidence: [ALB-00 reuse, contracts and decision queue](tasks/alb00_wedding_photo_album_plan.md). QA-ALB-01–08 are Planned, not skipped or passed. Existing RF-008/010/011/012/013/014/026/030 remain independent.
- Proposed action: ALB-D01 is approved (D-052); implement no code here. Approve remaining minimum entitlement, purpose-separated access, media-purpose/original/HEIC and lifecycle contracts before ALB-01. Add shared registry isolation and reference retention, enforce current state on every rendition/download, and verify tenant/revoke/moderation/reveal/upload races on isolated targets. Agree finite storage/hosting, consent/removal and recovery/export terms before customer use; cost assumptions are not approved limits. D-053's Hostinger/Cloudflare/R2 candidate requires effective CDN/private-storage, deployment and performance validation under RF-013; no release gate is closed.
- Target milestone: ALB-00 owner contracts → separately authorized ALB-01–04; M7 manual grant authority and M8/pilot release gates. No new package, gateway or parallel media/publishing system by inference.

## RF-032 — Test runner applied migrations to development MySQL

- Category: Confirmed development-process incident / database state change.
- Description: On 2026-10-25, `php artisan test --compact tests/Feature/PersonalRsvpHttpTest.php tests/Feature/GuestInvitationLifecycleTest.php` was run without process-only SQLite overrides. `.env` selected `mysql/wedding_rsvp`, and Laravel `RefreshDatabase` applied the 18 existing pending migrations. The test runner did not seed a user or synthetic customer data.
- Impact: The application schema now exists in the development MySQL database despite the earlier explicit-approval gate. Read-only Boost verification found 18 migration rows, 20 application tables, and zero rows in users/accounts/weddings/guests. The database is no longer uninitialized. Normal MySQL registration was not tested. No rollback/reset was attempted; no further MySQL writes are authorized by this incident record.
- Priority: P1 process/data-boundary correction before any future database write or normal-MySQL verification.
- Status: Safety correction verified; prior synchronization record in PR #1 reports owner approval to retain the empty initialized schema and a read-only connection check. Normal MySQL registration/login/CMS remains unverified; no new database-write authority follows from retention. Original incident and fail-closed regression evidence are preserved.
- Evidence: [DEV-ENV incident record and guard implementation](tasks/dev_env_single_origin.md#test-runner-incident-2026-10-25); QA-DEV-02/03. Read-only inventory found 18 migration rows, 20 application tables and zero rows in application/cache/job tables; no pre-existing application tables/data existed. PHPUnit 11.5.57 did not force old XML environment values, and Laravel loaded the existing compiled config; the child test process did not clear that cached config. The new bootstrap guard rejects unsafe inherited values and checks effective SQLite before tests.
- Proposed action: Preserve bootstrap/resolved-connection safeguards. Keep CI and fixture writing isolated from wedding_rsvp; obtain a separate bounded authorization before any normal-profile data write/verification. QA-DEV-02 remains blocked for that workflow, rather than treating schema retention as application readiness.
- Target milestone: Owner review before any further development MySQL operation; QA-OPS-11 InnoDB races must use a separately approved disposable target.

## MySQL verification evidence - 2026-10-20

Exact original inventory: eight WeddingDraftConcurrencyTest data sets, one IndividualRsvpMySqlConstraintTest method and ten PersonalRsvpConcurrencyTest data sets (the original eight plus rename/deletion). All were previously skipped because CMS_MYSQL_TEST_DATABASE was unset. Opt-in guards also require a recognized disposable name, APP_ENV=testing, migrated InnoDB schema/counters and suitable inspection privileges; no development fallback is allowed.

Method key (full names):

- D: Tests\Feature\Weddings\WeddingDraftConcurrencyTest::test_same_version_concurrent_requests_allow_exactly_one_complete_save
- C: Tests\Feature\IndividualRsvpMySqlConstraintTest::test_mysql_nullable_uniqueness_unsigned_ranges_and_restrictive_deletion
- P: Tests\Feature\PersonalRsvpConcurrencyTest::test_independent_workers_revalidate_permissions_versions_and_atomicity_after_real_lock_waits

Original 19, individually accounted for:

| ID  | Method / data set                       | Scenario                                                                                                   | Current-model validity                      | Final status |
| --- | --------------------------------------- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------- | ------------ |
| M01 | D / simultaneous first saves            | Competing first draft saves                                                                                | Valid                                       | Passed       |
| M02 | D / simultaneous existing draft updates | Competing saved-draft updates                                                                              | Valid                                       | Passed       |
| M03 | D / simultaneous first photo saves      | Competing initial hero uploads                                                                             | Valid                                       | Passed       |
| M04 | D / simultaneous photo replacements     | Complete winner plus losing-file cleanup                                                                   | Valid                                       | Passed       |
| M05 | D / simultaneous first publications     | One complete published revision wins                                                                       | Valid                                       | Passed       |
| M06 | D / simultaneous republications         | No duplicate conflicting republish                                                                         | Valid                                       | Passed       |
| M07 | D / simultaneous link generation        | Competing personal credential issuance                                                                     | Already retargeted from retired group links | Passed       |
| M08 | D / simultaneous link regeneration      | Competing personal credential rotation                                                                     | Already retargeted from retired group links | Passed       |
| M09 | C / no provider                         | Unique ownership/hash/delegation slots; null hashes; FKs; unsigned bounds; restricted guest/group deletion | Valid                                       | Passed       |
| M10 | P / issuance (original #0)              | Single first-issuance winner, stale loser                                                                  | Valid                                       | Passed       |
| M11 | P / independent (#1)                    | Two independent people both save from one baseline                                                         | Valid                                       | Passed       |
| M12 | P / same-guest (#2)                     | Conflicting target changes cannot silently overwrite                                                       | Valid                                       | Passed       |
| M13 | P / designation (#3)                    | Only one competing representative designation wins                                                         | Valid                                       | Passed       |
| M14 | P / revoke (#4)                         | Committed credential revocation denies waiting submissions                                                 | Valid                                       | Passed       |
| M15 | P / rotate (#5)                         | Rotation denies waiting old-token submissions                                                              | Valid                                       | Passed       |
| M16 | P / reassign (#6)                       | Representative reassignment invalidates old access                                                         | Valid                                       | Passed       |
| M17 | P / representative-batch (#7)           | Conflicting multi-person submissions remain atomic                                                         | Valid                                       | Passed       |
| M18 | P / rename (#8)                         | Committed rename rejects stale changed responses                                                           | Valid                                       | Passed       |
| M19 | P / retention (#9)                      | First issuance versus guest deletion has a consistent retained/deleted outcome                             | Valid                                       | Passed       |

D/P privileges: schema-scoped SELECT/INSERT/UPDATE/DELETE plus PROCESS for INNODB_TRX observation. Preparing the empty schema additionally used test-schema CREATE/DROP/ALTER/INDEX/REFERENCES; C exercises only its disposable schema and requires strict SQL mode. No case used production/customer credentials. One new temporary account had these test-only data privileges and temporary PROCESS access; an explicit development-table SELECT was denied. Credentials/app key were random and kept in a protected temporary directory, never printed or committed.

Additional coverage gaps found and tested (not counted among the original 19):

| ID  | P data set        | Additional scenario                                                              | Final status |
| --- | ----------------- | -------------------------------------------------------------------------------- | ------------ |
| A01 | delegation-revoke | Removing delegation before waiting representative submissions commit             | Passed       |
| A02 | unpublish         | Unpublication denies waiting RSVP writes                                         | Passed       |
| A03 | republish         | Latest published snapshot and correct response conflict behavior after republish | Passed       |
| A04 | rotate-revoke     | Concurrent rotation versus revocation has one lifecycle winner                   | Passed       |

Environment: new local schema wedding_rsvp_cms_test_20261020_80c23c4296, host 127.0.0.1:3306, MySQL Community Server 26.7.0, all 19 tables InnoDB, strict SQL mode and REPEATABLE-READ. The name/selected database/local server were checked before migrations. Fresh schema only; no reset of wedding_rsvp. Test principal could not read development tables. Parent ordinary regressions used SQLite :memory:; the opt-in cases explicitly switched to the dedicated MySQL schema. Cache/session/mail/queue/config/storage paths and application key were isolated. Worker guards revalidate the selected database and reject fallback. The disposable database, temporary user/global PROCESS privilege and credential file were removed after verification; protected synthetic JUnit evidence remains outside the repository.

First original run: 3 passed / 16 failed / 0 skipped. Reproduced harness causes: HTTP worker instantiated SessionGuard before binding its Request (TypeError); the transaction barrier's 20 ms polling kept observing early transaction metadata on this instance. Binding the actual synthetic HTTP request before guard setup and polling every 150 ms fixed those cases. Ten-second deadlines, independent workers and two actual LOCK WAIT requirements were retained; no timeout increase or relaxed application assertion. Safe phase/connection diagnostics and named data sets improve failures. A test-only restoration to legacy mode was corrected to personal. A missing import in the new republish fixture was corrected and its case rerun. No application defect/business-logic change was needed.

MySQL documents that transaction/locking metadata is transient and can be inconsistent across inspection views; this supports treating it as an observation barrier rather than application data. The polling correction is verified empirically on this instance, not a claimed universal server defect: [MySQL metadata consistency](https://dev.mysql.com/doc/refman/26.7/en/innodb-information-schema-internal-data.html).

Final commands (with isolated process-only environment): php artisan test --compact --group=mysql-concurrency --group=mysql-schema -> **23 passed / 483 assertions / 0 skipped**; focused RSVP HTTP/service/retirement regressions -> **83 passed / 562 assertions**; php artisan test --compact with opt-in configured -> **331 passed / 3,065 assertions / 0 skips**. JUnit output corroborated the engine results. Pint and syntax checks passed. No frontend/shared-type changes; frontend/browser tests were not rerun. No skipped case was deleted and no assertion/timeout was weakened.

361 -> 308 audit: before activation GuestRsvpTest had 40 expanded cases and InvitationLinkTest had 22 (recovered from tracked pre-activation source and enumerated with PHPUnit --list-tests). Current counts are 3 and 5: 62 -> 8, a reduction of 54 obsolete group-authority cases. PersonalRsvpHttpTest gained one invalid legacy-configuration case: 361 - 54 + 1 = 308. No test-suite configuration change explains the decrease. Current focused coverage totals 83: 38 personal HTTP + 37 personal service + 3 response-integrity + 5 permanent-retirement/logging cases. Authorization, private snapshots/media, validation, stale/partial/no-op responses, retention and atomic rollback are covered by the current model; shared group-wide authority/version expectations are intentionally retired. No unexplained count discrepancy found. Original 19 plus four added engine cases now give 308 + 23 = 331.

Limits: this verifies these controlled schedules on one MySQL version/isolation configuration, not arbitrary interleavings or production load. Remaining capacity/final-member/group/parent races and future withdrawal/seating integration remain RF-003/M8. External logs, credential-example, key custody, backups/retention and deployment risks remain open. 5C manual owner acceptance was subsequently confirmed in D-023, including the UI bug-fix retests; that confirmation does not broaden this engine evidence. Ongoing owner QA was not interrupted by the audit.

## RF-033 — Guest Management state-filtered search exception

- Category: Confirmed bug / query construction.
- Description: The 5D Active/Withdrawn/All state predicate was applied within a search callback that did not capture the eligibility service or selected state. The connected Laravel log also recorded a relation-type `TypeError` from an earlier `InvitationEligibility::constrain()` signature that rejected the `HasMany` passed by eager loading; the current service had already been widened to accept both `Builder` and `Relation`.
- Impact: Searching Guest Management could produce an HTTP 500 instead of returning matching groups, including while filtering withdrawn history.
- Priority: P1 for owner operations.
- Status: Resolved in source and owner Search 6/6 accepted. Focused in-memory SQLite covers partial/literal-wildcard/empty/no-result searches across states. Normal-profile MySQL compatibility/concurrency is not newly verified and remains explicit in QA-OPS-14.
- Proposed action: Preserve explicit closure captures and the `Builder|Relation` service contract; rerun Guest Management HTTP regressions after related query changes. Do not expose exception details to the owner UI.
- Target milestone: Focused post-integration correction, 2026-10-25.

## RF-034 — New Enchanted wedding missed the Photobooth opening

- Category: Confirmed integration/default mismatch.
- Description: The tested new wedding used Enchanted schema v2, but its draft and published revision had `experience.enabled=false`; the previous fresh-document starter also defaulted it off. The immutable renderer correctly omitted the opening for both generic and authorized personal visits. Existing saved records must keep that explicit choice.
- Impact: A newly published Enchanted website had no first-visit Photobooth even though the owner expected the approved experience.
- Priority: P1 for Enchanted onboarding.
- Status: Fresh-default/opt-out/immutable-snapshot behavior is isolated-verified; owner reports Photobooth functional integration 7/7, and D-061 separately accepts readiness 4/4. Previous saved-disabled-preview descriptions are historical, not the current owner verdict. QA-CMS-15 full fresh-customer/MySQL/production acceptance remains open; no saved draft/revision was rewritten.
- Proposed action: Keep the default scoped to newly created starter documents, retain the checkbox opt-out and explicit immutable publication, and never rewrite saved state as a repair. The exact owner steps and URLs are in the [post-integration handoff](tasks/post_integration_guest_search_photobooth.md).
- Target milestone: Focused post-integration correction, 2026-10-25.

## RF-035 — Photobooth printed before visible images were ready

- Category: Confirmed bug / asynchronous image readiness.
- Description: The opening trusted the invitation loader's ready event, which deliberately avoids blocking entry on images. The second/third strip images could remain incomplete when printing started. Reduced-motion CSS could also expose an unprepared strip.
- Impact: Empty/unfinished photo frames during the approved premium introduction; indefinite loading must not become the repair.
- Priority: P1 for Photobooth acceptance.
- Status: Closed under D-061: explicit owner acceptance of all four final image-readiness/access/failure/desktop-mobile-motion checks. Actual-element decode/dimension/current-source checks, cancellation, immediate exits and the eight-second fail-open deadline remain regression-protected.
- Evidence: [Focused closeout, tests and port-8000 slow-network motion](tasks/premium_delight_experience.md#image-readiness-correction--2026-10-26); QA-DEL-15. Desktop/mobile recorded one print start only after all three actual photos decoded, with no incomplete-frame samples/errors/POSTs and unchanged retained SQLite checksum. Publishing/media/guard regressions: 100 PHP cases/588 assertions; no MySQL operations.
- Proposed action: Preserve the accepted design and readiness regressions. DEL-10 audits independent device/security/engine gates; no additional visual change, draft/private fallback lookup or publication/data change is authorized.
- Target milestone: DEL-09a accepted 2026-10-26; scoped commit/push authorized by D-061, with DEL-10 audit next.

## RF-036 — GitHub CI coverage and full-suite assertion blocker

- Category: Resolved CI event/portability defects; independent release evidence gaps remain separate.
- Impact: The previous `main`/`develop` filters did not verify Draft PR #1 targeting `master`; a successful build/isolated feature subset did not establish an all-green application suite.
- Priority: P1 before relying on PR checks or declaring release readiness.
- Status: Resolved for DEL-10.2 CI reproducibility under D-063 at `30f94e8`. All four actual Linux PR/push test/quality jobs pass. Both full suites: 775 cases/4,983 assertions, 730 passed, zero failures/errors, 45 unchanged disposable-InnoDB skips. Guard/image preflight, 11 isolation cases/14 assertions, build and 15 Node cases pass; quality is non-mutating. InnoDB/browser/device/deployment readiness is not inferred.
- Evidence: The `26daace` baseline had 20 Linux failures/one error. Shared lowercase Inertia configuration resolves 19 assertion failures, the catalog test now checks all six legitimate unavailable entries, and inherited extension configuration plus bounded startup/socket checks resolves the server probe error. Windows full suite and focused Linux finder cases also pass. [Exact runs, diagnosis and coverage](tasks/premium_delight_experience.md#del-102b--ci-portability-corrections-2026-10-26); QA-CI-01/02.
- Action: Preserve locked installs, least privilege, real failure propagation, existence/availability/upload assertions and fail-closed SQLite isolation. Keep opt-in InnoDB skips explicit; do not connect CI to development MySQL or infer approval for production/merge/deployment. No remaining blocker in this bounded CI slice.
- Target milestone: DEL-10.2 complete for CI reproducibility; independent DEL-10.3–10.8 owner/engine/security/device/operations gates remain open.

## RF-037 — ONB-01 owner acceptance, engine proof and legacy cardinality

- Category: Approved product-rule change; remaining release verification/data-compatibility gates.
- Impact: The old customer paths allowed multiple weddings. D-064 now requires one ordinary owner workspace/one wedding; existing historical violations cannot be deleted/reassigned or silently made inaccessible to satisfy a new index. The relational schema remains 1:N for those exceptions and future membership architecture.
- Priority: P1 before deployment and final DEL-10.3 customer acceptance.
- Status: ONB-01 functionally owner-accepted under D-065; automated SQLite/browser evidence passed. Genuine InnoDB concurrency proof remains pending. Both creation endpoints use one locked transaction service; no customer path creates a second owner account/wedding. Legacy conflicts block creation and retain authorized access. No new schema constraint or normal MySQL operation.
- Evidence: Full PHP 786 cases/5,142 assertions (741 passed, zero failures/errors, 45 unchanged MySQL skips); 26 distinct browser cases passed. Pre-owner automated-run retained inventory 4/4/4, no duplicate cardinalities, unchanged full DB hash during that run; historical multi-record fixtures are preserved. Normal MySQL/deployment inventory is not newly verified. [Exact evidence and owner walkthrough](tasks/cms_customer_workflow_integration.md#onb-01--single-wedding-onboarding-2026-10-26); QA-ONB-01–04.
- Action: QA-ONB-04 and the separate CMS-UX-01 visual check are owner-accepted under D-066. Execute separately approved disposable InnoDB schedules for same-user/different sessions, shared owners, both endpoints and profile deletion. Before any later uniqueness migration, inventory/backup every target read-only, obtain explicit owner resolution for legacy violations and rehearse migration/rollback on a disposable copy. Do not weaken guards or add unapproved transfer/archive/role features. Future creation/import/operator paths must use the approved invariant boundary or receive separate review.
- Target milestone: ONB-01 acceptance → DEL-10.3, with engine/security/production gates retained.

## RF-038 — CMS-UX-01 editor visual acceptance

- Category: Owner-requested usability refinement / accepted manual verification.
- Impact: The global sidebar constrained the preview; desktop spacing and navigation consumed editing room at 100% zoom.
- Priority: P1 for this owner delivery, not a new production activation authorization.
- Status: Resolved for this editor refinement: owner accepts the final screenshot and all requested functional, responsive and zoom checks under D-066 / QA-CMSUX-02. This is not broader DEL-10.3 or production acceptance.
- Evidence: 78 browser, 125 PHP and 15 Node passes, zero failures/skips in this selection; dedicated shell, real responsive iframe, native Back warnings and quality/build evidence in the [existing workflow record](tasks/cms_customer_workflow_integration.md#cms-ux-01--full-width-compact-editor-2026-10-26). ONB-01 remains functionally accepted.
- Action: No further CMS editor change requested. Preserve owner-approved appearance and data; carry remaining full customer journey, engine, security and deployment checks into DEL-10.
- Target milestone: CMS-UX-01 acceptance complete; independent DEL-10.3–10.8 gates remain open.

## RF-039 — BRAND-01 customer-facing pre-launch UX

- Category: Implemented customer UX candidate; owner visual approval and commercial name clearance pending.
- Impact: Imbitado is selected for the customer-facing candidate, but trademark/name clearance, tagline/mark review and visual acceptance are not established for deployment.
- Priority: P1 before customer-facing launch.
- Status: D-069 selects Imbitado subject to clearance; the suggested tagline and paired-circle motif remain review candidates. QA-BRAND-01 is manual acceptance pending.
- Evidence: 52 guarded SQLite auth/onboarding passes/509 assertions, disposable onboarding 3/3, CMS workspace 2/2 and portal navigation 1/1 browser passes, seven read-only port-8000 public auth captures and disposable private/mobile captures. [Exact BRAND-01 evidence and owner queue](tasks/premium_delight_experience.md#brand-01--customer-entry-and-workspace-review-candidate-2026-10-27). Historical account names and local `.env` were not rewritten; the full DEL-10.3 editor fixture stall is separately open.
- Action: Owner reviews Imbitado desktop/mobile/appearance, tagline and paired-circle mark; complete commercial name/trademark clearance and set environment-specific APP_NAME/MAIL_FROM_NAME before launch. Retain auth/tenant/release gates and avoid unsupported feature, price and payment claims.
- Target milestone: BRAND-01 owner acceptance before customer-facing deployment; no production activation or normal MySQL evidence.

## RF-040 — PORTAL-UX-01 acceptance and Guest Hub projection boundary

- Category: Owner UX acceptance pending; future private-data/query migration risk.
- Impact: Simplified portal/editor navigation needs retained-preview owner review. A future combined Guests & RSVPs page could misstate active/withdrawn counts, expose private invitation state or load unbounded members if the existing Guests and Attendance queries are simply joined.
- Priority: P1 before affected launch or Guest Hub navigation cutover.
- Status: PORTAL-UX-01 automated checks pass; QA-PORTAL-01 owner pending. GUEST-HUB-01 is planning only, with no route/payload/schema change.
- Evidence: 143 guarded SQLite tests/1,769 assertions, 19 disposable browser passes, 15 Node contracts and quality/build pass. The current group list pages 25 groups with eager members; Attendance pages 10 aggregate groups or 25 individual guests with separate status filtering. [Current plan and owner steps](tasks/premium_delight_experience.md#portal-ux-01--imbitado-navigation-and-editor-review-2026-10-27). The DEL-10.3 full-editor fixture stall is a separate unresolved issue.
- Action: Owner reviews desktop/mobile/zoom and Save/Publish/navigation on port 8000. Before Guest Hub implementation, approve filter/history/bookmark semantics; specify one bounded owner-only DTO, lazy paged members, active-only summary rules and tenant/token tests.
- Target milestone: PORTAL-UX-01 owner acceptance; separately authorized GUEST-HUB-01 design/implementation and DEL-10 release gates.

## RF-041 — D-071 owner acceptance, combined guest query and separate browser limits

- Category: Owner UX acceptance pending; private-data/performance verification and independent browser defects.
- Impact: Guests & RSVPs now combines invitation actions and RSVP rows. A wrong filter could misstate active/withdrawn history, while a 25-group page can still load up to 2,500 named members (the pre-existing group maximum). One personal-RSVP browser case separately reports 1024px public-invitation horizontal overflow; the DEL-10.3 full-editor disposable-server stall remains unresolved.
- Priority: P1 before affected customer launch; the public-invitation overflow needs separate reproduction/triage without redesigning accepted visuals speculatively.
- Status: D-072 approves the editor and Guest Hub UX presentation; QA-CMSUX-03/QA-GH-01 still require their remaining functional/manual checks. Guarded SQLite and selected complete browser suites pass; no MySQL/performance/physical-device release proof or full-editor green claim.
- Evidence: 788 guarded PHP cases/5,217 assertions (743 passed, 45 opt-in InnoDB skipped), 18 complete disposable Edge passes across eight files and 15 Node contracts; one separate personal-RSVP browser case fails at its 1024px overflow assertion. [Execution record](tasks/premium_delight_experience.md#cms-ux-02--guest-hub-01-execution-2026-10-27). RF-040's planning-only status is historical and superseded by D-071 for Guest Hub implementation.
- Action: Finish the affected functional/manual checks on retained port 8000. Before launch, measure realistic group-page query count/bytes/latency and resolve the public overflow separately; keep tenant, token, withdrawn-history, MySQL/InnoDB, security and device gates open.
- Target milestone: CMS-UX-02/GUEST-HUB-01 owner acceptance, then bounded performance/public-browser triage and DEL-10 release gates.

## RF-042 — CMS-UX-03 laptop-density acceptance and real-preview limit

- Category: Owner visual acceptance resolved; presentation constraint remains.
- Impact: A 13-inch laptop can expose a CSS viewport smaller than its physical pixel count. The editor now gives the real invitation more usable space at 100% zoom, but cannot exactly reproduce the apparent invitation scale of 75% browser zoom without changing how the guest-facing renderer is displayed.
- Priority: P1 for independent device/preview verification; no invitation renderer change is authorized.
- Status: Revised density owner-accepted under D-074 on the actual 13-inch laptop. First candidate remains owner-rejected historical evidence. Full CMS browser, other devices and release checks remain open.
- Evidence: At 1280×720, the revised pane begins at 132px versus rejected 170px, and preview grows to 930×575px versus 880×528px. Laptop/mobile/200% revised captures, fresh disposable workspace 2/2, polish 7/7, guarded PHP 49/485 assertions and quality/build checks pass. The earlier 61 guarded PHP/716 assertions, 2 selected editor and 15 Node passes are historical, not rerun for this revision. [Exact captures and limits](tasks/premium_delight_experience.md#cms-ux-03--laptop-density-review-2026-10-28). The separate DEL-10.3 full-editor fixture stall is not resolved.
- Action: Preserve the accepted editor and real invitation renderer; verify independent DEL-10 browser, device and release gates without reopening visual design.
- Target milestone: CMS-UX-03 visual acceptance complete; independent DEL-10 release gates remain open.
