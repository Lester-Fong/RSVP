# Milestone 5D — Invitation Withdrawal and Active Guest Eligibility

Prepared: 2026-10-20. Updated: 2026-10-25. **W01–W06 owner-approved under D-036; 5D implementation is complete behind a default-off local/testing gate, with owner acceptance and real InnoDB verification pending (D-059).** The latest closeout is in the [5D implementation readiness handoff](milestone5d_implementation_readiness.md#15-5d2-implementation-closeout-2026-10-25). D-023 records full 5C acceptance. The historical 23 InnoDB passes do not verify withdrawal. See [roadmap](../mvp_roadmap.md), [decisions](../decisions.md), [5C design](milestone5c_individual_rsvp_architecture.md) and [issue register](../risks_and_followups.md).

D-021 requires withdrawal before Seating: preserve guests/answers/timestamps, deny withdrawn-person authority and exclude withdrawn people from active counts. W01–W06 below are **approved**, including preserved answers on reinstatement, the narrow post-reinstatement deletion exception and exact affected-person/version confirmation. D-059 authorizes the completed owner and Attendance integration; the feature remains default-off and no production activation is implied. Modern Classic, publishing, onboarding and Seating changes remain outside this batch.

2026-10-21 [implementation handoff](milestone5d_implementation_readiness.md) supplies the source map, approved exact confirmation/version contract, scoped APIs, smaller batches, unloaded-attribute safeguards and current 5D.1a verification. The original review was documentation-only; subsequent foundation tests use disposable in-memory SQLite. No migration was applied to development MySQL.

## 1. Current implementation and proposed schema/lifecycle

Relevant findings:

- `Guest` belongs to one invitation group; groups belong to weddings. There is no withdrawal state. `GuestController` and `InvitationGroupController` use wedding-first writes; the latter currently reports all retained people as invited.
- `PersonalRsvpTransaction` locks Wedding, Group, ordered Guests, Credentials, Delegations and Responses. `PersonalRsvp` revalidates publication, personal credentials and explicitly delegated targets inside those locks. It has no active-invitation eligibility check yet.
- A unique retained `guest_invitation_links` row records personal issuance; revocation clears secrets without deleting the row. A unique retained `rsvp_delegations` slot records dependent designation; null representative means inactive. Representatives require active independent credentials.
- `rsvp_responses` holds one current answer per guest, not an answer-history ledger. Pending is no row. Guest renames/changed responses use `rsvp_version`; authority changes use `rsvp_access_version`.
- Guest, group and Wedding model guards protect responded/issued/delegated records; restrictive child FKs backstop those relationships. Group-to-guest and wedding-to-group FKs themselves cascade. A never-issued, unanswered withdrawn guest would have no restrictive child FK, so explicit parent deletion guards are still needed.
- `WeddingAttendance` uses a left join for totals and separate queries for individual results, group counts and expanded members. All currently count every guest, independently of credential state. Generic published pages contain no guest data; personal pages compose a live authorized roster around the immutable wedding snapshot.

**W01 — Recommend one nullable `guests.withdrawn_at` timestamp**, default null, with an immutable datetime cast and server-generated time. Null means actively invited; non-null means currently withdrawn. Add it through one additive migration after approval; preserve all existing rows, responses and unique credentials. No reset, backfill, status enum, reason field, audit table or new tenant key is necessary. Do not mass-assign withdrawal from ordinary create/edit payloads.

Prefer explicit active/withdrawn query scopes over a global scope: transactions and authorized management must still find retained withdrawn records. Use existing group indexes initially; assess a composite group/withdrawal index with representative MySQL query plans before adding one. No speculative index or constraint change is approved here.

Extend the existing personal-schema readiness assertion to require the new column when implemented. A missing/rolled-back withdrawal column must fail closed, never make everyone active through a fallback. Dropping it would lose current withdrawal state; do not use a rollback/reset as an invitation-reinstatement mechanism. No elaborate legacy conversion is needed.

| Action                               | Invitation state   | Credentials and delegation                                                                      | Saved response                                          |
| ------------------------------------ | ------------------ | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| Create a guest                       | Active             | No automatic link or representation                                                             | Pending/no row                                          |
| Withdraw                             | Set timestamp once | Clear personal credential material; remove incoming and outgoing active designations atomically | Preserve status, `responded_at` and response timestamps |
| Repeat withdrawal at current version | Remains withdrawn  | No additional mutations                                                                         | Unchanged                                               |
| Reinstate                            | Clear timestamp    | Remain independent without an active link; no automatic restoration                             | Preserve previous answer, or pending if none existed    |
| Issue access after reinstatement     | Active             | Explicitly generate a fresh personal link or designate an eligible representative               | Unchanged                                               |

This column records current withdrawal, not a history of every withdrawal/reinstatement. Reinstatement clears the previous withdrawal time. Retained credential/delegation rows record issuance/designation evidence, not a complete lifecycle audit. Stronger audit requirements would need separate approval.

## 2. Authorization, delegation and retention rules

**W02 — Recommend immediate withdrawal of authority without withdrawing anyone else.** Reuse WeddingPolicy, authenticated nested account/wedding/group/guest binding, strict Form Requests and CSRF. Derive ownership from server relationships, never submitted ownership IDs. Proposed adapters are separate scoped POST withdraw/reinstate actions around one focused service; this document does not add routes.

| Subject/action                                               | Proposed result                                                                                                  |
| ------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Authorized owner withdraws/reinstates a scoped guest         | Allowed with current versions and explicit confirmation                                                          |
| Foreign account, wedding or manipulated group/guest          | Denied; no state or answer changes                                                                               |
| Withdrawn independent holder opens/submits old personal link | Generic private 404; old token remains invalid after reinstatement                                               |
| Withdrawn dependent                                          | Remove incoming designation; holder must reload and cannot include that person                                   |
| Withdrawn representative                                     | Revoke own credential and clear outgoing designations; dependents stay actively invited with their saved answers |
| Generate/recover/rotate a withdrawn person's link            | Reject with an owner-facing request to reinstate first                                                           |
| Assign/reassign delegation involving a withdrawn person      | Reject, including a withdrawn representative with inconsistent retained credentials                              |
| Anonymous visitor to generic published wedding               | Existing public behavior, without private guest/withdrawal data                                                  |

Clear delegation slots by setting representative to null; retain slots and credential rows. Clear even inconsistent active authority defensively. Dependents released by representative withdrawal become independent **without automatically issued links**; the owner must explicitly select another eligible representative or generate their own links. Never infer a replacement from names, age or group position. Rotation/revocation semantics for other active guests remain unchanged.

**W03 — Recommend reinstatement restores active eligibility and the previous saved answer, but no old permission.** An attending person therefore counts as attending again immediately; a declined person counts as declined, and an unanswered person stays pending. Show this consequence before confirmation. This preserves answers/timestamps and avoids inventing a reset-to-Pending or reconfirmation feature. If fresh confirmation is desired instead, approve that separate RSVP-state change before implementation. No token or designation may silently reactivate.

**W04 — Recommend retain group membership, including groups with zero active members.** Withdrawal is not deletion or reassignment. A group must still have at least one retained member, but may have zero actively invited members. Keep its label/contacts editable and its members discoverable. Adding a guest starts active/pending. Preserve the existing maximum of 100 retained members per group; withdrawal does not free a retained-record slot. Group totals must be labeled as groups, never people.

**W05 — Recommend block ordinary hard deletion while withdrawn, and retain all existing C04 protections after reinstatement.** Apply friendly guards to guest, group and Wedding model deletion paths so a parent cascade cannot bypass withdrawal retention. A wholly never-issued/never-delegated/unanswered active group may still use its existing confirmed deletion behavior and nonempty-group rules. A reinstated guest with no issuance, delegation or response evidence returns to that existing active-record deletion policy. If _ever withdrawn_ must permanently prohibit deletion even in that narrow case, `withdrawn_at` alone is insufficient evidence after reinstatement: approve an additional retained marker or broader deletion policy separately. Do not fabricate credential/delegation rows as retention flags. Broader account/SQL retention remains RF-011, not a guarantee supplied by model events.

## 3. Owner and guest UX

Future portal work must load Horizon Minimal before edits. Preserve automatic search/history, pagination, five-name collapse/search visibility, guest tags and current dialogs.

- Provide an invitation-state filter: **Active** by default, **Withdrawn**, **All**. Keep it distinct from personal-link state and RSVP status. Search applies within that filter; selection resets pagination and stays in the URL. Include groups with matching members; a fully withdrawn group remains discoverable under Withdrawn/All. Show filtered members/counts clearly, and all saved members in an explicit management/access detail view.
- Keep group totals distinct from **Active invited people** and a compact **Withdrawn people** count. An all-withdrawn wedding has zero active invited people without appearing to have lost its saved groups.
- Put **Withdraw invitation** in the guest's contextual management menu, away from ordinary rename. Confirm the named person, retained answer, exclusion from totals, revoked link and each affected representative/dependent. Explain that released dependents remain invited and need new access arrangements. Do not add bulk withdrawal in this milestone.
- For withdrawn people show a clear badge and **Reinstate invitation** confirmation. Display their retained RSVP as historical information in private management, without editable owner RSVP controls. The confirmation states whether their previous answer will re-enter active totals, and that a fresh link/designation is a separate action.
- RSVP Access shows withdrawn people as unavailable for personal credentials or representation, with an explanation. Preserve the explicit Recover link → Copy link flow, temporary secret lifetime and current-version synchronization; copying is not delivery. After a lifecycle action refresh the safe group snapshot, counters and available actions without background credential recovery.
- Public links never disclose why a holder is unavailable. After a dependent is withdrawn an already-open representative form must reject stale authority and reload to the remaining allowed people; do not announce withdrawn names in public errors. Already downloaded information cannot be recalled by revocation.
- Preserve Modern Classic unchanged. Require confirmation focus/trap/restoration, readable long/duplicate names, useful loading/error feedback and no overflow at 390px; check 1024/1440px and light/dark portal modes. Guest names remain escaped.

## 4. Attendance, publication and future Seating

**W06 — Recommend one active-eligibility definition across all operational reads: `withdrawn_at IS NULL`.** Apply it to the guest base query before joining responses, every aggregate/list/count, group eligibility and expanded group details. Keep wedding-wide totals independent of search/status/page. Preserve:

`Active invited = Attending + Declined + Pending`

An attending withdrawn person and their retained answer contribute zero to all active categories. Credential revocation alone still leaves invitation eligibility and totals unchanged. Do not filter only on response columns and accidentally remove pending guests. Do not join credentials/delegations into totals or persist derived counters. No new Attendance history dashboard is needed; private Guest Management supplies withdrawn-record review.

Published wedding snapshots, `/w/{slug}`, hero media and publication controls do not change. Personal reads/writes additionally require an active holder and active authorized dependents. Unpublished weddings remain unavailable even if everyone is actively invited. Withdrawal must not edit snapshots or expose operational state through public metadata/JSON.

Future Seating must exclude withdrawn guests from every eligibility query, regardless of retained attending answers. No seating records exist today. S01–S06 remain unapproved: S01 must combine active invitation with the eventual approved RSVP eligibility rule; S02 must separately decide withdrawal/reinstatement of existing assignments; S05 must invalidate the eventual planner on relevant eligibility changes under the same wedding lock; S06 output must exclude withdrawn placements according to its approved policy. Do not add seating versions, assignments, automatic removal behavior or drag-and-drop now.

## 5. Transaction, version and race contract

Extend the existing shared transaction rather than introducing a second lock system:

1. Lock Wedding → Group(s) ordered by ID → Guests ordered by ID → Personal Credentials → Delegations → RSVP Responses. Future seating locks follow only after separate approval.
2. Reauthorize the owner inside the transaction; resolve the current affected authority graph from locked rows. Validate the target's expected access/link versions and expected access versions for affected representatives/dependents. Reject a changed affected set as stale so confirmation cannot silently affect additional people. Existing per-group bounds keep this finite.
3. Validate eligibility/state and versions before any mutation. Set/clear withdrawal, clear credential material and active slots atomically. Reuse shared low-level lifecycle helpers without nesting independent commands that re-lock or increment the same counter twice. Return only a fresh allowlisted owner snapshot.
4. Advance `rsvp_access_version` once per actually affected guest per operation: the target, its former representative and its released dependents as applicable. Advance credential version only when credential material changes. Preserve `rsvp_version`, response status and response timestamps for withdrawal/reinstatement alone; name/actual answer changes retain their existing rules. Never reset counters or reuse old tokens.
5. An already matching state with current versions is a no-op: no new withdrawal time, version increments or answer writes. Stale versions still return existing typed stale-access feedback even when the requested end state matches; this prevents an old command replay across withdrawal/reinstatement cycles. No blind retries of stale owner intent.

Guest submission must recheck holder/target active eligibility inside the same transaction before accepting even an identical answer retry. If withdrawal commits first, the waiting old-holder request fails 404, or a remaining representative's old access version/removed target fails safely. If an authorized response commits first, withdrawal preserves it and removes it from active totals. An invalid/withdrawn target rejects the **entire** representative batch; do not silently drop targets or partially save others. Unrelated independent guests keep their response/access versions.

Add active-state checks to every credential/delegation entry point, personal lookup/presentation/submission and relevant CRUD parent guards. Avoid relying solely on UI hiding or null credentials. Guard reduced-column model loads and double increments. Preserve stateless private GET, CSRF-protected writes, digest-based/per-IP limit separation, no-store/referrer/indexing protections, allowlisted props and application token redaction. External access logs, key custody and credential-example findings remain open.

## 6. Automated tests and manual acceptance

Use disposable SQLite for functional/schema tests and a separately verified disposable MySQL/InnoDB target with independent workers and real lock-wait barriers for races. The 23 existing engine cases are passed historical evidence, **not** verification of these new scenarios. Never run resets/tests against development data.

| Coverage             | Required scenarios                                                                                                                                                                                                                                                                                         |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Schema/retention     | Existing synthetic rows default active; nullable timestamp/cast; preserved names/responses/response timestamps; no token/delegation deletion; withdrawn guest and parent deletion guards; approved reinstatement/deletion edge case                                                                        |
| Owner security       | Owner withdraw/reinstate; foreign account/wedding/group and manipulated payload denial; missing CSRF; stale version/affected-set rejection; active-only credential/delegation operations                                                                                                                   |
| Authority lifecycle  | Independent, dependent and representative withdrawal; released dependents stay invited; incoming/outgoing slots retained inactive; fresh credentials/explicit delegation after reinstatement; old tokens never work again                                                                                  |
| RSVP integrity       | Pending/attending/declined preservation; partial and atomic submissions; revoked/withdrawn holder denial; removed target rejection; repeated authorized no-op rules; unrelated independent saves remain valid                                                                                              |
| Counts/queries       | Zero active, all withdrawn, mixed/partially responded groups; correct subtraction/restoration per status; every Attendance view/detail/pagination/search path; groups versus people; filters do not alter summary totals; no N+1 or duplicate counts                                                       |
| Presentation/privacy | Only active authorized people in personal HTML/JSON; generic public page unchanged; no contacts/secrets in ordinary props/logs; expired links have private generic errors; Modern Classic/media/publication regressions                                                                                    |
| Portal/browser       | Active/Withdrawn/All URL/history and search; fully withdrawn groups discoverable; confirmations/impact lists, stale refresh, safe link memory, copy fallback, collapse/tags, responsive keyboard/focus/loading behavior                                                                                    |
| Real InnoDB races    | Withdrawal versus RSVP in both lock orders, atomic representative batch versus dependent withdrawal, withdrawal versus issuance/rotation/recovery/designation/reassignment, representative withdrawal versus dependent response, reinstatement versus old command, and relevant guest/group deletion races |

Manual owner checklist after implementation:

- [ ] Withdraw an attending independent guest: old link fails, answer/time remain privately visible, active totals decrease once; repeat safely.
- [ ] Withdraw a pending and a declined guest; verify active category totals and the invariant in individual/group views and filters.
- [ ] Withdraw Miguel while represented by Juan: Juan reloads without Miguel; old mixed submissions save nothing. Other independent people remain private and can respond normally.
- [ ] Withdraw Juan as representative: his link fails; Miguel stays invited with preserved answer but no active representative. Explicitly designate another eligible active person.
- [ ] Reinstate each case: original answer contributes according to approved W03, old links/designations stay inactive, fresh explicit access works. Test a second-window stale confirmation.
- [ ] Withdraw the last active group member; retain/discover the group, deny protected deletion and add/reinstate a member without losing records.
- [ ] Verify foreign-account denial, unpublished protection, generic public privacy, response persistence, Attendance and publishing regressions, mobile/light-dark/keyboard and clipboard retests.

## 7. Owner-approved decisions (D-036; implementation scoped separately)

| ID  | Approved rule (D-036)                                                                                                                                                                                                                       |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| W01 | One nullable `withdrawn_at`, no audit history/reason/new table; explicit scopes and additive migration, no database reset                                                                                                                   |
| W02 | Atomically revoke the withdrawn person's credential and incoming/outgoing designations; released dependents remain invited without automatically issued access                                                                              |
| W03 | Reinstate with the previous saved answer immediately counted, but no restored token/designation; no reconfirmation/reset-to-Pending feature                                                                                                 |
| W04 | Preserve group membership; allow zero active members; Active/Withdrawn/All management filter and distinct people/group totals; keep 100 retained members/group                                                                              |
| W05 | Block hard deletion while withdrawn plus existing C04 evidence after reinstatement; allow the narrow never-issued/delegated/responded reinstated record to return to existing deletion rules, without claiming permanent withdrawal history |
| W06 | Exclude withdrawn people consistently from all active Attendance/eligibility reads; preserve response versions/timestamps for eligibility-only actions and use access-version conflicts                                                     |

The owner approved W01–W06 on 2026-10-21. W06 includes the readiness handoff's exact affected-person access/response/link version confirmation. Permanent ever-withdrawn history and fresh RSVP reconfirmation are outside the approved minimal design. S01–S06 remain unapproved. 5D.1a/1b are implemented/authorized as an inactive backend foundation; later read/HTTP/UI batches need separate authorization. No old-data cutover tooling is required.

## 8. Recommended implementation batches after approval

1. **5D.1 — Schema and transactional lifecycle:** approve W01–W06 first; add the timestamp/cast/scopes and focused withdrawal/reinstatement service; integrate authority checks, versions and parent retention guards; isolated schema/service/security/response-preservation tests. Keep lifecycle commands internal until 5D.2 connects the active-count and owner workflows together. No separate legacy migration or new credential system. Review the exact additive migration before any development application.
2. **5D.2 — Active read integration and owner workflow:** update every Attendance/Guest Management count/list/detail and safe access contract together; connect compact owner confirmations/state filters, preserve existing portal UX and public Modern Classic; add focused HTTP/query/frontend regression tests. Avoid shipping a half-integrated state that withdraws authority but still counts people as invited.
3. **5D.3 — Verification and owner acceptance:** full isolated Laravel/frontend/type/lint/format/build checks, desktop/mobile/accessibility review, new real InnoDB race cases and manual checklist. Record passed/failed/unverified evidence honestly; obtain explicit 5D owner acceptance before any Seating work. Broader production risks remain in RF-003/008/009/011/012/021/023 and M8.

**Current status:** D-059 authorizes integrated 5D.2, now implemented behind a default-off local/testing flag. Owner acceptance and the approved real InnoDB race matrix remain open; see the [Section 15 closeout](milestone5d_implementation_readiness.md#15-5d2-implementation-closeout-2026-10-25). The earlier batch descriptions are historical sequencing. The development MySQL schema incident is recorded separately in [DEV-ENV](dev_env_single_origin.md#test-runner-incident-2026-10-25); no further DB writes are authorized.
