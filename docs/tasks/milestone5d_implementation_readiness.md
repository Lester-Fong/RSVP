# Milestone 5D — Implementation Readiness

Prepared: 2026-10-21. Updated: 2026-10-25. **W01–W06 OWNER-APPROVED under D-036. D-059 authorizes completion of 5D.2; the current closeout records the integrated default-off owner/Attendance implementation. The owner accepted the manual functional walkthrough under D-060; real disposable-InnoDB schedules remain pending.**

This handoff refines the [original withdrawal proposal](milestone5d_invitation_withdrawal.md). D-021 requires retained-record withdrawal, D-023 records accepted 5C and D-036 approves W01–W06 including W03 preserved-answer counting, W05's narrow deletion exception and W06's exact affected-person/version confirmation. D-059 authorizes the integrated owner/read implementation described in Section 15; Sections 13–14 remain historical foundation evidence. [Decisions](../decisions.md), [roadmap](../mvp_roadmap.md), [QA register](../qa/verification_register.md) and [risk register](../risks_and_followups.md) remain authoritative for status.

DEL-02d is finished with owner acceptance pending and remains untouched. CMS delivery remains prioritized; 5D is required before pilot and resumed Seating. S01–S06 remain unapproved. The original review was documentation-only; the 5D.1a closeout below separately records the subsequent isolated schema/model implementation and test evidence. Development MySQL schema inspection was read-only; no live migration, reset, credential or customer-data operation occurred.

## 1. Executive recommendation and approval boundary

Use one nullable guest withdrawal timestamp, the existing wedding-first transaction service, retained credentials/delegation slots and the existing per-guest version counters. Add one focused invitation-lifecycle service, explicit eligibility predicates, active-only Attendance queries and compact owner confirmations. No replacement credential system, soft-delete framework, audit ledger, derived counters, guest wedding key or legacy-data conversion is needed.

Withdrawal is distinct from credential revocation: a person can remain invited without a usable link, whereas withdrawal removes active eligibility and all self/delegated authority. Reinstatement restores eligibility, never credentials or representation.

The owner approved all W decisions, including W06's confirmation/version details, before 5D.1a. Permanent ever-withdrawn audit or mandatory fresh RSVP reconfirmation is outside that approval and would require a changed decision/schema.

### W01–W06 — OWNER-APPROVED (D-036, 2026-10-21)

| ID  | Recommendation                                                                                                                                                                                                                                                                                                     | Trade-off / alternative requiring a different decision                                                                                                                                                                                                                                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| W01 | Add nullable guests.withdrawn_at, default null, immutable datetime cast, server-generated time; explicit active/withdrawn scopes, no global scope. Existing guests remain active.                                                                                                                                  | Records current state only. Reinstatement erases the previous withdrawal time. Permanent history requires another field/table and retention policy; do not imply one timestamp supplies it. Start with existing group indexes; add a composite index only with measured query-plan justification.                               |
| W02 | In one transaction withdraw the target, clear its credential material, null its incoming and outgoing active delegation pointers, and invalidate affected access versions. Released dependents remain active, with saved answers, but no automatic access.                                                         | Automatically reassigning dependents or restoring an earlier credential would grant unapproved authority. The owner must explicitly arrange replacement access. Existing restrict-only revocation/removal operations may clean up retained relationships; grants, recovery, rotation and assignment require active eligibility. |
| W03 | Reinstatement immediately includes the preserved answer in active totals; unanswered people remain Pending. Require explicit fresh link issuance or explicit new delegation.                                                                                                                                       | A preserved Attending answer is historical, not a fresh confirmation. Explain that in the confirmation. Reconfirmation/reset-to-Pending would change approved RSVP behavior and needs a separate decision.                                                                                                                      |
| W04 | Preserve group membership; permit zero active members while retaining at least one record. Active is the default management filter, with Withdrawn and All. Keep the current 100 **retained** guests/group limit.                                                                                                  | All-withdrawn groups disappear from Active results but remain discoverable through Withdrawn/All. Counting only active people against capacity would allow unbounded retained groups and exceeds this scope.                                                                                                                    |
| W05 | Block ordinary hard deletion while withdrawn, and preserve existing issued/delegated/responded retention after reinstatement. A reinstated, never-issued, never-delegated, unanswered person returns to ordinary nonempty-group deletion rules.                                                                    | This narrow exception cannot preserve “ever withdrawn” history. A permanent deletion prohibition requires additional durable evidence or a broader deletion policy. Parent deletion guards must include currently withdrawn children, even with no restrictive child FK.                                                        |
| W06 | Explicitly exclude withdrawn people from every active count/list/eligibility query. Eligibility-only actions preserve response versions and all response timestamps. Advance access versions once per affected person. Validate an exact affected-person confirmation set with access, response and link versions. | Access versions alone miss names/answers changed during confirmation. Checking response versions for every displayed affected person is conservative: a concurrent saved answer requires review even if withdrawal could otherwise proceed. No new state counter or signed confirmation ticket is needed.                       |

No separate approval of Seating, CMS persistence, owner RSVP overrides, reset-to-Pending, notifications or bulk withdrawal is included.

## 2. Verified implementation map

Paths identify the readiness-audit baseline, including pre-existing uncommitted work, not HEAD. Sections 13/14 supersede baseline future-tense findings for the implemented 5D.1a/1b paths; active Attendance and owner HTTP/UI remain pending. These are integration points, not authorization to edit all files in any batch.

