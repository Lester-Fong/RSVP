Product and Architecture Decision Log
Last reconciled: 2026-10-28 (D-074 owner acceptance of revised CMS-UX-03 and shared-host staging evaluation; independent release gates remain open). A decision is Approved only when explicitly supported by the product owner's conversation/authorization; recommendations are labeled Proposed. This log is a summary, not permission to silently add features.
Approved / established direction

### D-074 — CMS-UX-03 accepted; shared-host staging evaluation (2026-10-28)

The owner accepted the revised compact CMS editor after real 13-inch laptop review at 100% browser zoom. Its editor-only presentation and the accepted invitation design are frozen; this acceptance closes QA-CMSUX-04 visual review, not the separate full CMS browser, customer-journey, engine, security or release gates. The owner prefers evaluating affordable Hostinger shared hosting (Premium versus Unlimited) for protected private online staging before deciding whether a VPS is needed. This supersedes D-053's KVM 2 *initial hosting candidate*, without approving purchase, domain, deployment, production activation or public pilot. Preserve the existing Laravel/Vue/Inertia application and fail-closed production feature gates. [DEPLOY-00 audit and proposed runbook](tasks/deploy00_hostinger_readiness.md).
### D-073 — CMS-UX-03 density revision after owner rejection (2026-10-28)

The owner rejected the first CMS-UX-03 100%-zoom visual candidate as too similar to the original editor. Keep the approved Imbitado/CMS-UX-02 interface and the genuine invitation renderer, but make the desktop editor chrome visibly denser: shorter header/status/tab area, smaller actions and forms, less pane padding and more usable preview width/height. Preserve nine keyboard-reachable sections, explicit Save/Publish and unsaved guards, mobile touch/input sizes, 200% zoom and all saved/publication behavior. No CSS/browser zoom, invitation scaling, feature/schema change or commit is approved. The revised candidate requires fresh owner visual acceptance.

### D-072 — Imbitado portal approval and laptop-density editor (2026-10-28)

The owner approves the current Imbitado branding direction, the simplified CMS-UX-02 editor presentation and the Guests & RSVPs consolidation. This accepts those customer-facing UX choices; it does not supply missing customer-journey, MySQL/InnoDB, security, performance or deployment evidence. Commercial name/trademark clearance remains required; the suggested tagline and provisional paired-circle symbol are not separately finalized by this statement.

For CMS-UX-03, keep the full-width split editor, real responsive invitation renderer, nine-section row and all explicit Save/Publish/navigation actions. Reduce laptop UI density through editor-scoped layout, spacing and control sizes at 100% zoom; keep touch/mobile targets and 200% zoom usable. No browser/CSS zoom, scaled invitation, saved-content change or new feature is approved. Final density acceptance awaits owner review.

### D-071 — CMS-UX-02 and GUEST-HUB-01 customer portal (2026-10-27)

Status: **implementation approved; owner acceptance pending.** The editor keeps its full-width split and explicit save/publish actions, strengthens Imbitado identity, uses smaller header actions and one ungrouped nine-section row, and removes the two preview helper labels. No saved document or invitation renderer changes.

The private customer destination is **Guests & RSVPs**, with four wedding-wide active-only summary cards, shared literal search/RSVP/invitation-state filters, Invitation groups by default and Individual guests as the alternate view. Show withdrawn/all history only when withdrawal is enabled. Preserve group/guest and personal-access actions, response/deletion/representative authority, tenant boundaries, token confidentiality and existing records. Old `/attendance` bookmarks redirect to the unified page, retaining validated filters and defaulting to the old individual view when no view is supplied; the protected group-detail JSON route remains. No owner editing of RSVP answers, schema change, production activation or MySQL write is approved. D-067/D-070 remain historical planning decisions superseded for the Guest Hub cutover only.

### D-070 — PORTAL-UX-01 customer navigation and editor; GUEST-HUB-01 planning direction (2026-10-27)

Status: **PORTAL-UX-01 implementation approved; owner visual acceptance pending.** Authenticated wedding pages with the Imbitado sidebar use Overview, Design invitation, Guests and Attendance there, without duplicate horizontal tabs. Keep the dashboard route and a Home link on authenticated screens without wedding context; standalone Classic editor/private preview retain their own navigation. Refine only the full-width CMS editor frame with ivory/copper styling, a clear save/publish hierarchy and five grouped labels for the same nine editable sections. Preserve explicit Save Draft/Publish, warnings, responsive preview, saved documents and all invitation visuals.

The owner approves **Guests & RSVPs** as the GUEST-HUB-01 *planning direction*: four private summary cards, groups first, individual guests alternate, shared search/status filters, expandable members and contextual actions with representative guidance. No hub route, payload, authorization, RSVP answer, schema or Attendance bookmark change is authorized in this milestone. Review filter/history/bookmark behavior and the bounded query strategy before implementation. BRAND-01, UX-02 and DEL-10 release gates remain separate.

### D-069 — Imbitado product identity (2026-10-27)

Status: **name selected for BRAND-01 implementation; commercial naming/trademark clearance and final visual acceptance pending.** Replace the provisional customer-facing “Wedding Workspace” identity with **Imbitado** on entry/auth, onboarding, private shell, browser titles and appropriate application metadata. Preserve the approved ivory-and-copper editorial direction and current functionality. “Your love story, beautifully shared.” is suggested tagline copy for owner review, not a separately accepted slogan. Keep the paired-circle symbol provisional; no substantially different logo, purchased-domain assumption, production activation or launch approval follows from this decision.

### D-068 — BRAND-01 customer entry and workspace redesign (2026-10-27)

Status: **implementation authorized; owner visual acceptance pending.** The owner approves a branded login-first `/`, redesigned Login/Register and shared auth shell, and a wedding-focused authenticated sidebar/header without starter-kit branding or developer links. Preserve registration, verification/recovery, mandatory onboarding, the one-wedding rule, CMS/guest/Attendance/publishing access, and the editor-only full-width layout. The warm ivory/copper editorial system is a review candidate. “Wedding Workspace” is a descriptive temporary UI label and the paired-circle motif is provisional; neither establishes a final company/product name or logo. Final identity and visual direction require owner review before deployment. No guest hub, invitation redesign, schema, production activation, commit or push is authorized by this decision.

### D-067 — UX-02 customer refinements; GUEST-HUB-01 assessment (2026-10-27)

Status: **UX-02 implementation approved; owner visual acceptance pending.** Remove contact email/phone from customer group forms and listings while retaining stored historical values and existing invitation authority. Remove visible response counts from the Enchanted personal thank-you card without changing result semantics or private Attendance. Newly selected and replacement CMS photographs start at Natural crop; existing saved crop and Fit/Fill choices remain. Center one/final unpaired Entourage group and wrapped attire swatch rows without changing stored order, typography or approved artwork. No migration, publication rewrite, RSVP data change or production activation.

