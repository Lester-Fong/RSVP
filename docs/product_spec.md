# Wedding RSVP SaaS — Product Specification (Living v0.1)

Last reconciled: 2026-10-16
Scope label: Confirmed direction + explicitly marked proposed details. This is not a claim that listed future features are implemented.

## 1. Product goal and audience

A reusable wedding website and RSVP platform for engaged couples in the Philippines. A couple can personalize and publish the existing Modern Classic wedding invitation, share invitation links, and understand how many invitees will attend. The same software supports DIY and done-for-you service. Modern Classic is the only current template; additional designs and template selection/switching are deferred until core functionality is developed (D-010).

Business goal: acquire paying customers profitably through referrals/personal networks first; use Facebook, Instagram and TikTok as supporting channels. Couples consult with us before purchase. Payments are initially coordinated manually via GCash/Maya/bank transfer; no gateway required for launch.

## 2. User types

- Couple/customer owner: Manages their wedding workspace, details, and (later) invitation/guest information.
- Partner/co-editor: Future authorized collaborator; invitation/permission workflow not implemented.
- Internal operator: Future team member who onboards done-for-you clients, configures their wedding, and records payment verification; exact permissions TBD.
- Guest: Future invited attendee, no customer login required to respond through a private invitation link.

## 3. Service journeys

DIY — desired journey: Introduced or referred → consultation/package agreement → customer access activated → edit supported Modern Classic wedding content → review/preview → publish → share invitations → track responses → manage seating.

Done-for-you — desired journey: Introduction → consultation → provide wedding assets/details → authorized staff configures Modern Classic using the same CMS → couple reviews/approves → publish → couple accesses dashboard and can update authorized details.

Automatic checkout, public self-service registration funnel, automatic reminders, and payment gateway integration are not needed for initial launch.

## 4. Capability roadmap

The approved priority is CMS editing and publishing for Modern Classic, followed by the guest/RSVP foundations, seating arrangements, and internal administration. Scope each milestone separately; all of these core capabilities take priority over expanding the template catalog.

| Stage | Capability | Functional expectations | Status |
| --- | --- | --- | --- |
| Foundation | Authentication and wedding workspace | Owner can create/edit a wedding; private tenant isolation | Implemented per prior verification; final owner approval pending |
| First website vertical slice | Existing Modern Classic invitation | Reads stored wedding facts; private responsive preview; separate content and presentation | Milestone 2A implemented; owner visual QA and acceptance pending |
| Core platform | Basic Modern Classic CMS editing | Introduction/closing text, ceremony/reception/closing visibility, live preview, and persistent private draft | Milestone 2B implemented; manual QA and owner acceptance pending |
| Future CMS scope | Additional content controls | Images, appearance, section visibility/order beyond the basic editor | Not included in Milestone 2B; scope separately |
| Core platform | Single wedding hero photo | Private select/preview/save/replace/remove through Modern Classic's existing draft editor | Milestone 2C.1 implemented; deployment prerequisites, verification, and owner acceptance tracked in checklist.md |
| Core platform | Publication | Draft save/preview and published snapshots; only published data appears on the public wedding page; link-preview metadata | Planned; routing/rendering/hosting decisions pending |
| Core platform | Guest management | Individuals and household/invitation groups; attendance/companion limits | Planned; guest and plus-one rules TBD |
| Core platform | RSVP tracking | Per-person attending/declined/pending states; group responses; counts and exports | Planned; response rules TBD |
| Core platform | Seating arrangements | Tables, capacities, guest assignment/reassignment, and saved arrangements | Planned core capability; eligibility and layout controls to scope |
| Core platform | Internal administration | Explicit staff access, assisted onboarding through the same CMS, manual order/payment records and verification | Planned; detailed permissions and workflow TBD |
| After core functionality | Additional wedding templates and selection/switching | Reuse the same wedding content and business logic; owner supplies Figma or Canva references before design work is scoped | Deferred by D-010 |
| Later enhancements | QR check-in, custom domains, automated payments/reminders, advanced analytics | Introduce only for validated demand | Deferred |

The first demonstration is not the complete MVP. Avoid announcing the product as customer-ready until the scoped core functionality, privacy, support, and operational safeguards are tested.

