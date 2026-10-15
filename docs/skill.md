Codex Task Playbooks (Read Only the Needed Section)
These are repository-local instructions, not a claim that Codex automatically installs executable skills. Root AGENTS.md tells Codex when to read them. Use only the playbook matching the active task; keep outputs concise.
Skill: review — Source-first, read-only code review
Trigger: review, audit, inspect, milestone sign-off.

1. Read task scope and handoff; inspect changed source, relevant tests, database constraints, policies, routes, and Git diff.
2. Reproduce or reason from observed code, not just a prior Codex summary.
3. Check tenancy, permissions, validation, cross-account IDOR, transaction boundaries, race conditions, browser form state, and MySQL-vs-SQLite differences as relevant.
4. Report findings CRITICAL / IMPORTANT / MINOR, each with file:line, evidence, impact, minimal fix and whether blocking.
5. End with APPROVED / APPROVED WITH MINOR NOTES / CHANGES REQUIRED. Report test checks actually run.
6. Do not edit code or documentation unless separately authorized.
   Skill: feature — Scoped implementation
   Trigger: approved feature milestone or change.
7. Restate success criterion and excluded features in 2–4 lines.
8. Inspect relevant code and docs; identify approved rules vs missing product decisions.
9. Implement the smallest vertical slice using Laravel policies/Form Requests/Eloquent and Vue 3 <script setup lang="ts">; avoid abstraction for abstraction's sake.
10. Add tests for happy path, validation, unauthorized/cross-tenant access and important edge cases.
11. Run targeted tests and frontend checks. Report exact results.
12. Synchronize checklist/handoff and other affected docs per AGENTS.md; don't inflate scope.
    Skill: database — Schema and tenancy change
    Trigger: migration, new table/foreign key, tenant relationship changes.
13. Review actual MySQL schema and ERD; design foreign keys, unique constraints and query indexes.
14. Make additive migrations. Never reset a database with real data. Use transactions and row locks where needed.
15. Tie tenant-owned records to an authorized parent and enforce scoping in server-side writes, reads and exports.
16. Test cross-account access and write attempts; avoid relying on SQLite to validate every MySQL-specific constraint.
17. Update architecture.md only for approved design changes; report migrations applied and to which environment without exposing secrets.
    Skill: frontend — Vue/Inertia quality
    Trigger: Vue component, editor, page, or form changes.
18. Favor existing components and design conventions. Vue 3 Composition API + TypeScript; typed props and Inertia forms.
19. Account for loading, validation errors, disabled states, responsive mobile layouts and accessible controls.
20. Keep domain authorization server-side. No secrets/guest private data in public Inertia props.
21. Run typecheck, lint, build and relevant behavior tests. Avoid arbitrary injected HTML.
    Skill: docs-sync — Preserve institutional memory
    Trigger: after an authorized implementation/fix, or an explicitly requested documentation task.
22. Compare the current task to actual diff, tests and approved decisions.
23. Mark checklist.md with evidence; update handoff.md in concise form.
24. Log only owner-approved choices in decisions.md; proposals remain proposals.
25. Edit product spec, architecture, README, AGENTS and skills only when impacted.
26. Do not reformat or rewrite entire documents, silently approve unresolved items, or update files during a read-only review.
    Compact completion format
    Result: completed | partial | blocked | review verdict
    Changed: path(s) and one-line purpose
    Verified: command → result (or not run, why)
    Risks: only genuine outstanding issues
    Docs: changed / unchanged with reason
    Next: one concrete next action