**GUEST-HUB-01 is a proposed future interface, not implementation approval:** assess a combined private Guests & RSVPs page using current scoped CRUD, attendance and personal-access services. Preserve legacy route/bookmark compatibility, deletion/withdrawal history, representative eligibility, credential secrecy and tenant boundaries. Owner review is required before route, payload or workflow changes. BRAND-01 and DEL-10 release gates remain separate. [Implementation evidence and proposal](tasks/premium_delight_experience.md#ux-02-and-guest-hub-01-owner-feedback-2026-10-27).

### D-066 — ONB-01/CMS-UX-01 acceptance and BRAND-01 pre-launch scope (2026-10-26)

Status: **ONB-01 and CMS-UX-01 OWNER-ACCEPTED.** The owner confirms mandatory single-wedding onboarding, congratulations, returning and legacy customer behavior, second-wedding denial and CMS integration. The owner also approves the final full-width CMS editor screenshot, desktop 100% proportions, separate scrolling, Save/Publish, unsaved Back safeguards, mobile and zoom behavior. The accepted implementations and invitation visuals are frozen; this acceptance does not close DEL-10.3's complete customer journey or independent database, security and release gates.

**BRAND-01 is a required pre-launch customer UX milestone, authorized for tracking and future owner-reviewed design only.** Its future scope is a wedding-service landing page; cohesive Login/Register and recovery/verification screens; removal of Laravel Starter Kit branding, repository and developer-documentation links from customer pages; and integration of the accepted onboarding/dashboard/editor journey. Direction: premium, romantic, modern and trustworthy for Filipino couples first, with accessible responsive language. No final name/logo, unimplemented feature claims, unapproved pricing or automatic payments. The owner must review brand identity and visual direction before implementation. No BRAND-01 code is authorized in this closeout.

The owner authorizes publishing the accepted ONB-01/CMS-UX-01 work to the existing integration branch and inspecting PR/push CI. No master merge, deployment, normal development MySQL change or production activation. [Accepted workflow evidence](tasks/cms_customer_workflow_integration.md#cms-ux-01--full-width-compact-editor-2026-10-26); [DEL-10 release plan](tasks/premium_delight_experience.md#del-10--invitation-mvp-readiness-audit-and-execution-plan).

### D-065 — ONB-01 acceptance and CMS-UX-01 editor refinement (2026-10-26)

Status: **ONB-01 functionally OWNER-ACCEPTED; CMS-UX-01 implementation authorized, final visual acceptance pending.** The owner confirms all ONB-01 manual checks passed and approves the completed experience. Separately remove the sidebar from the CMS editor only and refine responsive proportions/spacing at 100% zoom, with comfortable controls and the remaining width for the real preview. Preserve return/save/preview/publish, browser Back unsaved warnings, mobile switching, keyboard/200% zoom and all invitation visuals/data/security/gates. No CSS zoom or whole-page scaling.

Preserve all uncommitted ONB-01 work. **No commit/push until final CMS-UX-01 visual approval**; eventual publication must separate ONB-01 and CMS-UX-01 into focused commits. No normal MySQL changes, dependencies, production activation, merge or deployment. ONB acceptance does not close QA-ONB-03 engine proof or independent DEL-10 release gates. [Implementation and final review](tasks/cms_customer_workflow_integration.md#cms-ux-01--full-width-compact-editor-2026-10-26).

### D-064 — ONB-01 single-wedding customer workspace and mandatory onboarding (2026-10-26)

Status: **Approved product-rule change and implementation authorization; subsequent owner acceptance recorded in D-065.** One couple = one customer workspace = one wedding. An ordinary customer may own one workspace; that workspace may contain one wedding. This supersedes the earlier multi-wedding customer assumption, and approves P-009's dedicated onboarding, saved-name congratulations transition and wedding-focused dashboard. It does not approve an archive/replacement/transfer feature or partner/internal-role administration.

Registration and existing verification requirements lead to mandatory names-only-required onboarding; date, time and venues remain optional/editable later. First creation must be transactional/idempotent under retries, with no orphan records. Block second-wedding UI/direct requests, preserve tenant scopes, membership architecture, legacy data/publications and existing authorized access. Multiple historical workspaces/weddings require owner review, not automatic selection/deletion/reassignment. Existing single-wedding customers skip onboarding. Congratulations is tied only to a new successful creation and must not replay on ordinary reload/revisit; reduced motion, keyboard and mobile access remain required.

Implement and verify with fail-closed SQLite and separate disposable browser data; genuine InnoDB proof requires an approved disposable target. Do not alter normal development MySQL, approved invitation visuals, production gates, Album, deployment or master. **Do not commit/push until owner acceptance.** ONB-01 acceptance precedes final DEL-10.3 customer acceptance. [Current implementation, compatibility and owner walkthrough](tasks/cms_customer_workflow_integration.md#onb-01--single-wedding-onboarding-2026-10-26).

### D-063 — DEL-10.1 acceptance and DEL-10.2b CI portability corrections (2026-10-26)

Status: **DEL-10.1 is owner-accepted; the three identified CI failures may be corrected.** Align Inertia's application/test page paths with the lowercase Vue directory, validate the six-entry introduction catalog before correcting its stale assertion, and diagnose/fix the development-server test's startup/extension portability. Preserve component-existence, design availability, receiving-process upload limits and fail-closed SQLite assertions. Run focused/full isolated verification, quality/build and actual Linux PR/push jobs; make focused commits and push the existing integration branch.

Green CI may close DEL-10.2 reproducibility only. Independent InnoDB, security, deployment and owner-acceptance gates remain open. No normal development MySQL operations, visual changes, dependency upgrades, Album, production activation, unrelated refactoring, master merge or deployment.

Source: owner's **DEL-10.2b — Resolve CI Portability Failures and Achieve Green GitHub Actions**. [Execution evidence](tasks/premium_delight_experience.md#del-102b--ci-portability-corrections-2026-10-26).

### D-062 — DEL-10.1 evidence reconciliation and DEL-10.2 CI repair (2026-10-26)

Status: **Owner authorizes the first two existing DEL-10 submilestones.** Reconcile actual acceptance/verification evidence without closing unresolved gates; repair Actions for `master` PRs and integration pushes, using compatible runtimes, locked dependencies, least privilege, non-mutating quality checks and guarded isolated SQLite. Validate, make focused commits, push the existing integration branch and inspect actual workflow/job results, including Draft PR #1.

Application feature changes, broad dependency upgrades, unrelated formatting, production activation, real MySQL migrations/resets, Album, visual enhancements, merge and deployment remain excluded. DEL-09a at `c14771a` is accepted/frozen. Preserve D-060's 5D manual acceptance while keeping its disposable-InnoDB/production gates, and preserve DEL-05 manual acceptance requirements. Application/dependency/formatting failures outside CI scope must remain visible blockers for owner review, not be suppressed or silently repaired. The remaining six DEL-10 submilestones retain their existing approval boundaries.

Source: owner's **DEL-10.1 + DEL-10.2 — Release Evidence Reconciliation and GitHub CI Repair**. [Canonical plan and execution evidence](tasks/premium_delight_experience.md#del-10--invitation-mvp-readiness-audit-and-execution-plan-2026-10-26).

### D-061 — DEL-09a image-readiness acceptance and DEL-10 audit kickoff (2026-10-26)

Status: **DEL-09a Photobooth Image Readiness is OWNER-ACCEPTED.** The owner passed all four final checks: all three photos are ready before printing on a slow connection; Open/Skip work immediately during loading; failed/timed-out photos gracefully bypass printing; printing, falling and settling remain visually correct on desktop/mobile. Preserve the approved visuals and animations; no further visual changes are requested.

The owner authorizes a focused commit and push of the image-readiness implementation, relevant tests and acceptance documentation to `integration/m5-premium-wedding-20261025`, followed by verification of the remote SHA and existing Draft PR #1. No master merge or deployment is authorized.

DEL-10 is authorized for read-only reconnaissance and a documented execution plan only. Audit the entire invitation MVP, including DEL-05 acceptance, 5D engine/release gates, real-account customer flow, privacy/security, normal development MySQL versus retained synthetic SQLite, CI and operations. Do not implement DEL-10 application features/refactors, activate production-disabled features, begin ALB-01, modify development MySQL or weaken fail-closed tests. Detailed release/implementation actions require the subsequent scoped authorization identified in that plan.

Source: owner's **DEL-09a CLOSEOUT → DEL-10 FINAL QA KICKOFF**, with four explicit passed checks. [Implementation and acceptance evidence](tasks/premium_delight_experience.md#image-readiness-correction--2026-10-26). Earlier milestone and independent release gates remain separate.

### D-060 — Milestone 5D manual acceptance and fresh Enchanted opening default (2026-10-25)

Status: **The owner accepts the completed Milestone 5D manual functional walkthrough.** Preserve the approved W01–W06 implementation and default-off/production-disabled configuration. This acceptance does not waive disposable-InnoDB race verification, development-database restrictions, or independent production/security/release gates.

For new Enchanted Editorial starter documents, initialize the approved Photobooth opening as enabled so a newly published eligible wedding presents it on first visit. Owners may turn it off before Save/Publish. Never rewrite existing saved drafts, immutable revisions, or the current customer's saved preference; a previously saved disabled wedding requires an explicit owner edit and republish. Preserve deep-link/session bypass, generic/personal privacy, Modern Classic, and current visual design.

Source: owner's POST-INTEGRATION request and statement that the 5D manual functional walkthrough succeeded. [Focused fixes and verification](tasks/post_integration_guest_search_photobooth.md). No additional RSVP/5D rules or database operations are approved.

### D-059 — DEL-09a acceptance and Milestone 5D completion batch (2026-10-25)

Status: **DEL-09a Photobooth owner-accepted; 5D implementation subsequently owner-accepted under D-060.** The owner explicitly accepted the finalized single Photobooth opening. Freeze its approved implementation and retain its current design, motion, Hero handoff, source/snapshot boundaries and corrected photo framing.

The owner authorizes completion of Milestone 5D under the existing D-036 W01–W06 rules: expose the existing transactional withdrawal/reinstatement through authenticated owner workflows, exclude withdrawn invitations from active Attendance, retain guest/answer history, and reinstate without restoring old credentials or delegation. Do not redesign the rules or build a parallel RSVP system. Keep the app feature default-off and production-disabled until owner acceptance and outstanding engine/release gates are satisfied. No development DB migration/reset, dependencies, unrelated RSVP/5D scope, commits or pushes.

Source: owner's “Milestone 5D — Complete Invitation Withdrawal & Reinstatement / DEL-09a Photobooth” request. [Exact behavior, verification and owner checklist](tasks/milestone5d_implementation_readiness.md#15-5d2-implementation-closeout-2026-10-25). No new product rules supersede D-036.

### D-058 — First intro is the Enchanted Editorial Photobooth (2026-10-25)

Final owner feedback (same date): preserve the liked design/motion and fix only the unwanted Hero rectangle and inconsistent photo filling. Strip photographs use identical cover-filled viewports, retaining focal position while leaving saved website Fit/Fill/crop preferences untouched. Preserve programmatic Hero focus and interactive keyboard indicators. This is a focused correction request, not final DEL-09a acceptance or permission for broader work.

Status: **Superseded by D-059: DEL-09a is owner-accepted and frozen.** At authorization time, the finalized [photobooth brief](tasks/DEL-09a_Enchanted_Photobooth_Opening_Brief.md) superseded Botanical Ivory Envelope only for the first variant. One ivory/champagne/taupe print station, real published names/date/photos, a masked downward feed and restrained drop/settle; Open is available immediately. The approved bird appears after entry, reusing the same loader/Hero handoff. No envelope, additional variants, music, Album, production activation, reset/migration, dependency, commit or push.

Reuse the existing enabled/disabled CMS experience and immutable publication pipeline; preserve old disabled documents/snapshots. New `photobooth` version 1 is an additive allowlisted style, not a new storage system. Deep links and unavailable session storage fail open; no private guest state or bearer token belongs in the intro or session key. [Final implementation/evidence](tasks/premium_delight_experience.md#del-09a--enchanted-editorial-photobooth-opening). DEL-05, CMS-INTEGRATION and all independent release/security/device gates remain pending as previously recorded. This authorization does not accept their visuals or activate other reserved intros.

### D-057 — Connect existing Enchanted CMS to authenticated customer weddings (2026-10-25)

Status: **Owner-authorized implementation; end-to-end acceptance pending.** Enchanted Editorial is the default for new weddings through the existing authenticated Invitation workflow, using real wedding-scoped data and the existing complete editor, private media, saved-draft/version and immutable publication contracts. Preserve saved Modern Classic/v1 documents and history; no silent conversion, duplicate CMS/API/publisher or production activation. The local/testing gates remain default-off and production hard-denied. Opening an unconfigured wedding is read-only; the first explicit Save establishes the existing v2 document.

Source: owner's CMS-INTEGRATION request. [Implementation, exact verification and manual walkthrough](tasks/cms_customer_workflow_integration.md). MySQL initialization remains a separate owner-approved action; do not migrate/seed/reset/import it to bypass the environment issue. DEL-07b stays accepted, DEL-05 awaits visual acceptance, no DEL-09/Album/unrelated work, dependencies, commits or pushes.

### D-056 — Complete DEL-05a and DEL-05b together (2026-10-25)

Status: **Owner authorizes the complete DEL-05 Romantic Guest Experience engineering milestone**, including finishing/reusing DEL-05a, implementing server-confirmed romantic RSVP feedback in DEL-05b, combined browser/regression verification and documentation. Do not pause for owner acceptance between phases; deliver **IMPLEMENTED — AWAITING OWNER ACCEPTANCE**, never self-approve visual QA. This supersedes D-055's earlier stop-before-DEL-05b boundary only.

Use existing personal authorization/delegation/version/transaction infrastructure. Celebrate only actual acknowledged changed attending saves; decline respectfully, mixed/partial results neutrally, and never celebrate reloads, identical retries, failures or uncertain outcomes. Keep private RSVP state outside generic pages and immutable CMS snapshots. Preserve accepted DEL-07b artwork/flight, current media/CMS/publication and Modern Classic behavior. No development MySQL migration/reset/seed/data changes, new dependency, production activation, Album, DEL-07c/DEL-09, Seating, 5D, commit or push. Source: owner's subsequent full DEL-05 goal and acceptance requirements; [implementation/evidence](tasks/premium_delight_experience.md#del-05--romantic-guest-experience-closeout).

### D-055 — DEL-07b owner acceptance and DEL-05a authorization (2026-10-25)

Status: **Owner explicitly accepts DEL-07b**, including refined Hero waypoint, flower interactions and flight scaling. Preserve its approved SVG artwork, five orientations, continuous wings, loader handoff and guided-flight behavior. Broader physical-device/performance/release checks remain independent; no DEL-07c authorization.

The owner authorizes **DEL-05a only**: gentle progressive Story milestone photograph/title/description/decoration reveals, subtle staggered movement/opacity, forward/backward scroll, responsive layout and immediate accessible/reduced-motion/no-JS fallback. Reuse existing data/order/visibility/components and fixed milestone landing points; no CMS fields, animation dependency or flight-engine change. DEL-05a visual acceptance remains pending after implementation; no DEL-05b, Album or unrelated feature work.

Source: Owner's latest DEV-ENV continuation plus DEL-05a request, which explicitly requires proceeding with DEL-05a even if development MySQL initialization still awaits approval. [Story contract and evidence](tasks/premium_delight_experience.md#del-05a--interactive-our-story-progressive-reveals); [port-8000 startup and database boundary](tasks/dev_env_single_origin.md). No database initialization, reset, commit or push is authorized by this acceptance.

### D-054 — Single-origin local manual testing (2026-10-25)

Status: **Owner-approved DEV-ENV workflow direction.** All owner-facing manual application/QA URLs use `http://127.0.0.1:8000`, with stable wedding paths; Vite may retain internal 5173. Reuse the existing isolated synthetic preview infrastructure, preserve normal authentication/tenancy/CMS/publication/personal RSVP, and provide restartable Windows startup. Automated tests may use isolated internal services without disrupting 8000; no random-port owner preview URLs. No development DB changes without explicit approval, synthetic-to-MySQL import, reset, production bypass, new feature/dependency, commit or push.

Source: Owner's DEV-ENV request. [Implementation, diagnosis and remaining approval](tasks/dev_env_single_origin.md) document the retained SQLite `dev.cmd` profile and uninitialized MySQL separately. This approves the local workflow, not the 18 pending MySQL migrations, credential rotation, production hosting or DEL-07b visual acceptance. Album/DEL-05/DEL-07c and all release gates remain unchanged.

### D-052 — Explicit album reveal and staged updates (2026-10-25)

Status: **Owner-approved ALB-D01 behavior; documentation only.** Initial reveal remains explicit. After reveal, newly approved photos and presentation edits stay staged privately until the couple explicitly publishes **Update revealed album**. Visitors continue seeing the released presentation meanwhile. Removal/rejection of previously released content takes effect immediately, without waiting for an update. Approval does not automatically release new photos.

Source: Owner's explicit ALB-00 documentation follow-up. [Approved contract](tasks/alb00_wedding_photo_album_plan.md#alb-d01-approvals-after-reveal--owner-approved-2026-10-25). This resolves ALB-D01 only; schema/access/storage/lifecycle/commercial decisions ALB-D02–07 remain open as applicable. No ALB-01 implementation, deployment, production activation or DEL-07c authorization; DEL-07b still awaits owner visual acceptance and existing release gates are unchanged.

### D-051 — Premium website business and Our Wedding Memories direction (2026-10-25)

Status: **Owner-approved product direction; ALB-00 documentation/planning only.** Not authorization for album schema, implementation or activation.

The primary product is a premium wedding website service with secure personal RSVP, DIY customization and optional paid done-for-you work. Advanced drag-and-drop Seating is outside MVP priorities and deferred indefinitely unless real customer demand justifies reopening it. Preserve existing history/work; do not introduce a basic planner as a replacement commitment. This supersedes earlier unresolved advanced-Seating MVP placement, without removing 5D withdrawal, internal administration, security/deployment or pilot requirements.

Our Wedding Memories is a separate higher-value paid add-on, excluded from the lowest website package and separate from the invitation's 10-image gallery and immutable CMS publication. Guest QR/secure-link uploads need no account/app and start private/Pending; owners moderate individually/in bulk, feature favorites and explicitly reveal after the wedding. Hide/unreveal and entitlement disable/expiry retain media. Only approved content may appear after reveal; visitors use link-restricted Our Highlights/All Our Memories, captions/lightbox/individual downloads and an entitled/revealed Our Memories nav item. Server-side entitlement applies to administration, collection and viewing; future manual internal grants, no gateway. Security/privacy/finite storage terms remain prerequisites.

Source: Owner's explicit ALB-00 request. [Plan](tasks/alb00_wedding_photo_album_plan.md) separates these approved requirements from proposed architecture and ALB-D01–07. At this initial entry ALB-D01 was unresolved; D-052 subsequently approves explicit staged updates. Access distribution, lifecycle, original/download quality, HEIC, commercial quotas/retention/export and staff authority still need scoped approval. 500 photos / 10 per batch / 10 MB / approximately 12 months are cost assumptions only. Videos/social/AI/multiple album templates/automatic billing/unlimited or lifetime hosting remain deferred. DEL-07a acceptance and DEL-07b's pending visual gate are unchanged. No ALB-01 or DEL-07c authorization.

ID Decision Status Rationale / implications
D-001 Target engaged couples in the Philippines directly Approved Product messaging and early features should serve couples.
D-002 Offer both self-service (DIY) and done-for-you setup using one shared platform Approved Internal staff configure the same wedding records; no separate per-customer codebase.
D-003 Acquire initial customers via personal connections and referrals (including a wedding-host contact); use Facebook/Instagram/TikTok as supporting channels Approved Consultation-led onboarding; no large automated marketing funnel initially.
D-004 Initially accept manually coordinated GCash/Maya/bank transfers; no payment gateway yet Approved Payment records and verification may be built later; never treat an uploaded receipt alone as confirmation.
D-005 Use Laravel 12, Vue 3/TypeScript, Inertia, Tailwind 3, Vite, MySQL in one application Implemented Current installed foundation on PHP 8.2; avoid unapproved major upgrades.
D-006 Use account membership and wedding ownership in a shared-database modular monolith Implemented and manually accepted through Milestone 5B (D-018) Foundation models, migrations, routes and owner policies exist; release/security and MySQL checks remain separate.
D-007 Implement in scoped milestones with Codex; review and test before advancing Approved workflow Prevent scope drift and accumulate verified, reversible changes.
D-008 Use a Modern Classic invitation for the first private wedding preview; preview remains authenticated and wedding-scoped Approved Explicit Milestone 2A task establishes the visual direction and private preview boundary. Use existing relational wedding facts; do not publish a public page in this milestone.
D-009 Use Laravel Boost as a local Codex development assistant through project-scoped stdio MCP Approved Explicit tooling integration task. Keep Boost in Composer require-dev; isolate its schema metadata cache in memory and do not change product runtime behavior.

Status update for D-006 (2026-10-16): The owner later reported that the core browser flow and cross-account 403 check passed. Separate explicit Milestone 1 approval remains pending; MySQL concurrency was not manually exercised.

Status update for D-006 (2026-10-19): D-018 records explicit owner acceptance of Milestones 1–5B, closing the earlier owner-approval gap. This does not close unverified MySQL/security checks.

### D-010 — Modern Classic and core functionality first (2026-10-16)

Status: Approved

Context: The existing Modern Classic invitation has a private preview. CMS editing, publishing, guest management, RSVP tracking, seating arrangements, and internal administration remain planned work.

Decision: Use only the existing Modern Classic invitation while developing those core platform capabilities. Defer multiple-template support, template selection and switching, and additional visual designs until the core functionality is developed.

Why: Focus delivery on the wedding management and guest workflows needed for a usable platform.

Consequences: Keep structured, validated wedding content separate from presentation so future designs can reuse the same data and business logic. Scope CMS and publication work to Modern Classic's supported content; keep generic template engines, registries, and switching infrastructure outside current implementation scope. The product owner will later provide Figma or Canva references before additional wedding templates are scoped. Earlier registry and multiple-template proposals in `architecture.md` are deferred by this decision. Implement each core area through a separately scoped milestone; at that decision Milestone 2A acceptance was pending; D-018 later records acceptance through 5B.

Source: Explicit product-owner instruction to update the development roadmap on 2026-10-16.

### D-011 — Basic Modern Classic draft editor (2026-10-16)

Status: Approved scope; manually accepted with Milestones 1–5B in D-018 (2026-10-19). Separate release verification remains open.

Decision: Milestone 2B edits the introduction/closing messages and visibility of ceremony, reception, and closing sections. Reuse the existing Modern Classic renderer for reactive unsaved preview and the last-saved authenticated preview. Keep wedding names, date/time, and venues on the existing wedding record, edited through its existing form. Apply Horizon Minimal to the portal editor while preserving the invitation's visual identity.

Implementation choice delegated by this task: one wedding-scoped `website_drafts` record with schema-versioned, validated JSON and a save-version counter, documented in architecture.md §7. This is a mutable draft compatible with later publication snapshots, not published revision history. Messages are required plain text, maximum 500 characters each; visibility controls preserve hidden data. Stale saves are rejected.

Publishing, images, appearance controls, section reordering, additional templates, selection/switching, and a generic engine remain outside this milestone. No public route or new user role is introduced.

Source: Explicit Milestone 2B implementation request on 2026-10-16, including authority to choose and document the minimal draft schema.

### D-012 — Single private Modern Classic hero photo (2026-10-16)

Status: Approved implementation scope; owner manually accepted 2C.1 on 2026-10-16. MySQL concurrency verification remains pending.

Decision: Add one optional uncropped photo below the existing invitation header through the existing draft save flow. Use private Laravel storage, owner-authorized delivery, server-generated immutable keys, and two nullable draft reference columns without changing version-1 CMS JSON. Native GD/EXIF strips metadata and normalizes JPEG/PNG/WebP (5 MiB/12 MP/6,000px bounds) to static WebP, maximum 1,600px long edge. No original retention, new dependency, media library, gallery, crop editor, or publishing. Replacement/removal obey the existing locked counter; cleanup is reference-aware, with dry-run-first pruning of generated orphans older than 24 hours. Future published references must be protected before publication is implemented.

Source: Owner selected uncropped placement and 5 MiB/runtime preparation during planning, then explicitly instructed “Implement the plan.” The owner later confirmed manual acceptance, including upload and persistence after reload. Observed runtime/migration verification is in checklist.md; skipped MySQL cases remain pending.

### D-013 — Saved invitation publication (2026-10-16)

Status: Approved implementation scope; Milestone 3A manually accepted with 8/8 owner tests passed, confirmed in the Milestone 4A task on 2026-10-16. Earlier documentation called this publication milestone 2C.2. MySQL concurrency remains pending.

Decision: An authorized owner may publish a saved Modern Classic invitation to `/w/{slug}`, share it without authentication, edit drafts independently, republish reviewed changes, and unpublish without deleting wedding/draft/history. Snapshot wedding facts, supported validated CMS and media references independently; create immutable snapshots and change the active pointer atomically with stale/duplicate protection. Require saved supported draft, names, date and valid referenced media; time/venues remain optional. Retain snapshot media across draft replacement and pruning.

Delegated choices: minimal `website_revisions` plus wedding pointer/publication counter; Laravel public HTML/metadata enhanced by the existing Vue renderer and shared stylesheet. No additional SSR server, package, generic versioning/template framework, history/rollback UI or hosting infrastructure. The conservative noindex recommendation is implemented for owner review, not approval of a marketing/search-acquisition policy. Architecture.md records contracts and rendering tradeoffs.

Guest management, personalized links, RSVP, seating, payments, extra designs, advanced CMS, custom domains and production hosting remain out of scope. D-010 template deferral is unchanged. Automated checks are not owner acceptance or evidence that skipped MySQL cases passed.

Source: Explicit owner publishing implementation request, including authority to choose the smallest maintainable snapshot schema and appropriate public rendering strategy.

### D-014 — Named guests in wedding-scoped invitation groups (2026-10-16)

Status: Manually accepted by the owner on 2026-10-16 in the explicit authorization to proceed with Milestone 4B. Guest/MySQL concurrency remains pending.

Decision: Owners manage editable invitation labels, optional contact email/phone, and one or more named guests per group. Each seat is a named guest, duplicate names are allowed, and group totals are distinct from invited-person totals. Guest existence implies no RSVP. Create/edit use portal modals; saved guest/group removal requires explicit confirmation.

Delegated schema: `invitation_groups.wedding_id` and `guests.invitation_group_id` define one constrained ownership chain. Omit a redundant guest wedding ID to eliminate conflicting parents; wedding queries use the indexed group relation/has-many-through. Standard FKs cannot require a child row: transactional application creation and locked last-person checks enforce nonempty groups on all management write paths. A 100-guest per-group technical safety bound is not a wedding pricing/guest quota. Strict payload allowlists reject ownership/membership injection; membership cannot be reassigned.

Permanent authorized deletion and cascading group members are permitted before delivery/RSVP exists. Revisit deletion/retention before adding those records. No token, public guest route, sending, response, plus-one placeholder, seating or import/export capability is included. Modern Classic and D-010 remain unchanged.

Source: Explicit Milestone 4A request and delegated minimal schema/limits choices; the same request confirms Milestone 3A owner acceptance (8/8).

### D-015 — Recoverable personalized invitation links (2026-10-16)

Status: Implemented and manually accepted by the owner on 2026-10-16 in the authorization to proceed with Milestone 5A. MySQL concurrency and deployment logging prerequisites remain pending.

Decision: An owner explicitly generates, retrieves for copying, revokes and regenerates a group's bearer link at `/invite/{token}`. Use 32 random bytes, unique SHA-256 lookup hash, encrypted-at-rest recoverable token and monotonic version on the same group. Never include the hash/ciphertext/recovered URL in bulk lists or ordinary Inertia props. Authorized no-store JSON actions return the URL only on request. No expiry, delivery inference, sending or RSVP submission.

The current saved group label and member names are visible to anyone possessing an active link; subsequent saved operational edits are immediately visible. Wedding content/media come only from the latest supported published snapshot; draft edits remain private. Unpublished weddings and inactive/unknown tokens return generic 404. Reuse the same published resolver, Blade document and unchanged Modern Classic Vue renderer; put the greeting outside that component.

Issued groups cannot be hard deleted, including after revocation; this supersedes D-014's group-deletion permission only after issuance. Never-issued groups retain existing deletion behavior. Member edits/removal remain permitted before RSVP exists; revisit retention/deletion and parent cascades before future delivery/response features.

Security tradeoffs approved in the plan: recoverable encryption depends on protecting the Laravel application key independently from database/backups. Database plus key compromise can reveal links. Preserve keys through coordinated rotation or regenerate links; never change keys automatically. Browser history/clipboard and recipients or services given a bearer URL can retain it; noindex/no-referrer/generic metadata are not access control. Generic metadata does not prevent a preview service with the URL from fetching permitted names.

Latest owner clarification limits logging changes to safe application controls. Standard configured Laravel channels redact paths/credential-shaped strings and public exception details are sanitized before reporting. PHP development-server and upstream access logs are outside Laravel control and remain explicit HTTPS/deployment logging prerequisites. Do not edit vendor internals or add server infrastructure just to hide those logs. No additional approval is inferred for production deployment.

Source: Explicit approval of the prepared 4B implementation plan, current saved-member semantics, in-place work preserving uncommitted 4A, and clarified request-logging boundaries. The owner subsequently confirmed 4B manual acceptance in the Milestone 5A request; 4A acceptance remains preserved.

### D-016 — Per-person guest RSVP submission (2026-10-16)

Status: Milestone 5A manually accepted by the owner on 2026-10-19, including persistence, partial submissions, updates, stale saves, revocation and publication protections. MySQL verification remains pending.

Decision: An active personalized invitation permits its bearer to save attending/declined choices for currently named group members, reopen and update them, and submit only part of the group. Absence of a response means pending; omitted people retain their existing choice. Responses are independent relational records, never CMS or published-snapshot content. Unpublished weddings and invalid/revoked/rotated links cannot accept responses. Anyone possessing the link can respond for its group; no guest account is required.

Consequences: Implement unique guest-linked responses with timestamps, transactional token/publication/membership revalidation and stale-update protection. Renaming preserves identity and responses; a guest with a response cannot be permanently removed. New guests start pending. Membership/label edits remain visible to existing link holders. No response clearing, attendance dashboard, deadline, anonymous plus-one, meal/questionnaire, delivery or archival system is introduced. Milestone 5B owns attendance summaries and management.

Source: Explicit Milestone 5A implementation request, including partial responses, reopening/editing, integrity requirements and delegated minimal schema/deletion-policy choices. Implementation details and unverified MySQL races are documented in architecture.md.

### D-017 — Read-only wedding attendance monitoring (2026-10-19)

Status: Approved scope; Milestone 5B and subsequent portal refinements manually accepted by the owner in the roadmap request, 2026-10-19 (D-018).

Decision: Authorized wedding owners may monitor per-person attendance, wedding-wide invited/attending/declined/pending totals, individual responses and invitation-group breakdowns. Search and status filtering are server-side and paginated; summary cards always cover the whole wedding. Pending means no response, and revoked links retain already saved responses. No owner RSVP override, reset, bulk modification or export is included.

Consequences: Derive counts from existing relational records; do not persist counters or expose private attendance in public wedding snapshots. Group details load on demand. Keep guest management and public RSVP submission unchanged. Owner corrections/resetting require a future explicit decision (RF-004).

Source: Explicit Milestone 5B implementation request, including 5A manual acceptance and read-only boundaries.

### D-018 — MVP sequence and acceptance through 5B (2026-10-19)

Status: Owner-approved milestone order and acceptance record; planning/documentation only.

Decision: Milestones 1–5B are completed and manually accepted, including the customer portal refinements. Continue with 6 Seating Planner → 7 Internal Admin, assisted onboarding and manual payment management → 8 Security, concurrency, deployment and release readiness → 9 End-to-end customer pilot and acceptance. Advanced Figma/Canva designs, multiple templates and reusable dynamic CMS expansion remain deferred until MVP completion through Milestone 9.

Consequences: [mvp_roadmap.md](mvp_roadmap.md) is the canonical remaining roadmap. Keep evidence in checklist.md and issue status in risks_and_followups.md. The older 1/2A/2B/5B owner-acceptance gaps are reconciled; eight skipped MySQL tests and unresolved security/deployment findings remain open. The roadmap's S01–S06 seating rules/schema/interaction/output recommendations and detailed milestone contracts require approval before implementation. No migration, dependency, application feature, commit or push is authorized by this planning task.

Source: Explicit MVP Roadmap Audit and Milestone 6 Planning request, including confirmation that 5B/portal refinements passed manual acceptance and that Milestones 1–5B are complete and accepted.

### D-019 — Individual RSVP credentials and explicit household delegation (2026-10-19)

Status: Owner-approved product direction and required MVP milestone **5C**; architecture planning only. No application implementation or credential/data migration authorized yet.

Decision: Replace group-wide RSVP authority with an independent secure credential for each independent respondent. A wedding owner, or a future appropriately authorized operator, must explicitly designate representatives for specific dependents in the same invitation group. Each dependent has at most one representative; representatives require independent access, and dependents do not require simultaneous personal access by default. No self-delegation, chains, cycles or authority inferred from age, name or family relationship. Revocation/reassignment immediately changes permissions without deleting existing answers. Link possession is bearer authority, not proof of identity; OTP remains outside MVP.

Consequences: The approved remaining sequence is **5C → 6 Seating → 7 Internal Admin/assisted onboarding/manual payments → 8 Security/concurrency/deployment → 9 Customer pilot**, retaining D-018's acceptance through 5B and post-M9 template/CMS deferral. D-015/D-016's group-wide authority is superseded as the future product contract; their historical implementation/manual acceptance remains valid, and current code still uses group authority until a verified 5C cutover. Existing per-guest responses, timestamps, Attendance counting and snapshot/draft separation must be preserved. Legacy credentials must not retain write authority after activation.

The [5C architecture review](tasks/milestone5c_individual_rsvp_architecture.md) contains recommendations, not approved technical decisions: schema/counters, guest-name visibility, full legacy revocation versus read-only access, representative revocation consequences, retention and deployment/rollback policy require C01–C06 review before code. S01–S06 seating proposals remain unapproved and must be reconciled with individual/delegated responses; do not start 6.1. The one-active-wedding/onboarding proposal is separate and unapproved (P-009).

Source: Explicit Milestone 5C planning request approving individual access and designated household representatives, insertion before Seating and documentation-only boundaries.

### D-020 — Approved 5C contracts; implement foundation batch only (2026-10-19)

Status: C01–C06 explicitly approved by the owner, with amended C04. Implementation authorization is **5C.1a only**; approval of later contracts does not authorize their activation.

Decision: C01 retires legacy group-wide reads/writes/issuance through a controlled drained maintenance cutover and separate `/rsvp/{token}` personal namespace, without restoring unpatched group-writing binaries. C02 shows an individual greeting and only the respondent/authorized dependents. C03 representative revocation removes outgoing designations with confirmation; active rotation preserves them, and removing a designation never revives an old personal link. C04 preserves issued/delegated/responded guest records and requires a separate MVP invitation-withdrawal workflow (D-021). C05 approves two credential/delegation tables, two guest counters, wedding-first locks, per-guest optimistic checks, atomic representative batches and safe authorized no-op retries. C06 starts with isolated schema/model compatibility tests only.

Consequences: 5C.1a may add migrations, persistence models/relationships, factories and isolated tests. Credential rows start at version 1 on first issuance; no row means never issued. Retain cleared credential rows after revocation. Guest counters default to 0, without incrementing them in existing workflows yet. No live migration application, credentials/backfill, delegation assignment, revocation/cutover, public route, frontend or withdrawal implementation is authorized in this batch. Existing legacy group behavior stays active until the later controlled cutover. Implement narrow service/locking integration only after separate 5C.1b authorization. S01–S06 and P-009 remain unapproved.

Source: Explicit owner approval of C01–C06 and instruction to implement Milestone 5C.1a only, including C04's withdrawal adjustment.

Subsequent implementation authorization (2026-10-19): the separate 5C.1b task authorizes internal credential/delegation/RSVP services, isolated tests and safe existing-table wedding-first locks. It does not authorize migration application, new personal/customer routes, UI, legacy revocation or public activation. See checklist/architecture §7F for observed phase evidence; no additional product decision is inferred.

Subsequent implementation authorization (2026-10-19): the separately requested 5C.2 delivered disconnected UI. The 5C.3a task authorizes secure server-gated HTTP integration, identity/retention hooks and isolated personal-mode tests only. Default development stays legacy/unmigrated. No real cutover, credential clearing, deployment or 5C.3b/3c execution is authorized. This records explicit scope, not a new product decision or approval of Seating.

### D-021 — Required invitation withdrawal before Seating (2026-10-19)

Status: Approved MVP direction and placement; detailed product/technical rules and implementation require separate review.

Decision: Insert **5D — Invitation Withdrawal and Active Guest Eligibility** after 5C and before 6. Preserve retained guest records and saved answers/timestamps while allowing explicit withdrawal. Withdrawal must remove the person's response authority, prevent representative submission for them and exclude them from active invited/attendance counts. Define group membership/representative effects, reinstatement/fresh credentials, future seating eligibility/assignment treatment, tenant/concurrency protections and owner confirmations/statuses before implementation.

Consequences: Sequence is **5C → 5D → 6 → 7 → 8 → 9**. Both 5C and 5D must be resolved and owner-accepted before seating development, with S01–S06 separately approved. No `withdrawn_at`, guest status column, extra table, active-count filter or withdrawal UI in 5C.1a. Existing counts remain all current named guests. No reinstatement or group/seating cleanup policy is implicitly approved.

Source: Owner's amended C04 and explicit new Milestone 5D requirement in the 5C.1a implementation task.

### D-022 - Disposable development reset and individual RSVP activation (2026-10-20)

Status: Explicitly approved by the owner; overrides the pre-launch maintenance/backfill requirements of C01/D-020 for this disposable local database only. C02-C06 and amended C04/5D remain in force.

Decision: All current development data is synthetic/disposable. Verify the actual LOCAL connection, then authorize php artisan migrate:fresh only there. No legacy data backfill, historical token preservation, maintenance rehearsal or old-data rollback tooling is required. Activate individual RSVP and explicit same-group delegation as the normal application workflow. Permanently deny old group GET/POST/lifecycle routes regardless of configuration. Default RSVP_MODE=personal; any other configured value fails closed, never restores legacy authority. The local canonical URL must match the served origin.

Observed target: APP_ENV local, MySQL 127.0.0.1:3306, database wedding_rsvp, same connected DATABASE() name and local machine hostname. Reset and all 13 migrations completed; no seed/customer credentials were issued. The local canonical origin is http://127.0.0.1:8000. Automated tests still use separate disposable databases.

Consequences: The old immediate 5C.3b/3c migration-rehearsal gates are superseded for this pre-launch activation. Manual owner acceptance of 5C is still pending. Production deployment, backup/restore, old-binary rollback, external access logs, key custody, credential-example and actual MySQL concurrency verification remain M8 release gates. This is not permission to reset another database, deploy, implement 5D/Seating or commit/push. S01-S06 remain unapproved.

Source: Owner's explicit Milestone 5C.3 activation task and disposable-local-development reset authorization.

### D-023 — Milestone 5C owner acceptance (2026-10-20)

Status: Explicit owner confirmation; accepted functionality and test evidence, not authorization to implement 5D.

Decision: Milestone 5C is implemented and manually accepted, including all personal RSVP/household delegation acceptance scenarios and the RSVP Access clipboard and consecutive multi-member state retests. Individual RSVP remains the active development workflow; legacy group-wide invitation/RSVP authority is retired. Preserve earlier milestone acceptance, including 3A 8/8.

Evidence: The owner confirms all 5C manual acceptance tests passed. The original 19 MySQL cases plus four additional races passed against isolated MySQL/InnoDB (23 passes / 483 assertions); exact scope and limitations remain in the issue register. RF-025 is closed by the owner-confirmed retests. Remaining race scenarios and production security/deployment findings remain open. No new manual test count is inferred.

Consequences: Plan 5D next, using [the withdrawal proposal](tasks/milestone5d_invitation_withdrawal.md). D-021 requirements remain approved direction; W01–W06 detailed lifecycle/schema/UX recommendations require separate approval and implementation authorization. Do not start Seating until 5D is implemented/accepted and S01–S06 are separately approved. This documentation task neither modifies development data nor reruns tests.

Source: Owner request “Accept Milestone 5C and Plan Milestone 5D”, including explicit acceptance of bug-fix retests and all 23 isolated InnoDB cases.

### D-024 — Prioritize CMS v2/premium websites; postpone Seating (2026-10-20)

Status: Owner-approved priority pivot and architecture/planning task only. No CMS implementation, schema, dependency, design or revised complete MVP approval is inferred.

Decision: Prioritize planning an advanced structured Wedding Website CMS with configurable templates, one original premium template initially, reusable typed content, guided editing and an elegant envelope-opening experience. Postpone Seating until a new priority decision; it is not permanently cancelled. This explicitly supersedes D-010/D-018's timing restriction that multiple designs/advanced CMS wait until after all core milestones/M9. Preserve the accepted Modern Classic design and Milestones 1–5C, personal RSVP authority and secure snapshot publishing.

Consequences: [The CMS v2 proposal](tasks/cms_v2_premium_wedding_website_plan.md) owns CMS-A–E recommendations and CMS-01–CMS-09 approval questions. No freeform page builder, unapproved external runtime or copied proprietary artwork. Detailed schema, first design, media processing/limits, compatibility and editor contracts await approval and separate implementation authorization. 5D withdrawal remains required before the customer pilot and before future Seating; W01–W06 remain unapproved. Internal Admin/assisted onboarding/manual payments, release/security and customer pilot acceptance remain future requirements. S01–S06 remain unapproved. Confirm Seating's eventual MVP/pilot placement before declaring the revised MVP scope final; it has not silently disappeared.

Source: Explicit owner CMS v2 and Premium Template Architecture planning request, supplied Canva/Figma references and priority change after 5C acceptance. No database, runtime or application changes are authorized.

### D-025 — Figma fidelity and five independent introductions (2026-10-20)

Status: Owner-approved product direction and planning addendum only. Final architecture, exact reference frames/designs and implementation require approval.

Decision: Use Figma as a design specification source for Laravel/Vue wedding templates. Plan at least five distinguishable customizable introductions, selected independently of the website template and stored with validated settings in the CMS draft and published snapshot. Proposed names are Royal Wax Seal, Classic Ivory Letter, Botanical Bloom, Modern Folded Card and Romantic Photo Reveal; names and visual designs remain provisional. Require documented source frames, measurements/tokens/fonts/asset rights/responsiveness/motion/CMS fields/deviations, real matching-viewport browser comparisons and a discrepancy report before per-design owner acceptance. Preserve accessible skip/reduced motion/keyboard/mobile/replay/no-JS content, optional music, curated bounded customization and the guest/RSVP separation.

Consequences: Extend [the CMS proposal](tasks/cms_v2_premium_wedding_website_plan.md) and its batches. The supplied file `ibQ6WfXWt7sG2oPiRzIRvP`, page `0:1`, is the first official inspection/planning trial; [findings](tasks/figma_first_trial_design_spec.md) record a successful official MCP read. It does not approve a particular child frame, asset license, animation, final architecture or completed design. Vue 3 + TypeScript, existing Laravel/Inertia conventions and reusable accessible HTML/CSS/tokens remain required; generated code is reference only. No new dependencies, templates/components, commit or push are authorized by this task. D-024's priority and other milestone/security obligations remain intact.

Source: Explicit owner “Figma Design Fidelity and Five Customizable Envelope Introductions” addendum and first-trial Figma URL; owner requests findings/requirements/plan for approval before implementation.

### D-026 — Authorize CMS-A.1 internal contracts only (2026-10-20)

Status: Explicit owner implementation authorization for CMS-A.1 only; automatic verification is separate from manual design acceptance.

Decision: Implement code-owned website/introduction manifests, independent selection/version compatibility, PHP-authoritative schema-v2 validation for theme/intro/welcome/event details/closing/RSVP information, matching TypeScript contracts and shared-fixture/availability regressions. Reserve five intro identities without customer availability. Preserve schema-v1 Modern Classic, immutable publishing, private previews, personal RSVP/delegation, Attendance and navigation. Keep v2 saving/publishing/public rendering unavailable. No record conversion, migration application/reset, media processing, visual components, music/video, 5D, Seating, dependency additions, commit or push.

Reference approval: Figma file `ibQ6WfXWt7sG2oPiRzIRvP`, frames `81:146` and `2:70`, are initial implementation references only. Final fidelity, desktop adaptation, motion, asset/font rights and FD-01–FD-06 remain pending. The five intro designs are approved direction/reserved identities, not completed designs.

Consequences: The [CMS task's implemented A.1 section](tasks/cms_v2_premium_wedding_website_plan.md#12-implemented-cms-a1--internal-contract-foundation) owns exact internal bounds/presets, evidence and remaining decisions. CMS-01/CMS-10 are authorized only to this internal subset; CMS-A.2 persistence/media, full architecture, customer workflows and CMS-B visuals need separate approval. Other CMS-02–CMS-09/CMS-11 choices remain open except the two reference selections above. Modern Classic and 5C remain accepted; 5D is required before pilot; Seating remains postponed.

Source: Owner task “Implement CMS-A.1 — Typed CMS v2 and Template/Intro Contracts”, explicitly authorizing A.1 only and requiring a stop afterward.

### D-027 — Authorize CMS-A.2 persistence and image foundation only (2026-10-20)

Status: Explicit owner implementation authorization for CMS-A.2 only; automatic verification is not customer activation or visual acceptance.

Decision: Add template key/version persistence to existing drafts/revisions with valid Modern Classic defaults, preserving all prior versions/JSON/snapshots. Add minimal owned image assets and restrictive immutable revision references, models/relationships, synthetic fixtures and isolated ownership/constraint/compatibility tests. Keep independent introduction settings in the existing validated JSON. Use code-owned private storage/metadata and a narrow internal atomic snapshot/reference boundary; do not expose new customer routes or change the active v1 publisher.

Consequences: [CMS plan Section 13](tasks/cms_v2_premium_wedding_website_plan.md#13-implemented-cms-a2--minimal-persistence-and-image-foundation) records exact tables/defaults/constraints and evidence. Migrations apply only to verified disposable test targets in this batch; no development migration/reset/data change. No timezone, uploads, replacement processing, media delivery/cleanup jobs, audio/video, template switching, v2 saving/publishing/rendering, envelope/entourage UI, 5D or Seating. Existing Modern Classic/personal RSVP remain accepted; v2 and all five intros remain unavailable. CMS-B first Figma trial requires separate implementation authorization and outstanding FD-01–FD-06/rights/fidelity decisions. Media delivery/retention/quotas and remaining CMS/MVP/security decisions remain open.

Source: Owner task “Implement CMS-A.2 — Minimal Persistence and Media Foundation”, confirming A.1 completion and approving only this persistence scope. No commit/push authorized.

### D-028 — Authorize CMS-B.1a isolated Figma visual trial (2026-10-20)

Status: Explicit owner implementation authorization for a visual trial only; design acceptance remains pending.

Decision: Implement floral-trial and independent botanical-bloom from official Figma frames 81:146/2:70 using reusable Vue components, typed synthetic content and isolated browser access. Mobile follows measured source composition; desktop and simple opening motion are documented proposals. Rights-unconfirmed source artwork must not be redistributed; provisional original or licensed substitutes are allowed and must be identified. Preserve Modern Classic and personal RSVP. Do not activate CMS-v2 saving/publishing, mutate development data, implement other introductions/music/video or start later milestones.

Consequences: [Trial evidence](tasks/figma_first_trial_design_spec.md#8-cms-b1a-implemented-visual-trial) records actual comparison and remaining FD-01–FD-06 differences. The registry remains unavailable to customer workflows. Trial controls follow Horizon Minimal; floral invitation styling remains separate. Owner visual review precedes B.1b integration approval. This does not approve final asset fidelity, all CMS decisions or the remaining four intro designs.

Source: Owner task “CMS-B.1a — First Figma-to-Vue Visual Implementation Trial”. No commit/push authorized.

### D-029 — Enchanted Editorial isolated visual slice (2026-10-20)

Status: Owner-authorized CMS-B.2a implementation only; final visual acceptance and CMS integration remain pending.

Decision: Prioritize Enchanted Weddings as the premium visual direction; Romantic Wedding Story is a secondary wedding-content reference, not the Floral Trial. Implement only an isolated `enchanted-editorial` header/photographic hero and first editorial wedding-details section. Preserve asymmetric photography, overlaps, botanical linework, warm ivory/champagne and copper serif composition; adapt the business landing page into a personal invitation. Use typed synthetic wedding facts and the supplied private prenup photos, which the owner confirms are used with their sister's permission. Do not interpret this permission as commercial stock redistribution rights.

Consequences: Screenshot-based estimates must be identified because official Figma MCP is rate-limited. No source artwork is copied; original line art and licensed font substitutions are documented. Browser captures and private photo serving remain local. No intro, customer template/editor/publishing activation, database change or additional section is authorized. Existing Floral Trial, Modern Classic and personal RSVP remain intact. Owner visual review is a separate gate.

Source: Owner task “CMS-B.2a — Enchanted Editorial Premium Wedding Website Trial” and in-task family-photo permission. No commit/push authorization.

### D-030 — Enchanted direction approved; complete visual trial only (2026-10-21)

Status: Owner approves the B.2a desktop visual direction and authorizes CMS-B.2b; complete-template visual acceptance and CMS activation remain pending.

Decision: Extend the existing Enchanted hero into a cohesive, responsive local trial with optional story, canonical-date/time/named-zone countdown, six supplied private gallery photos with manual controls, independently authored public entourage groups and informational RSVP/closing. Use typed display inputs and reusable sections. Preserve the hero direction, Modern Classic and personal self/dependent authorization. This does not approve the whole template or activate a schema change, CMS editor/upload/save/publish, introductions, music/video or new RSVP behavior.

Consequences: New section types are proposed presentation contracts, requiring separately approved PHP validation/media resolution/timezone and publishing integration before customer use. Family photos remain private/local under the permission recorded in D-029. Complete-page/mobile/interaction owner acceptance is still a separate gate; source artwork/font identities and commercial distribution rights remain qualified.

Source: Owner task “CMS-B.2b — Expand Enchanted Editorial into a Complete Wedding Website”. No commit/push authorization.

### D-031 — Premium Delight direction and main-CMS-first delivery (2026-10-21)

Status: Approved product direction and documentation planning only; implementation batches and outstanding technical/design decisions require separate approval.

Decision: Prioritize the main Enchanted website, reusable content contracts, guided editor/image assignment, secure previews/publication and existing personal RSVP integration before five independent introductions. Plan personalized holder/dependent-only greetings, an interactive love-story timeline, server-confirmed RSVP feedback, optional licensed wedding music and lightweight Vue/CSS/SVG 2.5D effects. Preserve content independently of template presentation and guest identity independently of CMS. The owner approves Enchanted's overall visual direction, not final template acceptance. Defer AI assistants, video hosting/processing, WebGL and heavy motion frameworks from this cycle.

Hummingbird direction: The supplied private GIF is the primary anatomical and motion reference, not a cleared production asset. Create original or commercially licensed fine-art botanical illustration preserving its recognizable silhouette and colorful feathers. Separate nearly stationary hovering with extremely fast blurred wings/minimal body movement from slow short curved scroll-flight paths. Flight pauses keep the hovering bird alive. Demonstrate a later animated prototype against Enchanted and obtain owner approval before reusable CMS motion integration; no prototype implementation is authorized now.

Consequences: [Premium Delight plan](tasks/premium_delight_experience.md) owns proposed contracts, motion layering/budgets, music rights/runtime, usability gates and DEL-01–10 delivery slices. These qualify unimplemented portions of the earlier A–E plan without renaming completed evidence. Main-website-first requires an explicit disabled-intro contract and preservation of known unsupported content on template switches; both remain proposed changes to A.1. Music sources/caps, timezone policy, contract versioning, motion defaults and final art need specific approvals. 5D remains required before pilot/future Seating; Seating is postponed, S01–S06 unapproved, and M7/M8/M9 remain required. No customer v2 activation, application/data changes or implementation authorization follows from this planning decision.

Source: Owner task “Define Premium Delight Experience and Integrate It into Our CMS Roadmap”, hummingbird addendum and clarification separating hover/flight. No commit/push authorized.

### D-032 — DEL-01 reusable internal content foundation (2026-10-21)

Status: Owner-approved DEL-01 / CMS-A.3a only; implemented and automatically verified as an internal foundation.

Decision: Add PHP-authoritative Story/milestones, Gallery and custom Entourage contracts with matching TypeScript/shared fixtures, explicit disabled introduction and content-preserving template capability projection. Keep 128 KiB, 12 milestones, 24 gallery images, 16 role groups/250 people total and the plan's bounded text/member limits. Register Enchanted as unavailable. Read-only aggregate inspection found no schema-v2 drafts/revisions in the active local database, so evolve the internal v2 shape directly; require explicit intro `enabled` and do not reinterpret or convert v1. No database writes/migrations or customer CMS endpoints are authorized.

Consequences: Use the complete validated document for future saves; a filtered projection must never replace the source document. Image references are structural UUID/preset values, not ownership or public delivery grants. Existing v1, publishing and personal RSVP remain active and unchanged. DEL-02 ownership/upload/private-resolution, guided editor, customer publishing, timezone, music/motion/introductions and full premium acceptance remain separate work. The prior D-031 direction and unresolved PD decisions beyond this batch stand.

Source: Owner task “DEL-01 / CMS-A.3a — Reusable Wedding Content Foundation”. No commit/push authorization.

### D-033 — DEL-02a secure reusable image foundation (2026-10-21)

Status: Owner-authorized internal backend implementation only; automated evidence is recorded in CMS plan Section 18.

Decision: Reuse the proven GD/EXIF hero normalizer for wedding-owned immutable image assets, private code-owned storage, owner-authorized internal resolution and full-source media-reference validation. Keep the existing JPEG/PNG/WebP upload and decoded-image limits. Register verified processed metadata through the existing A.2 schema, with transactional reservation and conservative filesystem compensation. No new schema, HTTP endpoint, customer editor, v2 saving/publishing, public delivery or development data operation is authorized.

Consequences: Modern Classic retains its existing upload interface/storage; reusable assets remain a separate inactive integration foundation. A UUID is not authorization. Future HTTP adapters and DEL-03 must independently verify ownership, private headers and current-visible publication references. Filesystem/database crash recovery and new media races remain explicit gates, not claimed atomicity. No general asset deletion or cleanup scheduler is introduced.

Source: Owner task “DEL-02a — Secure Reusable Wedding Image Upload Foundation”. No commit/push authorization.

### D-034 — Future gallery auto-slide default (2026-10-21)

Status: Approved product requirement; implementation deferred to the gallery/editor integration batch.

Decision: Couples can choose automatic image advancement for the reusable gallery/carousel, enabled by default. This changes the future CMS gallery direction; it does not rewrite the completed B.2b manual-only trial or add playback settings to the active schema in DEL-02a.

Consequences: Future implementation must retain manual navigation and accessible visitor pause controls, avoid focus changes, and define reduced-motion, offscreen/background and timer-cleanup behavior. Exact interval/presentation and validated setting shape remain implementation proposals. No carousel/UI change is included in this backend batch.

Source: Owner's DEL-02a addendum requesting an auto-slide option, default on.

### D-035 — DEL-02b isolated guided editor and private media HTTP (2026-10-21)

Status: Explicit owner authorization for DEL-02b only; production/customer CMS-v2 saving and publishing remain unavailable.

Decision: Build reusable Welcome/Story/Gallery/Entourage controls and a functional authenticated private image picker over DEL-02a, with immediate Enchanted preview. Keep text/layout/section state in the current tab; upload success means a private asset was stored, never that a draft was saved/published. Use a server-controlled default-off boundary permitting only explicit local/test preview environments with required schema. Preserve canonical facts, validated full source and template capability projection; no per-template customer form or new media pipeline.

Consequences: New HTTP adapters use account/wedding ownership, CSRF, separate throttles, private/no-store/nosniff responses and safe errors. Ordinary portal navigation remains unchanged. A self-contained loopback harness uses verified temporary SQLite/storage/sessions and synthetic accounts; development MySQL is untouched. Hero/story-cover assignments are typed local preview slots, not silently added persisted CMS fields. Their durable shared contract, timezone, new media races, retained/public delivery and full draft/publishing remain DEL-03 approval gates. D-034 auto-slide remains a future requirement; this preview's gallery is manual. No intro/music/motion, 5D or Seating authorization.

Source: Owner task “DEL-02b — Reusable Guided CMS Editor and Secure Media Picker”, with Horizon Minimal supplied for portal controls. No commit/push authorization.

### D-036 — W01–W06 approved; authorize 5D.1a foundation only (2026-10-21)

Status: Explicit owner approval of W01–W06 in the [5D readiness handoff](tasks/milestone5d_implementation_readiness.md). Implementation authorization is limited to **5D.1a schema/model/factory/isolated-test foundation**, not withdrawal activation.

Decision:

- W01: One nullable guest withdrawal timestamp, explicit scopes, no global scope, no mass assignment, no withdrawal audit/history table or speculative index.
- W02: Future withdrawal atomically revokes the target's credentials and incoming/outgoing delegations; released dependents remain invited without automatic replacement access.
- W03: Future reinstatement counts the preserved RSVP immediately, without restoring credentials/designations or resetting an answer to Pending.
- W04: Preserve group membership, permit zero active members, use Active/Withdrawn/All management filtering and retain the 100-retained-members limit.
- W05: Block deletion while withdrawn and retain issued/delegated/responded records after reinstatement; the narrow reinstated never-issued/delegated/unanswered exception returns to ordinary deletion rules. A single current timestamp does not prove ever-withdrawn history.
- W06: Future active reads consistently exclude withdrawn guests; eligibility-only changes preserve RSVP response versions/timestamps. Use access-version invalidation plus an exact affected-person confirmation set containing access, response and link versions.

Consequences: 5D.1a adds persistence only. No authority changes, credential/delegation mutations, active-count filtering, readiness activation, routes or UI are authorized in this batch. No development migration/reset. Later 5D.1b/5D.2/5D.3 need separate scoped authorization; full 5D owner acceptance remains pending. DEL-02d acceptance remains pending; S01–S06 are unapproved and Seating stays postponed.

Source: Owner task “Milestone 5D.1a — Withdrawal Schema & Model Foundation”. No commit/push authorization.

### D-037 — Authorize 5D.1b internal lifecycle and eligibility only (2026-10-21)

Status: Explicit owner authorization for 5D.1b under approved W01–W06/D-036. Full 5D is not activated or manually accepted.

Decision: Implement internal owner-authorized withdrawal/reinstatement with wedding-first locks, exact affected-person/access/response/link confirmation, atomic revocation and retained answers, eligibility and deletion guards, plus focused isolated regression evidence. Keep the unmigrated development application compatible. No withdrawal HTTP/UI, active Attendance filtering, development migration/reset, CMS/publication or Seating changes.

Implementation boundary: A default-off immutable `InvitationEligibility` dependency is enabled explicitly by isolated test bindings only. No enabled provider binding, environment/configuration flag or public activation route is installed. Lifecycle calls refuse the default context; enabled personal services and guards require the withdrawal schema and loaded state. Later read/HTTP/UI integration and any activation require separately authorized work. This preserves the existing application without silently treating a missing column as active in the enabled path.

Source: Owner task “Milestone 5D.1b — Internal Lifecycle & Eligibility”. No commit/push authorization.

### D-038 — DEL-02e gallery limit and isolated editor visibility (2026-10-21)

Status: Owner-approved scope; automated implementation evidence is separate from pending owner acceptance.

Decision: A gallery contains at most **10 distinct image assets**, replacing D-032's 24-item gallery bound. Hidden items count toward the limit. The same owned image may still be reused in other sections/slots. Permit multi-photo upload and selection through the existing authenticated private library; no new delivery or persistence authority. Enabled empty Story/Gallery/Entourage remain visible and navigable in the isolated editor, with editor-only guidance; public output must not acquire empty placeholders. Remove the unconditional Story ampersand only.

Boundary: Text/layout remain tab-only. No schema, migration, customer Save/Publish, RSVP, 5D or template-design expansion. Existing image security limits and owner authorization remain unchanged. D-034's future auto-slide decision is unaffected; this editor keeps manual gallery navigation.

Source: Owner task “DEL-02e — Section Visibility & Gallery UX”. No commit/push authorization.

### D-039 — DEL-02e owner acceptance and DEL-03a contract gate (2026-10-22)

Status: Explicit owner acceptance of DEL-02e. DEL-03a implementation is requested, subject to the owner's explicit requirement to stop for unresolved important schema/version contracts.

Decision: Preserve the accepted Enchanted editor/design and implement durable owner drafts/private preview next, with optimistic concurrency, complete source/media preservation and no public publishing activation. DEL-03b owns immutable premium publication. No development reset/migration execution, RSVP/5D changes, unrelated dependencies, commit or push.

Approval boundary: Inspection confirms that durable photo slots, canonical timezone and handling an existing schema-v1 draft need decisions before implementation. [DEL-03a contract proposal](tasks/del03a_draft_persistence_contract.md) records DP-01–03 as **unapproved**. This entry does not approve those proposed field shapes/defaults or claim DEL-03a implemented. No application changes/tests/database operations occurred during this stop.

Source: Owner task “Implement CMS DEL-03a — Persistent Draft Saving and Private Preview”, including confirmation that DEL-02e passed owner acceptance.

### D-040 — DEL-03a persistence contracts approved (2026-10-22)

Status: Owner-approved DP-01–03, superseding D-039's contract-approval stop only.

Decision: Add optional independent `content.photo_slots` (hero/details/story using existing image references) without changing older v2 source semantics. Add canonical `weddings.timezone`, default `Asia/Manila`, validated against supported IANA names. Reuse the single wedding draft and optimistic version; never silently replace a Modern Classic draft or discard content during template changes. Block unresolved transitions. Preserve current immutable published snapshots/live websites, enforce photo ownership and reuse private media infrastructure. No per-template draft store or unnecessary abstraction.

Scope: Implement DEL-03a owner-authorized persistent saving/reload, dirty/stale feedback and private saved preview only, preserving DEL-02e and RSVP/5D. No development database reset or migration execution, public CMS-v2 publication, commit or push. Automatic verification does not constitute owner acceptance; DEL-03b remains separately authorized work.

Source: Owner explicitly approves all three DEL-03a contracts and instructs implementation. [Implementation and verification](tasks/del03a_draft_persistence_contract.md#6-del-03a-implementation-and-verification-closeout-2026-10-22).

### D-041 — DEL-03a.1 editor polish and optional presentation settings (2026-10-22)

Status: Owner-approved implementation scope. The owner confirms basic DEL-03a Save/reload/saved-preview tests passed; full polish acceptance remains separate.

Decision: Keep backend validation authoritative while showing friendly linked errors, marked fields and accessible feedback. Newly initialized Gallery is enabled; never override saved hidden preferences. Add reusable optional image `frame: fit|fill` and gallery `slideshow: {autoplay, interval_seconds}` within existing v2 content, without migrations/template-specific storage. Automatic slideshow defaults on for multiple visible images; expose manual arrows and pause/play, stop for interaction/focus, offscreen/hidden documents and reduced motion. Use bounded 5/8/12-second intervals. Older source without settings stays valid and is not rewritten on read; absent image framing preserves original presentation until explicitly changed.

Refine Enchanted font loading/display weights and add restrained original static rings/plane/flower SVGs plus visible-section landing references. No animated hummingbird (DEL-07), public CMS-v2 publication, RSVP/5D change, development database reset, commit or push. Preserve current template layout and persistence/version/security contract.

Source: Owner task “DEL-03a.1 — Editor UX & Wedding Design Polish”. [Implementation and evidence](tasks/del03a_draft_persistence_contract.md#7-del-03a1--editor-ux-and-wedding-design-polish).

### D-042 — DEL-03a.2 final UX polish and hummingbird preparation (2026-10-22)

Status: Owner-approved implementation scope; manual acceptance remains separate.

Decision: Consolidate CMS errors into one accessible linked warning summary, retain field focus/expansion and distinguish success/information/stale notices with restrained backgrounds. Fresh galleries and their media library start empty; list only the wedding's uploaded assets and preserve every existing upload/selection. Keep demonstration content in test fixtures. Add an accessible mobile Enchanted hamburger menu and a subtle reduced-motion-aware photograph transition while retaining desktop navigation, autoplay/manual/pause and existing intervals.

Expose stable, document-ordered landing markers for every visible major section and Story milestone, using milestone IDs. DEL-07 must use FRONT for forward-facing hover/rest, LEFT for flight toward left-side destinations and RIGHT for flight toward right-side destinations; derive flight orientation from direction/destination, including reverse scrolling. This batch prepares only the marker/discovery contract and records those states; no bird artwork, sprites, wing/scroll-follow animation or animation engine. Preserve drafts, approved design and RSVP/5D behavior. No public activation, migrations, development reset, dependencies in the project, commits or pushes. Stop after DEL-03a.2.

Source: Owner task “DEL-03a.2 — Final UX Polish & Hummingbird Preparation”. Owner additionally authorizes matching temporary WebAssembly QA tooling outside the repository after Windows blocked the installed native parser; project dependencies and Windows security settings remain unchanged. [Implementation/evidence](tasks/del03a_draft_persistence_contract.md#8-del-03a2--final-ux-polish-and-hummingbird-preparation).

## Working design proposals — not yet final product approvals

### D-053 — Owner-preferred hosting candidate for evaluation (2026-10-25)

Status: **Preferred candidate for evaluation; not an approved or verified production configuration.** Evaluate Hostinger VPS (KVM 2 candidate) for the existing Laravel + Vue/Inertia monolith, MySQL and Laravel workers; Cloudflare CDN for safe public static asset caching; Cloudflare R2 for private wedding-album object storage; one branded domain serving wedding-scoped routes. Preserve Inertia and Laravel public rendering; no separate Vercel/Netlify frontend deployment, standalone Vue SPA conversion or separate public API merely for MVP hosting.

Source: Owner's explicit candidate proposal, with no provisioning/deployment authority. [Evaluation scope, cache isolation and future validation](tasks/alb00_wedding_photo_album_plan.md#deployment-candidate--evaluation-only) distinguish public static assets from personalized RSVP/authenticated pages/Inertia/private media, which must not enter shared caches. Capacity, cost, region, runtime, private R2 delivery, effective CDN rules, performance, restore and operational security remain unverified release gates. No account/configuration/domain/storage/DB/dependency change or production activation. D-052's product approval does not approve this infrastructure; DEL-07b's pending visual gate remains unchanged.

ID Proposal What must be decided before implementation
P-001 D-026 authorizes only the delivered internal CMS-A.1 subset of CMS-01/CMS-10; remaining CMS-01–CMS-11 contracts/persistence/customer work in tasks/cms_v2_premium_wedding_website_plan.md require approval. D-011 remains the accepted live v1 boundary.
P-002 D-025/D-026 reserve five independent intro identities and select `81:146`/`2:70` as initial Figma references. Final visuals/desktop/motion/rights, completed-design acceptance and customer template compatibility remain unapproved; never copy unlicensed artwork.
P-003 Future revision history/rollback and signed draft previews Core publication is implemented under D-013; browsing, expiration/deletion and rollback rules remain undecided.
P-004 Milestone 6 Seating Planner is postponed under D-024 until a new priority decision, not cancelled. S01–S06 remain unapproved; 5D implementation/acceptance is still a prerequisite if Seating resumes. Confirm eventual pilot/MVP inclusion separately.
P-005 Trial launch prices: DIY ₱899; done-for-you ₱2,499 Validate pricing, support workload, revisions, hosting duration, guest caps; not committed prices.
P-006 Optional full Inertia SSR and deployment hosting Public HTML/metadata use Laravel plus Vue under D-013; a separate SSR process or hosting setup still requires approval.
P-007 Partner collaboration and multi-account switching remain optional; scoped internal staff access is required in Milestone 7 Approve the minimal operator/grant/audit policy for M7; do not treat a broad collaboration or permission system as approved.
P-008 Milestone 5C C01–C06 and amended C04 were approved under D-020/D-021; implementation/activation subsequently completed under D-022 and owner acceptance is recorded in D-023. Historical phase limits remain evidence, not current activation blockers.
P-009 Wedding-focused onboarding/workspace proposal **Onboarding/cardinality direction approved and superseded by D-064 / ONB-01.** Historical proposal: one active wedding, guided registration/setup, congratulatory transition, focused dashboard and possible multiple couple logins. The approved rule is one customer-owned workspace/one wedding, with preserved legacy exceptions and blocked further creation; it adds no archive/replacement flow. Partner grants/recovery, multiple couple logins, internal roles and assisted handover remain future decisions, not newly approved features.
P-010 W01–W06 are resolved by owner approval D-036, including preserved RSVP on reinstatement, the narrow W05 deletion exception and exact affected-person/version confirmation. Only 5D.1a foundation is authorized; later lifecycle/read/HTTP/UI integration needs separate authorization. [Readiness handoff](tasks/milestone5d_implementation_readiness.md). S01–S06 remain separate and unapproved.
P-011 D-032 resolves PD-01's delivered internal content subset; D-033 authorizes DEL-02a's internal image boundary. D-035/038/039 cover the isolated editor; D-040 approves slots/canonical timezone/private drafts. D-041 authorizes optional Fit/Fill and isolated Gallery playback implementing D-034's default-on direction. Remaining PD-02–PD-06 require publication-instant/DST rules, music rights/runtime, original hummingbird prototype, shared motion defaults/budgets and final design/pilot cutline. DEL-03b/public customer integration remains separate; no complete motion system or public slideshow activation is implied.

### D-043 — DEL-03a.2 acceptance and controlled DEL-03b implementation (2026-10-22)

Status: Approved scope; DEL-03b owner acceptance pending.

Source: Owner explicitly reports DEL-03a.2 OWNER-ACCEPTED (17/17 manual checks passed) and requests DEL-03b implementation.

Decision: Reuse the existing locked publication/revision infrastructure for complete validated immutable CMS-v2 snapshots, canonical facts/timezone and relevant media references. Add generic secure public Enchanted rendering/current-visible media, saved-state Publish/Republish/Unpublish and mobile smooth section navigation while preserving accepted design, drafts, Modern Classic and RSVP privacy. Reject unsupported personal invitation combinations pending separately authorized DEL-04. Verify in disposable environments including focused InnoDB checks; activation remains controlled through integrated verification and owner acceptance.

Boundaries: No production deployment/uncontrolled public CMS-v2 activation, development MySQL reset/migration application, new parallel publishing system, DEL-04 personal Enchanted RSVP, unrelated CMS/5D/Seating/hummingbird work, commit or push. Implementation uses default-off dual local/testing flags and hard production denial. [Implemented contract and exact evidence](tasks/del03a_draft_persistence_contract.md#9-del-03b--immutable-publishing-and-public-wedding-website). Earlier pending DEL-03a acceptance/publication statements are historical. Retention, template conversion/history restoration and production release policy are not newly approved.

### D-044 — focused DEL-03b.1 polish and loading-bird handoff (2026-10-22)

Status: Approved scope; delivered visuals/behavior await owner review.

Source: Owner explicitly requests DEL-03b.1 only: empty fresh photographic content/library, desktop smooth navigation, editor saved-state Publish/Republish replacing global reload, a readiness overlay with hovering hummingbird and initial Hero handoff, reusable FRONT/LEFT/RIGHT foundation and existing landing hooks.

Decision: Implement the smallest safe changes reusing immutable publisher and saved-draft rules. Scope permits original decorative loader artwork and first-landing animation, superseding earlier no-bird restrictions for this loading slice only. Reduced motion remains static; no full scroll-follow system is required or implemented. Conditional stale/uncertain-save recovery remains without restoring the global reload action. No owner acceptance of the future complete DEL-07 prototype is implied.

Boundaries: No unrelated RSVP/5D, DEL-04, migration/reset/dependency/deployment work or commit/push. Existing default-off local/testing activation and production denial remain. [Exact implementation and verification](tasks/del03a_draft_persistence_contract.md#10-del-03b1--focused-preview-publishing-and-loader-polish).

### D-045 — DEL-03b.1 acceptance and DEL-04 personal Enchanted integration (2026-10-22)

Status: Approved scope; DEL-04 implemented/automatically verified, owner acceptance pending (QA-UI-21).

Source: Owner explicitly reports DEL-03b.1 OWNER-ACCEPTED (14/14 checks), and requests DEL-04.

Decision: Connect published Enchanted snapshots to existing personal credentials, guest/dependent authorization, response service, CSRF/rate limits, versioning and wedding-first transactions. Separate current private guest state/greeting from immutable display content; generic pages have no private form/state. Lift only the temporary unsupported-personal guards for the exact supported controlled Enchanted version; never automatically revoke/rotate/recreate credentials. Preserve 5C Pending/partial/stale/server-confirmed behavior, Modern Classic, immutable media/publication and the accepted loader/Hero handoff.

Boundaries: Controlled local/testing CMS activation only; production availability and unfinished 5D withdrawal remain disabled. No new RSVP storage/service, intros/music/scroll-follow/celebration work, development database reset/migration, deployment, commit or push. Focused PHP/browser/privacy and disposable InnoDB schedules verified; stop for DEL-04 owner acceptance. [Exact implementation/evidence](tasks/del03a_draft_persistence_contract.md#11-del-04--personalized-enchanted-rsvp-integration).

### D-046 — DEL-04 acceptance and DEL-04.1 countdown (2026-10-22)

Status: DEL-04 owner-accepted, 20/20. DEL-04.1 implementation delivered; visual/manual acceptance pending QA-UI-22.

Source: Owner explicitly confirms DEL-04 acceptance and authorizes the focused countdown/navigation polish task.

Decision: Add an optional Countdown section to CMS-v2 Enchanted Editorial between Wedding Details and Our Story. Its only editable CMS values are heading/subtitle; canonical date, time and IANA timezone come from saved Wedding facts in private preview and from the immutable revision facts on published/personal pages. New Enchanted starter documents may enable it; old saved preferences and published snapshots remain unchanged. Reuse existing validation/save/publication/rendering and date-timezone behavior. Keep a readable no-JS date/time fallback and the stable `section:countdown` waypoint.

Why: This is an additive section under the approved v2 extension contract and avoids duplicating authoritative wedding facts in mutable CMS JSON. Snapshot rendering preserves published-site immutability.

Boundaries: No Guest Guide, RSVP/5D changes, new persistence/migration, production activation, bird artwork/flight/wing animation, commit or push. Preserve existing Publish/Republish confirmation/version guards and add only the editor’s “Back to wedding” control. [Exact behavior, tests, screenshots and isolated preview](tasks/del03a_draft_persistence_contract.md#del-041--wedding-countdown-and-editor-navigation-polish).

### D-047 — DEL-04.1 acceptance and DEL-04.2 Guest Guide (2026-10-22)

Status: DEL-04.1 owner-accepted, 14/14. DEL-04.2 implemented and automatically verified; QA-UI-23 manual review pending.

Source: Owner explicitly accepts all 14 DEL-04.1 checks and authorizes a reusable Enchanted Editorial Guest Guide between Entourage and RSVP.

Decision: Add one optional additive CMS-v2 `guest_guide` section with independently visible Attire, Gift, and FAQ blocks. Retain FAQ limits of seven, attire swatch limit of five, plain-text bounds and credential-free HTTPS registry links. New starters include the three neutral editable FAQs; older source/revisions remain untouched and may opt in through an explicit Add Guest Guide action. Hidden block/section content remains in saved immutable source but is omitted from public presentation. The informational RSVP answer uses the existing authorized section anchor for a personal visitor and neutral link guidance on generic pages. Continue using the existing CMS save, preview and immutable publication pipelines.

Why: The section is an additive Enchanted v2 content contract that requires no relational storage, new publication mechanism or parallel RSVP workflow. Explicit opt-in preserves older owner preferences; server and TypeScript checks bound content and prevent accidental bearer-link publication.

Boundaries: No schema bump/migration, payment processing, new RSVP behavior, 5D work, production activation, hummingbird flight, dependency, commit or push. Modern Classic and existing RSVP behavior remain unchanged. [Exact scope, verification and isolated preview](tasks/del03a_draft_persistence_contract.md#del-042--guest-guide).

### D-048 — DEL-04.2 acceptance and DEL-07a layered SVG prototype (2026-10-22)

Status: DEL-04.2 owner-accepted. DEL-07a is authorized as an isolated artwork/motion prototype and shared loader refinement; visual acceptance remains QA-DEL-06.

Source: Owner explicitly accepts Guest Guide and chooses layered SVG over attempted photorealistic frame assets. Authorizes five continuous facing states, independent fast wings, natural hover, bounded curved travel/turn/nectar landing and a contained demonstration using the existing bird/loader/waypoints.

Decision: Reuse one original code-authored SVG and EnchantedHummingbird for LEFT, FRONT_LEFT, FRONT, FRONT_RIGHT and RIGHT. Morph the same anatomy between headings; wing motion remains independent of body/heading/travel. At flower arrivals, orient the bill toward the blossom for nectar hover; this refines the earlier always-FRONT-on-arrival foundation. Reduced motion has a static decorative fallback. Coordinates/headings remain transient, with no CMS or database fields. Provide owner controls, website integration and visual/motion evidence before DEL-07b approval.

Boundaries: DEL-07a only. No scroll-following coordinator, music/introduction/RSVP/5D changes, production activation, development database operations, dependencies, commits or pushes. Artwork quality and final motion/size remain subject to owner visual approval. [Design, verification and isolated showcase](tasks/premium_delight_experience.md#del-07a--layered-svg-hummingbird-and-contained-motion-prototype).

Open product decisions

- DEL-03a DP-01–03 are approved under D-040. Explicit template conversion/mapping remains unapproved. D-043 separately authorizes controlled immutable DEL-03b publication and canonical timezone/UTC snapshot resolution; no automatic conversion or production activation.

- Which additional Modern Classic fields/sections, if any, should follow the bounded D-011 editor?
- Confirm the conservative public indexing recommendation and future snapshot/media retention policy; `/w/{slug}` and authenticated draft previews are established by D-013.
- Guest delivery/access, future plus-one behavior, RSVP editing cutoff and deadline defaults. D-014 establishes required names and optional group contacts only.
- Which guests can be assigned seats, and what capacity and layout-editing rules are required?
- Wedding/data retention and export policy, customer terms, hosting duration and cancellation/refund terms.
- Manual payment workflow and proof-of-payment requirements; who may verify and activate an order?
- Partner roles and how done-for-you staff access customer workspaces.
- Deployment host and support for server-rendered public invitation pages.

### D-050 — DEL-07b owner visual feedback refinement (2026-10-24)

Status: Approved scope for refinement; DEL-07b owner visual acceptance remains pending.

Source: Owner explicitly requests the three refinements before acceptance: actual nectar-centre contact, a visible upper-right Hero first flower and perceptible travel growth toward 1.18× desktop with a smaller safe mobile value. Preserve the exact accepted SVG, five headings, continuous wings and current scroll/CMS/RSVP/publishing contracts.

Decision: Refine the existing bird/waypoint consumer using DOM-derived nectar geometry and calibrated profile bill offsets; retain one first waypoint/bird. Rest at 1.0×, grow toward 1.18× desktop / 1.10× mobile, return to unit scale on approach, and slightly emphasize mid-flight speed while preserving reduced motion and safe bounds. Deliver actual before/after Hero/milestone/footer motion evidence and focused verification; persist no coordinates or new CMS fields.

Boundaries: This is DEL-07b owner-feedback work only. No broader DEL-07c, unrelated redesign, dependency, migration, development DB operation, production activation, commit or push. [Design and QA evidence](tasks/premium_delight_experience.md#del-07b-owner-feedback-refinement--nectar-contact-hero-and-travel-zoom).

### D-049 — DEL-07b guided hummingbird flight integration (2026-10-23)

Status: Approved for implementation; owner visual acceptance pending.
Context: DEL-07a's accepted original SVG, five-heading morph and Hero handoff need to become one persistent decoration across the published Enchanted page.
Decision: Reuse the existing loader/EnchantedHummingbird instance. After Hero readiness, discover visible document-ordered section and Story milestone markers and guide the bird between their transient viewport positions with frame-coalesced scroll/resize/mutation work, eased quadratic paths, subtle scale/orientation changes and flower-facing resting offsets. Keep wings independent, exclude hidden markers, suspend travel for reduced motion and retain pointer transparency. Store no coordinates or CMS data.
Why: This preserves the approved artwork and waypoint identity while keeping layout changes, fast/reverse scrolling and mobile behavior safe without a second animation system.
Consequences: DEL-07b is implemented and requires owner visual acceptance before DEL-07c polishing. Physical-device, assistive-technology and measured performance gates remain open; publishing, RSVP, CMS and 5D contracts are unchanged.
Source: Owner DEL-07b authorization after DEL-07a acceptance; [design and evidence](tasks/premium_delight_experience.md#del-07b--guided-hummingbird-flight-integration).

Decision-entry template
Append only after owner approval:

### D-XXX — Short decision (YYYY-MM-DD)

Status: Approved | Superseded (by D-YYY)
Context: What problem was decided?
Decision: Exactly what was approved?
Why: Main reasons and tradeoffs.
Consequences: What implementation/docs change follows?
Source: User approval / relevant approved task.
Never edit old approved entries to erase history. Supersede them with a new decision. Do not convert Codex recommendations to approved decisions without approval.
