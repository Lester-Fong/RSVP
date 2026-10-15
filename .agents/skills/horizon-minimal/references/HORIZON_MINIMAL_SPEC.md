# Horizon Minimal UI/UX Specification

## 1. Purpose

Horizon Minimal is the visual and interaction system for Horizon Church V2.

It is inspired by the Bryl Minimal design language, adapted from an editorial/portfolio aesthetic into a practical church operations dashboard. The goal is not to copy a website layout. The goal is to preserve the qualities that make the reference strong:

- monochrome restraint
- typography-led hierarchy
- precise spacing
- hairline structure
- soft geometry
- limited texture
- deliberate motion

while still supporting real management workflows, tables, forms, attendance, follow-up, and mobile usage.

## 2. Product character

Horizon should feel:

**calm, modern, clean, intentional, warm through spacing rather than color, and easy to operate.**

Avoid:
- green/blue accent palettes
- colored navigation backgrounds
- large stat-card grids
- repeated bordered containers
- generic dashboard gradients
- over-rounded bubbly controls
- dense enterprise layouts

## 3. Color system

### Light theme

| Token | Value | Purpose |
|---|---:|---|
| `--hm-bg` | `#ffffff` | page background |
| `--hm-ink` | `#0a0a0a` | primary text / primary inverted fill |
| `--hm-gray-50` | `#fafafa` | faint surface |
| `--hm-gray-100` | `#f5f5f5` | subtle surface / hover |
| `--hm-gray-200` | `#e9e9e9` | hairline borders |
| `--hm-gray-300` | `#d4d4d4` | stronger border / control |
| `--hm-gray-400` | `#a3a3a3` | tertiary text |
| `--hm-gray-500` | `#737373` | muted text |
| `--hm-gray-700` | `#404040` | secondary strong text |
| `--hm-gray-900` | `#171717` | near-ink |

### Dark theme

| Token | Value | Purpose |
|---|---:|---|
| `--hm-bg` | `#0c0c0f` | page background |
| `--hm-ink` | `#f4f4f5` | primary text / inverted fill |
| `--hm-gray-50` | `#18181b` | faint surface |
| `--hm-gray-100` | `#1e1e22` | subtle surface / hover |
| `--hm-gray-200` | `#2a2a30` | hairline borders |
| `--hm-gray-300` | `#3a3a42` | stronger border |
| `--hm-gray-400` | `#8a8a92` | tertiary text |
| `--hm-gray-500` | `#a0a0a8` | muted text |
| `--hm-gray-700` | `#d4d4d8` | secondary strong text |
| `--hm-gray-900` | `#ededf0` | near-ink |

Rules:
- Routine UI emphasis is monochrome.
- Primary action = ink background + theme background text.
- Secondary action = background/transparent + hairline border.
- Error/destructive states use text, iconography, labels, border treatment, and hierarchy; never rely on color alone.
- Status badges use text/border/inversion before color.
- No decorative gradients.

## 4. Theme behavior

Support exactly:
- Light
- Dark
- System

Default to `System`. Persist an explicit choice. Theme switching remaps semantic tokens rather than maintaining two unrelated designs.

## 5. Typography

Font roles:
- Geist — body/UI, buttons, forms, tables
- Geist Mono — nav micro-labels, statuses, timestamps, section markers, metadata
- Geist Pixel — selected page-title/signature display moments only; fall back to Geist Mono

Suggested scale:
- UI body: 15px
- Small UI/meta: 13px
- Micro-label: 10–11px, uppercase, mono, tracked
- Page title: 2.1rem–2.6rem desktop
- Section heading: 1.15rem–1.35rem
- Modal heading: 1.2rem–1.4rem

Preferred page-heading pattern:

```text
03 — ORGANIZATION

life groups
Manage current groups and assigned leaders.
```

## 6. Layout

Desktop:
- fixed left sidebar around 14rem
- compact top utility/header bar
- hairline separators, not header shadows

Mobile:
- sticky top bar
- accessible overlay/drawer navigation
- no document-level horizontal overflow

Purpose-based widths:
- reading/help/settings: 42rem max
- standard management page: 64rem–72rem
- table-heavy page: available content width
- modal forms: generally 28rem–40rem

Page spacing:
- mobile horizontal: 1rem
- desktop horizontal: 1.5rem–2rem
- section gaps: 2.5rem–3.5rem
- component gaps: 0.75rem–1rem

## 7. Sidebar/navigation

Brand:
- monochrome H monogram or future approved logo
- Horizon Church
- small workspace/role context if useful
- no green brand block

Navigation:
- inactive = muted
- hover = ink
- active = ink + stronger weight + small leading marker/arrow
- no large tinted active rectangle

User area:
- separate with a hairline
- name, role, theme control, sign out
- avoid a large tinted user card

## 8. Buttons

Primary:
- ink background
- background-colored text
- 6–8px radius
- 34–40px height
- no gradient

Secondary:
- transparent/background
- 1px hairline border
- ink text

Ghost:
- borderless by default

Destructive:
- explicit verb
- warning copy
- confirmation modal
- do not depend on red alone

## 9. Inputs and forms

- gray-50/background fill
- 1px gray-200/300 border
- 6px radius
- readable labels
- concise helper/error copy
- visible focus
- mono micro-label treatment is allowed only when readable

