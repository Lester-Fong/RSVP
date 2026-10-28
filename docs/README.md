Wedding RSVP SaaS — Documentation Map
This folder is the durable context for Codex. Start at root [`AGENTS.md`](../AGENTS.md); read the smallest relevant references instead of loading everything for every task.
File Owns Update trigger
tasks/DEL-09a_Enchanted_Photobooth_Opening_Brief.md Owner's finalized first-intro visual/storyboard/accessibility/verification and 12-point acceptance direction D-058 supersedes the first envelope concept only; do not rewrite this as an acceptance record.
tasks/cms_customer_workflow_integration.md Authenticated real customer registration/wedding/Invitation/CMS/private-preview/publishing integration, backward compatibility, isolated evidence and owner MySQL action D-057; update actual verification and owner acceptance without changing production/release gates.
tasks/premium_delight_experience.md Premium Delight contracts, accepted DEL-07 bird/flight, DEL-05a/b Story/RSVP feedback and motion evidence D-056 authorizes complete DEL-05 without intermediate acceptance; synchronize actual checks and combined owner review without waiving release/device/performance gates.
tasks/dev_env_single_origin.md Port-8000 Windows manual startup, retained preview/MySQL separation, exact initialization approval boundary and environment verification D-054 mandates one owner-facing origin; update after startup/data/key decisions and actual checks. No automatic migration/seed/reset or production bypass.
tasks/deploy00_hostinger_readiness.md DEPLOY-00 Hostinger shared-host compatibility audit, private-staging proposal, release gates and runbook D-074 accepts revised CMS-UX-03 and authorizes evaluation only; purchase, DNS, migration, activation and deployment require later approval.
tasks/alb00_wedding_photo_album_plan.md Our Wedding Memories approved product direction, source-level reuse assessment, proposed access/media/reveal contracts, ALB-D01–07 decisions and ALB-01–04 batches D-051 approves business direction, D-052 approves ALB-D01 staged updates, D-053 records a preferred hosting candidate for evaluation only; update after explicit owner decisions and scoped implementation evidence. No album implementation/activation or DEL-07b acceptance inferred.
mvp_roadmap.md Canonical remaining MVP sequence, dependencies, acceptance gates, Milestone 6 proposals and pilot checklist Owner roadmap/scope approval or milestone status change; proposals remain unapproved until recorded in decisions.md
product_spec.md Target customers, user journeys, MVP scope and acceptance criteria Approved product requirement change
decisions.md Approved choices, rationale, open decision requests Explicit product-owner approval / reversal
architecture.md ERD, modules, tenancy, routing, CMS technical approach Approved architecture change
checklist.md Milestone progress and verification evidence Every authorized implementation milestone/fix
handoff.md Short current-state summary for the next Codex run After an authorized implementation/fix
risks_and_followups.md Structured issue register: confirmed bugs, unverified checks, security/deployment risks, accepted MVP limits and follow-ups Review relevant entries before implementation/fixes; update affected evidence/status/actions afterward
qa/verification_register.md Canonical QA execution/acceptance queue, exact MySQL inventory, prerequisites and evidence Review affected IDs before verification; update new skips/missing cross-engine coverage/visual and release gates at every implementation milestone closeout. Historical passes remain evidence; missing tests are Planned.
skills.md On-demand task playbooks / quality gates Development workflow change
tasks/milestone1_review.md Read-only source review assignment Edit only if the review scope changes
tasks/milestone5c_individual_rsvp_architecture.md Individual credentials/delegations design, historical transition phases, permissions, concurrency and acceptance D-020 approves C01–C06, D-022 activates development and D-023 records owner acceptance; preserve phase evidence. D-021 requires separate 5D withdrawal before Seating

tasks/milestone5d_invitation_withdrawal.md Proposed withdrawal schema/lifecycle, authority/retention rules, reinstatement, active counts, owner UX, races and implementation batches D-021 approves direction only; W01–W06 and implementation authorization remain pending; 5D must be owner-accepted before Seating

tasks/cms_v2_premium_wedding_website_plan.md Proposed typed CMS v2, independent website/intro contracts, five introduction designs, media, guided editor, compatibility and CMS-0/A–E batches D-024 approves priority and D-025 approves Figma/five-intro direction only; implementation/CMS-01–CMS-11 and final revised MVP scope remain unapproved

How documents stay consistent

`tasks/figma_first_trial_design_spec.md` owns the first official Figma trial's inspected frame IDs/measurements/tokens, source evidence, CMS mapping and FD-01–FD-06 approval gaps. The CMS v2 plan owns the independent five-intro architecture/batches and CMS-01–CMS-11 questions. D-025 approves direction only; update evidence when references or owner approvals change. Browser fidelity and completed-design acceptance remain unverified until implementation.

1. A direct user decision goes into decisions.md, then into the relevant specification/architecture when appropriate.
2. Codex implements the scoped change and verifies it.
3. Codex updates checklist.md and handoff.md with actual, evidenced results; it updates other docs only when affected.
   Maintain affected entries in risks_and_followups.md and link to their IDs rather than duplicating the full issue inventory. A proposed action is not product approval, and tests alone do not close an owner acceptance blocker.
4. A read-only review must not edit files, including documentation. Document recommended fixes in the review output instead.
5. Never mark manual browser QA or code review complete just because tests passed. Separate Codex-reported evidence from personally verified outcomes.
   Installation into the existing local repository
   This pack intentionally does not include or replace docs/architecture.md, which already exists in your project. Put AGENTS.md in the repository root, and merge the included docs/ files into your existing docs/ directory. Your repository already has an AGENTS.md: back it up and reconcile any project-specific rules before replacing it. Preserve README.md, your real .env, source code and migrations. See the chat instructions accompanying the download.
