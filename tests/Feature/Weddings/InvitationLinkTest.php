<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\PublishedWedding;
use App\Services\WeddingHeroPhotoProcessor;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InvitationLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_generation_stores_only_unique_hash_and_ciphertext_and_keeps_links_out_of_list_props(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $result = $this->actingAs($owner)->postJson($this->action($wedding, $group, 'generate'), ['link_version' => 0])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->json();
        $token = basename($result['url']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/D', $token);
        $this->assertSame(['status' => 'active', 'version' => 1, 'url' => $result['url']], $result);
        $stored = DB::table('invitation_groups')->find($group->id);
        $this->assertSame(hash('sha256', $token), $stored->invitation_token_hash);
        $this->assertNotSame($token, $stored->invitation_token);
        $this->assertStringNotContainsString($token, $stored->invitation_token);
        $this->assertSame($token, Crypt::decryptString($stored->invitation_token));
        $this->assertArrayNotHasKey('invitation_token', $group->fresh()->toArray());
        $this->assertArrayNotHasKey('invitation_token_hash', $group->fresh()->toArray());
        $response = $this->get(route('accounts.weddings.guests.index', [$wedding->account_id, $wedding]))->assertOk()
            ->assertDontSee($token, false)->assertDontSee($stored->invitation_token_hash, false)->assertDontSee($stored->invitation_token, false);
        $response->assertInertia(fn (Assert $page) => $page->where('groups.data.0.link_status', 'active')->where('groups.data.0.link_version', 1));
        $other = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $otherResult = $this->postJson($this->action($wedding, $other, 'generate'), ['link_version' => 0])->assertOk()->json();
        $this->assertNotSame($result['url'], $otherResult['url']);
    }

    public function test_repeat_copy_and_generation_keep_the_same_active_link_and_stale_generation_is_rejected(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        $before = $group->fresh()->getAttributes();
        foreach (['copy', 'generate', 'copy'] as $action) {
            $this->postJson($this->action($wedding, $group, $action), ['link_version' => 1])->assertOk()->assertJsonPath('url', $url)->assertJsonPath('version', 1);
        }
        $this->postJson($this->action($wedding, $group, 'generate'), ['link_version' => 0])->assertUnprocessable()->assertJsonValidationErrors('invitation_link');
        $this->assertSame($before, $group->fresh()->getAttributes());
    }

    public function test_rotation_revocation_and_regeneration_invalidate_previous_tokens_and_reject_stale_actions(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $first = $this->issue($wedding, $group);
        $second = $this->postJson($this->action($wedding, $group, 'regenerate'), ['link_version' => 1])->assertOk()->assertJsonPath('version', 2)->json('url');
        $this->assertNotSame($first, $second);
        $this->get($first)->assertNotFound();
        $this->get($second)->assertOk();
        foreach (['copy', 'revoke', 'regenerate', 'generate'] as $action) {
            $this->postJson($this->action($wedding, $group, $action), ['link_version' => 1])->assertUnprocessable();
        }
        $this->postJson($this->action($wedding, $group, 'revoke'), ['link_version' => 2])->assertOk()->assertExactJson(['status' => 'revoked', 'version' => 3, 'url' => null]);
        $this->get($second)->assertNotFound();
        $this->assertNull($group->fresh()->invitation_token_hash);
        $this->assertNull($group->fresh()->getRawOriginal('invitation_token'));
        $this->postJson($this->action($wedding, $group, 'revoke'), ['link_version' => 3])->assertOk()->assertJsonPath('version', 3);
        foreach (['copy', 'generate'] as $action) {
            $this->postJson($this->action($wedding, $group, $action), ['link_version' => 3])->assertUnprocessable();
        }
        $third = $this->postJson($this->action($wedding, $group, 'regenerate'), ['link_version' => 3])->assertOk()->json('url');
        $this->get($third)->assertOk();
        $this->get($first)->assertNotFound();
        $this->get($second)->assertNotFound();
    }

    #[DataProvider('inactiveActions')]
    public function test_never_generated_groups_cannot_be_copied_revoked_or_rotated(string $action): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner)->postJson($this->action($wedding, $group, $action), ['link_version' => 0])->assertUnprocessable();
        $this->assertSame('not_generated', $group->fresh()->linkStatus());
    }

    public static function inactiveActions(): array
    {
        return [['copy'], ['revoke'], ['regenerate']];
    }

    #[DataProvider('deniedActors')]
    public function test_all_link_actions_require_an_authenticated_wedding_owner(string $actor): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        $this->app['auth']->forgetGuards();
        if ($actor !== 'anonymous') {
            $user = User::factory()->create();
            if ($actor === 'viewer') {
                $wedding->account->users()->attach($user, ['role' => 'viewer']);
            }
            $this->actingAs($user);
        }
        foreach (['generate', 'copy', 'revoke', 'regenerate'] as $action) {
            $this->postJson($this->action($wedding, $group, $action), ['link_version' => 1])
                ->assertStatus($actor === 'anonymous' ? 401 : 403)->assertDontSee(basename($url), false);
        }
        $this->assertSame(1, $group->fresh()->invitation_link_version);
    }

    public static function deniedActors(): array
    {
        return [['anonymous'], ['foreign user'], ['viewer']];
    }

    public function test_manipulated_nested_parents_and_request_fields_cannot_expose_or_change_links(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        [, $foreign, $foreignGroup] = $this->fixture();
        $sibling = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Pair', 'public_slug' => 'sibling']);
        $siblingGroup = InvitationGroup::factory()->for($sibling)->withGuests()->create();
        $this->actingAs($owner);
        foreach (['generate', 'copy', 'revoke', 'regenerate'] as $action) {
            foreach ([$foreignGroup, $siblingGroup] as $other) {
                $this->postJson($this->action($wedding, $other, $action), ['link_version' => 0])->assertNotFound();
            }
        }
        $this->postJson($this->action($wedding, $group, 'generate'), ['link_version' => 0, 'wedding_id' => $foreign->id, 'invitation_token' => 'injected'])
            ->assertUnprocessable()->assertJsonValidationErrors('invitation_link');
        $this->postJson($this->action($wedding, $group, 'generate'), [])->assertUnprocessable()->assertJsonValidationErrors('link_version');
        $this->assertSame(0, $group->fresh()->invitation_link_version);
        $this->assertNull($foreignGroup->fresh()->invitation_token_hash);
    }

    public function test_anonymous_invitation_shows_only_current_saved_group_members_and_published_facts_with_generic_metadata(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        $other = InvitationGroup::factory()->for($wedding)->withGuests()->create(['label' => 'Unrelated group']);
        $other->guests()->sole()->update(['name' => 'Unrelated guest']);
        $group->update(['label' => '<script>Family</script>']);
        $group->guests()->sole()->update(['name' => '<img src=x onerror=alert(1)>']);
        $this->app['auth']->forgetGuards();
        $response = $this->get($url)->assertOk()
            ->assertSee("You're invited", false)->assertSee('&lt;script&gt;Family&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>Family</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('Published Alex')->assertDontSee('Unrelated guest')->assertDontSee('Unrelated group')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        foreach (['private-contact@example.test', '+63 private phone', $owner->email, 'account_id', 'invitation_group_id', 'invitation_token', 'link_version', 'schema_version', 'hero_photo_path', 'Ziggy', basename($url)] as $private) {
            $response->assertDontSee($private, false);
        }
        $head = explode('</head>', $response->getContent())[0];
        $this->assertStringNotContainsString('Family', $head);
        $this->assertStringNotContainsString('onerror', $head);
        $this->assertStringContainsString('content="Wedding invitation"', $head);
        $group->update(['label' => 'Updated family']);
        $group->guests()->create(['name' => 'New saved member']);
        $this->get($url)->assertOk()->assertSee('Updated family')->assertSee('New saved member');
    }

    public function test_public_access_never_stores_the_bearer_url_in_the_session_or_sets_a_session_cookie(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        $this->app['auth']->forgetGuards();
        $this->withSession(['_previous' => ['url' => 'http://localhost/safe']]);
        $this->get($url)->assertOk()->assertCookieMissing(config('session.cookie'));
        $this->assertSame('http://localhost/safe', session()->previousUrl());
    }

    public function test_unpublishing_and_republishing_gate_the_link_without_changing_its_token_or_reading_drafts(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        $draft = $wedding->websiteDraft()->sole();
        $content = $draft->content;
        $content['introduction_message'] = 'PRIVATE UNSAVED PUBLICATION';
        $draft->update(['content' => $content, 'lock_version' => 2]);
        $wedding->update(['partner_one_name' => 'Unpublished name']);
        $this->get($url)->assertSee('Published Alex')->assertDontSee('PRIVATE UNSAVED PUBLICATION')->assertDontSee('Unpublished name');
        $publisher = app(WeddingPublication::class);
        $publisher->unpublish($wedding, 1);
        $this->get($url)->assertNotFound()->assertSee('This invitation is unavailable.')->assertDontSee(basename($url), false);
        $this->publish($wedding);
        $this->get($url)->assertOk()->assertSee('Unpublished name')->assertSee('PRIVATE UNSAVED PUBLICATION');
        $this->assertSame(1, $group->fresh()->invitation_link_version);
        DB::table('website_revisions')->where('id', $wedding->fresh()->published_revision_id)->update(['schema_version' => 999]);
        $this->get($url)->assertNotFound();
    }

    public function test_issued_and_revoked_groups_cannot_be_hard_deleted_but_never_issued_groups_can(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $never = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $this->actingAs($owner);
        $this->issue($wedding, $group);
        $delete = route('accounts.weddings.invitation-groups.destroy', [$wedding->account_id, $wedding, $group]);
        $this->deleteJson($delete)->assertUnprocessable()->assertJsonValidationErrors('invitation_link');
        $this->postJson($this->action($wedding, $group, 'revoke'), ['link_version' => 1])->assertOk();
        $this->deleteJson($delete)->assertUnprocessable();
        $this->assertModelExists($group);
        $this->assertSame(1, $group->guests()->count());
        $this->delete(route('accounts.weddings.invitation-groups.destroy', [$wedding->account_id, $wedding, $never]))->assertSessionHasNoErrors();
        $this->assertModelMissing($never);
    }

    public function test_invalid_and_malformed_tokens_return_identical_private_generic_404_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        foreach ([str_repeat('a', 64), str_repeat('b', 64), 'malformed'] as $token) {
            $response = $this->get('/invite/'.$token)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee($token, false);
            $this->assertSame('This invitation is unavailable.', $response->getContent());
        }
    }

    public function test_decryption_failure_is_understandable_and_regeneration_recovers_without_exposing_values(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        DB::table('invitation_groups')->where('id', $group->id)->update(['invitation_token' => 'corrupt-ciphertext']);
        $this->postJson($this->action($wedding, $group, 'copy'), ['link_version' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation_link' => 'This link could not be recovered.'])
            ->assertDontSee('corrupt-ciphertext', false)->assertDontSee(basename($url), false);
        $this->postJson($this->action($wedding, $group, 'regenerate'), ['link_version' => 1])->assertOk();
        $this->get($url)->assertNotFound();
    }

    public function test_failed_token_storage_rolls_back_and_keeps_the_old_link_usable(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        $before = $group->fresh()->getAttributes();
        InvitationGroup::updating(function (): void {
            throw new RuntimeException('Synthetic update failure');
        });
        $this->postJson($this->action($wedding, $group, 'regenerate'), ['link_version' => 1])->assertServerError()
            ->assertDontSee(basename($url), false)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, $group->fresh()->getAttributes());
        $this->get($url)->assertOk();
    }

    public function test_link_actions_require_csrf_outside_the_testing_environment(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->app->detectEnvironment(fn () => 'production');
        $this->actingAs($owner)->postJson($this->action($wedding, $group, 'generate'), ['link_version' => 0])->assertStatus(419);
        $this->assertSame(0, $group->fresh()->invitation_link_version);
    }

    public function test_public_access_is_ip_rate_limited_without_using_tokens_as_cache_keys(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.70']);
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->get('/invite/'.str_repeat('c', 64))->assertNotFound();
        }
        $this->get('/invite/'.str_repeat('d', 64))->assertStatus(429)->assertHeader('Retry-After')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee(str_repeat('d', 64), false);
    }

    public function test_configured_application_logs_redact_urls_tokens_nested_context_and_exception_details(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rsvp-link-log-');
        config(['logging.channels.single.path' => $path]);
        Log::forgetChannel('single');
        $token = str_repeat('e', 64);
        $url = 'https://example.test/invite/'.$token;
        try {
            Log::channel('single')->error('Request '.$url, ['token' => 'prefix'.$token.'suffix', 'nested' => ['url' => $url], 'exception' => new RuntimeException('Failure '.$url)]);
            $output = file_get_contents($path);
            $this->assertStringNotContainsString($token, $output);
            $this->assertStringContainsString('[redacted]', $output);
            $this->assertStringContainsString('RuntimeException', $output);
        } finally {
            Log::channel('single')->getLogger()->close();
            Log::forgetChannel('single');
            unlink($path);
        }
    }

    public function test_personalized_access_preserves_public_photo_and_private_draft_photo_rules(): void
    {
        Storage::fake('wedding_photos');
        [$owner, $wedding, $group] = $this->fixture();
        $photo = (string) Str::uuid();
        $path = 'accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/hero/'.$photo.'.webp';
        Storage::disk('wedding_photos')->put($path, app(WeddingHeroPhotoProcessor::class)->normalize(UploadedFile::fake()->image('photo.png', 40, 50)));
        $wedding->websiteDraft()->sole()->update(['hero_photo_disk' => 'wedding_photos', 'hero_photo_path' => $path]);
        $this->publish($wedding);
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        $this->app['auth']->forgetGuards();
        $photoUrl = route('public.weddings.photo', ['slug' => $wedding->public_slug, 'photo' => $photo]);
        $this->get($url)->assertOk()->assertSee($photoUrl, false);
        $this->get($photoUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('accounts.weddings.invitation.hero-photo', [$wedding->account_id, $wedding, $photo]))->assertRedirect(route('login'));
        $this->actingAs($owner)->postJson($this->action($wedding, $group, 'revoke'), ['link_version' => 1])->assertOk();
        $this->get($url)->assertNotFound();
        $this->get($photoUrl)->assertOk();
    }

    public function test_unexpected_public_errors_never_render_debug_details_or_credentials(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->actingAs($owner);
        $url = $this->issue($wedding, $group);
        config(['app.debug' => true]);
        $this->mock(PublishedWedding::class)->shouldReceive('render')->andThrow(new RuntimeException('Failure '.$url));
        $this->get($url)->assertServerError()->assertDontSee(basename($url), false)
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Private workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Published Alex', 'partner_two_name' => 'Published Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => 'links-'.fake()->uuid()]);
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 1, 'content' => WebsiteDraft::defaultContent()]);
        $this->publish($wedding);
        $group = InvitationGroup::factory()->for($wedding)->withGuests()->create(['label' => 'Santos Family', 'contact_email' => 'private-contact@example.test', 'contact_phone' => '+63 private phone']);
        $group->guests()->sole()->update(['name' => 'Named guest']);

        return [$owner, $wedding, $group];
    }

    private function publish(Wedding $wedding): void
    {
        $wedding = $wedding->fresh();
        $publisher = app(WeddingPublication::class);
        $summary = $publisher->summary($wedding);
        $publisher->publish($wedding, ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']]);
    }

    private function issue(Wedding $wedding, InvitationGroup $group): string
    {
        return $this->postJson($this->action($wedding, $group, 'generate'), ['link_version' => 0])->assertOk()->json('url');
    }

    private function action(Wedding $wedding, InvitationGroup $group, string $action): string
    {
        return route('accounts.weddings.invitation-links.update', [$wedding->account_id, $wedding, $group, $action]);
    }
}
