# DEL-09a — Enchanted Editorial Photobooth Opening

**Status:** Product-owner visual direction / Codex implementation follow-up, **not** an acceptance record.  
**Scope:** One premium introduction variant for the actual published Enchanted Editorial wedding invitation.  
**Supersedes:** The earlier *Botanical Ivory Envelope* visual concept for the **first** intro. This is an opening experience, **not a literal envelope**.  
**Execution:** If Codex is still working on the prior DEL-09a task, queue this as the next follow-up in the **same conversation**. Inspect/reuse its safe work; don't run competing agents against the same files.

## A. Product intent and visual north star

Make a guest feel they have arrived at a personal, nostalgic wedding keepsake: a beautifully designed **photobooth print station** that prints a physical-looking strip of the couple's photographs before the guest opens their invitation. The photos, not the machinery, are the star.

The owner's screenshots show a dark burgundy page with the couple's names above a silver ornamental photo-frame/photobooth plate; a thin photo strip moves **downward out of a horizontal slot**, progressively exposing black-and-white couple photos, then hangs/falls gently and settles. A visible **OPEN INVITATION** action leads into the invitation. **Use these references for composition and physical motion, not exact imagery, colors, branding, or ornament shapes.** Create an original Enchanted Editorial variant.

**Enchanted Editorial translation:** warm ivory/cream canvas, champagne or softly brushed pewter/taupe machine/frame, copper/rose-brown details, delicate botanical linework, expressive serif names and refined letter spacing. Use a nostalgic, tactile paper-and-photo treatment: slight grain/shadow, understated dimensional relief, elegant black-and-white or softly desaturated imagery. Avoid cartoon machines, gaudy gold, burgundy reference-theme copying, generic envelope flaps, scrapbook clutter, or flashy effects. The bird appears **after** entering the invitation; don't let it compete with the booth intro.

### Suggested visual hierarchy

1. Quiet introductory line: **WE'RE GETTING MARRIED** or **YOU'RE INVITED**.
2. Couple's **real published names**, with restrained editorial typography and mobile-safe wrapping.
3. Beautiful central metallic/ivory photobooth frame or print station with a narrow horizontal output slot.
4. A **vertical film/photo strip** containing the couple's real, authorized published photographs, visually moving out of that slot.
5. Wedding date from real published facts when present, rendered unobtrusively.
6. Immediately available **Open Invitation** primary action; optional quiet **Skip animation** secondary action; no audio or invented autoplay music.

The first screen should be aesthetically complete even before animation, at small mobile dimensions, on short laptop viewports, and when an image fails.

## B. Locked storyboard — visible guest UX

These durations are initial design targets, not forced waits or arbitrary guarantees. Tune after watching an actual desktop and mobile capture. **The CTA must stay actionable from the first usable frame**; never force guests to wait for the print sequence.

| Beat | Approximate sequence | Visual / motion | Interaction rule |
| --- | --- | --- | --- |
| **0. Arrival** | 0–0.4 s | Cream backdrop appears, couple's names and metallic frame settle with very subtle opacity/elevation. The output slot looks tangible. | `Open Invitation` is visible, focusable and active; optional skip works. |
| **1. Print begins** | 0.4–1.2 s | Leading portion of a vertical print strip comes out **downward from behind the slot**; 1st photograph becomes visible. Movement should be masked by the slot/frame, not float in front of it. | Early click exits cleanly without waiting. |
| **2. Memories emerge** | 1.2–2.5 s | Strip extends progressively, revealing remaining frames (ideally 3, possibly 2–4, based on real published media). Subtle printer feed rhythm without cheesy jitter. | No extra user action required for printing. |
| **3. Release and settle** | 2.5–3.2 s | After clearing the slot, the strip makes a restrained downward drop, tiny rotation/swing and soft settling motion. It remains legible, visually anchored and within safe viewport bounds. | CTA remains available. |
| **4. Open Invitation** | On explicit click, whenever clicked | Intro transitions elegantly out; website Hero becomes visible and the **existing single hummingbird loader/Hero handoff** continues without a second gate, duplicate bird or fresh loading flicker. | Restore proper scroll, focus and requested anchor. |
| **5. Continue** | Rest of visit | Normal invitation, Story, gallery, RSVP and navigation. | No repeat intro on internal navigation; user can view invitation immediately on subsequent appropriate visits. |

**Physics/motion notes:** Perceived realism is more important than precise time. A convincing *print-from-slot* needs a clipping/occlusion layer, scale-independent strip dimensions, paper shadow that changes subtly as it emerges, and an eased drop/small damped settle—not a full-page slide of a ready-made collage. No physical collisions engine or heavy animation library is needed. Avoid overshooting the CTA, obscuring meaningful content, or causing vertical document reflow.

### Entry, return, and special routes