| Source                                                                                                                                                                                                                                                   | Current behavior / 5D consequence                                                                                                                                                                                                                                                                                                        |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Guest](../../app/Models/Guest.php)                                                                                                                                                                                                                      | Only name is fillable; personal-mode rename increments rsvp_version, membership moves are rejected, and response/issuance/delegation guards protect deletion. Add the timestamp/cast/scopes without making it ordinary CRUD input. Do not double-increment versions through a lifecycle model hook.                                      |
| [InvitationGroup](../../app/Models/InvitationGroup.php), [Wedding](../../app/Models/Wedding.php)                                                                                                                                                         | Parent model deletion guards exist. Extend them for currently withdrawn descendants; database cascades do not invoke descendant Eloquent events.                                                                                                                                                                                         |
| [PersonalRsvpTransaction](../../app/Services/PersonalRsvpTransaction.php)                                                                                                                                                                                | run locks Wedding → scoped Group → all retained Guests by ID → Credentials by guest ID → Delegations by dependent ID → Responses by guest ID, with transaction retries. Owner policy is checked inside the transaction. Reuse member, clearCredential, bumpAccess and overflow checks. Never filter withdrawn rows out of this lock set. |
| [PersonalInvitationLink](../../app/Services/PersonalInvitationLink.php)                                                                                                                                                                                  | Random 32-byte tokens encoded as 64 hex characters, SHA-256 lookup, encrypted recovery, retained rows, versioned lifecycle. No withdrawal eligibility today. Do not call its public revoke operation inside withdrawal: never-issued revocation is rejected and nested lifecycle calls risk duplicate effects/version increments.        |
| [RsvpDelegationManager](../../app/Services/RsvpDelegationManager.php)                                                                                                                                                                                    | Exact affected access versions, same-group enforcement, no chains/cycles, active independent representative, retained dependent slot; removes access without deleting answers. Add active-person eligibility for grants and representative selection.                                                                                    |
| [PersonalRsvp](../../app/Services/PersonalRsvp.php)                                                                                                                                                                                                      | Read/page/submit re-resolve credentials, membership, publication and authority inside the lock transaction. Presentation is self plus authorized dependents only. Opaque target references are credential-scoped HMACs. Validate eligibility before both changed saves and identical retries.                                            |
| [PersonalRsvpAccess](../../app/Services/PersonalRsvpAccess.php)                                                                                                                                                                                          | Safe owner aggregate and explicit URL recovery; ordinary state has access/link versions but no response version or invitation state. Extend safe DTOs for confirmation and eligibility, without exposing tokens in reads. Existing nested service calls need not be broadly rewritten.                                                   |
| [RsvpMode](../../app/Services/RsvpMode.php), [EnforceRsvpMode](../../app/Http/Middleware/EnforceRsvpMode.php)                                                                                                                                            | Only the personal configuration is valid; legacy routes are denied. Schema readiness checks counters/tables. Add the withdrawal column to integrated readiness; Attendance currently needs explicit inclusion in the readiness boundary. Missing selected attributes must not be interpreted as active.                                  |
| [GuestController](../../app/Http/Controllers/GuestController.php), [InvitationGroupController](../../app/Http/Controllers/InvitationGroupController.php)                                                                                                 | Wedding-first mutations, retained-person capacity/nonempty rules, scoped search/pagination. Reduced guest selects and totals need deliberate state additions. Current count means retained people, not future active invited people.                                                                                                     |
| [WeddingAttendance](../../app/Services/WeddingAttendance.php), [WeddingAttendanceController](../../app/Http/Controllers/WeddingAttendanceController.php)                                                                                                 | Summary, filtered guests, group counts and expanded group details use separate query paths. Apply active eligibility to each, including nested matching/count subqueries. Response uniqueness prevents duplicate joins. Read transactions currently wrap dashboard results.                                                              |
| [Owner controller](../../app/Http/Controllers/PersonalRsvpAccessController.php), [PersonalDelegationRequest](../../app/Http/Requests/PersonalDelegationRequest.php)                                                                                      | Thin adapters and action-specific validation already exist. Delegation requests allow at most three version entries; withdrawal can affect the whole bounded group and needs its own request, not a raised global limit.                                                                                                                 |
| [PersonalRsvpHttp](../../app/Services/PersonalRsvpHttp.php), [routes](../../routes/web.php), [bootstrap](../../bootstrap/app.php), [provider](../../app/Providers/AppServiceProvider.php)                                                                | Personal failures already use safe private responses. Stale access/response is HTTP **422**, not 409. Existing rsvp-access namespace participates in privacy/error handling and owner throttling. Preserve token-free CSRF setup, digest submission limiter, broad IP protection and retired legacy routes.                              |
| [Guests page](../../resources/js/pages/weddings/Guests.vue), [RsvpAccessDialog](../../resources/js/components/wedding/RsvpAccessDialog.vue), [types](../../resources/js/types/personal-rsvp.ts), [adapter](../../resources/js/lib/personal-rsvp-http.ts) | Reuse contextual access actions, dialogs, temporary URL memory, tags, collapse, automatic filters and history. Extend typed owner-only state; do not infer server permissions in Vue.                                                                                                                                                    |
| [PersonalInvitation](../../resources/js/components/wedding/PersonalInvitation.vue), [PersonalRsvpForm](../../resources/js/components/wedding/PersonalRsvpForm.vue)                                                                                       | Preserve authorized target presentation, partial submissions, stale feedback and real server success. No withdrawal controls belong on public invitations.                                                                                                                                                                               |
| [WeddingPublication](../../app/Services/WeddingPublication.php), [PublishedWedding](../../app/Services/PublishedWedding.php)                                                                                                                             | Publish/unpublish lock the Wedding; immutable wedding snapshots contain no operational guest eligibility. Withdrawal must not mutate a revision, publication version, draft or public media.                                                                                                                                             |

### Constraint and deletion facts

- [Guest/group migration](../../database/migrations/2026_10_16_090634_create_invitation_groups_and_guests_tables.php): wedding-to-group and group-to-guest deletion cascade.
- [Responses](../../database/migrations/2026_10_16_104215_create_rsvp_responses_table.php): unique guest response and restrictive guest FK; Pending means no record.
- [Credentials](../../database/migrations/2026_10_19_073458_create_guest_invitation_links_table.php): unique guest, nullable unique digest, restrictive guest FK.
- [Delegations](../../database/migrations/2026_10_19_073459_create_rsvp_delegations_table.php): unique dependent, nullable representative, restrictive guest FKs. These do not enforce same-group ownership.
- [Counters](../../database/migrations/2026_10_19_073459_add_rsvp_versions_to_guests_table.php): unsigned guest counters with zero defaults and service overflow handling.
- A revoked dependent slot remains evidence for that dependent. Nulling representative pointers does not retain every former representative edge; valid representatives already have a retained issued-credential row.
- A never-issued/delegated/responded withdrawn person has no protective child FK. Ordinary guest/group/wedding application guards must protect that state. Raw query deletes/bulk SQL and direct cascading parent deletes can bypass model guards; do not claim database-enforced permanent withdrawal retention.
- Account-to-wedding deletion is restrictive. [Profile deletion](../../app/Http/Controllers/Settings/ProfileController.php) has its own owner/orphan checks; there is no need to invent an account cascade or rewrite its account-first lock path for this feature. No wedding delete HTTP route was found. Test existing parent model boundaries and any future sanctioned delete service explicitly.

## 3. Proposed schema, lifecycle and version contract

Only proposed DDL: add nullable TIMESTAMP withdrawn_at to guests, default null. No data backfill, reset, reason, enum, deleted_at, audit table or new credential row. Use the application's existing timestamp storage convention; return owner-facing times in the established ISO/date display convention. Owner input never sets timestamps.

Do not apply a global scope: retained guest lookup, transaction locks, history display and deletion guards need inactive records. An explicit activeInvitation scope is a query convenience; a domain eligibility predicate must also reject unloaded/missing withdrawal state rather than treating absent data as null. Add the new column to relevant reduced selects.

| Transition                                          | Invitation state | Authority effects                                                                                     | Response effects                              |
| --------------------------------------------------- | ---------------- | ----------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| Create                                              | Active/null      | No automatic credential/designation                                                                   | Pending/no row                                |
| Withdraw active independent                         | Timestamp now    | Clear token/hash if material exists; retain credential row                                            | No response mutation                          |
| Withdraw dependent                                  | Timestamp now    | Null incoming representative; retain slot; invalidate previous representative's access view           | Preserve answer                               |
| Withdraw representative                             | Timestamp now    | Revoke own link; null outgoing slots; released dependents remain active without restored links        | Preserve all answers                          |
| Repeat withdrawal against current state/versions    | Same timestamp   | No mutation when invariant already satisfied                                                          | No mutation                                   |
| Reinstate                                           | Null             | No restored token or designation; explicit later fresh issuance/assignment                            | Old saved answer immediately counts under W03 |
| Repeat reinstatement against current state/versions | Active           | No mutation; do not revoke a newly issued active link merely because an old reinstate command arrives | No mutation                                   |

No-op is allowed only after ownership, expected state, exact affected set and versions match. A lost-success retry carrying old versions gets stale feedback and a refresh; do not automatically replay it or add a general idempotency-token store. A fresh request at current versions is a no-op. This prevents withdrawal/reinstatement ABA and accidental revocation of newly issued access.

### Version effects

| Field                                                  | Withdrawal                                                                                             | Reinstatement                                                                  | Unrelated person          |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------ | ------------------------- |
| Target rsvp_access_version                             | +1 once on real state change                                                                           | +1 once on real state change                                                   | Unchanged                 |
| Previous representative access version                 | +1 if its outgoing authority loses target                                                              | No automatic change                                                            | Unchanged unless affected |
| Released dependent access version                      | +1 if its incoming authority is removed                                                                | No automatic change                                                            | Unchanged unless affected |
| Credential version                                     | +1 only when stored credential material is actually cleared; no row is created for never-issued guests | Unchanged; future explicit issuance/rotation follows current service semantics | Unchanged                 |
| Guest rsvp_version                                     | Unchanged                                                                                              | Unchanged                                                                      | Unchanged                 |
| Response status/responded_at/created_at/updated_at     | Unchanged                                                                                              | Unchanged                                                                      | Unchanged                 |
| Group rsvp_version; wedding publication/draft versions | Not a withdrawal conflict gate; no bump required for this feature                                      | Same                                                                           | Unchanged                 |

Advance each affected access version once, using a deduplicated ordered set. Prevalidate overflow and the entire command before writes; transaction rollback must restore all state on any later failure. The guest row's own updated_at may change; do not present that as an RSVP response time.

## 4. Permission and eligibility matrix

