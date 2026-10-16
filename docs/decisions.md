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

Status: Approved implementation scope; owner acceptance pending.

Decision: Add one optional uncropped photo below the existing invitation header through the existing draft save flow. Use private Laravel storage, owner-authorized delivery, server-generated immutable keys, and two nullable draft reference columns without changing version-1 CMS JSON. Native GD/EXIF strips metadata and normalizes JPEG/PNG/WebP (5 MiB/12 MP/6,000px bounds) to static WebP, maximum 1,600px long edge. No original retention, new dependency, media library, gallery, crop editor, or publishing. Replacement/removal obey the existing locked counter; cleanup is reference-aware, with dry-run-first pruning of generated orphans older than 24 hours. Future published references must be protected before publication is implemented.

Source: Owner selected uncropped placement and 5 MiB/runtime preparation during planning, then explicitly instructed “Implement the plan.” Runtime preparation, migration deployment, disposable MySQL verification, and manual acceptance remain operational prerequisites, not completed facts.

## Working design proposals — not yet final product approvals

ID Proposal What must be decided before implementation
P-001 Extended structured CMS for Modern Classic D-011 implements only two messages and three visibility controls; images, appearance, order controls, and broader section contracts still require approval. Registry support remains deferred by D-010.
P-002 Additional visual template compositions that reuse wedding content and business logic Deferred by D-010 until core functionality is developed and the owner provides Figma or Canva references; later compatibility and selection behavior still need scoping.
P-003 Separate draft/published revisions and preview links Publication flow, expiration and rollback rules.
P-004 Seating arrangements within the core platform roadmap Guest eligibility, table capacity, and layout/editing requirements; scope delivery after the guest/RSVP foundations.
P-005 Trial launch prices: DIY ₱899; done-for-you ₱2,499 Validate pricing, support workload, revisions, hosting duration, guest caps; not committed prices.
P-006 Public page SSR/server-readable metadata strategy Hosting target and implementation choice.
P-007 Partner collaboration, scoped staff grants, multi-account switching Access policy and launch necessity.

Open product decisions

- Which additional Modern Classic fields/sections, if any, should follow the bounded D-011 editor?
- Is the initial public wedding URL /w/{slug}? How will private/unpublished previews work?
- Required guest fields, named plus-one behavior, RSVP editing cutoff, RSVP deadline defaults.
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
