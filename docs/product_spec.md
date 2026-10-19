# Wedding RSVP SaaS — Product Specification (Living v0.1)

Last reconciled: 2026-10-16
Scope label: Confirmed direction + explicitly marked proposed details. This is not a claim that listed future features are implemented.

## 1. Product goal and audience

A reusable wedding website and RSVP platform for engaged couples in the Philippines. A couple can personalize and publish the existing Modern Classic wedding invitation, share invitation links, and understand how many invitees will attend. The same software supports DIY and done-for-you service. Modern Classic is the only current template; additional designs and template selection/switching are deferred until core functionality is developed (D-010).

Business goal: acquire paying customers profitably through referrals/personal networks first; use Facebook, Instagram and TikTok as supporting channels. Couples consult with us before purchase. Payments are initially coordinated manually via GCash/Maya/bank transfer; no gateway required for launch.

## 2. User types

- Couple/customer owner: Manages their wedding workspace, invitation drafts/publication and private named guest list.
- Partner/co-editor: Future authorized collaborator; invitation/permission workflow not implemented.
- Internal operator: Future team member who onboards done-for-you clients, configures their wedding, and records payment verification; exact permissions TBD.
- Guest: Future invited attendee, no customer login required to respond through a private invitation link.

## 3. Service journeys

DIY — desired journey: Introduced or referred → consultation/package agreement → customer access activated → edit supported Modern Classic wedding content → review/preview → publish → share invitations → track responses → manage seating.

Done-for-you — desired journey: Introduction → consultation → provide wedding assets/details → authorized staff configures Modern Classic using the same CMS → couple reviews/approves → publish → couple accesses dashboard and can update authorized details.

Automatic checkout, public self-service registration funnel, automatic reminders, and payment gateway integration are not needed for initial launch.

## 4. Capability roadmap

The approved priority is CMS editing and publishing for Modern Classic, followed by the guest/RSVP foundations, seating arrangements, and internal administration. Scope each milestone separately; all of these core capabilities take priority over expanding the template catalog.

| Stage                        | Capability                                                                    | Functional expectations                                                                                                       | Status                                                                                                           |
| ---------------------------- | ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| Foundation                   | Authentication and wedding workspace                                          | Owner can create/edit a wedding; private tenant isolation                                                                     | Implemented per prior verification; final owner approval pending                                                 |
| First website vertical slice | Existing Modern Classic invitation                                            | Reads stored wedding facts; private responsive preview; separate content and presentation                                     | Milestone 2A implemented; owner visual QA and acceptance pending                                                 |
| Core platform                | Basic Modern Classic CMS editing                                              | Introduction/closing text, ceremony/reception/closing visibility, live preview, and persistent private draft                  | Milestone 2B implemented; manual QA and owner acceptance pending                                                 |
| Future CMS scope             | Additional content controls                                                   | Images, appearance, section visibility/order beyond the basic editor                                                          | Not included in Milestone 2B; scope separately                                                                   |
| Core platform                | Single wedding hero photo                                                     | Private select/preview/save/replace/remove through Modern Classic's existing draft editor                                     | Milestone 2C.1 implemented; deployment prerequisites, verification, and owner acceptance tracked in checklist.md |
| Core platform                | Publication                                                                   | Explicit publish/republish/unpublish of immutable snapshots at `/w/{slug}`, private drafts, server-delivered sharing metadata | Milestone 3A accepted: 8/8 owner tests; MySQL concurrency pending; hosting separate                              |
| Core platform                | Guest management                                                              | Private named guests in invitation groups; distinct group/person totals and search                                            | Milestone 4A accepted; 5A adds responses; no anonymous plus-ones                                                 |
| Core platform                | RSVP tracking                                                                 | Per-person attending/declined/pending states; group responses; counts and exports                                             | 5A submissions implemented, owner QA pending; 5B summaries/exports deferred                                      |
| Core platform                | Seating arrangements                                                          | Tables, capacities, guest assignment/reassignment, and saved arrangements                                                     | Planned core capability; eligibility and layout controls to scope                                                |
| Core platform                | Internal administration                                                       | Explicit staff access, assisted onboarding through the same CMS, manual order/payment records and verification                | Planned; detailed permissions and workflow TBD                                                                   |
| After core functionality     | Additional wedding templates and selection/switching                          | Reuse the same wedding content and business logic; owner supplies Figma or Canva references before design work is scoped      | Deferred by D-010                                                                                                |
| Later enhancements           | QR check-in, custom domains, automated payments/reminders, advanced analytics | Introduce only for validated demand                                                                                           | Deferred                                                                                                         |

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

Milestone 2C.1 adds one optional private hero photo between Modern Classic's existing header and celebration details, uncropped and preserving aspect ratio. JPEG/PNG/WebP uploads are limited to 5 MiB, 12 million pixels, and 6,000px per dimension. Saved originals/location metadata are discarded; the platform retains an optimized static WebP, up to 1,600px on its long edge, without upscaling. HEIC, galleries, cropping controls, and animation preservation are excluded. Photo changes join Save Draft and stale-save safeguards; unsaved photos appear only in local preview. Draft photo URLs remain authenticated; 2C.2 exposes only the current published snapshot's reference through a separate public route.

### Implemented publication — Milestone 3A (previously 2C.2; D-013)

