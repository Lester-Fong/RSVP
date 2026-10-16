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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.2. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project uses the streamlined Laravel 11+ structure: register middleware, exceptions, and routing in `bootstrap/app.php` and service providers in `bootstrap/providers.php`. There is no `app/Http/Kernel.php` or `app/Console/Kernel.php`, and commands in `app/Console/Commands/` auto-register.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
