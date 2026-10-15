Wedding RSVP SaaS — Documentation Map
This folder is the durable context for Codex. Start at root [`AGENTS.md`](../AGENTS.md); read the smallest relevant references instead of loading everything for every task.
File Owns Update trigger
product_spec.md Target customers, user journeys, MVP scope and acceptance criteria Approved product requirement change
decisions.md Approved choices, rationale, open decision requests Explicit product-owner approval / reversal
architecture.md ERD, modules, tenancy, routing, CMS technical approach Approved architecture change
checklist.md Milestone progress and verification evidence Every authorized implementation milestone/fix
handoff.md Short current-state summary for the next Codex run After an authorized implementation/fix
skills.md On-demand task playbooks / quality gates Development workflow change
tasks/milestone1_review.md Read-only source review assignment Edit only if the review scope changes

How documents stay consistent

1. A direct user decision goes into decisions.md, then into the relevant specification/architecture when appropriate.
2. Codex implements the scoped change and verifies it.
3. Codex updates checklist.md and handoff.md with actual, evidenced results; it updates other docs only when affected.
4. A read-only review must not edit files, including documentation. Document recommended fixes in the review output instead.
5. Never mark manual browser QA or code review complete just because tests passed. Separate Codex-reported evidence from personally verified outcomes.
   Installation into the existing local repository
   This pack intentionally does not include or replace docs/architecture.md, which already exists in your project. Put AGENTS.md in the repository root, and merge the included docs/ files into your existing docs/ directory. Your repository already has an AGENTS.md: back it up and reconcile any project-specific rules before replacing it. Preserve README.md, your real .env, source code and migrations. See the chat instructions accompanying the download.
