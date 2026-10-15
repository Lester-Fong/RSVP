---
name: horizon-minimal
description: Apply the Horizon Minimal UI/UX system to Horizon Church V2 frontend work: a modern monochrome, typography-first, Bryl-Minimal-inspired operations interface with modal CRUD, hairline tables, dark mode, restrained motion, and strict visual QA. Use for any React/Tailwind screen, component, layout, form, modal, table, navigation, empty state, or UI refactor in Horizon V2.
---

# Horizon Minimal

You are the UI/UX design authority for Horizon Church V2.

This skill adapts the visual language of `bryllim/bryl-minimal-design` for a real church operations application. It preserves Horizon's product behavior and accessibility requirements while enforcing a modern, clean, aesthetic, monochrome interface.

## Required references

Before designing or changing Horizon UI, read:

1. `references/HORIZON_MINIMAL_SPEC.md`
2. `evals/evals.json`

Treat the specification as the visual source of truth and the eval file as the completion gate.

## Priority

When instructions conflict, use this order:

1. Locked Horizon product/business decisions in `docs/DECISIONS.md`
2. Accessibility, security, and functional correctness
3. This Horizon Minimal skill
4. Existing frontend styling

Never change business behavior merely to satisfy a visual rule.

## Core design intent

Horizon should feel:
- modern
- editorial
- precise
- calm
- premium but not luxurious
- highly readable
- intentionally minimal
- suitable for non-technical church leaders

It must not feel like:
- a generic green admin template
- a Bootstrap dashboard
- a colorful SaaS analytics product
- an art portfolio that sacrifices usability
- a dense enterprise back office
- a futuristic/glassmorphism interface

## Non-negotiable visual rules

- Use a monochrome semantic palette. Do not introduce green, blue, purple, or other brand accent colors for routine UI emphasis.
- Use typography, inversion, whitespace, dividers, and hierarchy instead of accent color.
- Use Geist for body/UI, Geist Mono for micro-labels/meta, and Geist Pixel only for selected display moments when available.
- Do not overuse the pixel font.
- Use one-pixel hairline borders and restrained soft shadows.
- Prefer whitespace and dividers over wrapping every section in a card.
- Management/list screens may be wide; reading/form content stays narrower.
- Primary actions are ink-on-background inverted buttons.
- Active navigation is indicated by ink/arrow/weight, not a tinted background block.
- CRUD create/edit forms open in accessible modals by default.
- Destructive confirmation uses an explicit confirmation modal.
- Tables use hairline row separation and compact, readable headers.
- Use ellipsis action menus for secondary row actions when appropriate.
- Support light, dark, and system theme modes.
- Halftone texture is a signature accent only; use it sparingly.
- Respect `prefers-reduced-motion`.
- Never trade accessibility for minimalism.

## Horizon-specific UX rules

### CRUD
Use modal dialogs for short create/edit forms.

Use a dedicated page only when the workflow is too large for a modal, such as:
- a complex multi-section record
- a detail view with sub-navigation
- attendance management requiring substantial workspace
- a multi-step flow

### Life Groups
- `New Life Group` opens a modal.
- `Edit` opens a modal.
- `Archive` uses a confirmation modal.
- Do not push the existing list/table down by rendering the form inline.

### Future management screens
Members, Ministries, Visitors, Users, Follow Up, Events, and OpenCell should inherit the same visual language and interaction conventions.

## Implementation approach

When restyling an existing screen:

1. Preserve behavior and routing.
2. Replace color tokens with Horizon Minimal semantic tokens.
3. Apply typography roles.
4. Correct layout width, spacing, border, and radius.
5. Convert short inline CRUD forms into modals.
6. Simplify cards and heavy containers.
7. Refine tables and row actions.
8. Add/verify theme behavior.
9. Add restrained motion only after static design works.
10. Perform visual QA using `evals/evals.json`.

Do not rebuild working application architecture purely for aesthetics.

## Visual QA requirement

For every substantial frontend change:
- inspect at desktop width around 1440px
- inspect at mobile width around 390px
- verify both light and dark themes when theme support is involved
- verify keyboard interaction for overlays/navigation
- verify no document-level horizontal overflow
- verify focus is visible
- run the Horizon Minimal evals

### Passing rule

- Every `hard_fail` rule must pass.
- Overall weighted score should be at least 90/100.
- If a hard fail is present, the UI task is not complete even if lint/build pass.
- If visual/browser verification cannot be performed, explicitly report which checks remain unverified.

## Avoid

Do not:
- add arbitrary accent colors
- create giant tinted active-nav rectangles
- create huge empty bordered boxes as the default empty state
- use multiple competing card styles
- use gradients as decoration
- use glassmorphism
- use heavy drop shadows
- use oversized headings everywhere
- put every form on a dedicated page
- render short create/edit forms inline above list content
- add a full design framework on top of Tailwind
- add animation before layout/accessibility is correct
- use color as the only way to communicate state
- fake product data just to make a design feel populated
