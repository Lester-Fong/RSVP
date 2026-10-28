<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Guest;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\WebsiteRevisionMedia;
use App\Models\Wedding;
use App\Services\CmsDraft;
use App\Services\CmsEditorPreview;
use App\Services\PersonalInvitationLink;
use App\Services\UploadWeddingImage;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CmsPublicationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_complete_source_and_all_media_are_retained_but_only_current_visible_display_is_public(): void
    {
        [$owner, $wedding, $source, $images] = $this->fixture();
        $guest = Guest::factory()->for(InvitationGroup::factory()->for($wedding))->create(['name' => 'Private Guest Sentinel']);
        $draft = $wedding->websiteDraft()->sole()->getAttributes();

        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();

        $revision = $wedding->websiteRevisions()->sole();
        $this->assertSame($source['content'], $revision->content);
        $this->assertSame('enchanted-editorial', $revision->template_key);
        $this->assertSame('Asia/Manila', $revision->wedding_facts['timezone']);
        $this->assertSame('2027-05-12T08:30:00Z', $revision->wedding_facts['wedding_instant_utc']);
        $this->assertSame($draft, $wedding->websiteDraft()->sole()->getAttributes());
        $this->assertEqualsCanonicalizing([$images['visible']['asset_id'], $images['hidden']['asset_id']], $revision->mediaAssets()->pluck('wedding_media_assets.id')->all());
        $page = $this->get($this->publicUrl($wedding))->assertOk()->assertSee('Snapshot story')->assertSee('Visible moment')
            ->assertSee('public-enchanted', false)->assertSee('"timeZone":"Asia\\/Manila"', false)
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        foreach ([$owner->email, $guest->name, $images['hidden']['asset_id'], $images['unused']['asset_id'], 'Hidden moment', 'Secret caption',
            'Private Ceremony Sentinel', 'wedding_media', 'asset_id', 'published_revision_id', 'account_id', 'rsvp_version', 'csrf', 'auth', 'Ziggy'] as $private) {
            $page->assertDontSee($private, false);
        }
        $this->app['auth']->forgetGuards();
        $this->assertSame($page->getContent(), $this->get($this->publicUrl($wedding))->assertOk()->getContent());
        $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertOk()->assertHeader('Content-Type', 'image/webp');
        foreach (['hidden', 'unused'] as $kind) {
            $this->get($this->mediaUrl($wedding, $images[$kind]['asset_id']))->assertNotFound();
        }
    }

    public function test_draft_and_fact_edits_leave_the_live_snapshot_and_media_unchanged_until_republish(): void
    {
        [$owner, $wedding, $source, $images] = $this->fixture();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $old = $wedding->websiteRevisions()->sole();
        $before = $old->getAttributes();
        $source['content']['sections'][3]['data']['paragraphs'] = ['Changed saved narrative'];
        $source['content']['photo_slots']['hero'] = $images['unused'];
        $source['content']['sections'][3]['data']['milestones'] = [];
        $source['content']['sections'][4]['data']['items'] = [];
        $wedding->update(['timezone' => 'America/New_York', 'partner_one_name' => 'Changed Alex']);
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);

        $this->get($this->publicUrl($wedding))->assertOk()->assertSee('Snapshot story')->assertSee('Alex')->assertDontSee('Changed saved narrative')->assertDontSee('Changed Alex');
        $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertOk();
        $this->get($this->mediaUrl($wedding, $images['unused']['asset_id']))->assertNotFound();
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();

        $this->get($this->publicUrl($wedding))->assertOk()->assertSee('Changed saved narrative')->assertSee('Changed Alex')->assertSee('America\\/New_York', false);
        $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertNotFound();
        $this->get($this->mediaUrl($wedding, $images['unused']['asset_id']))->assertOk();
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->assertSame(2, $wedding->fresh()->publication_version);
        $this->assertSame('2027-05-12T20:30:00Z', $wedding->websiteRevisions()->latest('revision_number')->first()->wedding_facts['wedding_instant_utc']);
        Storage::disk('wedding_media')->assertExists('weddings/'.$wedding->id.'/images/'.$images['visible']['asset_id'].'.webp');
    }

    #[DataProvider('orderedLists')]
    public function test_reordering_alone_changes_the_fingerprint_and_publishes_an_independent_order(string $list): void
    {
        [$owner, $wedding, $source] = $this->fixture();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $old = $wedding->websiteRevisions()->sole();
        $before = $old->content;
        if ($list === 'sections') {
            $source['content']['sections'] = array_reverse($source['content']['sections']);
        } else {
            $source['content']['sections'][4]['data']['items'] = array_reverse($source['content']['sections'][4]['data']['items']);
        }
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);
        $this->assertTrue(app(WeddingPublication::class)->summary($wedding->fresh())['hasChanges']);
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->assertSame(2, $wedding->websiteRevisions()->count());
        $this->assertSame($source['content'], $wedding->fresh()->publishedRevision->content);
        $this->assertSame($before, $old->fresh()->content);
    }

    public static function orderedLists(): array
    {
        return [['sections'], ['photographs']];
    }

    public function test_unpublish_denies_page_and_media_and_republish_preserves_retained_history(): void
    {
        [$owner, $wedding, , $images] = $this->fixture();
        $payload = $this->payload($wedding);
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $payload)->assertOk();
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->assertDatabaseCount('website_revisions', 1);
        $this->postJson($this->publishUrl($wedding), $payload)->assertUnprocessable();
        $this->postJson($this->unpublishUrl($wedding), ['publication_version' => 1])->assertRedirect();
        $this->get($this->publicUrl($wedding))->assertNotFound();
        $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertNotFound();
        $this->assertDatabaseCount('website_revisions', 1);
        $this->assertDatabaseCount('website_revision_media', 2);
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->postJson($this->unpublishUrl($wedding), ['publication_version' => 1])->assertUnprocessable();
        $this->get($this->publicUrl($wedding))->assertOk();
        $this->assertDatabaseCount('website_revisions', 2);
    }

    public function test_stale_draft_facts_and_publication_tokens_cannot_publish_newer_unreviewed_data(): void
    {
        [$owner, $wedding, $source] = $this->fixture(false);
        $old = $this->payload($wedding);
        $source['content']['sections'][3]['data']['paragraphs'] = ['New draft'];
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $old)->assertUnprocessable()->assertJsonValidationErrors('publication');
        $facts = $this->payload($wedding);
        $wedding->update(['timezone' => 'Asia/Tokyo']);
        $this->postJson($this->publishUrl($wedding), $facts)->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertNull($wedding->fresh()->published_revision_id);
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_other_accounts_viewers_anonymous_and_wrong_nested_routes_cannot_publish_or_unpublish(): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $payload = $this->payload($wedding);
        $this->postJson($this->publishUrl($wedding), $payload)->assertUnauthorized();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson($this->publishUrl($wedding), $payload)->assertForbidden();
        $wedding->account->users()->attach($other, ['role' => 'viewer']);
        $this->postJson($this->publishUrl($wedding), $payload)->assertForbidden();
        $account = Account::create(['name' => 'Wrong account']);
        $this->actingAs($owner)->postJson(route('accounts.weddings.publish', [$account, $wedding]), $payload)->assertNotFound();
        $this->postJson($this->publishUrl($wedding), $payload)->assertOk();
        $this->actingAs($other)->postJson($this->unpublishUrl($wedding), ['publication_version' => 1])->assertForbidden();
        $this->get($this->publicUrl($wedding))->assertOk();
    }

    #[DataProvider('activationCases')]
    public function test_publishing_requires_both_flags_and_explicit_staging_authorization(
        mixed $flag, mixed $preview, mixed $stagingAuthorization, string $environment, bool $allowed): void
    {
        [$owner, $wedding] = $this->fixture(false);
        config(['cms_editor.publishing_enabled' => $flag, 'cms_editor.preview_enabled' => $preview,
            'cms_editor.staging_authorized' => $stagingAuthorization]);
        if ($environment !== 'testing') {
            $this->withoutMiddleware(ValidateCsrfToken::class);
        }
        $this->app->instance('env', $environment);
        try {
            $response = $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding));
            if ($allowed) {
                $response->assertOk();
                $this->get($this->publicUrl($wedding))->assertOk();
                $this->assertDatabaseCount('website_revisions', 1);
            } else {
                $response->assertConflict();
                $this->get($this->publicUrl($wedding))->assertNotFound();
                $this->assertDatabaseCount('website_revisions', 0);
            }
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public static function activationCases(): array
    {
        return [
            'local unchanged' => [true, true, false, 'local', true],
            'testing unchanged' => [true, true, false, 'testing', true],
            'publishing off' => [false, true, true, 'staging', false],
            'publishing string' => ['true', true, true, 'staging', false],
            'publishing numeric' => [1, true, true, 'staging', false],
            'publishing missing' => [null, true, true, 'staging', false],
            'preview off' => [true, false, true, 'staging', false],
            'staging unauthorized' => [true, true, false, 'staging', false],
            'staging authorization malformed' => [true, true, 'true', 'staging', false],
            'staging authorized' => [true, true, true, 'staging', true],
            'production still denied' => [true, true, true, 'production', false],
        ];
    }

    public function test_published_v2_page_and_media_obey_staging_gate_without_changing_the_snapshot(): void
    {
        [$owner, $wedding, , $images] = $this->fixture();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $revisionId = $wedding->fresh()->published_revision_id;
        $this->app->instance('env', 'staging');
        try {
            $this->get($this->publicUrl($wedding))->assertNotFound();
            $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertNotFound();
            config(['cms_editor.staging_authorized' => true]);
            $this->get($this->publicUrl($wedding))->assertOk()->assertDontSee('Secret caption');
            $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertOk()->assertHeader('Content-Type', 'image/webp');
            $this->get($this->mediaUrl($wedding, $images['hidden']['asset_id']))->assertNotFound();
            $this->app->instance('env', 'production');
            $this->get($this->publicUrl($wedding))->assertNotFound();
            $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertNotFound();
            $this->assertSame($revisionId, $wedding->fresh()->published_revision_id);
            $this->assertDatabaseCount('website_revisions', 1);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    #[DataProvider('wallTimes')]
    public function test_publication_snapshots_only_an_unambiguous_canonical_instant(string $zone, ?string $time, string $date, ?string $instant, bool $accepted): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $wedding->update(['timezone' => $zone, 'wedding_time' => $time, 'wedding_date' => $date]);
        $response = $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding));
        if ($accepted) {
            $response->assertOk();
            $this->assertSame($instant, $wedding->websiteRevisions()->sole()->wedding_facts['wedding_instant_utc']);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('publication');
            $this->assertDatabaseCount('website_revisions', 0);
        }
    }

    public static function wallTimes(): array
    {
        return [
            ['Asia/Manila', null, '2027-05-12', null, true],
            ['UTC', '00:00', '2027-05-12', '2027-05-12T00:00:00Z', true],
            ['Asia/Kathmandu', '00:00', '2027-05-12', '2027-05-11T18:15:00Z', true],
            ['America/New_York', '01:30', '2026-11-01', null, false],
            ['America/New_York', '02:30', '2027-03-14', null, false],
            ['America/New_York', '03:30', '2027-03-14', '2027-03-14T07:30:00Z', true],
            ['Not/AZone', '12:00', '2027-05-12', null, false],
        ];
    }

    public function test_unknown_template_version_and_invalid_full_hidden_source_are_rejected_without_mutation(): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $draft = $wedding->websiteDraft()->sole();
        $draft->update(['template_version' => 9]);
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable();
        $content = $draft->content;
        $content['sections'][3]['enabled'] = false;
        $content['sections'][3]['data']['heading'] = '<unsafe>';
        $draft->update(['template_version' => 1, 'content' => $content]);
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable();
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertSame($content, $draft->fresh()->content);
    }

    public function test_older_v2_source_without_slots_or_slideshow_publishes_without_being_rewritten(): void
    {
        [$owner, $wedding, $source] = $this->fixture(false);
        unset($source['content']['photo_slots']);
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->assertSame($source['content'], $wedding->websiteRevisions()->sole()->content);
        $this->get($this->publicUrl($wedding))->assertOk();
    }

    public function test_failed_media_binding_rolls_back_the_new_revision_and_keeps_the_prior_live_website(): void
    {
        [$owner, $wedding, $source] = $this->fixture();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $pointer = $wedding->fresh()->published_revision_id;
        $source['content']['sections'][3]['data']['paragraphs'] = ['Must not appear after failure'];
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);
        $event = 'eloquent.created: '.WebsiteRevisionMedia::class;
        Event::listen($event, fn () => throw new RuntimeException('Synthetic binding failure'));
        try {
            $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertStatus(500);
        } finally {
            Event::forget($event);
        }
        $this->assertSame($pointer, $wedding->fresh()->published_revision_id);
        $this->assertSame(1, $wedding->fresh()->publication_version);
        $this->assertDatabaseCount('website_revisions', 1);
        $this->assertDatabaseCount('website_revision_media', 2);
        $this->get($this->publicUrl($wedding))->assertOk()->assertSee('Snapshot story')->assertDontSee('Must not appear after failure');
    }

    public function test_missing_hidden_media_and_cross_wedding_references_cannot_be_published(): void
    {
        [$owner, $wedding, $source, $images] = $this->fixture();
        $disk = Storage::disk('wedding_media');
        $disk->delete('weddings/'.$wedding->id.'/images/'.$images['hidden']['asset_id'].'.webp');
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable();
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Couple', 'public_slug' => Str::uuid()]);
        $asset = app(UploadWeddingImage::class)->upload($owner, $other, UploadedFile::fake()->image('foreign.png', 32, 32));
        $source['content']['photo_slots']['details']['asset_id'] = $asset['id'];
        $wedding->websiteDraft()->sole()->update(['content' => $source['content']]);
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable();
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_public_media_is_cross_wedding_scoped_and_corrupt_bytes_fail_safely(): void
    {
        [$owner, $wedding, , $images] = $this->fixture();
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Couple', 'public_slug' => Str::uuid()]);
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->get($this->mediaUrl($other, $images['visible']['asset_id']))->assertNotFound();
        Storage::disk('wedding_media')->put('weddings/'.$wedding->id.'/images/'.$images['visible']['asset_id'].'.webp', 'corrupt');
        $this->get($this->mediaUrl($wedding, $images['visible']['asset_id']))->assertNotFound();
        $this->get($this->publicUrl($wedding))->assertOk();
    }

    public function test_active_personal_credentials_survive_enchanted_publication_without_revoking_anyone(): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $guest = Guest::factory()->for(InvitationGroup::factory()->for($wedding))->create();
        $link = GuestInvitationLink::factory()->for($guest)->withSyntheticToken(str_repeat('a', 64))->create();
        $before = $link->fresh()->getAttributes();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->assertSame($before, $link->fresh()->getAttributes());
        $this->assertDatabaseCount('website_revisions', 1);
    }

    public function test_enchanted_publication_supports_personal_credentials_reads_and_explicit_revocation(): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $group = InvitationGroup::factory()->for($wedding)->create();
        $guest = Guest::factory()->for($group)->create();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $credentialUrl = route('accounts.weddings.rsvp-access.credential', [$wedding->account_id, $wedding, $group, $guest, 'generate']);
        $url = $this->postJson($credentialUrl, ['expectedVersion' => 0, 'expectedAccessVersion' => 0])->assertOk()->json('url');
        $this->assertDatabaseCount('guest_invitation_links', 1);
        $this->get($url)->assertOk();
        $this->postJson($url.'/responses', [])->assertUnprocessable();
        app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guest, 'revoke', 1, 1);
        $this->get($url)->assertNotFound();
        $this->assertNull($guest->invitationLink()->sole()->token_hash);
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_owner_overview_exposes_the_same_controlled_publication_workflow_and_cms_review_links(): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $this->actingAs($owner)->get(route('accounts.weddings.show', [$wedding->account_id, $wedding]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('publication.cmsV2', true)->where('publication.supportedDraft', true)
                ->where('cmsEditorUrl', route('accounts.weddings.cms-preview.show', [$wedding->account_id, $wedding], false)));
        $this->get(route('accounts.weddings.invitation.edit', [$wedding->account_id, $wedding]))->assertRedirect(route('accounts.weddings.cms-preview.show', [$wedding->account_id, $wedding]));
        $this->get(route('accounts.weddings.preview', [$wedding->account_id, $wedding]))->assertRedirect(route('accounts.weddings.cms-preview.saved-preview', [$wedding->account_id, $wedding]));
    }

    public function test_unsafe_canonical_facts_are_escaped_and_unknown_published_versions_fail_closed(): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $name = '<img src=x onerror=alert(1)>';
        $wedding->update(['partner_one_name' => $name]);
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->get($this->publicUrl($wedding))->assertOk()->assertSee($name)->assertDontSee($name, false);
        $revision = $wedding->websiteRevisions()->sole();
        DB::table('website_revisions')->where('id', $revision->id)->update(['template_version' => 99]);
        $this->get($this->publicUrl($wedding))->assertNotFound();
    }

    public function test_editor_can_publish_saved_drafts_as_json_without_redirecting_or_bypassing_stale_versions(): void
    {
        [$owner, $wedding, $source] = $this->fixture(false);
        $editor = route('accounts.weddings.cms-preview.show', [$wedding->account_id, $wedding]);
        $this->actingAs($owner)->get($editor)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('publication.draftVersion', 1)->where('publication.published', false)
            ->where('endpoints.publish', route('accounts.weddings.publish', [$wedding->account_id, $wedding], false)));
        $stale = $this->payload($wedding);
        $source['content']['sections'][3]['data']['paragraphs'] = ['Saved from the editor'];
        $this->putJson($editor.'/draft', ['document' => $source, 'draft_version' => 1])->assertOk()
            ->assertJsonPath('publication.draftVersion', 2)->assertJsonPath('publication.published', false);
        $this->postJson($this->publishUrl($wedding), $stale)->assertUnprocessable();
        $this->assertDatabaseCount('website_revisions', 0);
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk()
            ->assertJsonPath('publication.published', true)->assertJsonPath('publication.hasChanges', false)
            ->assertJsonPath('publication.publicationVersion', 1);
        $source['content']['sections'][3]['data']['paragraphs'] = ['Private newer edit'];
        $this->putJson($editor.'/draft', ['document' => $source, 'draft_version' => 2])->assertOk()
            ->assertJsonPath('publication.hasChanges', true)->assertJsonPath('publication.publicationVersion', 1);
        $this->get($this->publicUrl($wedding))->assertSee('Saved from the editor')->assertDontSee('Private newer edit');
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk()->assertJsonPath('publication.publicationVersion', 2);
        $this->get($this->publicUrl($wedding))->assertSee('Private newer edit');
    }

    public function test_empty_saved_and_published_websites_do_not_invent_photographs_or_media(): void
    {
        [$owner, $wedding] = $this->fixture(false);
        $editor = route('accounts.weddings.cms-preview.show', [$wedding->account_id, $wedding]);
        $this->actingAs($owner)->get($editor)->assertInertia(fn (Assert $page) => $page
            ->has('initialImages', 0)->has('initialDocument.content.sections.4.data.items', 0)
            ->where('initialDocument.content.photo_slots.hero', null));
        $this->getJson($editor.'/images')->assertOk()->assertJsonCount(0, 'images');
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->get($this->publicUrl($wedding))->assertOk()->assertDontSee('<img', false)
            ->assertDontSee('Couple photograph unavailable')->assertDontSee('A moment to remember');
        $this->assertDatabaseCount('wedding_media_assets', 0);
        $this->assertDatabaseCount('website_revision_media', 0);
    }

    public function test_countdown_saves_publishes_from_snapshot_facts_and_hides_only_after_republication(): void
    {
        [$owner, $wedding, $source] = $this->fixture(false);
        $countdown = array_search('countdown', array_column($source['content']['sections'], 'type'), true);
        $this->assertIsInt($countdown);
        $source['content']['sections'][$countdown]['data'] = ['heading' => 'A day until forever', 'subtitle' => 'Our favorite day is getting closer.'];
        $this->assertArrayNotHasKey('wedding_date', $source['content']['sections'][$countdown]['data']);
        $this->assertArrayNotHasKey('wedding_time', $source['content']['sections'][$countdown]['data']);
        $this->assertArrayNotHasKey('timezone', $source['content']['sections'][$countdown]['data']);

        $editor = route('accounts.weddings.cms-preview.show', [$wedding->account_id, $wedding]);
        $this->actingAs($owner)->putJson($editor.'/draft', ['document' => $source, 'draft_version' => 1])->assertOk();
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $revision = $wedding->fresh()->publishedRevision;
        $this->assertSame($source['content'], $revision->content);
        $this->assertSame('2027-05-12', $revision->wedding_facts['wedding_date']);
        $this->assertSame('16:30', $revision->wedding_facts['wedding_time']);
        $this->assertSame('Asia/Manila', $revision->wedding_facts['timezone']);

        $html = $this->get($this->publicUrl($wedding))->assertOk()
            ->assertSee('A day until forever')->assertSee('Our favorite day is getting closer.')
            ->assertSee('section:countdown')->assertSee('12 May 2027')->assertSee('4:30 PM')->getContent();
        $this->assertStringNotContainsString('<dl', $html);

        $wedding->update(['wedding_date' => '2028-02-20', 'wedding_time' => '09:15', 'timezone' => 'Asia/Kathmandu']);
        $this->get($this->publicUrl($wedding))->assertSee('A day until forever')->assertSee('12 May 2027')->assertDontSee('20 February 2028');

        $source['content']['sections'][$countdown]['data']['heading'] = 'Our celebration is near';
        $this->putJson($editor.'/draft', ['document' => $source, 'draft_version' => 2])->assertOk();
        $this->get($this->publicUrl($wedding))->assertSee('A day until forever')->assertDontSee('Our celebration is near')->assertSee('12 May 2027');
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->get($this->publicUrl($wedding))->assertSee('Our celebration is near')->assertSee('20 February 2028')->assertSee('9:15 AM');

        $source['content']['sections'][$countdown]['enabled'] = false;
        $this->putJson($editor.'/draft', ['document' => $source, 'draft_version' => 3])->assertOk();
        $this->get($this->publicUrl($wedding))->assertSee('Our celebration is near')->assertSee('section:countdown');
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->get($this->publicUrl($wedding))->assertDontSee('Our celebration is near')->assertDontSee('section:countdown');
        $this->assertSame('Our celebration is near', $wedding->websiteDraft()->sole()->content['sections'][$countdown]['data']['heading']);
        $this->assertFalse($wedding->websiteDraft()->sole()->content['sections'][$countdown]['enabled']);
    }

    public function test_guest_guide_blocks_publish_from_the_saved_snapshot_and_hidden_copy_stays_private(): void
    {
        [$owner, $wedding, $source] = $this->fixture(false);
        $index = array_search('guest_guide', array_column($source['content']['sections'], 'type'), true);
        $this->assertIsInt($index);
        $guide = &$source['content']['sections'][$index]['data'];
        $guide['attire'] = ['enabled' => true, 'dress_code' => 'Garden formal', 'description' => 'Soft colors are welcome.',
            'swatches' => [['id' => (string) Str::uuid(), 'label' => 'Terracotta', 'color' => '#A36E59']]];
        $guide['gifts']['message'] = 'Hidden gift preference sentinel';
        $guide['faqs']['enabled'] = true;
        $guide['faqs']['items'][2]['answer'] = 'Use your personal invitation link to respond.';
        $editor = route('accounts.weddings.cms-preview.show', [$wedding->account_id, $wedding]);
        $this->actingAs($owner)->putJson($editor.'/draft', ['document' => $source, 'draft_version' => 1])->assertOk();
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $revision = $wedding->fresh()->publishedRevision;
        $this->assertSame($source['content'], $revision->content);

        $html = $this->get($this->publicUrl($wedding))->assertOk()
            ->assertSee('section:guest-guide')->assertSee('Garden formal')->assertSee('Terracotta')->assertSee('#A36E59')
            ->assertSee('How do I RSVP?')->assertSee('personal invitation link shared with you')
            ->assertDontSee('Hidden gift preference sentinel')->getContent();
        $this->assertStringNotContainsString('<a href="#rsvp">Open your personal RSVP', $html);

        $source['content']['sections'][$index]['data']['gifts']['enabled'] = true;
        $source['content']['sections'][$index]['data']['gifts']['heading'] = 'A note from us';
        $this->putJson($editor.'/draft', ['document' => $source, 'draft_version' => 2])->assertOk();
        $this->get($this->publicUrl($wedding))->assertDontSee('A note from us');
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertOk();
        $this->get($this->publicUrl($wedding))->assertSee('A note from us')->assertSee('Hidden gift preference sentinel');
    }

    private function fixture(bool $photos = true): array
    {
        config(['cms_editor.preview_enabled' => true, 'cms_editor.publishing_enabled' => true]);
        $this->withoutVite();
        Storage::fake('wedding_media');
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Private Owner Workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'wedding_date' => '2027-05-12',
            'wedding_time' => '16:30', 'timezone' => 'Asia/Manila', 'ceremony_venue' => 'Private Ceremony Sentinel', 'public_slug' => Str::uuid()]);
        $source = CmsEditorPreview::document();
        $source['content']['sections'][1]['enabled'] = false;
        $source['content']['sections'][3]['data']['paragraphs'] = ['Snapshot story'];
        $source['content']['sections'][7]['data']['message'] = 'Please contact the couple to RSVP.';
        $images = [];
        if ($photos) {
            foreach (['visible', 'hidden', 'unused'] as $kind) {
                $asset = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image($kind.'.png', 64, 96));
                $images[$kind] = ['asset_id' => $asset['id'], 'alt' => 'Wedding photograph', 'focal' => 'top', 'crop' => 'natural', 'frame' => 'fit'];
            }
            $source['content']['photo_slots']['hero'] = $images['visible'];
            $source['content']['photo_slots']['details'] = $images['hidden'];
            foreach (['visible', 'hidden'] as $kind) {
                $source['content']['sections'][3]['data']['milestones'][] = ['id' => (string) Str::uuid(), 'enabled' => $kind === 'visible',
                    'title' => ucfirst($kind).' moment', 'photo' => $images[$kind]];
                $source['content']['sections'][4]['data']['items'][] = ['id' => (string) Str::uuid(), 'enabled' => $kind === 'visible',
                    'image' => $images[$kind], 'caption' => $kind === 'visible' ? 'Public caption' : 'Secret caption'];
            }
        }
        app(CmsDraft::class)->save($owner, $wedding, $source, 0);

        return [$owner, $wedding, $source, $images];
    }

    private function payload(Wedding $wedding): array
    {
        $summary = app(WeddingPublication::class)->summary($wedding->fresh());

        return ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']];
    }

    private function publishUrl(Wedding $wedding): string
    {
        return route('accounts.weddings.publish', [$wedding->account_id, $wedding]);
    }

    private function unpublishUrl(Wedding $wedding): string
    {
        return route('accounts.weddings.unpublish', [$wedding->account_id, $wedding]);
    }

    private function publicUrl(Wedding $wedding): string
    {
        return route('public.weddings.show', ['slug' => $wedding->public_slug]);
    }

    private function mediaUrl(Wedding $wedding, string $asset): string
    {
        return route('public.weddings.media', ['slug' => $wedding->public_slug, 'asset' => $asset]);
    }
}