| Actor / operation                                                            | Proposed result                                                                                                                          |
| ---------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| Authorized wedding owner withdraws/reinstates an in-scope retained member    | Allowed with current confirmation; publication is not a prerequisite                                                                     |
| Other account, foreign wedding/group/guest or manipulated ownership          | Denied through scoped binding/policy and locked membership; no changes                                                                   |
| Withdrawn holder uses old GET/POST or token-free CSRF setup then old POST    | Invitation GET/POST generic private 404; CSRF setup alone grants nothing                                                                 |
| Active representative submits a withdrawn/removed dependent from an old form | Whole batch rejected; current actor access version is stale or target is unauthorized                                                    |
| Remaining independent member submits valid unchanged authority               | Allowed; no false conflict from someone else's withdrawal                                                                                |
| Active guest without link / active released dependent                        | Still invited and counted; no self-response authority until explicit access is granted                                                   |
| Grant/copy/rotate personal credentials for withdrawn person                  | Denied even if inconsistent residual token material exists                                                                               |
| Designate withdrawn representative or dependent                              | Denied; no automatic substitute representative                                                                                           |
| Explicit revoke/remove on retained withdrawn record                          | Restrict-only cleanup may be allowed under current versions and existing issuance rules; never grant/reactivate access or delete answers |
| Generic published wedding visitor                                            | Same published invitation; no identities, withdrawal status or attendance data                                                           |
| Any guest when wedding unpublished/unsupported                               | Existing generic private 404, irrespective of active eligibility                                                                         |
| Legacy group credential                                                      | Continues denied; cannot exchange for personal access                                                                                    |

Check eligibility inside credential resolution and submission transactions, not merely in the picker or by clearing tokens. Check all targets before identical retry acceptance. If stored authority is inconsistent (cross-group edge, withdrawn representative or impossible simultaneous access), fail closed and report a safe internal diagnostic without credentials; do not silently repair on GET or silently omit submitted people.

Previously delivered HTML cannot be recalled from a visitor's screen. Immediate revocation means subsequent server reads/writes fail after the withdrawing transaction commits. Preserve private/no-store, no-referrer and noindex controls; no polling/remote erasure promise is made.

## 5. Owner confirmation and HTTP contracts

Proposed base: existing authenticated, scoped
/accounts/{account}/weddings/{wedding}/invitation-groups/{invitationGroup}/rsvp-access/guests/{guest}.

Use existing named-route conventions, WeddingPolicy update, normal session/CSRF, owner limiter and PersonalRsvpHttp private error handling. No guest-facing lifecycle endpoint or new role.

| Method / suffix       | Contract                                                                                                                             |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| GET /invitation-state | Owner-authorized inspection of current state and exact affected people; no mutation, credential recovery or publication prerequisite |
| POST /withdraw        | Confirmed withdrawal with strict expected-state/version payload                                                                      |
| POST /reinstate       | Confirmed reinstatement with strict expected-state/version payload                                                                   |

Inspection returns an allowlisted owner-only model: target name/state/withdrawnAt, preserved response and response time, server-specified action capabilities, affected people with their consequence, and the following confirmation object. Current confirmation does not grant authorization; Laravel recalculates everything in the writing transaction.

```json
{
    "expectedState": "active",
    "expectedGuests": [{ "guestId": 17, "accessVersion": 2, "responseVersion": 1, "linkVersion": 3 }]
}
```

IDs here are synthetic owner-side references, never public RSVP target IDs. A missing credential has linkVersion 0. Use active/withdrawn as expectedState; action comes from the route. Reject unknown top-level and nested keys, duplicate IDs, invalid integers, empty arrays, over-100 arrays and foreign/stale entries. Scope ownership from routes and locked records, never payload IDs.

Affected set for withdrawal: target plus current incoming representative plus all active outgoing dependents, deduplicated; valid no-chain data bounds the set by the retained group limit. Reinstatement affects only the target because withdrawn authority must already be cleared. An unexpected retained active edge blocks safe reinstatement pending diagnosis.

**Why all three versions:** access catches authority/state changes; response catches renamed people or changed answers shown in confirmation; link catches credential lifecycle. Do not submit names, status or timestamp as authoritative command input. Exact-set comparison catches a newly assigned dependent not present in the earlier impact review. Group-label/contact-only changes and publication changes do not change this command's authorization or require their counters.

Success: HTTP 200 with safe updated invitation state and refreshed owner group state/capabilities; no token URL. Refresh wedding-wide active totals through the normal read contract. Stale state/set/version: HTTP 422 with existing stale_access semantics and “Invitation details changed. Review the latest information and confirm again.” Malformed input: 422 validation. Preserve current authentication, CSRF, denial and 429/Retry-After conventions; do not introduce a contradictory 409 contract. No automatic stale retry. Clear any recovered URL from existing dialog memory after lifecycle changes/close/navigation.

Keep inspection and mutations private/no-store and nosniff where existing response conventions apply. Do not place names, response choices, payloads or tokens in diagnostic logs. The existing namespace is intentional: it reuses server mode/readiness/privacy handling. Test each new route against that boundary rather than assuming a matching URL alone proves security.

## 6. Transaction algorithm and races

1. Enter PersonalRsvpTransaction.run with owner, wedding and group. Reauthorize after wedding lock; lock all retained members and related rows in existing order. Do not add a preliminary guest-row lock or active-only group load.
2. Resolve target from locked membership; inspect current authority graph and state. Recompute the exact affected set and compare every expected version and expected state. Reject all invalid data before mutations.
3. Validate invariant/overflow and compute deduplicated access changes. For withdrawal, set timestamp, clear target credential with existing low-level helper, null incoming/outgoing designation pointers without deleting slots. Do not create credentials or restore earlier links.
4. Advance affected access counters once. Do not save any response or advance response versions. For reinstatement, clear timestamp and bump target access only; retain revoked material/slots.
5. Return a safe state; commit once. Failures roll back timestamp, credentials, designations and counters together. No filesystem operation, message delivery or external side effect belongs in retryable transaction callbacks.

Only one group is involved because MVP delegation is same-group. Retaining the coarse wedding lock is simpler and already shared by publish/unpublish and personal RSVP. It serializes transactions for a wedding but per-guest versions avoid false stale errors for independent people. No new wedding eligibility version is needed.

| Schedule / edge                                      | Required outcome                                                                                                                                         |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Withdrawal commits before waiting RSVP               | Holder denied or dependent batch rejected in full; no changed answer                                                                                     |
| RSVP commits before waiting withdrawal               | Answer preserved; previously inspected owner confirmation becomes stale because its response version changed; owner reviews and explicitly retries       |
| Representative changes after confirmation            | Exact set/access versions reject old command; newly assigned people cannot be silently affected                                                          |
| Recovery/rotation/issuance commits before withdrawal | Relevant versions reject old confirmation; fresh confirmed withdrawal revokes resulting material                                                         |
| Withdrawal commits before grant/recovery             | Eligibility denial; no leak or newly active credential                                                                                                   |
| Withdraw then reinstate before old command executes  | Access version detects ABA even if timestamp is null again                                                                                               |
| Rename after confirmation                            | Response version forces review; no stale-name destructive confirmation                                                                                   |
| Same-state duplicate request                         | Current state/version no-op; obsolete payload stale; no extra timestamps/counter bumps                                                                   |
| Unpublish/republish races                            | Wedding lock serializes; withdrawal independent of publication. Waiting guest write still checks current publication. Republish never restores authority |
| Guest addition / independent answer                  | Does not invalidate unrelated personal RSVP; new guest remains active Pending. Confirmation only changes if its actual affected set/version changes      |
| Guest/group/parent deletion race                     | Guarded writer must lock wedding before child decisions; no delete can bypass newly committed state. Issued/delegated/responded FK backstops remain      |
| Corrupt ciphertext / max counter                     | Credential clearing need not decrypt; overflow or inconsistent authority fails atomically and safely                                                     |
| All members withdrawn                                | Group retained/discoverable, zero active counts; no need to invent a placeholder invited person                                                          |
| Reinstated never-issued pending record later deleted | Only approved W05 exception and existing last-retained-member/capacity rules apply                                                                       |

