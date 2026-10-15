ask: Milestone 1 Independent Code Review (READ-ONLY)
Invocation: “Read root AGENTS.md and execute docs/tasks/milestone1_review.md in review-only mode. Do not edit files.”
Goal
Determine whether Account & Wedding Foundation is safe, correct, minimal, and ready for Milestone 2. Do not implement fixes, migrate databases, install dependencies, update documentation, commit, or push.
Inputs to inspect

- AGENTS.md, docs/handoff.md, relevant tenancy and access sections of docs/architecture.md, Milestone 1 scope in docs/checklist.md.
- Actual User, Account, AccountMember, Wedding models and relationships.
- Wedding controller, policies, Form Requests, routes, migration(s), Vue/Inertia pages, and tests — discover precise paths in repository.
- git status, relevant Git diff if available. Avoid assuming the working tree is clean.
  Checks (evidence required)

1. Tenant isolation: membership-scoped list/view/update, nested route binding, policy enforcement, cross-account direct URL access, tampered account_id/wedding_id, authenticated-but-unauthorized requests, mass assignment, status/slug misuse.
2. Database integrity: FKs/indexes/uniques; duplicate memberships; collisions/concurrent slug creation; first-account-and-wedding transaction; behavior if owner already has an account; date/time nullability; MySQL-vs-SQLite mismatch.
3. User experience: create/edit happy path, form errors, loading/submit state, persistent values after reload, keyboard/mobile accessibility; no unnecessary architecture/duplication.
4. Tests: check that security scenarios truly assert denial and do not merely test redirects or existence. Identify gaps. Safe non-destructive tests may be run only against isolated test configuration; never the development DB for destructive test operations.
5. Scope: no unrelated auth changes, guest features, payment integration, unnecessary packages, or over-engineering.
   Output (concise, evidence-first)

- Verdict: APPROVED / APPROVED WITH MINOR NOTES / CHANGES REQUIRED.
- Findings, most severe first: severity; file:line; observed code; why it matters; minimal recommended correction; blocker (yes/no).
- Good decisions worth keeping (briefly).
- Checks performed: exact commands and results (or not run and reason).
- Manual browser QA still required: what the owner needs to test.
- Proposed docs updates: list only; do not modify any files.
- Next action: one small fix task if blocked, otherwise manual QA + owner approval/commit.
  Do not fill categories with invented issues. Distinguish confirmed bugs from plausible risks needing reproduction.