- Show only for eligible **published Enchanted Editorial** guest-facing invitation entry; use one existing wedding-specific browsing-session memory strategy (if practical), avoiding PII/tokens in keys.
- Do not introduce the intro in **authenticated CMS editing**, no-JS rendering, Modern Classic, unpublished pages, or normal navigation between sections/pages.
- **Deep links / hash navigation:** preserve requested target. Prefer skipping intro for `#our-story`, `#rsvp`, etc. unless existing contract deliberately handles a direct opening that preserves the anchor. Never hijack RSVP access.
- **Personal RSVP entry:** if entering on a personal invitation link, don't expose private RSVP data in the intro; clicking open must land in the right experience. The intro must not change response state or grant access.
- **Refresh, Back/Forward, preview, revisits:** no unintended replay, jump to wrong section or loop. If session persistence is unavailable, fail *open* to the actual website.
- **Images slow/failed:** display an elegant fallback and keep Open Invitation working. Never keep guests behind a spinner/animation awaiting every photo forever.

## C. Real photo sourcing and CMS constraints

1. Use only **real published wedding facts and explicitly authorized published media references**. Do not accidentally pick a private CMS draft, pending photo, synthetic fixture, third-party demo picture, or future guest Photo Album asset.
2. Compose a tasteful vertical print strip from existing couple photos (preferred: up to three or four **distinct, published** Hero/Gallery/Story images if those categories are permitted by the current publishing contract). Inspect the actual renderer and published-image allowlist first; reuse the existing media resolver and rendering rules.
3. Photo orientation/crops should preserve faces and avoid awkward head cutoffs. Fit/focus should reuse legitimate published crop data where available. Never invent unique couple portraits, and avoid fake duplicate frames as a hidden assumption.
4. **Sparse content:** with 1–2 authorized images, use an intentionally designed short strip/fewer frames or unobtrusive editorial typographic filler. With no safe photo, show a dignified monogram/typography fallback, or bypass the booth print while still offering Open Invitation. No broken `<img>` or unauthorized assets.
5. Don't add a new image-upload pipeline or template-wide CMS schema just for this first intro unless existing media cannot satisfy the product requirement. If a dedicated selected-photobooth-photo control becomes necessary, report a **small separately approvable follow-up** instead of silently expanding scope.
6. Couple names, wedding date/timezone and media must match the **immutable published invitation**, not unsaved draft edits. Republish behavior must be correct and backward compatible.

## D. Implementation approach — inspect, then adapt

**First action:** Read AGENTS.md, DEL-09 tasks, D-051/ALB-00 relevant boundaries, current DEL-09a working diff, public Enchanted renderer/Blade bootstrap, EnchantedLoading + hummingbird handoff, published media resolver, and established browser fixtures. Determine how much of the prior *envelope intro* task can be reused (overlay lifecycle, session guard, keyboard/focus behavior, no-JS fallback). **Do not blindly stack a new intro on an old intro.**

**Suggested architecture, not a mandate:**

- One scoped `PhotoboothOpening` presentation component (or equivalent naming consistent with repository), with a small explicit state progression, e.g. `ready/printing/settling/opening/opened`. Use CSS/SVG transforms and masked overflow for feed mechanics; a lightweight layered SVG/CSS plate is better than a huge video file. Keep substantial animations transform/opacity based.
- Component receives safe **published** display props (names/date/photo renditions) from the existing wedding renderer; no new anonymous data endpoint just to load the intro. Enforce existing published-media filters.
- **One entrance coordinator** shared with the existing loader. Avoid both running full-screen blocking transitions or rendering duplicate hummingbirds. Don't alter approved bird SVG or scroll-flight geometry to accommodate the intro.
- **Action always available:** clicking Open during print completes/cancels pending timers/animations idempotently and enters the actual site. Provide cleanup on unmount, route changes, tab visibility changes, and reduced-motion preference changes. Don't leave scroll locked.
- If using sessionStorage, scope by stable nonsecret wedding identity and handle unavailable/denied storage. Never store bearer RSVP tokens, personal names, or media credentials there.
- Prevent CLS and jank; build responsive with CSS `clamp()`, max dimensions, safe viewport units (`dvh` where appropriate), safe-area insets; center subject across portrait phones, short screens and desktop.
- Reuse existing fonts, accessibility patterns and asset pipeline; avoid a new motion library, CDN dependence, video background or extra network origin.

### Reduced motion, no-JS and accessible interaction

- `prefers-reduced-motion`: render a static beautiful frame (optionally a fully emerged still strip) and a direct Open Invitation action; no falling, swinging, printing or unprompted motion.
- No JavaScript: the underlying invitation remains readable and navigable without the intro acting as a blocking screen.
- Keyboard: Escape/Skip when supported; Open must work with Enter/Space, logical initial focus, visible focus indicators and focus restoration after opening.
- Screen readers: label intro sensibly; mark purely visual machine, photos-as-decoration and motion layers appropriately. Don't announce each printed frame; keep one meaningful accessible heading and button.
- Users can always open the actual invitation; animation must never be required for RSVP or navigation.