Raw SQL/model bulk deletion remains outside ordinary guarded CRUD. Do not claim the timestamp enforces restrictive deletion at the database level. A stronger database-wide lifecycle guarantee is a separate retention design decision.

## 7. Active queries, management UX and publishing

### Active Attendance

Apply active eligibility to summary's base guest query, matchingGuests, individual pagination, group whereHas/matching_count, every withCount aggregate and expanded group members. Never filter retained transaction relationships globally. Do not join credentials/delegations into totals.

Wedding-wide invariant: **Invited = Attending + Declined + Pending**, where all four contain only active guests and Pending means no response row. Revoking a link alone leaves these totals unchanged. Reinstatement includes the preserved status exactly once under W03. Search, filters and pagination do not change summary cards; separately identify matching result counts.

Exclude all-withdrawn groups from active Attendance results. Historical withdrawn answers belong in owner management detail, visibly marked “Not included in attendance totals,” not in a second analytics dashboard. Reuse existing consistent date formatting for response timestamps, distinct from withdrawal time.

Keep summary/list reads coherent within the existing read transaction. MySQL REPEATABLE READ can provide a shared snapshot; READ COMMITTED can mix statement snapshots. Verify actual isolation before claiming cross-query coherence. Prefer targeted integration over adding redundant counters or locking all readers.

### Guest Management

Add Active / Withdrawn / All as a separate invitation-state filter, default Active, with existing debounced search, URL/history synchronization and page reset. Keep scope-bound pagination. Define group summaries distinctly: active people, withdrawn people and retained people; group counts are never people counts.

For listing, select groups containing a member in the chosen state and matching the existing group-label-or-member-name semantics. Within each card show state-eligible members; a group-label match admits all such members. Guest-name matches must remain visible beyond the first-five collapse. Full owner management/access dialogs may deliberately show all retained members with clear badges. Do not make a hidden withdrawn match look like an unrelated Active result. Wedding-wide active totals stay unfiltered; pagination/matched-group counts are labeled separately. Retained totals remain available for capacity.

Use contextual Withdraw invitation / Reinstate invitation actions rather than extra permanent buttons. Confirmation lists:

- target and current saved response;
- access lost, prior representative and released dependents by name;
- released people remain invited and need explicit new access;
- retained responses/timestamps and active-total effects;
- reinstatement counts the old answer but does not revive old links.

Clear stale state with review/refresh, not blind retry. Disable duplicate submissions, preserve filters/local UI state, restore focus, wrap long names and provide understandable blocked-deletion reasons. Active released dependents are “Invited — access not arranged,” not withdrawn or Pending unless no answer exists. Show safe server capabilities for delete/grant/withdraw/reinstate; client state is never authorization.

Before portal implementation load Horizon Minimal and applicable Vue/Tailwind skills. Preserve existing tag input, compact groups, keyboard ordering, clipboard handling and automatic filters. No changes to DEL-02d CMS panes or template design.

### Published invitation and future Seating

Generic /w/{slug} and immutable revisions do not change. No withdrawal names/state in public HTML, CMS JSON, SEO or metadata. Personal invitations still combine the published snapshot with current authorized active people; test both Blade fallback and hydrated data.

Owners can manage eligibility while unpublished; guest read/write remains unavailable. Re-publishing does not undo withdrawal, and changing eligibility does not make publication appear stale.

If Seating resumes, active eligibility must be a prerequisite even for a preserved Attending answer. Assignment removal/reinstatement policy and seating-version effects belong to S01/S02/S05 approval; do not create assignments, seating counters, hooks or implicit restoration now. 5D acceptance remains required before resumed M6.

## 8. Small implementation batches and gates

These refine the original 5D.1–5D.3 sequence. D-036 authorized 5D.1a and D-037 authorized 5D.1b; both are delivered as an inactive backend foundation. 5D.2/3 need separate authorization. W01–W06 approval is not normal-app activation permission.

| Batch                                             | Deliverable / independent exit evidence                                                                                                                                                                                                                       | Boundary                                                                                                                                              |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| **5D.1a — schema/model foundation**               | One additive timestamp migration; cast/explicit scopes; synthetic active/withdrawn factories; isolated fresh/upgrade/cast/serialization tests, existing records/answers/links unchanged                                                                       | No service/route/UI activation, query-count behavior change, live migration/reset or authority changes                                                |
| **5D.1b — internal lifecycle and eligibility**    | Focused internal service using existing locks; exact confirmation/version validation, revocation/reinstatement, authority checks across personal services, retention guards and fail-closed readiness integration; focused functional/security/rollback tests | New lifecycle remains unrouted. Coordinate readiness deployment with schema; do not run against an unmigrated normal app. Do not add runtime fallback |
| **5D.2a — active reads and safe owner contracts** | Every Attendance/management query updated, state/capability DTOs and consistent active totals; all-withdrawn/search/pagination/hidden-name/retention regression coverage                                                                                      | Complete reads and write guards together before exposing withdrawal; no half-integrated customer feature                                              |
| **5D.2b — HTTP and compact owner UX**             | Scoped inspect/withdraw/reinstate adapters, strict requests, confirmations, filter/history and error behavior; real HTTP/CSRF and connected browser evidence                                                                                                  | First test in isolated DB/storage/session/cache; never expose authority-changing feature before applicable security/integrity tests pass              |
| **5D.3 — engine and owner acceptance**            | Execute approved independent-worker InnoDB schedules, affected full regression/quality gates and manual acceptance; record exact outcomes                                                                                                                     | Engine failures affecting integrity block activation. Obtain explicit owner acceptance; production release findings remain open                       |

Safety/permission/data-integrity tests are gates before affected activation, not work automatically deferred to Milestone 8. Relevant engine work can run during owner QA only in a separate verified disposable database; no interference with CMS or owner testing. No broad reset or legacy conversion is required.

Deployment of the integrated feature must use schema-ready binaries. Old code can ignore withdrawn_at and still grant authority; the existing RSVP mode flag cannot make old binaries safe. Do not roll old/new permission implementations together or roll back the column as reinstatement. Production backup/restore and old-binary protections remain RF-021/M8. This pre-launch planning does not authorize deployment or a complicated migration tool.

## 9. Verification plan — proposed, not executed

Schema/model/upgrade coverage passes (7 / 53); the internal lifecycle/eligibility subset now passes `GuestInvitationLifecycleTest` (48 / 238). D5-F02–06 are exercised internally; F01's enabled personal readiness and F07's retention portions also have functional evidence. Attendance readiness/active queries, new HTTP/browser workflows and all D5 InnoDB schedules remain **Planned**. Existing 422 rendering is tested without adding an endpoint. Track the delivered subsets through QA-OPS-12/13 and the complete workflow through QA-OPS-11; parent/capacity races remain QA-RACE-08. SQLite and existing 5C evidence do not prove engine races or full withdrawal activation.

### Unit/contract and relational service checks

Where confirmation parsing, version comparison or affected-set normalization is extracted into a pure helper, add focused unit cases for boundary integers, duplicate IDs, invalid state and exact-set normalization. Do not mock a database transaction to claim authorization or locking safety; the relational service and HTTP scenarios below remain required.

Use isolated in-memory SQLite and synthetic fixtures; distinguish model/service tests from real engine behavior.

