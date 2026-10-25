<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Services\PersonalInvitationLink;
use App\Services\PersonalRsvp;
use App\Services\RsvpDelegationManager;
use App\Services\WeddingPublication;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class GuestRsvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_state_remains_isolated_from_development_cache(): void
    {
        $this->assertInstanceOf(ArrayStore::class, Cache::store()->getStore());
    }

    public function test_personal_http_batch_failure_rolls_back_responses_versions_and_timestamps(): void
    {
        [$token, $guests] = $this->fixture();
        $state = app(PersonalRsvp::class)->read($token);
        $responses = array_map(fn ($guest): array => ['guest' => $guest['reference'], 'version' => $guest['version'], 'status' => 'attending'], $state['guests']);
        $created = 0;
        RsvpResponse::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new RuntimeException('Synthetic storage failure');
            }
        });
        try {
            $this->postJson('/rsvp/'.$token.'/responses', ['access_version' => $state['access_version'], 'responses' => $responses])
                ->assertServerError()->assertDontSee($token)->assertDontSee('Synthetic storage failure')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertDatabaseCount('rsvp_responses', 0);
            foreach ($guests as $guest) {
                $this->assertSame(0, $guest->fresh()->rsvp_version);
            }
        } finally {
            RsvpResponse::flushEventListeners();
        }
    }

    public function test_repeated_personal_partial_submission_preserves_timestamp_and_pending_people(): void
    {
        [$token] = $this->fixture();
        $url = '/rsvp/'.$token;
        $state = $this->getJson($url)->assertOk()->json('rsvp');
        $this->assertSame(['pending', 'pending'], array_column($state['guests'], 'status'));
        $input = ['access_version' => $state['access_version'], 'responses' => [['guest' => $state['guests'][0]['reference'], 'version' => 0, 'status' => 'attending']]];
        $saved = $this->postJson($url.'/responses', $input)->assertOk()->json('rsvp');
        $this->travel(2)->minutes();
        $this->postJson($url.'/responses', $input)->assertOk()->assertJsonPath('rsvp', $saved);
        $this->getJson($url)->assertJsonPath('rsvp.guests.1.status', 'pending');
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    private function fixture(): array
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic respondent QA']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => 'synthetic-rsvp', 'status' => 'draft']);
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 0, 'content' => WebsiteDraft::defaultContent()]);
        $publisher = app(WeddingPublication::class);
        $summary = $publisher->summary($wedding);
        $publisher->publish($wedding, ['draft_version' => 0, 'publication_version' => 0, 'snapshot_token' => $summary['snapshotToken']]);
        $group = InvitationGroup::factory()->for($wedding)->withGuests(2)->create();
        $guests = $group->guests()->orderBy('id')->get();
        $token = app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guests[0], 'generate', 0, 0)['token'];
        app(RsvpDelegationManager::class)->assign($owner, $wedding, $group, $guests[1], $guests[0], [$guests[0]->id => 1, $guests[1]->id => 0], 0);

        return [$token, $guests];
    }
}