## 10. Modal convention

Short create/edit operations use a modal by default.

Examples:
- Create/Edit Life Group
- Create/Edit Ministry
- compact Member edits
- archive confirmation

Do not use inline forms for short CRUD.

Modal visual:
- full viewport overlay
- 25–35% dark overlay
- backdrop blur where supported
- centered panel
- width 28rem–40rem
- 16px radius
- hairline border
- soft deep shadow in light
- minimal shadow in dark
- 1.5rem–1.75rem padding
- accessible close control
- 180–220ms fade/scale

Modal interaction:
- focus trap
- Escape closes when safe
- focus returns to trigger
- backdrop click may close safe create/edit modals
- destructive confirmation should not accidentally close during submit
- accessible title/description relationships

Mobile:
- near-full-width with safe margins
- full-height dialog allowed for complex forms

## 11. Tables and management lists

Tables are first-class.

Style:
- no heavy outer card required
- hairline row dividers
- compact mono uppercase headers
- clear primary text
- muted secondary metadata
- subtle row hover
- avoid zebra striping by default

Example:

```text
NAME                  LEADER                  STATUS        ···
──────────────────────────────────────────────────────────────
Young Adults          Juan Dela Cruz          ACTIVE         ···
Couples Group         Maria Santos            ACTIVE         ···
North Group           Peter Reyes             ARCHIVED       ···
```

Row actions:
- ellipsis menu for secondary actions
- primary create action stays in page header

Mobile:
- local table overflow if needed, never page-level overflow
- compact list adaptation only when table becomes unusable

## 12. Cards and surfaces

Cards are not the default wrapper.

Use cards for:
- meaningful independent object grouping
- interactive object
- modal/panel
- visual content that benefits from framing

Prefer whitespace/alignment/hairlines over nested cards.

Radius ladder:
- large/card/modal: 16px
- medium: 12px
- small/control: 8px
- input: 6px
- pills: fully rounded

Shadows:
- soft, low-opacity, large blur, negative spread
- dark mode relies mainly on borders

## 13. Empty states

Avoid giant bordered boxes occupying most of the viewport.

Preferred:
- small halftone/icon accent
- concise title
- one short explanation
- one primary action when appropriate
- generous whitespace

Do not repeat the same CTA in header and center without a clear reason.

## 14. Status badges

- compact pills
- hairline border
- mono uppercase text
- small padding
- no strong colored backgrounds
- selected/high-priority may invert
- text communicates meaning independently of color

## 15. Halftone signature

Use monochrome dot texture sparingly:
- login visual area
- one dashboard/empty-state accent
- selected onboarding moment

Do not use behind dense tables or as full-page wallpaper.

## 16. Login page

Keep the current split structure if useful.

Left:
- monochrome
- restrained Horizon message
- halftone as signature
- no green fill

Right:
- simple sign-in panel
- minimal border/shadow
- Geist/mono hierarchy
- black primary button

## 17. Dashboard direction

When real metrics exist, prefer divided stat rows over colorful cards.

```text
MEMBERS          VISITORS         FOLLOW UPS        LIFE GROUPS
142              18               7                 9
──────────────────────────────────────────────────────────────
```

Do not invent fake metrics.

## 18. Motion

- micro state changes: 160–220ms
- modal fade/scale: 180–220ms
- hover lift/shadow: 280–380ms
- optional page entrance: 400–600ms
- strong ease-out
- no bounce
- always respect `prefers-reduced-motion`

## 19. Focus and accessibility

Required:
- semantic landmarks
- visible focus ring
- keyboard navigation
- labelled icon buttons
- focus trap in modal/drawer
- Escape closes overlays
- focus restoration
- readable contrast
- no meaning via color alone
- labels linked to controls
- useful errors
- reduced-motion support
- light/dark-compatible native controls

## 20. Responsive quality bar

Inspect at minimum:
- 1440px
- around 1024px
- 390px mobile

Pass:
- no page-level horizontal overflow
- no clipped modal controls
- no inaccessible table actions
- working navigation drawer
- no text collisions
- practical tap targets
- modal scrolls internally if necessary

## 21. Life Groups reference behavior

Default:
- micro-label + title + description
- primary New Life Group action
- simple table/list
- quiet Refresh action

Empty:
- restrained empty state
- no giant dotted bordered rectangle
- one clear CTA

Create/Edit:
- modal
- name, leader, description
- Cancel + Save/Create
- no inline form pushing list downward

Archive/reactivate:
- explicit confirmation for archive

Row menu, when useful:
- View gatherings (once implemented)
- Edit
- Archive / Reactivate

## 22. Component expectations

Prefer a small reusable set:
- Button
- Modal/Dialog
- PageHeader
- Input
- Select
- Textarea
- Field
- StatusBadge
- Table
- RowActionsMenu
- EmptyState
- LoadingState
- ErrorState
- ThemeToggle

Do not build a generic design framework beyond actual Horizon needs.

## 23. Quality gate

Frontend work is complete only after:
- lint passes
- build passes
- relevant behavior tests pass
- desktop/mobile visual review is performed where practical
- overlay keyboard behavior is verified
- Horizon Minimal evals pass
- no hard-fail rule remains

See `../evals/evals.json`.