- **D5-F01:** Additive upgrade over pre-existing Pending/Attending/Declined guests defaults active; timestamp nullability/cast; unique/FK/version constraints unchanged. Verify schema-ready failure when column missing, including Attendance. No reset/backfill.
- **D5-F02:** Independent, dependent and representative withdrawal; exact credential clearing, retained rows/slots, released active dependents, zero automatic issuance; retained names/membership/response fields byte-for-byte or value-for-value unchanged.
- **D5-F03:** Reinstate each state, preserved answer/count, independent without active access, explicit fresh issuance/assignment and all old token denial. Repeat withdrawal/reinstatement and ABA.
- **D5-F04:** Affected-set validation, all expected counters, missing/unknown/duplicate IDs, newly assigned dependents, renamed people, changed answers, overflow and exact-once increments; no response-version/timestamp mutation.
- **D5-F05:** Fail after credential clearing and after designation updates; assert full rollback. Corrupt ciphertext revocation, invalid cross-group graph fail-closed, all withdrawn and no-credential targets.
- **D5-F06:** Deny withdrawn holder/target on read/write/no-op retry, grant/copy/rotate/assignment; preserve valid unrelated independent submissions and restrict-only cleanup semantics.
- **D5-F07:** Delete withdrawn never-issued pending guest and parent denied; issued/responded/delegated retention after reinstatement; W05 narrow deletion exception, nonempty retained group and 100-retained cap.
- **D5-F08:** Mixed zero/all/partial active statuses; every Attendance group/detail/count/filter/pagination path; invariant and summary independence; preserved status after reinstatement, revocation-only totals unchanged; no duplicate joins/N+1.
- **D5-F09:** State filter plus label/name searches, escaped wildcard behavior, long/duplicate names, matching name beyond first five, all-withdrawn discovery, retained versus active counts.
- **D5-F10:** Generic public snapshot, fingerprint/publication versions/media and personal authorized roster privacy unchanged; unpublish/republish never restores eligibility/credentials.

Reuse [service tests](../../tests/Feature/PersonalRsvpServicesTest.php), [HTTP tests](../../tests/Feature/PersonalRsvpHttpTest.php), [foundation tests](../../tests/Feature/IndividualRsvpFoundationTest.php), [migration tests](../../tests/Feature/IndividualRsvpMigrationTest.php), [guest CRUD](../../tests/Feature/Weddings/WeddingGuestManagementTest.php), [Attendance](../../tests/Feature/Weddings/WeddingAttendanceTest.php) and [publication](../../tests/Feature/Weddings/WeddingPublicationTest.php). New focused InvitationWithdrawal test classes are proposed; they do not exist yet. Avoid asserting SQL row locking from SQLite or duplicating every test at every layer.

### HTTP/security checks

- **D5-H01:** Owner inspection and both commands with real scoped bindings/policy; unauthenticated, foreign account, same-account wrong wedding, foreign group/guest, manipulated body and unknown/nested fields; zero unauthorized mutations.
- **D5-H02:** Real CSRF setup/protected command, missing CSRF failure, no blanket exemption, private/no-store headers and genuine owner limiter/Retry-After. Use a real HTTP/session fixture where the normal Laravel test environment disables CSRF.
- **D5-H03:** Safe 422 stale_access contract and updated inspection; no silent retry; lost-success duplicate, expired session, unavailable schema and 429 are understandable and private.
- **D5-H04:** Old withdrawn/rotated links and stale representative batches denied, whole invalid batch unchanged, valid remaining holder only sees active authorized people in HTML/JSON; opaque references never authorize by themselves.
- **D5-H05:** No tokens in owner inspection, list/history, errors, logs, generic metadata or response URLs. Explicit recovery remains separate and no-store; withdrawn recovery denied. Legacy group routes still denied.

### Browser and owner workflow checks

Extend [Guest Management browser coverage](../../tests/Frontend/GuestManagement.browser.test.mjs), [RSVP Access regressions](../../tests/Frontend/RsvpAccessRegression.browser.test.mjs), [connected personal flow](../../tests/Frontend/PersonalRsvpIntegration.browser.test.mjs), [Attendance browser coverage](../../tests/Frontend/Attendance.browser.test.mjs) and [portal UX](../../tests/Frontend/PortalUx.browser.test.mjs).

- **D5-B01:** Active/Withdrawn/All auto-filter, debounce/search, query URL, Back/Forward/refresh and page reset; groups with no active members; matched hidden-name display; retained filter states across mutation.
- **D5-B02:** Independent/dependent/representative impact confirmations, explicit reinstate explanation, stale review, loading/double-click prevention, useful deletion error, no spurious delivery claim.
- **D5-B03:** Two open owner/guest windows: changed authority and answers; refusal then refresh, no unrelated names or partial batch save; cleared temporary credential URL on lifecycle/close/navigation.
- **D5-B04:** 390/414px mobile, 1024/1440px desktop, light/dark portal, long names/100-member group, focus trap/restoration, keyboard-only confirmation, reduced motion and no horizontal overflow. Preserve tags/collapse/clipboard behavior.
- **D5-B05:** Existing personal RSVP update/stale/no-op, Modern Classic/publishing and Attendance walkthrough. Leave isolated CMS/DEL-02d unchanged; do not rerun broad CMS visual suites unless a shared dependency is actually affected.

### Isolated MySQL/InnoDB schedule matrix

Proposed new data sets in [PersonalRsvpConcurrencyTest](../../tests/Feature/PersonalRsvpConcurrencyTest.php), or a focused sibling sharing its guarded worker pattern. **None has run.** Use two independent connections/processes, READY barriers and observed actual lock waiting before release; sequential requests do not prove races. Exercise both commit orders where outcomes differ.

| ID     | Contending operations                                    | Required invariant                                                                                                                                   |
| ------ | -------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| D5-M01 | Self RSVP ↔ owner withdrawal                            | Withdraw-first denies save; save-first preserves answer and invalidates old confirmation                                                             |
| D5-M02 | Multi-target representative RSVP ↔ dependent withdrawal | All-or-nothing submission; no written withdrawn target; current response versions gate owner review                                                  |
| D5-M03 | Representative RSVP ↔ representative withdrawal         | Entire old holder access revoked; committed answers retained; dependents stay active                                                                 |
| D5-M04 | Generate / recover / rotate / revoke ↔ withdrawal       | No active link or recovered URL from a transaction ordered after withdrawal; prior successful recovery does not keep link valid                      |
| D5-M05 | Designate / reassign / remove ↔ target withdrawal       | Exact-set revalidation, no withdrawn representative/dependent, no permission resurrection                                                            |
| D5-M06 | New outgoing dependent ↔ representative withdrawal      | Previously unseen affected person forces stale confirmation; fresh confirmed action clears all edges                                                 |
| D5-M07 | Withdraw / reinstate / repeated stale command            | Access counter detects ABA; no duplicate version/time mutations                                                                                      |
| D5-M08 | Rename or changed answer ↔ owner confirmation           | Displayed-person versions protect destructive confirmation; unrelated independent save remains valid                                                 |
| D5-M09 | Guest/group deletion ↔ withdrawal or personal issuance  | Parent/child guards and retained FK evidence prevent lost protected rows in both orders                                                              |
| D5-M10 | Last-retained-member removals / additions ↔ lifecycle   | Nonempty retained group and 100 retained limit survive contention; active zero permitted                                                             |
| D5-M11 | Unpublish / republish ↔ withdrawal and RSVP             | No unpublished write, no restoration of withdrawn authority; snapshot unchanged by eligibility                                                       |
| D5-M12 | Injected transaction failure while second writer waits   | Rollback leaves no partial revocation/answer/counter; waiting writer revalidates committed state                                                     |
| D5-M13 | Dashboard read transaction ↔ withdraw/reinstate         | Under verified isolation, all summary/list/count results agree; no mixed active/retained invariant                                                   |
| D5-M14 | Sanctioned Wedding parent deletion ↔ eligibility write  | Where an actual guarded writer exists, no cascade erases protected guest/response state; otherwise mark proposed coverage pending writer, not passed |

Harness prerequisites: APP_ENV testing, explicit verified local disposable database matching wedding_rsvp_cms_test (or guarded suffix), never wedding_rsvp, no inherited read/write URL fallback, correct SELECT DATABASE identity, complete migrations including withdrawal, InnoDB engines and known isolation. Use distinct storage/cache/session targets. The current harness requires INNODB_TRX/lock-observation visibility; obtain appropriate isolated-server privileges rather than silently skipping proof or granting broad privileges on a customer server. Record exact skips/failures and worker connections; never log tokens in process arguments/output. Do not reset development or start this harness from this planning task.

The 23 historical InnoDB passes comprise the original 19 plus four later schedules. Normal-suite opt-in omissions do not erase those passes. The separate CMS persistence constraint case remains unverified, and planned CMS/media races remain separate. New D5 cases are Planned, not additional “skipped tests.”

### Manual owner acceptance after automated gates

