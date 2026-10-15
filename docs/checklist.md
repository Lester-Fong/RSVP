Wedding RSVP SaaS — Milestone Checklist
Updated: 2026-10-16. [x] reflects Codex-reported verification, unless otherwise stated. Local source review and manual browser checks must be marked separately. Milestone acceptance requires the owner to approve its review.
Phase 0 — Foundation

- [x] Initialize Git repository and Laravel 12 + Vue 3/TypeScript + Inertia + Tailwind + Vite project (Codex report).
- [x] Configure MySQL wedding_rsvp and run default additive migrations (Codex report).
- [x] Run foundation tests/build/typecheck/format checks (27 tests and 64 assertions reported at foundation stage).
- [x] Create initial docs/architecture.md (Codex report; proposed design, not implemented modules).
- [ ] Confirm safe initial Git checkpoint/commit exists (Codex reported no commit; do not assume).
      Milestone 1 — Account and wedding foundation
- [x] Add accounts, account_members, weddings tables (Codex report).
- [x] Add Eloquent relationships, owner membership, first-wedding transaction (Codex report).
- [x] Add create/view/edit wedding flows and dashboard (Codex report).
- [x] Add scoped routes, account/wedding policies, validation and slug handling (Codex report).
- [x] Run automated tests and quality checks (37 tests, 102 assertions; frontend/build/lint checks passed per Codex).
- [x] Perform source-level Milestone 1 review and resolve the two IMPORTANT ownership/concurrency findings (Codex review and fix; not owner approval).
- [x] Rerun automated checks after the review fixes (39 tests / 120 assertions; TypeScript, build, Prettier, targeted ESLint, and Pint passed).
- [ ] Manually verify browser happy path with a real account (create, edit, refresh, dashboard persistence).
- [ ] Manually verify cross-account access denied using a second account, including direct URLs.
- [ ] Owner approves Milestone 1; commit approved implementation and docs (commit only when authorized).
      Milestone 2 — Template engine and first public-page proof of concept
- [ ] Agree on first design's sections and the versioned template/content contract.
- [ ] Choose public wedding rendering / preview strategy and hosting assumptions.
- [ ] Write a scoped implementation plan and acceptance tests (no full CMS yet).
- [ ] Build one responsive template using wedding facts and typed components.
- [ ] Demonstrate authorized preview, isolation and correct mobile rendering.
- [ ] Review, manually test, approve, and commit (when authorized).
      Future milestones (do not implement yet)
- [ ] Structured editor for text, images, colors, fonts and section ordering.
- [ ] Draft/save/preview/publish workflow with versioned revisions.
- [ ] Invitation groups and individual guests.
- [ ] Per-guest RSVP, dashboard counts, and exports.
- [ ] Assisted onboarding and manual order/payment verification tools.
- [ ] Table and seating planner with capacity constraints.
- [ ] Production safeguards: policies, backups, privacy/retention, performance, monitoring, launch QA.
      Blockers / watch items
- PHP CLI is 8.2 and may need upgrade for future Laravel majors; remain compatible with Laravel 12 until explicitly changed.
- pdo_sqlite was enabled only temporarily for test processes; ordinary php artisan test may still fail until a persistent safe configuration is set.
- Nine npm audit development-tool advisories were previously reported, while npm audit --omit=dev passed; track deliberate upgrades separately.
- Manual browser QA, explicit owner approval, and an initial Git commit remain pending.
  Milestone close-out rule
  A task is implemented when code is in place; verified when required checks were actually run; approved only after code review, manual QA where applicable, and explicit owner sign-off. Never collapse these stages into one checkbox.
