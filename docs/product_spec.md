Wedding RSVP SaaS — Product Specification (Living v0.1)
Last reconciled: 2026-10-16
Scope label: Confirmed direction + explicitly marked proposed details. This is not a claim that listed future features are implemented.

1. Product goal and audience
   A reusable wedding website and RSVP platform for engaged couples in the Philippines. A couple can choose a design, personalize and publish a wedding invitation, share invitation links, and understand how many invitees will attend. The same software supports DIY and done-for-you service.
   Business goal: acquire paying customers profitably through referrals/personal networks first; use Facebook, Instagram and TikTok as supporting channels. Couples consult with us before purchase. Payments are initially coordinated manually via GCash/Maya/bank transfer; no gateway required for launch.
2. User types

- Couple/customer owner: Manages their wedding workspace, details, and (later) invitation/guest information.
- Partner/co-editor: Future authorized collaborator; invitation/permission workflow not implemented.
- Internal operator: Future team member who onboards done-for-you clients, configures their wedding, and records payment verification; exact permissions TBD.
- Guest: Future invited attendee, no customer login required to respond through a private invitation link.

3. Service journeys
   DIY — desired journey: Introduced or referred → consultation/package agreement → customer access activated → choose a design → edit wedding content/theme → review/preview → publish → share invitations → track responses.
   Done-for-you — desired journey: Introduction → consultation → provide wedding assets/details → staff configures wedding using the same CMS → couple reviews/approves → publish → couple accesses dashboard and can update authorized details.
   Automatic checkout, public self-service registration funnel, automatic reminders, and payment gateway integration are not needed for initial launch.
4. Capability roadmap
   Stage Capability Functional expectations Status
   Foundation Authentication and wedding workspace Owner can create/edit a wedding; private tenant isolation Implemented per Codex, review pending
   First website vertical slice One responsive wedding invitation design Reads existing wedding data; private preview; clear design/content separation Next after Milestone 1 review; specification pending
   Core sellable product Select from reusable curated designs Change text, photos, color, fonts, supported section visibility/order; previews Planned
   Core sellable product Publication Draft vs published; only published data appears on guest site; safe preview Planned
   Core sellable product Invitations and guests Individuals and household/invitation groups; ability to limit attendance/companions Planned; rules TBD
   Core sellable product RSVP dashboard Per-person attending/declined/pending; group responses; counts and exports Planned
   Initial operations Assisted service workflow Staff can configure for a customer; manual order/payment record and confirmation Planned; minimal manual process acceptable during pilots
   Enhancement Seating planner Tables, capacities, assign/reassign confirmed guests, save layouts After core; priority to validate
   Later QR check-in, custom domains, automated payment/reminders, advanced analytics Only if validated demand Deferred

The first demonstration is not the complete MVP. Avoid announcing the product as customer-ready until core publish/RSVP functionality, privacy, support, and operational safeguards are tested. 5. Proposed customer-facing acceptance criteria (not implementation scope yet)
Wedding website

- Different curated templates consume the same normalized wedding facts; switching designs does not erase names, dates, guest records, or RSVPs.
- A mobile-first preview shows the selected design and customer content.
- Only authorized users edit drafts; published pages do not inadvertently show draft changes.
- Invitations have useful social link-preview metadata.
  Invitations and RSVPs
- An invitation can identify multiple named guests (family/household).
- Each invited person has an independent attendance state, so partial-family attendance is countable.
- A private invitation link cannot expose unrelated wedding/guest data.
- Counts and exports reflect attendance per person, not number of forms submitted.
- Rules for plus-ones, changes, deadlines and optional questions require owner approval before implementation.
  Service operations
- DIY and done-for-you use the same underlying wedding data and CMS.
- Payment and publishing/activation must not rely solely on a screenshot as proof of funds.
- Scope, revisions, hosting duration, data retention and cancellation terms must be defined before real customer launch.

6. Explicit non-goals for the next implementation
   Do not build RSVP forms, seating, payment gateway, subscription billing, custom domains, mass messaging, marketing automation, generic drag-anything page builder, extra tenant databases, or multi-service infrastructure during the next template proof-of-concept.
7. Open product questions and success metrics
   See decisions.md for unresolved decisions. Early validation should measure: referred inquiries, consultations-to-sales, paid orders, DIY completion, time spent per done-for-you order, support burden, and customer satisfaction. Pricing is a hypothesis (₱899 DIY / ₱2,499 done-for-you) and not an approved public offer.