## 5. Customer-facing behavior and future acceptance criteria

### Implemented basic invitation editor — Milestone 2B

- From a wedding overview, its authorized owner opens **Customize invitation**. Each wedding has an independent private draft.
- Edit introduction and closing messages (required plain text, up to 500 characters each, multiline supported). Defaults remain “Joyfully invite you to celebrate their wedding” and “We look forward to celebrating with you”. Unsupported fields and markup are rejected; rendered text is escaped.
- Toggle ceremony details, reception details, and closing message. Couple names, invitation heading, and celebration date/time remain visible. Hidden content stays saved, including venue values on the wedding record.
- Live preview reflects local unsaved changes using Modern Classic. **Save Draft** persists customization; reopening/refreshing the editor and the standalone private preview show the last saved draft. Stale editor saves show a conflict error rather than overwrite newer work.
- **Edit wedding details** links to the existing form for names, date/time, and venues. Those facts are not duplicated into CMS JSON.
- The editor uses Horizon Minimal's restrained monochrome layout and existing light/dark/system preferences, with the existing portal font as fallback where Geist is unavailable. The invitation retains its own Modern Classic identity.
- No public publishing, history/rollback, image uploads, design controls, or template switching is included. Automated verification is recorded in checklist.md; owner acceptance still requires manual review.

### Future wedding website behavior

Milestone 2C.1 adds one optional private hero photo between Modern Classic's existing header and celebration details, uncropped and preserving aspect ratio. JPEG/PNG/WebP uploads are limited to 5 MiB, 12 million pixels, and 6,000px per dimension. Saved originals/location metadata are discarded; the platform retains an optimized static WebP, up to 1,600px on its long edge, without upscaling. HEIC, galleries, cropping controls, and animation preservation are excluded. Photo changes join the existing Save Draft operation and stale-save safeguards; unsaved photos appear only in local preview. Authenticated owner-only delivery remains private until a separately scoped publication feature exists.

- Modern Classic renders structured, validated wedding content separately from its Vue layout and styling. Future designs can reuse that content and the same business logic.
- Wedding facts, guests, RSVPs, and seating remain independent of visual design; operational data stays relational.
- A mobile-first preview shows Modern Classic and the customer's content.
- Only authorized users edit drafts; published pages do not inadvertently show draft changes.
- Invitations have useful social link-preview metadata.

### Invitations and RSVPs

- An invitation can identify multiple named guests (family/household).
- Each invited person has an independent attendance state, so partial-family attendance is countable.
- A private invitation link cannot expose unrelated wedding/guest data.
- Counts and exports reflect attendance per person, not number of forms submitted.
- Rules for plus-ones, changes, deadlines and optional questions require owner approval before implementation.

### Seating arrangements

- Tables and guest assignments are saved within the authorized wedding; assignment consistency and table capacities are enforced.
- Attendee eligibility and layout-editing controls require scoping before implementation.

### Service operations

- DIY and done-for-you use the same underlying wedding data and CMS.
- Internal staff access to customer weddings is explicit, scoped, and auditable; exact roles and permissions require approval.
- Payment and publishing/activation must not rely solely on a screenshot as proof of funds.
- Scope, revisions, hosting duration, data retention and cancellation terms must be defined before real customer launch.

## 6. Current delivery boundary and deferred scope

Deliver the core capabilities through separately scoped milestones using only Modern Classic. Keep content separate from presentation, and keep generic template engines, registries, and switching layers outside current implementation scope.

Multiple-template support, template selection/switching, and additional visual designs are deferred until core functionality is developed. The product owner will later provide Figma or Canva references before additional wedding templates are scoped. Evaluate any need for shared template infrastructure at that later stage.

Payment gateway integration, subscription billing, custom domains, mass messaging, marketing automation, a generic drag-anything page builder, extra tenant databases, and multi-service infrastructure remain outside the current core scope.

## 7. Open product questions and success metrics

See decisions.md for unresolved decisions. Early validation should measure: referred inquiries, consultations-to-sales, paid orders, DIY completion, time spent per done-for-you order, support burden, and customer satisfaction. Pricing is a hypothesis (₱899 DIY / ₱2,499 done-for-you) and not an approved public offer.