- [ ] Withdraw Pending, Attending and Declined independent guests; old links fail, exact saved answers/times remain visible privately and active totals decrease once.
- [ ] Withdraw a represented dependent; refresh holder excludes that person; stale mixed submission saves nothing; other independent guests remain private and work.
- [ ] Withdraw representative; all outgoing people stay invited and counted, without representation; arrange explicit replacement access.
- [ ] Reinstate every case; approved prior answer counts, old links remain invalid, new link/designation works only after explicit action.
- [ ] Change an answer/name/representative in another window during confirmation; review stale response, then deliberately confirm current impact.
- [ ] Withdraw final active group member; find retained group via Withdrawn/All; verify deletion protection, adding/reinstating, retained-capacity behavior and W05 exception.
- [ ] Verify active totals invariant in both views, filters and pages; unpublication and republishing/privacy/Modern Classic stay correct.
- [ ] Complete mobile/desktop/light-dark/keyboard/focus/clipboard checks with no fake success or delivery claims.

### Execution economy for future implementation

Run focused new tests plus affected files first. After the integrated batch, run the isolated full Laravel suite once, affected frontend/browser tests, typecheck, scoped ESLint/Prettier, required Pint for PHP, PHP syntax and production build. Rerun only affected failures after changes. Use repository temporary-extension SQLite commands; never redirect the suite to development MySQL. Engine tests require their own explicit verified target and evidence. This documentation task ran none of those application checks.

## 10. Gaps, risks and resolved contradictions

| Finding                                                                                           | Resolution / gate                                                                                                                                    |
| ------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Existing services treat every retained member as eligible                                         | Expected 5D feature gap, not evidence that accepted 5C was broken. Integrate explicit eligibility across reads/writes before activation              |
| Owner DTO lacks response versions; delegation request limited to three people                     | Add dedicated confirmation DTO/request bounded by retained group limit. Do not weaken existing strict delegation validation                          |
| Schema readiness excludes withdrawal, and Attendance is outside current personal middleware paths | Integrated readiness must cover affected reads/CRUD; missing schema fails closed, not everyone-active fallback                                       |
| Reduced-column selections omit future state                                                       | Add explicit columns and tests; never treat an absent attribute as invited                                                                           |
| “Retain history” could imply an audit ledger                                                      | Preserve existing identity/current answer/timestamps and issuance/designation evidence; neither current RSVP storage nor W01 stores complete history |
| Single timestamp versus ever-withdrawn retention                                                  | W05 explicitly chooses narrow reinstatement deletion exception; permanent retention requires different approval                                      |
| Stale errors proposed as 409 in some generic designs                                              | Match actual PersonalRsvpHttp 422 stale kinds; avoid frontend/server mismatch                                                                        |
| Restoring saved Attending may look like fresh consent                                             | W03 owner confirmation and historical timestamp distinguish it; no automatic reconfirmation feature                                                  |
| Parent cascades bypass descendant model hooks                                                     | Extend existing sanctioned parent guards and race coverage; do not assert raw-SQL/FK enforcement for unanswered never-issued withdrawn guests        |
| Old binaries ignore eligibility                                                                   | Controlled schema-ready deployment later; no mixed permission deployment or unsafe rollback. RF-021 remains open                                     |
| More per-feature flags / multi-group framework / audit tickets                                    | Unnecessary here. Existing server mode, scoped transaction and per-person counters suffice; keep lifecycle unrouted until coordinated integration    |
| CMS/publication coupling                                                                          | Guest operational state stays relational and out of CMS/revisions. No DEL-02d change or new publish fingerprint field                                |

See RF-003/023 for remaining races, RF-008 for external PHP/proxy/APM logs, RF-009 for tracked credential examples, RF-011/012 for retention/key/backup custody, RF-014 for proxy/rate fairness, RF-021 for deployment and RF-024 for withdrawal. All remain open as applicable. CSRF/no-store/application redaction do not protect external access logs. Bearer links remain transferable credentials; copying is not delivery. No reset-to-Pending or JavaScript-free RSVP is added.

## 11. Completed 5D.1a prompt — historical scope, do not rerun

```text
Implement Milestone 5D.1a ONLY — Invitation Withdrawal Schema/Model Foundation.

Prerequisite: The owner explicitly approves W01–W06, including W05's narrow
post-reinstatement deletion exception and W06's exact affected-person
confirmation/version strategy, in docs/tasks/milestone5d_implementation_readiness.md.
If approval is absent or differs, stop and reconcile before coding.

Follow AGENTS.md. Read that handoff, the original 5D proposal, current decisions,
handoff and QA-OPS-11/QA-RACE-08. Reinspect Git status, Guest, migration/factory
conventions, RsvpMode and isolated database/test guards. Preserve DEL-02d,
accepted 5C/CMS work and all unrelated uncommitted files.

Implement only:
1. One additive migration adding nullable guests.withdrawn_at, default null.
2. An immutable datetime cast, explicit active/withdrawn query scopes and minimal
   synthetic fixture support; no global scope or fillable withdrawal attribute.
3. Isolated schema/upgrade/model tests: existing guests remain active, null/non-null
   behavior and scopes, saved responses/timestamps/credentials/delegations unchanged.
   Document unloaded-attribute handling required by the later eligibility integration.
4. Synchronize affected checklist/handoff/QA status with exact evidence.

Do not wire withdrawal/reinstatement services, mutate credentials/delegations,
change active queries/counters, expose HTTP/UI controls, activate readiness hooks
that would break the unmigrated running app, or apply the migration to development.
No reset, customer changes, dependency/configuration changes, CMS/DEL-02d edits,
Seating, commit or push.

Run focused isolated migration/model tests and relevant PHP/Pint/syntax checks.
Use SQLite test isolation; any MySQL check requires a separately verified disposable
target. Preserve the 23 historical engine passes and separate pending CMS case.
Report exact migration/deletion semantics, files/results, pending engine checks and
the scoped next 5D.1b task. Stop after 5D.1a.
```

## 12. Decision-ready closeout

W01–W06 are owner-approved and 5D.1a/1b are implemented as an inactive backend foundation. The next separately authorized batch is **5D.2a active reads and safe owner contracts**. Full 5D is not accepted; no normal-app lifecycle/read/HTTP/UI activation is implied by these foundations or W decisions.

Keep original planning verification, current foundation verification and historical application/InnoDB evidence distinct. No development database, credential, CMS, deployment or manual-acceptance state changed in 5D.1a.

## 13. 5D.1a implementation closeout

Owner approval: D-036 (2026-10-21), all W01–W06, authorizing **5D.1a only**. Completed source files:

- [Additive migration](../../database/migrations/2026_10_21_142258_add_withdrawn_at_to_guests_table.php): nullable TIMESTAMP, default null; no backfill, FK/index/counter changes. MySQL compilation yields ``alter table `guests` add `withdrawn_at` timestamp null``; nullable TIMESTAMP defaults to NULL. Rollback drops only that field and discards any stored withdrawal state; it is not reinstatement.
- [Guest](../../app/Models/Guest.php): immutable datetime cast plus opt-in, qualified `activeInvitation()` and `withdrawnInvitation()` scopes. Name remains the only fillable attribute. No global scope, model default, new event or automatic version/lifecycle operation.
- [GuestFactory](../../database/factories/GuestFactory.php): synthetic `active()` and `withdrawn()` states. Default factory still inserts only name, preserving use with the unmigrated schema.
- [GuestWithdrawalFoundationTest](../../tests/Feature/GuestWithdrawalFoundationTest.php): fresh default/mass assignment, scope composition/retained rows/qualified joins, immutable timestamp/factory states, pre-existing-data upgrade, exact related-row/timestamp/version preservation, FK/index metadata preservation, reduced selection and MySQL grammar compilation without connection.

**Unloaded state for 5D.1b:** never equate a missing attribute with a loaded null. Require `array_key_exists('withdrawn_at', $guest->getAttributes())` or reload the full row under the existing transaction before eligibility decisions. Add explicit projection columns and fail-closed schema/readiness coverage in the coordinated later integration, including Attendance. No new readiness requirement was activated here, so the running unmigrated application remains usable. Current scopes are query helpers, not authorization.

