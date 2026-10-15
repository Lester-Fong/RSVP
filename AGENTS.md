Wedding RSVP SaaS — Codex Working Instructions
Repository-level agent instructions. This file is the entry point, not the entire project context. Follow the linked documents selectively to minimize tokens and avoid scope drift.

1. Product and technology
   Build a multi-customer Wedding RSVP SaaS for engaged couples in the Philippines, sold through both self-service and done-for-you workflows. Early customers come from referrals/personal networks, with Facebook, Instagram, and TikTok supporting outreach. Payments are initially discussed and verified manually (GCash/Maya/bank transfer); do not add a payment gateway without approval.
   Existing stack: Laravel 12, PHP 8.2, Vue 3 + TypeScript, Inertia.js, Tailwind CSS 3, Vite, MySQL. Keep a modular monolith. Use the existing repository conventions; avoid unnecessary packages, microservices, generic repositories, GraphQL, or infrastructure changes.
2. Source-of-truth map — read on demand
   Need Read
   Current task/status/next action [`docs/handoff.md`](docs/handoff.md)
   What the product must do, and MVP limits [`docs/product_spec.md`](docs/product_spec.md)
   Approved decisions and unresolved proposals [`docs/decisions.md`](docs/decisions.md)
   Technical design, ERD, tenancy, CMS and routes [`docs/architecture.md`](docs/architecture.md)
   Milestones, acceptance criteria, verification [`docs/checklist.md`](docs/checklist.md)
   Small task-specific playbooks [`docs/skills.md`](docs/skills.md)
   First pending review assignment [`docs/tasks/milestone1_review.md`](docs/tasks/milestone1_review.md)
   How to install/run/test locally README.md
   Documentation rules and index [`docs/README.md`](docs/README.md)

Context efficiency: At task start, read this file, docs/handoff.md, and only the relevant sections of the specification/architecture/skills/checklist. Inspect actual files before making claims. Do not dump entire files or repeatedly summarize unchanged context.
Precedence: Direct instructions for the current task > newly approved entries in docs/decisions.md > docs/product_spec.md for product behavior > docs/architecture.md for proposed technical design > tracking documents. If they conflict, report the conflict and ask for a decision before changing behavior. Do not silently rewrite approved decisions. 3. Operating modes and scope control

- Review / plan / audit: Read-only unless the task explicitly authorizes creating a report/document. No code edits, migrations, package installs, commits, or pushes.
- Implementation: Make the smallest coherent change needed for the authorized milestone. Prefer existing Laravel models, policies, Form Requests, migrations, Inertia pages and Vue components. Don't build future features opportunistically.
- Fix: Identify root cause; change the smallest relevant area; test the regression.
- Unclear requirement or significant tradeoff: State assumptions/options; do not silently decide product rules. Safe non-destructive implementation details may be chosen autonomously.
- Never run destructive database commands (migrate:fresh, db:wipe, migrate:refresh, reset/seed against valuable data) or modify system-wide PHP settings without explicit approval. Treat database credentials and .env as secrets.

4. Architecture and security guardrails

- Tenant ownership currently follows User -> account_members -> Account -> Weddings. Scope private queries and nested routes through membership and Laravel policies. Never trust client-supplied account_id, wedding_id, ownership, role, or payment verification status.
- Keep wedding facts and operational data relational. Plan website appearance/content as validated versioned sections (JSON revisions), separate from RSVPs, guests, orders and seating.
- Public wedding pages and guest RSVP links are different access surfaces. Private guest data must not leak into public props/HTML, logs, exports, or other accounts.
- Use explicit validation, authorization, DB constraints and transactions for multi-record operations. Avoid executing arbitrary user HTML/JS.
- Keep PHP 8.2 compatibility until a deliberate version-upgrade decision is approved.

5. Testing and verification

- Before changing code: inspect relevant source, tests, migrations, and Git diff/status.
- After changing code: run the relevant Laravel tests, frontend typecheck/lint/format, and build as applicable. Report exact commands and pass/fail; never claim checks you did not run.
- Laravel tests currently use an isolated in-memory SQLite database with temporary PHP extension loading in Codex's environment; do not redirect tests to the wedding_rsvp development database. Global pdo_sqlite may remain disabled.
- For database changes, add/adjust migrations and feature tests; check MySQL-specific constraints where applicable. No destructive resets.
- For security-sensitive access changes, test another user's/account's read and write attempts, not only happy paths.
- npm audit has reported development-tool advisories, while npm audit --omit=dev previously passed. Investigate with evidence; never run npm audit fix --force or a breaking major upgrade automatically.

6. Mandatory documentation sync (part of each authorized implementation)
   Documentation updates are task-completion work, not a separate future promise. Before reporting a task complete, inspect what actually changed and synchronize only affected documents:
1. docs/checklist.md — mark steps complete only with observed evidence; record blockers or pending manual QA. Maintain the next actionable step.
1. docs/handoff.md — keep concise: current state, what changed, verification, known risks, next task.
1. docs/decisions.md — add a dated entry only for an explicit owner-approved product/technical decision; keep unresolved recommendations under Proposals/open questions, not as approved facts.
1. docs/product_spec.md — update only when requirements or scope actually change.
1. docs/architecture.md — update only when a design is approved and implementation changes the architecture/ERD/contracts. Preserve rationale; avoid speculative diagrams.
1. README.md — update only when setup, dependencies, commands, or environment behavior changes.
1. docs/skills.md and AGENTS.md — update only when our development workflow/policies materially change; never weaken security or expand project scope silently.
   Exception: For read-only review tasks, do not edit documentation. Report proposed documentation updates in the review output; apply them only in a separately authorized fix/docs task. If the task explicitly forbids documentation edits, honor it and report out-of-date files.
   Never claim these files update by themselves: they are maintained when Codex performs and finishes an authorized task. No unattended automation is configured.
1. Git and output economy

- No commit/push unless explicitly requested. Do not overwrite pre-existing uncommitted changes. Review git status/diff before and after work.
- Final response should be compact and actionable: Summary; Files changed; Verification (commands + results); Risks/blockers; Docs synchronized; Next recommended action. For reviews, prioritize findings by severity with path:line references and state APPROVED / APPROVED WITH NOTES / CHANGES REQUIRED.
- Avoid repeating full architecture/specification text, huge code blocks, lengthy narration, or speculative plans when a task asks for a small change.