- Customize → Save Draft → Preview → return to the overview → Publish invitation → confirm → copy/view `/w/{slug}` without authentication. A saved supported draft, both names, a wedding date and valid referenced media are required; time/venues may be empty. Publication is never automatic.
- Publish freezes wedding facts, CMS and photo reference independently. Further draft/fact/photo edits do not change the public invitation. Republish reviewed saved changes explicitly; stale or duplicate submissions cannot overwrite newer publication state.
- Unpublish makes the public page/photo return 404 without deleting the wedding, draft or historical snapshots. Historical media remains retained privately; there is no history/rollback interface.
- Public HTML includes only intentionally visible presentation content, escaped and rendered in Modern Classic. Sharing metadata and the primary invitation are delivered by Laravel; the existing Vue renderer enhances them without a separate SSR service. Private account, payment, membership and CMS metadata are excluded.
- The overview displays draft/published status, last publication time and URL actions. Recommended default: ask search engines not to index/follow/archive the page. This is a public URL, not access control; previously downloaded copies and third-party caches cannot be recalled. Hosting and real social-preview verification require a reachable deployment and remain separate from this milestone.
- The owner confirms Milestone 3A passed 8/8 manual acceptance tests (recorded from the Milestone 4A task). MySQL concurrency and external-host social-preview verification remain pending; acceptance of 2C.1 is preserved. Guest management is scoped separately below; publication adds no RSVP, additional design, advanced CMS, custom domain or payment capability.

- Modern Classic renders structured, validated wedding content separately from its Vue layout and styling. Future designs can reuse that content and the same business logic.
- Wedding facts, guests, RSVPs, and seating remain independent of visual design; operational data stays relational.
- A mobile-first preview shows Modern Classic and the customer's content.
- Only authorized users edit drafts; published pages do not inadvertently show draft changes.
- Invitations have useful social link-preview metadata.

### Implemented private guest management — Milestone 4A (D-014)

- Open **Guest Management** from the wedding overview. Create an individual, couple or family invitation group with an editable label, optional contact email/phone and at least one named guest. Every invited seat has a name; duplicate names are allowed. Anonymous plus-ones are not supported.
- View groups, all named members of each group, and distinct invitation-group/invited-person totals. Search labels/member names and paginate 25 groups at a time. Global wedding totals remain unchanged by search. A per-group technical safety bound of 100 named guests does not set a wedding-wide commercial guest quota.
- Edit group/contact details, add named guests, and rename people. Explicitly confirm saved person/group removal. A group's final person cannot be removed alone; delete the whole group instead. Deletion was initially permanent for pre-delivery/pre-RSVP records; 4B protects issued groups and 5A protects responded people as described below.
- The list and modal forms follow Horizon Minimal and preserve the current layout/theme architecture. Contacts and guest identities remain private to the owner; no information is copied into public wedding HTML or publication snapshots. The Modern Classic design is unchanged.
- The owner confirms manual Milestone 4A acceptance in the authorization to proceed with 4B; automated evidence remains in checklist.md. Milestone 4A itself added no guest links; 4B adds the bounded personalized links below. Sending, RSVP responses, seating, anonymous companions and import/export remain absent.

### Implemented personalized invitation links — Milestone 4B (D-015)

- From a group's actions menu, open **Invitation link**. Generate, copy again after reopening, revoke or regenerate; confirm revocation/regeneration. Display Not generated, Active or Revoked. No automated sending or delivery tracking is implied, and there is no automatic expiration.
- Anyone holding an active `/invite/{token}` URL can see the group's current saved label/member names and the latest published Modern Classic invitation. Saved group/member changes are immediately visible to existing holders; wedding draft/fact edits remain private until republished. Unpublished weddings and invalid/revoked/rotated URLs return generic 404. Private contacts, IDs and CMS metadata are excluded.
- Recoverable encrypted credentials are available only through explicit authorized actions, not lists or ordinary page props. Tokens are bearer credentials; recipients, browser/clipboard history and services given the URL can retain them. Generic preview metadata, noindex and no-referrer do not create access control. Require protected keys, HTTPS and deployment access-log/monitoring redaction.
- Once any link has been issued, the group is retained even after revocation; revoke access instead of deleting it. Never-issued groups remain deletable. Members remain editable before RSVP; revisit deletion/retention before adding responses/delivery.
- The owner confirms manual Milestone 4B acceptance in the authorization to proceed with 5A. Milestone 4B itself added no RSVP submission; 5A extends its bearer access below. QR, sending, seating, analytics, additional design and payment capabilities remain outside this scope.

### Implemented guest RSVP submission — Milestone 5A (D-016)

- Only personalized invitations show the RSVP section. Anyone with the active bearer link can save or update attending/declining choices for current named group members while the wedding is published. Generic `/w/{slug}` pages have no RSVP controls or response data.
- Each person has at most one relational response with a response timestamp. No record means pending. Partial submissions preserve omitted answers; repeated identical submissions are safe. Changed submissions from stale invitation versions are rejected and require reloading/reviewing saved choices.
- The form shows pending/saved choices, success/errors and supports mobile/keyboard use. Saved responses can be reopened and changed between attending and declined. Clearing a recorded answer back to pending is not included. JavaScript is required for saving; static HTML still shows saved states.
- Current saved names remain visible to link holders. Renaming keeps the response, additions start pending, and responded people cannot be deleted. Existing issued-group protection and nonempty-group rules remain in force. No archive/history system or full attendance dashboard is added; summaries/filtering/attendance management belong to 5B.
- Automated evidence is tracked in checklist.md; Milestone 5A manual owner acceptance is pending. No deadlines, anonymous plus-ones, meals, dietary questions, sending or seating.

### Future invitations and RSVPs

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