### Exact verification evidence

All PHP test commands use process-only settings: APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array, SESSION_DRIVER=array, SESSION_PATH=/. SQLite/EXIF extensions and memory limits below are CLI-only; no global PHP configuration or normal environment file changed.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit --no-progress tests/Feature/GuestWithdrawalFoundationTest.php
# Final focused result: 7 passed, 53 assertions, zero failures/skips.

php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit --no-progress --stop-on-error --stop-on-failure --filter 'GuestWithdrawalFoundationTest|IndividualRsvpFoundationTest|IndividualRsvpMigrationTest|PersonalRsvpServicesTest|PersonalRsvpHttpTest|WeddingGuestManagementTest|WeddingAttendanceTest|WeddingPublicationTest'
# Final regression result: 182 passed, 1,683 assertions, zero failures/skips.

php vendor/bin/pint --dirty --format agent app/Models/Guest.php database/factories/GuestFactory.php database/migrations/2026_10_21_142258_add_withdrawn_at_to_guests_table.php tests/Feature/GuestWithdrawalFoundationTest.php
# Passed; normalized imports in the new test only.
```

`php -l` passed individually for all four PHP files above. Changed-document Prettier and `git diff --check` are closeout gates. No frontend/shared type/UI change, so frontend/browser/TypeScript/build checks were not rerun. This task ran affected Laravel regressions, not a full Laravel suite or MySQL harness.

Initial execution corrections: Artisan's test subprocess did not inherit temporary SQLite loading (seven driver errors before assertions), so execution switched to direct PHPUnit. The first direct run found a fixture-only extra dependent created by `representedBy()`; explicitly associating the already-created dependent corrected it. The first broad run inherited the invalid cookie path (182 executed, 16 errors/86 failures); the next stopped at 67 tests/462 assertions because EXIF/256MiB image-runtime prerequisites were absent. The final command supplies the documented process settings and passes all 182. No business logic or existing tests were altered to obtain these results.

Historical **23 InnoDB passes / 483 assertions** remain unchanged, as does the separate unverified CMS constraint test. No new MySQL test was skipped in this selected run. Actual MySQL withdrawal-column upgrade/constraint verification remains unexecuted; new withdrawal lifecycle/race schedules remain Planned, not Passed. Compiled DDL is not engine or concurrency proof.

### Next authorized-work boundary

Historical next step at the 5D.1a closeout was internal lifecycle/eligibility, subsequently authorized by D-037 and delivered in Section 14. Active Attendance/owner integration remains 5D.2; engine/manual acceptance gates remain 5D.3. No CMS, DEL-02d, RSVP permissions, credentials or development records changed in 5D.1a.

## 14. 5D.1b internal lifecycle closeout

D-037 authorizes this batch only. **Internal services are implemented, not activated for normal application traffic.** No migration, reset, customer/credential operation, new route, owner UI, Attendance change, production binding or configuration change occurred.

### Implemented source and behavior

- [GuestInvitationLifecycle](../../app/Services/GuestInvitationLifecycle.php): owner-authorized inspect/withdraw/reinstate; strict expected state and exact affected-person access/response/link versions inside the existing wedding-first transaction. Unknown/nested/duplicate/unbounded or non-integer input fails validation; stale state/set/version uses existing stale_access 422 semantics. Safe owner state contains no credentials, contacts or unrelated members.
- [InvitationEligibility](../../app/Services/InvitationEligibility.php): immutable default-off context, schema requirement, loaded-attribute checks and retained guest/parent guards. Guest deletion also re-reads stored eligibility so a previously loaded active model cannot erase a committed withdrawal.
- Existing [transaction](../../app/Services/PersonalRsvpTransaction.php), [credential](../../app/Services/PersonalInvitationLink.php), [delegation](../../app/Services/RsvpDelegationManager.php), [personal RSVP](../../app/Services/PersonalRsvp.php) and [mode/readiness](../../app/Services/RsvpMode.php) services have explicit enabled-path checks. [Guest](../../app/Models/Guest.php), [Group](../../app/Models/InvitationGroup.php) and [Wedding](../../app/Models/Wedding.php) invoke the inactive guards; their existing retention rules remain.
- Withdrawal changes only eligibility/credential/delegation/access state. Preflight overflow, clear credential without decryption, disconnect retained slots and advance target/former representative/released dependent access once each. RSVP records, response timestamps and response versions remain unchanged. Reinstatement restores eligibility only; old tokens/designations stay inactive. Current-version same-state retry is a no-op; obsolete confirmation/ABA is stale, not automatically replayed.
- All authorization/membership/authority decisions re-read locked rows. Independent people keep their baselines. Invalid target batches and failures after credential/version writes roll back completely. Reads fail closed on inconsistent withdrawn authority; restrict-only explicit revoke/removal remains possible. No audit table, new counter or duplicate lifecycle implementation was introduced.

### Exact inactive boundary and migration compatibility

Normal container resolution yields `new InvitationEligibility(enabled: false)`. No enabled provider binding exists. The isolated test explicitly installs:

```php
$this->app->instance(InvitationEligibility::class, new InvitationEligibility(enabled: true));
```

Both lifecycle and existing transactions consult the server-side context; no submitted field, query parameter or environment flag enables it. Lifecycle refuses the default context. When enabled, missing withdrawal schema or unloaded state returns unavailable (503); withdrawn personal holders/targets return generic 404 before reads or identical retries. Owner grants/recovery/rotation/designation fail validation. Default-off ordinary application paths retain the accepted pre-withdrawal behavior and do not query the new column. The test removes the column only from guarded in-memory SQLite, then proves personal read/copy, rename/delete and old readiness still work while the lifecycle refuses to run.

Do not bind enabled in normal traffic yet: the development schema is unmigrated and active Attendance/owner integration is incomplete. This is an explicit internal foundation boundary, not permission to ignore withdrawn state after future activation. 5D.2a must finish all read/DTO/readiness integration and coordinate activation/schema readiness before any later HTTP/UI exposure. No runtime schema fallback or automatic migration is permitted.

Deletion guards supplement C04/FKs. Ordinary guest/group writers already hold wedding-first locks; no new parent deletion writer is introduced. Raw SQL, bulk deletes or uncoordinated parent operations bypassing the sanctioned transaction contract are not claimed safe. Real InnoDB parent/lifecycle races remain Planned. No publication snapshot or fingerprint is changed.

### Exact verification

Final focused **48 passed / 238 assertions** in [GuestInvitationLifecycleTest](../../tests/Feature/GuestInvitationLifecycleTest.php). This covers default-off/unmigrated compatibility, schema/unloaded fail-closed, Pending/Attending/Declined retention, personal/representative/dependent withdrawal, reinstatement/fresh access, independent saves, exact-set/each-version/stale-name/answer/rotation checks, invalid input, owner/tenant denial, eligibility before unchanged retries, corrupt ciphertext, overflow, rollback, stale deletion/parent retention and unchanged publication data. The safe 422 test calls the existing renderer; it is not a withdrawal HTTP integration test.

Final affected regression bundle **230 passed / 1,921 assertions / zero failures/skips**, including the 48 new cases. CLI-only environment: APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, CACHE_STORE=array, SESSION_DRIVER=array, SESSION_PATH=/.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit --no-progress --stop-on-error --stop-on-failure tests/Feature/GuestInvitationLifecycleTest.php

php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=exif -d memory_limit=256M vendor/bin/phpunit --no-progress --stop-on-error --stop-on-failure --filter 'GuestInvitationLifecycleTest|GuestWithdrawalFoundationTest|IndividualRsvpFoundationTest|IndividualRsvpMigrationTest|PersonalRsvpServicesTest|PersonalRsvpHttpTest|WeddingGuestManagementTest|WeddingAttendanceTest|WeddingPublicationTest'

php vendor/bin/pint --dirty --format agent app/Services/InvitationEligibility.php app/Services/GuestInvitationLifecycle.php app/Services/PersonalRsvpTransaction.php app/Services/PersonalInvitationLink.php app/Services/RsvpDelegationManager.php app/Services/PersonalRsvp.php app/Services/RsvpMode.php app/Models/Guest.php app/Models/InvitationGroup.php app/Models/Wedding.php tests/Feature/GuestInvitationLifecycleTest.php
```

