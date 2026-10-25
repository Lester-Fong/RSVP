<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\Wedding;
use App\Services\WeddingAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeddingAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[DataProvider('populations')]
    public function test_summary_counts_each_person_once_and_missing_responses_as_pending(array $states, array $expected): void
    {
        [$owner, $wedding] = $this->fixture();
        if ($states) {
            $this->group($wedding, 'Family', $states);
        }
        $this->actingAs($owner)->get($this->url($wedding))->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->component('weddings/Attendance')->where('summary', $expected)
                ->has('guests.data', count($states))->where('groups', null));
        $this->assertSame($expected['invited'], $expected['attending'] + $expected['declined'] + $expected['pending']);
    }

    public static function populations(): array
    {
        return [
            'empty' => [[], ['invited' => 0, 'attending' => 0, 'declined' => 0, 'pending' => 0]],
            'pending' => [[null, null], ['invited' => 2, 'attending' => 0, 'declined' => 0, 'pending' => 2]],
            'attending' => [['attending', 'attending'], ['invited' => 2, 'attending' => 2, 'declined' => 0, 'pending' => 0]],
            'declined' => [['declined', 'declined'], ['invited' => 2, 'attending' => 0, 'declined' => 2, 'pending' => 0]],
            'partial' => [['attending', 'declined', null, null], ['invited' => 4, 'attending' => 1, 'declined' => 1, 'pending' => 2]],
        ];
    }

    public function test_group_breakdowns_keep_full_totals_and_load_only_matching_members(): void
    {
        [$owner, $wedding] = $this->fixture();
        $group = $this->group($wedding, 'Santos Family', ['attending', 'attending', 'declined', null]);
        $this->actingAs($owner)->get($this->url($wedding).'?view=groups&status=attending')
            ->assertInertia(fn (Assert $page) => $page->where('guests', null)->where('matchedGuests', 2)->has('groups.data', 1)
                ->where('groups.data.0', ['id' => $group->id, 'label' => 'Santos Family', 'invited' => 4,
                    'attending' => 2, 'declined' => 1, 'pending' => 1, 'matching' => 2])->missing('groups.data.0.guests'));
        $this->getJson($this->groupUrl($wedding, $group).'?status=pending')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')->assertJsonPath('data.0.respondedAt', null);
    }

    public function test_pagination_and_filters_never_change_wedding_wide_totals(): void
    {
        [$owner, $wedding] = $this->fixture();
        for ($index = 0; $index < 12; $index++) {
            $this->group($wedding, 'Family '.$index, ['attending', 'declined', null]);
        }
        $this->actingAs($owner)->get($this->url($wedding).'?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('guests.data', 11)->where('guests.total', 36)
            ->where('summary', ['invited' => 36, 'attending' => 12, 'declined' => 12, 'pending' => 12]));
        $this->get($this->url($wedding).'?view=groups&status=pending&page=2')->assertInertia(fn (Assert $page) => $page
            ->has('groups.data', 2)->where('groups.total', 12)->where('matchedGuests', 12)->where('summary.invited', 36));
        $group = $this->group($wedding, 'Large family', array_fill(0, 30, null));
        $this->getJson($this->groupUrl($wedding, $group))->assertJsonCount(25, 'data')->assertJsonPath('total', 30);
        $this->getJson($this->groupUrl($wedding, $group).'?page=2')->assertJsonCount(5, 'data');
    }

    public function test_updated_responses_renames_new_guests_and_revocation_preserve_counts(): void
    {
        [$owner, $wedding] = $this->fixture();
        $group = $this->group($wedding, 'Family', ['attending', 'declined', null]);
        $guest = $group->guests()->first();
        $guest->update(['name' => 'Renamed person']);
        $guest->rsvpResponse()->update(['status' => 'declined']);
        Guest::factory()->for($group)->create(['name' => 'New pending person']);
        $group->forceFill(['invitation_link_version' => 2, 'invitation_token_hash' => null, 'invitation_token' => null])->save();
        $this->actingAs($owner)->get($this->url($wedding))->assertInertia(fn (Assert $page) => $page
            ->where('summary', ['invited' => 4, 'attending' => 0, 'declined' => 2, 'pending' => 2])
            ->where('guests.data.0.name', 'Renamed person')->where('guests.data.0.status', 'declined')
            ->where('guests.data.0.respondedAt', '2026-10-19T08:00:00+00:00')->where('guests.data.3.status', 'pending'));
    }

    #[DataProvider('filters')]
    public function test_search_and_status_match_the_same_guest_and_escape_wildcards(array $filters, int $count): void
    {
        [$owner, $wedding] = $this->fixture();
        $family = $this->group($wedding, 'Santos Family', ['attending', 'declined', null]);
        foreach ($family->guests as $index => $guest) {
            $guest->update(['name' => ['Juan', 'Maria', 'Andrea'][$index]]);
        }
        $other = $this->group($wedding, 'Other family', [null]);
        $other->guests()->first()->update(['name' => 'Literal %_! person']);
        $this->actingAs($owner)->get($this->url($wedding).'?'.http_build_query($filters))->assertInertia(fn (Assert $page) => $page
            ->where('guests.total', $count)->where('matchedGuests', $count)->where('summary.invited', 4)->where('summary.pending', 2));
        $this->get($this->url($wedding).'?'.http_build_query([...$filters, 'view' => 'groups']))
            ->assertInertia(fn (Assert $page) => $page->where('matchedGuests', $count));
    }

    public static function filters(): array
    {
        return [
            'guest' => [['search' => 'Juan'], 1], 'group' => [['search' => 'Santos'], 3],
            'attending' => [['status' => 'attending'], 1], 'declined' => [['status' => 'declined'], 1],
            'pending' => [['status' => 'pending'], 2], 'combined' => [['search' => 'Santos', 'status' => 'pending'], 1],
            'same person' => [['search' => 'Maria', 'status' => 'attending'], 0],
            'percent' => [['search' => '%'], 1], 'underscore' => [['search' => '_'], 1],
            'escape' => [['search' => '!'], 1], 'whitespace' => [['search' => '   '], 4],
        ];
    }

    public function test_both_endpoints_require_owner_and_scoped_account_wedding_group(): void
    {
        [$owner, $wedding] = $this->fixture();
        [$other, $foreign] = $this->fixture();
        $group = $this->group($wedding, 'Private', [null]);
        $foreignGroup = $this->group($foreign, 'Foreign', [null]);
        $this->get($this->url($wedding))->assertRedirect(route('login'));
        $this->getJson($this->groupUrl($wedding, $group))->assertUnauthorized();
        $this->actingAs($other)->get($this->url($wedding))->assertForbidden();
        $this->getJson($this->groupUrl($wedding, $group))->assertForbidden();
        $this->actingAs($owner)->get($this->url($wedding, ['account' => $foreign->account_id]))->assertNotFound();
        $this->getJson($this->groupUrl($wedding, $foreignGroup))->assertNotFound();
        $sibling = $wedding->account->weddings()->create(['partner_one_name' => 'Sibling', 'partner_two_name' => 'Couple', 'public_slug' => (string) Str::uuid()]);
        $this->getJson($this->groupUrl($wedding, $this->group($sibling, 'Sibling', [null])))->assertNotFound();
        $viewer = User::factory()->create();
        $wedding->account->users()->attach($viewer, ['role' => 'viewer']);
        $this->actingAs($viewer)->get($this->url($wedding))->assertForbidden();
        $this->getJson($this->groupUrl($wedding, $group))->assertForbidden();
    }

    public function test_responses_exclude_contacts_tokens_foreign_people_and_cannot_write(): void
    {
        [$owner, $wedding] = $this->fixture();
        [, $foreign] = $this->fixture();
        $group = $this->group($wedding, 'Family', ['attending']);
        $group->forceFill(['contact_email' => 'secret-contact@example.test', 'contact_phone' => 'SECRET-PHONE',
            'invitation_token' => 'SECRET-TOKEN', 'invitation_token_hash' => hash('sha256', 'SECRET-TOKEN'), 'invitation_link_version' => 1])->save();
        $this->group($foreign, 'FOREIGN-SENTINEL', [null]);
        $this->actingAs($owner);
        foreach (['guests', 'groups'] as $view) {
            $this->get($this->url($wedding).'?view='.$view)->assertOk()->assertDontSee('secret-contact@example.test')
                ->assertDontSee('SECRET-PHONE')->assertDontSee('SECRET-TOKEN')->assertDontSee('FOREIGN-SENTINEL')->assertDontSee($group->invitation_token_hash);
        }
        $response = $this->getJson($this->groupUrl($wedding, $group))->assertOk();
        $this->assertSame(['id', 'name', 'group', 'status', 'respondedAt'], array_keys($response->json('data.0')));
        $response->assertDontSee('secret-contact')->assertDontSee('SECRET-TOKEN')->assertDontSee('SECRET-PHONE');
        $before = DB::table('rsvp_responses')->get()->toJson();
        $this->post($this->url($wedding), ['status' => 'declined'])->assertStatus(405);
        $this->postJson($this->groupUrl($wedding, $group), ['status' => 'declined'])->assertStatus(405);
        $this->assertSame($before, DB::table('rsvp_responses')->get()->toJson());
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $filters): void
    {
        [$owner, $wedding] = $this->fixture();
        $group = $this->group($wedding, 'Family', [null]);
        $query = '?'.http_build_query($filters);
        $this->actingAs($owner)->getJson($this->url($wedding).$query)->assertUnprocessable();
        $this->getJson($this->groupUrl($wedding, $group).$query)->assertUnprocessable();
    }

    public static function invalidFilters(): array
    {
        return [[['status' => 'unknown']], [['view' => 'secret']], [['page' => 0]], [['page' => 100001]],
            [['search' => ['nested']]], [['search' => str_repeat('x', 256)]]];
    }

    public function test_eager_loading_query_count_does_not_grow_per_guest(): void
    {
        [, $wedding] = $this->fixture();
        $group = $this->group($wedding, 'Family', ['attending']);
        $service = app(WeddingAttendance::class);
        $filters = ['search' => '', 'status' => 'all', 'view' => 'guests'];
        DB::enableQueryLog();
        $service->guests($wedding, $filters);
        $single = count(DB::getQueryLog());
        DB::disableQueryLog();
        Guest::factory()->count(24)->for($group)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->guests($wedding, $filters);
        $this->assertSame($single, count(DB::getQueryLog()));
        $this->assertLessThanOrEqual(4, $single);
        DB::disableQueryLog();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Attendance workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie',
            'wedding_date' => '2027-05-12', 'public_slug' => (string) Str::uuid()]);

        return [$owner, $wedding];
    }

    private function group(Wedding $wedding, string $label, array $states): InvitationGroup
    {
        $group = InvitationGroup::factory()->for($wedding)->create(['label' => $label]);
        foreach ($states as $status) {
            $guest = Guest::factory()->for($group)->create();
            if ($status !== null) {
                $guest->rsvpResponse()->create(['status' => $status, 'responded_at' => '2026-10-19 08:00:00']);
            }
        }

        return $group;
    }

    private function url(Wedding $wedding, array $parameters = []): string
    {
        return route('accounts.weddings.attendance.index', [...['account' => $wedding->account_id, 'wedding' => $wedding->id], ...$parameters]);
    }

    private function groupUrl(Wedding $wedding, InvitationGroup $group): string
    {
        return route('accounts.weddings.attendance.group', ['account' => $wedding->account_id, 'wedding' => $wedding->id, 'invitationGroup' => $group->id]);
    }
}
