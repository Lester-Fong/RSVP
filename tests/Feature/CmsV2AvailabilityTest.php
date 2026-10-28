<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use App\Services\PersonalInvitationLink;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsV2AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_payload_cannot_overwrite_a_v1_draft_or_change_the_current_publication(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->withoutVite();
        WebsiteDraft::factory()->for($wedding)->create();
        app(WeddingPublication::class)->publish($wedding, $this->publishPayload($wedding));
        $draft = $wedding->websiteDraft()->sole()->getAttributes();
        $revision = $wedding->websiteRevisions()->sole()->getAttributes();
        $input = $this->v2();

        $this->actingAs($owner)->putJson(route('accounts.weddings.invitation.update', [$wedding->account_id, $wedding]), [
            'draft_version' => 0, 'schema_version' => 2, 'template_key' => $input['template_key'], 'content' => $input['content'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['schema_version', 'template_key', 'content']);

        $this->assertSame($draft, $wedding->websiteDraft()->sole()->getAttributes());
        $this->assertSame($revision, $wedding->websiteRevisions()->sole()->getAttributes());
        $this->get(route('public.weddings.show', $wedding->public_slug))->assertOk()->assertSee('Modern Classic')
            ->assertDontSee('intro_style')->assertDontSee('botanical-bloom');
    }

    public function test_a_stored_v2_draft_is_not_editable_previewable_or_publishable_and_keeps_the_old_publication(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->withoutVite();
        WebsiteDraft::factory()->for($wedding)->create();
        app(WeddingPublication::class)->publish($wedding, $this->publishPayload($wedding));
        $pointer = $wedding->fresh()->published_revision_id;
        DB::table('website_drafts')->where('wedding_id', $wedding->id)->update(['schema_version' => 2, 'content' => json_encode($this->v2()['content'], JSON_THROW_ON_ERROR)]);

        $this->actingAs($owner)->getJson(route('accounts.weddings.invitation.edit', [$wedding->account_id, $wedding]))->assertConflict();
        $this->getJson(route('accounts.weddings.preview', [$wedding->account_id, $wedding]))->assertConflict();
        $this->postJson(route('accounts.weddings.publish', [$wedding->account_id, $wedding]), $this->publishPayload($wedding))->assertConflict();

        $this->assertSame($pointer, $wedding->fresh()->published_revision_id);
        $this->assertDatabaseCount('website_revisions', 1);
        $this->assertDatabaseHas('website_drafts', ['wedding_id' => $wedding->id, 'schema_version' => 2]);
        $this->get(route('public.weddings.show', $wedding->public_slug))->assertOk()->assertSee('Modern Classic');
    }

    public function test_v2_published_snapshots_are_unavailable_on_generic_and_personal_surfaces(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $group = InvitationGroup::factory()->for($wedding)->withGuests(1)->create();
        $guest = $group->guests->sole();
        $issued = app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guest, 'generate', 0, 0);
        $revision = WebsiteRevision::factory()->for($wedding)->create(['schema_version' => 2, 'content' => $this->v2()['content']]);
        $wedding->forceFill(['published_revision_id' => $revision->id])->save();

        $this->get(route('public.weddings.show', $wedding->public_slug))->assertNotFound()->assertDontSee('intro_settings');
        $url = '/rsvp/'.$issued['token'];
        $this->get($url)->assertNotFound()->assertDontSee($guest->name);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url.'/responses', [])->assertNotFound();

        $this->assertDatabaseCount('rsvp_responses', 0);
        $this->assertDatabaseCount('guest_invitation_links', 1);
        $this->assertSame($revision->id, $wedding->fresh()->published_revision_id);
    }

    /** @return array{User, Wedding} */
    private function ownedWedding(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic CMS account']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create([
            'partner_one_name' => 'Modern Classic', 'partner_two_name' => 'Unchanged', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->unique()->slug(),
        ]);

        return [$owner, $wedding];
    }

    /** @return array{draft_version: int, publication_version: int, snapshot_token: string} */
    private function publishPayload(Wedding $wedding): array
    {
        $summary = app(WeddingPublication::class)->summary($wedding->fresh());

        return ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']];
    }

    /** @return array<string, mixed> */
    private function v2(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/cms-v2-contracts.json'), true, 32, JSON_THROW_ON_ERROR)['base'];
    }
}
