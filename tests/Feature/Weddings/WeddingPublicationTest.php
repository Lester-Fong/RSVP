<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use App\Services\WeddingHeroPhotoProcessor;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WeddingPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_publishes_an_independent_snapshot_and_public_html_contains_only_public_data(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $draft = $wedding->websiteDraft()->sole()->getAttributes();
        $this->actingAs($owner)->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors()->assertRedirect();
        $revision = $wedding->websiteRevisions()->sole();
        $this->assertSame($wedding->presentationData(), $revision->wedding_facts);
        $this->assertSame(WebsiteDraft::defaultContent(), $revision->content);
        $this->assertSame(1, $revision->revision_number);
        $this->assertSame(1, $wedding->fresh()->publication_version);
        $this->assertSame($draft, $wedding->websiteDraft()->sole()->getAttributes());
        $signedIn = $this->get($this->publicUrl($wedding))->assertOk()->assertSee('<h1', false)->assertSee('Published Alex')
            ->assertSee('property="og:title"', false)->assertSee('Wednesday, May 12, 2027')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Cache-Control', 'no-store, private');
        foreach ([$owner->email, $wedding->account->name, 'account_id', 'wedding_id', 'schema_version', 'lock_version', 'fingerprint', 'hero_photo_disk', 'hero_photo_path', 'auth', 'Ziggy'] as $private) {
            $signedIn->assertDontSee($private, false);
        }
        $this->app['auth']->forgetGuards();
        $anonymous = $this->get($this->publicUrl($wedding))->assertOk();
        $this->assertSame($signedIn->getContent(), $anonymous->getContent());
    }

    public function test_saved_draft_is_private_until_explicitly_published(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->get($this->publicUrl($wedding))->assertNotFound();
        $this->get(route('accounts.weddings.invitation.edit', [$wedding->account_id, $wedding]))->assertRedirect(route('login'));
        $this->actingAs($owner)->get($this->publicUrl($wedding))->assertNotFound();
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_editing_facts_and_draft_then_republishing_never_mutates_the_old_snapshot(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $original = $wedding->websiteRevisions()->sole();
        $before = $original->getAttributes();
        $draft = $wedding->websiteDraft()->sole();
        $content = $draft->content;
        $content['introduction_message'] = 'New saved draft message';
        $content['sections']['reception'] = false;
        $draft->update(['content' => $content, 'lock_version' => 2]);
        $wedding->update(['partner_one_name' => 'Updated name', 'wedding_date' => '2028-06-10']);

        $this->get($this->publicUrl($wedding))->assertOk()->assertSee('Published Alex')->assertDontSee('Updated name')->assertDontSee('New saved draft message');
        $this->get(route('accounts.weddings.show', [$wedding->account_id, $wedding]))->assertInertia(fn (Assert $page) => $page->where('publication.hasChanges', true));
        $this->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $this->get($this->publicUrl($wedding))->assertOk()->assertSee('Updated name')->assertSee('New saved draft message');
        $this->assertSame($before, $original->fresh()->getAttributes());
        $this->assertDatabaseCount('website_revisions', 2);
        $this->assertSame(2, $wedding->fresh()->publication_version);
    }

    public function test_unpublish_retains_draft_and_history_and_stale_requests_cannot_restore_or_remove_newer_publication(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $payload = $this->payload($wedding);
        $this->actingAs($owner)->post($this->publishUrl($wedding), $payload)->assertSessionHasNoErrors();
        $this->postJson($this->publishUrl($wedding), $payload)->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertDatabaseCount('website_revisions', 1);
        $this->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('website_revisions', 1);
        $this->assertSame(1, $wedding->fresh()->publication_version);
        $before = $wedding->websiteDraft()->sole()->getAttributes();
        $this->post($this->unpublishUrl($wedding), ['publication_version' => 1])->assertSessionHasNoErrors();
        $this->get($this->publicUrl($wedding))->assertNotFound();
        $this->postJson($this->publishUrl($wedding), $payload)->assertUnprocessable();
        $this->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $this->postJson($this->unpublishUrl($wedding), ['publication_version' => 1])->assertUnprocessable();
        $this->get($this->publicUrl($wedding))->assertOk();
        $this->assertSame($before, $wedding->websiteDraft()->sole()->getAttributes());
        $this->assertDatabaseCount('website_revisions', 2);
        $this->assertSame(3, $wedding->fresh()->publication_version);
    }

    public function test_anonymous_other_accounts_viewers_and_wrong_nested_routes_cannot_publish_or_unpublish(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        [$stranger, $other] = $this->ownedWedding();
        $before = $wedding->fresh()->getAttributes();
        $payload = $this->payload($wedding);
        foreach ([$this->publishUrl($wedding), $this->unpublishUrl($wedding)] as $url) {
            $this->post($url, $payload)->assertRedirect(route('login'));
        }
        $this->actingAs($stranger);
        foreach ([$this->publishUrl($wedding), $this->unpublishUrl($wedding)] as $url) {
            $this->post($url, $payload)->assertForbidden();
        }
        $wedding->account->users()->attach($stranger, ['role' => 'viewer']);
        foreach ([$this->publishUrl($wedding), $this->unpublishUrl($wedding)] as $url) {
            $this->post($url, $payload)->assertForbidden();
        }
        $this->actingAs($owner);
        foreach (['publish', 'unpublish'] as $action) {
            $this->post(route('accounts.weddings.'.$action, [$other->account_id, $wedding]), $payload)->assertNotFound();
        }
        $this->assertSame($before, $wedding->fresh()->getAttributes());
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_other_accounts_cannot_replace_or_unpublish_an_existing_publication(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        [$stranger] = $this->ownedWedding();
        $this->actingAs($owner)->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $before = $wedding->fresh()->getAttributes();
        $this->actingAs($stranger)->post($this->publishUrl($wedding), $this->payload($wedding))->assertForbidden();
        $this->post($this->unpublishUrl($wedding), ['publication_version' => 1])->assertForbidden();
        $this->get($this->publicUrl($wedding))->assertOk();
        $this->assertSame($before, $wedding->fresh()->getAttributes());
        $this->assertDatabaseCount('website_revisions', 1);
    }

    public function test_client_supplied_pointers_or_media_cannot_bypass_publication_validation(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), [...$this->payload($wedding), 'published_revision_id' => 99, 'hero_photo_path' => '../private.webp'])
            ->assertUnprocessable()->assertJsonValidationErrors(['published_revision_id', 'hero_photo_path']);
        $this->postJson($this->unpublishUrl($wedding), ['publication_version' => 0, 'account_id' => 99])
            ->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->assertNull($wedding->fresh()->published_revision_id);
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_publication_requires_csrf_protection_outside_the_testing_environment(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->app['env'] = 'production';
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertStatus(419);
        $this->postJson($this->unpublishUrl($wedding), ['publication_version' => 0])->assertStatus(419);
        $this->assertDatabaseCount('website_revisions', 0);
    }

    #[DataProvider('missingFields')]
    public function test_required_wedding_facts_are_checked_at_publication(string $field, mixed $value): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $wedding->update([$field => $value]);
        $before = $wedding->websiteDraft()->sole()->getAttributes();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable()->assertJsonValidationErrors('wedding.'.$field);
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertSame($before, $wedding->websiteDraft()->sole()->getAttributes());
        $this->assertSame(0, $wedding->fresh()->publication_version);
    }

    public static function missingFields(): array
    {
        return [['partner_one_name', ''], ['partner_two_name', ''], ['wedding_date', null]];
    }

    public function test_unsaved_unsupported_and_invalid_stored_content_cannot_be_published(): void
    {
        [$owner, $wedding] = $this->ownedWedding(false);
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable()->assertJsonValidationErrors('publication');
        $draft = WebsiteDraft::factory()->for($wedding)->create(['schema_version' => 999]);
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertConflict();
        $draft->update(['schema_version' => 1, 'content' => ['introduction_message' => '<script>unsafe</script>', 'closing_message' => 'Closing', 'sections' => ['ceremony' => true, 'reception' => true, 'closing' => '1']]]);
        $before = $draft->fresh()->getAttributes();
        $this->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable()->assertJsonValidationErrors(['content.introduction_message', 'content.sections.closing']);
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->assertDatabaseCount('website_revisions', 0);
    }

    #[DataProvider('changedSource')]
    public function test_changes_since_review_reject_publication_without_touching_the_saved_draft(string $source): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $payload = $this->payload($wedding);
        if ($source === 'facts') {
            $wedding->update(['ceremony_venue' => 'A changed venue']);
        } else {
            $wedding->websiteDraft()->update(['lock_version' => 2]);
        }
        $before = $wedding->websiteDraft()->sole()->getAttributes();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $payload)->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertSame($before, $wedding->websiteDraft()->sole()->getAttributes());
    }

    public static function changedSource(): array
    {
        return [['facts'], ['draft']];
    }

    public function test_snapshot_photo_survives_draft_replacement_removal_unpublish_and_pruning(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner);
        $this->savePhoto($wedding);
        $path = $wedding->websiteDraft()->sole()->hero_photo_path;
        $photo = pathinfo($path, PATHINFO_FILENAME);
        $publicPhoto = route('public.weddings.photo', [$wedding->public_slug, $photo]);
        $this->get($publicPhoto)->assertNotFound();
        $this->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $bytes = $disk->get($path);
        $this->app['auth']->forgetGuards();
        $this->get($publicPhoto)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($bytes);
        $this->actingAs($owner);
        $this->savePhoto($wedding);
        $newPath = $wedding->websiteDraft()->sole()->hero_photo_path;
        $this->get(route('public.weddings.photo', [$wedding->public_slug, pathinfo($newPath, PATHINFO_FILENAME)]))->assertNotFound();
        $this->get($publicPhoto)->assertOk()->assertStreamedContent($bytes);
        $this->put(route('accounts.weddings.invitation.update', [$wedding->account_id, $wedding]), ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 3, 'hero_photo_action' => 'remove'])->assertSessionHasNoErrors();
        $disk->assertMissing($newPath);
        $disk->assertExists($path);
        $this->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $this->get($publicPhoto)->assertNotFound();
        $this->post($this->unpublishUrl($wedding), ['publication_version' => 2])->assertSessionHasNoErrors();
        touch($disk->path($path), now()->subDays(3)->timestamp);
        $this->artisan('wedding-photos:prune --delete')->assertSuccessful();
        $disk->assertExists($path);
        $this->assertDatabaseCount('website_revisions', 2);
    }

    #[DataProvider('badPhotos')]
    public function test_invalid_missing_and_cross_wedding_media_cannot_be_published(string $kind): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        [, $other] = $this->ownedWedding();
        $target = $kind === 'cross-wedding' ? $other : $wedding;
        $path = 'accounts/'.$target->account_id.'/weddings/'.$target->id.'/hero/'.fake()->uuid().'.webp';
        if ($kind !== 'missing') {
            $disk->put($path, $kind === 'corrupt' ? 'not a valid image' : app(WeddingHeroPhotoProcessor::class)->normalize(UploadedFile::fake()->image('photo.png')));
        }
        $draft = $wedding->websiteDraft()->sole();
        $draft->update(['hero_photo_disk' => $kind === 'disk' ? 'public' : 'wedding_photos', 'hero_photo_path' => $path]);
        $before = $draft->fresh()->getAttributes();
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public static function badPhotos(): array
    {
        return [['missing'], ['cross-wedding'], ['corrupt'], ['disk']];
    }

    public function test_hidden_content_and_private_or_unsupported_pointers_are_never_exposed(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $content = WebsiteDraft::defaultContent();
        $content['sections'] = ['ceremony' => false, 'reception' => false, 'closing' => false];
        $content['closing_message'] = 'Hidden private closing';
        $wedding->websiteDraft()->update(['content' => $content]);
        $wedding->update(['ceremony_venue' => 'Hidden private venue', 'partner_one_name' => 'Alex"><script>alert(1)</script>']);
        $this->actingAs($owner)->post($this->publishUrl($wedding), $this->payload($wedding))->assertSessionHasNoErrors();
        $this->get($this->publicUrl($wedding))->assertOk()->assertDontSee('Hidden private closing')->assertDontSee('Hidden private venue')->assertDontSee('<script>alert(1)</script>', false)->assertSee('Alex"><script>alert(1)</script>');
        $wedding->websiteDraft()->update(['schema_version' => 999]);
        $this->get($this->publicUrl($wedding))->assertOk();
        DB::table('website_revisions')->where('wedding_id', $wedding->id)->update(['schema_version' => 999]);
        $this->get($this->publicUrl($wedding))->assertNotFound();
        DB::table('website_revisions')->where('wedding_id', $wedding->id)->update(['schema_version' => 1]);
        [, $other] = $this->ownedWedding();
        $other->published_revision_id = $wedding->fresh()->published_revision_id;
        $other->save();
        $this->get($this->publicUrl($other))->assertNotFound();
    }

    public function test_failed_publication_rolls_back_new_revision_and_leaves_draft_and_pointer_intact(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $before = $wedding->websiteDraft()->sole()->getAttributes();
        WebsiteRevision::created(function (): void {
            throw new RuntimeException('Simulated publish failure');
        });
        $this->actingAs($owner)->postJson($this->publishUrl($wedding), $this->payload($wedding))->assertServerError();
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertNull($wedding->fresh()->published_revision_id);
        $this->assertSame(0, $wedding->fresh()->publication_version);
        $this->assertSame($before, $wedding->websiteDraft()->sole()->getAttributes());
    }

    public function test_snapshots_cannot_be_edited_or_deleted_through_the_model(): void
    {
        [, $wedding] = $this->ownedWedding();
        $revision = WebsiteRevision::factory()->for($wedding)->create();
        $before = $revision->fresh()->getAttributes();
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $revision->update(['content' => []]) : $revision->delete();
                $this->fail('Snapshot mutation was permitted.');
            } catch (LogicException) {
                $this->assertSame($before, $revision->fresh()->getAttributes());
            }
        }
    }

    private function ownedWedding(bool $saveDraft = true): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Private customer account']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Published Alex', 'partner_two_name' => 'Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->uuid()]);
        if ($saveDraft) {
            WebsiteDraft::factory()->for($wedding)->create();
        }

        return [$owner, $wedding];
    }

    private function payload(Wedding $wedding): array
    {
        $state = app(WeddingPublication::class)->summary($wedding->fresh());

        return ['draft_version' => $state['draftVersion'], 'publication_version' => $state['publicationVersion'], 'snapshot_token' => $state['snapshotToken']];
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

    private function savePhoto(Wedding $wedding): void
    {
        $this->post(route('accounts.weddings.invitation.update', [$wedding->account_id, $wedding]), [
            '_method' => 'put', 'content' => json_encode(WebsiteDraft::defaultContent()), 'draft_version' => $wedding->websiteDraft()->sole()->lock_version,
            'hero_photo_action' => 'replace', 'hero_photo' => UploadedFile::fake()->image('photo.png'),
        ], ['Content-Type' => 'multipart/form-data'])->assertSessionHasNoErrors();
    }
}