Pint passes on those 11 PHP files; `php -l` passes individually on all 11. Documentation Prettier/links/QA totals and `git diff --check` are closeout checks. Initial focused subset was 42 passes/220 assertions, expanded to 48/238 after adding exact-set/dependent-response, reassignment/order, stale-model deletion and reinstatement rollback tests. No failures occurred in executed 5D.1b tests. No frontend/shared type changed, so frontend/browser/type/build checks were not repeated. No full Laravel or MySQL run is claimed.

The **23 historical InnoDB passes / 483 assertions** and the separate pending CMS constraint case remain distinct. No new engine schedule has run; D5-M01–M14 remain Planned. Their future independent workers must explicitly install the enabled context in each isolated process, verify the migrated disposable InnoDB target and observe real contention. Default-off worker execution would not test withdrawal. Existing RF-003/008/009/011/012/021/023/024 findings remain open as applicable.

### Proposed 5D.2a scope — not authorized here

Implement explicit active scopes in every Attendance summary/list/group/count/detail/search/pagination path and consistent retained/active/withdrawn Guest Management totals. Add safe owner invitation-state/capability/confirmation DTOs without recovering credentials or exposing secrets; preserve group membership, retained capacity, search/history and independent response authority. Extend Attendance readiness in the enabled context, with coherent read transactions and all-withdrawn/Pending/reinstatement invariants tested. Keep withdrawal routes/UI and normal-app activation off until separately authorized coordinated integration, required engine/security gates and schema readiness. No development migration or UI activation is implied by 5D.2a. Stop after 5D.1b now.

## 15. 5D.2 implementation closeout (2026-10-25)

D-059 explicitly authorizes the remaining owner/read integration under the unchanged D-036 W01–W06 contract. **Implementation is complete; the owner accepted the manual functional walkthrough under D-060. Real InnoDB verification remains open.** The earlier Section 14 boundary is historical foundation status, superseded here. DEL-09a Photobooth owner acceptance is recorded in D-059; its approved source and behavior were not changed in the 5D batch.

### Integrated behavior

- `config/rsvp.php` and the `InvitationEligibility` binding now expose `RSVP_WITHDRAWAL_ENABLED`, default `false`, and permit it only when `RSVP_MODE=personal` in local/testing. Production and other environments cannot enable this integration through the setting. Existing services still fail closed on a missing `withdrawn_at` schema. No migration or database initialization was added by this implementation.
- New owner-only nested GET inspect and POST change routes reuse `GuestInvitationLifecycle` and `PersonalRsvpTransaction`'s Wedding-first locked order. Scoped binding, `update` policy authorization, CSRF, strict bounded confirmation payload validation, exact affected-set/version rechecks, typed stale feedback and `no-store, private` response headers are retained. HTTP payloads contain no token, contact or unrelated guest data.
- Guest Management adds retained Active (default), Withdrawn and All filters to URL/history/search/pagination. Group totals remain invitation groups; active and withdrawn person totals are separate. Zero-active groups remain available under All. Saved response status/timestamp is shown only in this owner surface. Withdrawn members remain editable but cannot gain links/delegation until reinstated.
- RSVP Access marks withdrawn invitations and explains access is unavailable; credential recovery/issuance and delegation controls are absent. Existing server-side service validation remains authoritative for stale or tampered clients.
- Attendance summary, guest list and group counts include only active invitations when enabled. Retained RSVP records and response versions/timestamps remain unchanged; reinstatement returns the existing answer to active totals without restoring old token/designation. Generic wedding/public/personal props remain unchanged and do not expose withdrawal state.

### Exact verification

Final isolated Laravel selection: **89 passed / 676 assertions**, including the existing lifecycle/RSVP regression set and new HTTP checks for private no-store inspection/changes, stale replay rejection, retained attending response, withdrawn bearer denial, reinstatement without access restoration, active/withdrawn/all filters, all-withdrawn group retention, zero active Attendance and default-off/tenant denial.

```powershell
# Process-only isolation; run from the repository PowerShell window.
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:DATABASE_URL=''
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:SESSION_PATH='/'
$env:APP_CONFIG_CACHE=Join-Path $env:TEMP 'rsvp-del5d-no-config.php'
$env:APP_ROUTES_CACHE=Join-Path $env:TEMP 'rsvp-del5d-no-routes.php'
$env:PHP_INI_SCAN_DIR=Join-Path $env:TEMP 'rsvp-cms-editor-LrKACO\ini'
$env:LARAVEL_STORAGE_PATH=Join-Path $env:TEMP 'rsvp-del5d-isolated-storage'
New-Item -ItemType Directory -Force -Path $env:LARAVEL_STORAGE_PATH\framework\cache\data,$env:LARAVEL_STORAGE_PATH\framework\sessions,$env:LARAVEL_STORAGE_PATH\framework\views,$env:LARAVEL_STORAGE_PATH\logs,$env:LARAVEL_STORAGE_PATH\app\private | Out-Null
php artisan test --compact tests/Feature/PersonalRsvpHttpTest.php tests/Feature/GuestInvitationLifecycleTest.php
```

Connected installed Edge: `node --test tests/Frontend/GuestWithdrawal.browser.test.mjs` — **1 passed / 0 skipped** using its isolated migrated SQLite, owner session and test-only `RSVP_WITHDRAWAL_ENABLED=true` harness. It withdraws a representative, confirms the dependent remains active, reinstates without reissuing access, and checks active Attendance. No retained owner preview data was written.

`npm.cmd run typecheck`, scoped `npm.cmd exec -- eslint ...`, scoped `npm.cmd exec -- prettier --check ...`, `vendor/bin/pint --dirty --format agent` on changed PHP files, PHP syntax checks and `npm.cmd run build` pass. Exact changed paths are in the closeout's delivery record and repo status. No migrations were authored. No disposable InnoDB target was configured or used; the local `wedding_rsvp` schema is not an approved disposable target. D5 withdrawal/reinstatement versus RSVP/credential/delegation/parent races remain Planned, not skipped or passed. Manual owner acceptance is recorded under D-060; QA-OPS-11 remains open for real disposable-InnoDB evidence only.

### Owner review on the required origin

Use only the retained synthetic SQLite preview, never the MySQL profile. Stop the currently running `dev.cmd` with Ctrl+C, then in the same PowerShell window start the retained preview with the local-only feature flag:

```powershell
$env:RSVP_WITHDRAWAL_ENABLED='true'
.\dev.cmd
```

Open the sign-in at [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login), then Guest Management at [http://127.0.0.1:8000/accounts/1/weddings/1/guests](http://127.0.0.1:8000/accounts/1/weddings/1/guests) and Attendance at [http://127.0.0.1:8000/accounts/1/weddings/1/attendance](http://127.0.0.1:8000/accounts/1/weddings/1/attendance). `dev.cmd` prints the retained preview sign-in details privately. Do not stop an unidentified process or choose another port. Closing that terminal drops the process-only flag; ordinary startup remains disabled.

For the manual review, use a synthetic guest without existing credentials/delegation so the retained preview's test links are not revoked. Confirm Active/Withdrawn/All filtering and separate person/group totals; withdraw and reinstate, verify the saved answer/timestamp stays, Attendance excludes then restores the person, and RSVP Access explains the unavailable state without link controls. A representative release and old bearer-token denial are covered by the isolated browser/HTTP tests; avoid changing a credential-bearing retained fixture solely to repeat those destructive effects. Review mobile layout and keyboard dialog focus. Explicitly report acceptance or issues; DEL-09a needs no further acceptance.

### Database incident boundary

The first non-isolated test invocation accidentally applied the pending 18 migrations to `wedding_rsvp`; this is documented in [DEV-ENV](dev_env_single_origin.md#test-runner-incident-2026-10-25) and RF-032. Read-only verification afterward found 20 application tables and zero user/account/wedding/guest rows. No rollback or further MySQL writes were attempted. This does not count as owner-approved initialization, normal login verification, or 5D engine evidence. Owner review is required before any additional MySQL action.
