Product and Architecture Decision Log
Last reconciled: 2026-10-16. A decision is Approved only when explicitly supported by the product owner's conversation/authorization; recommendations are labeled Proposed. This log is a summary, not permission to silently add features.
Approved / established direction
ID Decision Status Rationale / implications
D-001 Target engaged couples in the Philippines directly Approved Product messaging and early features should serve couples.
D-002 Offer both self-service (DIY) and done-for-you setup using one shared platform Approved Internal staff configure the same wedding records; no separate per-customer codebase.
D-003 Acquire initial customers via personal connections and referrals (including a wedding-host contact); use Facebook/Instagram/TikTok as supporting channels Approved Consultation-led onboarding; no large automated marketing funnel initially.
D-004 Initially accept manually coordinated GCash/Maya/bank transfers; no payment gateway yet Approved Payment records and verification may be built later; never treat an uploaded receipt alone as confirmation.
D-005 Use Laravel 12, Vue 3/TypeScript, Inertia, Tailwind 3, Vite, MySQL in one application Implemented Current installed foundation on PHP 8.2; avoid unapproved major upgrades.
D-006 Use account membership and wedding ownership in a shared-database modular monolith Implemented (Milestone 1 review blockers fixed; manual QA and owner approval pending) Foundation models, migrations, routes and policies exist. Tenant isolation still needs manual browser QA.
D-007 Implement in scoped milestones with Codex; review and test before advancing Approved workflow Prevent scope drift and accumulate verified, reversible changes.
D-008 Use a Modern Classic invitation for the first private wedding preview; preview remains authenticated and wedding-scoped Approved Explicit Milestone 2A task establishes the visual direction and private preview boundary. Use existing relational wedding facts; do not publish a public page in this milestone.
D-009 Use Laravel Boost as a local Codex development assistant through project-scoped stdio MCP Approved Explicit tooling integration task. Keep Boost in Composer require-dev; isolate its schema metadata cache in memory and do not change product runtime behavior.

Status update for D-006 (2026-10-16): The owner later reported that the core browser flow and cross-account 403 check passed. Separate explicit Milestone 1 approval remains pending; MySQL concurrency was not manually exercised.

### D-010 — Modern Classic and core functionality first (2026-10-16)

Status: Approved

Context: The existing Modern Classic invitation has a private preview. CMS editing, publishing, guest management, RSVP tracking, seating arrangements, and internal administration remain planned work.

Decision: Use only the existing Modern Classic invitation while developing those core platform capabilities. Defer multiple-template support, template selection and switching, and additional visual designs until the core functionality is developed.

Why: Focus delivery on the wedding management and guest workflows needed for a usable platform.

Consequences: Keep structured, validated wedding content separate from presentation so future designs can reuse the same data and business logic. Scope CMS and publication work to Modern Classic's supported content; keep generic template engines, registries, and switching infrastructure outside current implementation scope. The product owner will later provide Figma or Canva references before additional wedding templates are scoped. Earlier registry and multiple-template proposals in `architecture.md` are deferred by this decision. Implement each core area through a separately scoped milestone; existing Milestone 2A QA and acceptance remain pending.

Source: Explicit product-owner instruction to update the development roadmap on 2026-10-16.

### D-011 — Basic Modern Classic draft editor (2026-10-16)

Status: Approved scope; implementation subject to manual review and owner acceptance.

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

Status: Approved Milestone 5A scope; implementation and automated verification tracked in checklist.md. Manual 5A owner acceptance is pending.

Decision: An active personalized invitation permits its bearer to save attending/declined choices for currently named group members, reopen and update them, and submit only part of the group. Absence of a response means pending; omitted people retain their existing choice. Responses are independent relational records, never CMS or published-snapshot content. Unpublished weddings and invalid/revoked/rotated links cannot accept responses. Anyone possessing the link can respond for its group; no guest account is required.

Consequences: Implement unique guest-linked responses with timestamps, transactional token/publication/membership revalidation and stale-update protection. Renaming preserves identity and responses; a guest with a response cannot be permanently removed. New guests start pending. Membership/label edits remain visible to existing link holders. No response clearing, attendance dashboard, deadline, anonymous plus-one, meal/questionnaire, delivery or archival system is introduced. Milestone 5B owns attendance summaries and management.

Source: Explicit Milestone 5A implementation request, including partial responses, reopening/editing, integrity requirements and delegated minimal schema/deletion-policy choices. Implementation details and unverified MySQL races are documented in architecture.md.

## Working design proposals — not yet final product approvals

ID Proposal What must be decided before implementation
P-001 Extended structured CMS for Modern Classic D-011 implements only two messages and three visibility controls; images, appearance, order controls, and broader section contracts still require approval. Registry support remains deferred by D-010.
P-002 Additional visual template compositions that reuse wedding content and business logic Deferred by D-010 until core functionality is developed and the owner provides Figma or Canva references; later compatibility and selection behavior still need scoping.
P-003 Future revision history/rollback and signed draft previews Core publication is implemented under D-013; browsing, expiration/deletion and rollback rules remain undecided.
P-004 Seating arrangements within the core platform roadmap Guest eligibility, table capacity, and layout/editing requirements; scope delivery after the guest/RSVP foundations.
P-005 Trial launch prices: DIY ₱899; done-for-you ₱2,499 Validate pricing, support workload, revisions, hosting duration, guest caps; not committed prices.
P-006 Optional full Inertia SSR and deployment hosting Public HTML/metadata use Laravel plus Vue under D-013; a separate SSR process or hosting setup still requires approval.
P-007 Partner collaboration, scoped staff grants, multi-account switching Access policy and launch necessity.

Open product decisions

- Which additional Modern Classic fields/sections, if any, should follow the bounded D-011 editor?
- Confirm the conservative public indexing recommendation and future snapshot/media retention policy; `/w/{slug}` and authenticated draft previews are established by D-013.
- Guest delivery/access, future plus-one behavior, RSVP editing cutoff and deadline defaults. D-014 establishes required names and optional group contacts only.
- Which guests can be assigned seats, and what capacity and layout-editing rules are required?
- Wedding/data retention and export policy, customer terms, hosting duration and cancellation/refund terms.
- Manual payment workflow and proof-of-payment requirements; who may verify and activate an order?
- Partner roles and how done-for-you staff access customer workspaces.
- Deployment host and support for server-rendered public invitation pages.
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