## E. Exact scope / non-goals

**In:** one high-quality original Enchanted Editorial photobooth opening; published real-wedding data; responsive paper-strip mechanics; accessible opening; existing loader handoff; documented manual QA and screenshots/motion.

**Out:** literal envelope style, all five planned intro variants, template marketplace, audio, photo album product/guest uploads, new payment/entitlement rules, new photo pipeline, user-configurable photobooth themes, heavy 3D physics, seating, unrelated CMS overhaul and DEL-07 artwork changes. Do not alter the ten-image invitation Gallery limit.

**Safety boundaries:** Account/wedding scoping, private drafts, immutable publication, RSVP privacy/authorization, unchanged Modern Classic, DEV/QA fixtures never in production, no DB reset/migration/seed/data alteration without explicit approval, no unrelated dependencies, no commits/pushes, no production activation. Respect the existing staged status of DEL-05 owner acceptance and Milestone 5D/release gates; do not declare them complete.

## F. Verification and owner-visible evidence

Add focused tests (or expand existing ones) covering:

- A **new real published Enchanted Editorial wedding** with real published image references; not only synthetic demo.
- Couple/date/media personalization; no draft/private media leak; sparse/zero photos.
- Intro only on eligible guest entrance; once-per-session logic, reload, Back/Forward, deep links, direct RSVP/private links, concurrent tabs if relevant.
- Open clicked early/late, Skip, rapid double click, image failure, lost sessionStorage, reduced motion toggle during animation, cleanup/cancellation.
- Visual print strip **originating behind slot**, emerging sequentially, gentle drop/swing and stable resting position; no layout jump, clipped strip, overlap with names/CTA.
- Desktop short laptop viewport, 390px mobile, narrow 320px phone, tall/landscape viewport; performance on realistic midrange phone when possible.
- Keyboard/screen reader semantics, no-JS fallback, no trapped scroll/focus.
- Existing hummingbird one-bird identity and Hero handoff; existing Story/RSVP confirmation; CMS save/publish/republish, Modern Classic regressions.

Run relevant PHPUnit, frontend/browser, TS, lint/format and production build checks. Browser captures should include **before printing, halfway emerged, fully released, and post-open Hero**, for both desktop and mobile. Capture **real motion video**, not screenshots alone, and point out any design compromise.

**Manual-review origin:** `http://127.0.0.1:8000` for actual published wedding and local synthetic regression only as supplemental evidence. Do not require the owner to chase random localhost ports; internal browser harness ports may remain isolated.

**Stop gate:** DEL-09a *Implemented — Awaiting Owner Visual Acceptance*. Never self-approve visual polish. Report changes, exact commands/results, real-wedding URL, screenshots, desktop/mobile video and short acceptance checklist. If a manual/env action is necessary (server, `.env`, permitted migration, photo upload), provide exact action and impact rather than attempting risky workarounds.

## G. Owner acceptance checklist

1. First frame looks like **our Enchanted Editorial brand**, not a copy of burgundy reference or a literal envelope.
2. The metallic/taupe photobooth body is aesthetically convincing; output slot visually reads as a physical slot.
3. Couple names, date and photographs are **from the published wedding**, not demo data.
4. The photo strip visibly **prints downward from behind the slot**, exposing successive pictures.
5. Fully printed strip **drops/swings/settles naturally** and remains on-screen on mobile.
6. Open Invitation is clearly visible and works **before, during and after** animation.
7. Open transitions smoothly into the Hero, with the **same single hummingbird** and no second blocking loader.
8. Refresh, return visits, anchor links and personal RSVP do not replay/intrude incorrectly.
9. No photo, broken photo, reduced motion and no-JS degrade gracefully.
10. Mobile 320–390px, short desktop and keyboard interactions are beautiful and usable.
11. Saving unrelated drafts does not change live intro until explicitly republished.
12. Modern Classic and privacy/security behavior remain unchanged.

---

## Copy-ready Codex execution instruction

> **Follow-up to the active DEL-09a task:** Read this entire `DEL-09a_Enchanted_Photobooth_Opening_Brief.md` and implement it as the authoritative visual/interaction direction. It supersedes the earlier *Botanical Ivory Envelope* design **only for the first opening intro**. Inspect and reuse the existing DEL-09a work rather than duplicating or discarding safe implementation. Make the photobooth physically print a vertical real-couple photo strip from its slot, release it with a restrained fall/settle, then open the actual Enchanted Editorial published Hero while preserving our single hummingbird handoff. Proceed through implementation, targeted regressions and desktop/mobile motion evidence; stop for owner visual acceptance. Keep owner-facing previews on port 8000 and observe all existing no-reset/no-commit/no-push rules.
