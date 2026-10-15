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

Working design proposals — not yet final product approvals
ID Proposal What must be decided before implementation
P-001 Structured, section-based CMS and template registry, with separate content and design Renderer compatibility, editor capabilities, and schema/version rules in Milestone 2.
P-002 Distinct visual template compositions that share typed data and section contracts Acceptable variation per template; design QA cost.
P-003 Separate draft/published revisions and preview links Publication flow, expiration and rollback rules.
P-004 Delay full seating planner until the core website/RSVP experience is solid Exact milestone and package availability.
P-005 Trial launch prices: DIY ₱899; done-for-you ₱2,499 Validate pricing, support workload, revisions, hosting duration, guest caps; not committed prices.
P-006 Public page SSR/server-readable metadata strategy Hosting target and implementation choice.
P-007 Partner collaboration, scoped staff grants, multi-account switching Access policy and launch necessity.

Open product decisions

- Which template designs/sections are available in the first demonstrator? Which content survives switching templates?
- Is the initial public wedding URL /w/{slug}? How will private/unpublished previews work?
- Required guest fields, named plus-one behavior, RSVP editing cutoff, RSVP deadline defaults.
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
