# Post-integration guest search and Photobooth

**2026-10-25 — focused integration fix; owner verification pending.** D-060 records the owner's manual acceptance of Milestone 5D. The withdrawal implementation remains default-off in normal traffic; disposable-InnoDB races and production/release gates remain open.

## Root causes and behavior

Guest Management search failed inside nested query closures because they referenced `$eligibility` and `$invitationState` without capturing either value. The latest Laravel error log also contained a `TypeError`: an eager-loaded `HasMany` relation was passed to an `InvitationEligibility::constrain()` signature that accepted only `Builder`. The current working-tree service had already been widened to accept `Builder|Relation`; this batch preserves that compatibility and captures the two values in both search closures. Search remains bound, escapes `%`, `_` and `!`, and applies the same Active/Withdrawn/All state to both group matching and loaded guests. SQLite feature coverage exercises these HTTP behaviors and tenant scoping remains through the authorized nested wedding route.

The saved wedding tested at account 3 / wedding 3 uses Enchanted Editorial schema v2, but its existing draft and published revision 8 both have `experience.enabled=false`. The template adapter already emitted Photobooth for both generic and authorized personal pages when the immutable published snapshot enabled it; there was no synthetic-only restriction or renderer split. The prior new-document default also disabled it. Fresh Enchanted starter documents now include the approved Photobooth v1 settings enabled by default. Owners can uncheck the option before Save/Publish. Existing drafts and revisions are not rewritten, and a change stays private until explicit publication. Published-only photo sourcing, sparse fallback, session suppression and deep-link bypass are unchanged.

The retained account-3/wedding-3 example remains intentionally disabled because it is already saved and published. To enable its intro, open the CMS, check **Photobooth opening**, save, then explicitly Republish. Its generic URL is `http://127.0.0.1:8000/w/shelby-cotton-rowan-greer-u22ptwni`; its editor is `http://127.0.0.1:8000/accounts/3/weddings/3/cms-preview`. The port-8000 retained profile is SQLite, not the uninitialized `wedding_rsvp` MySQL database. No data or credentials were copied or changed.

## Verification

Final focused command ran with process-only `APP_ENV=testing`, in-memory SQLite, empty `DB_URL`/`DATABASE_URL`, array cache/session, unique config/route caches and the approved temporary PHP extension scan path:

```powershell
php -d memory_limit=512M vendor/bin/phpunit --no-progress --stop-on-error --stop-on-failure --filter 'CmsEditorPreviewTest|CmsCustomerWorkflowTest|CmsPhotoboothOpeningTest|PersonalRsvpHttpTest|WeddingGuestManagementTest|WeddingAttendanceTest'
```

Result: **126 passed / 1,781 assertions**. The new-customer feature test registers a real test account, creates a wedding, reads/saves the fresh default, publishes it, checks generic presentation and guest privacy, issues a synthetic guest-scoped link through the existing service, and confirms the personal published page uses the same opening. Existing publication tests prove disabled saved snapshots remain disabled until explicit republish. Guest search coverage checks partial and exact-special-character matches, empty/no-result searches, all three invitation states, and withdrawn response retention.

`php vendor/bin/pint --format agent` on the changed PHP files, three PHP syntax checks and `git diff --check` pass. Current-source port-8000 GET for the retained wedding returns HTTP 200. The real browser automation harness was unavailable in this run (`QA_PLAYWRIGHT_MODULE` and `QA_BROWSER_EXECUTABLE` are unset); the updated customer journey browser test requires that harness for visual/manual-motion confirmation. No development MySQL write, migration, seed, reset, dependency change, commit or push occurred.

## Owner verification and remaining gates

1. Search the retained Guest Management page at `http://127.0.0.1:8000/accounts/3/weddings/3/guests` using active, withdrawn and all filters, special characters, an empty search and a no-result term.
2. Open the existing CMS URL above; confirm its saved Photobooth option remains off, turn it on, Save, then Republish. Confirm the generic public URL shows the intro once and that the wedding-scoped session suppresses repeat visits. Use a fresh private browser session on the authorized personal link to confirm the same intro appears before the personal greeting; direct RSVP/deep links remain available.
3. For a newly registered wedding, the fresh CMS document now starts enabled. Turn it off before publication to verify the opt-out path. No public page changes before explicit publication.

The existing example's published flag is still false pending owner action; no retained customer data was altered to manufacture a review state. The actual real-customer database/login cannot be verified against `wedding_rsvp` until its separate owner-approved initialization is resolved. Disposable InnoDB withdrawal races, production activation, device/security/release checks and the MySQL incident review remain unchanged.
