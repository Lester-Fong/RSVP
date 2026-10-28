<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WeddingGuestManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[DataProvider('namedGroups')]
    public function test_owner_creates_individual_or_multi_person_groups_and_reloads_them(array $names): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->post($this->url($wedding, 'invitation-groups.store'), [
            'label' => 'Santos Family', 'contact_email' => 'family@example.test', 'contact_phone' => '+63 917 000 0000',
            'guests' => array_map(fn (string $name): array => ['name' => $name], $names),
        ])->assertSessionHasNoErrors()->assertRedirect($this->url($wedding, 'guests.index'));
        $group = $wedding->invitationGroups()->sole();
        $this->assertSame($names, $group->guests()->pluck('name')->all());
        $this->assertSame('family@example.test', $group->contact_email);
        $this->assertSame('+63 917 000 0000', $group->contact_phone);
        $this->actingAs($owner)->get($this->url($wedding, 'guests.index'))->assertInertia(fn (Assert $page) => $page
            ->component('weddings/Guests')->where('totals.groups', 1)->where('totals.people', count($names))
            ->where('groups.data.0.guests_count', count($names))->where('groups.data.0.label', 'Santos Family')
            ->missing('groups.data.0.contact_email')->missing('groups.data.0.contact_phone'));
    }

    public static function namedGroups(): array
    {
        return ['individual' => [['John Reyes']], 'couple' => [['Mark', 'Anna']], 'family with duplicate names' => [['Alex', 'Alex', 'Jamie', 'Jordan']]];
    }

    public function test_group_and_guest_crud_preserve_ids_and_do_not_touch_other_groups(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $group = InvitationGroup::factory()->for($wedding)->withGuests(2)->create();
        $untouched = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $original = $untouched->fresh()->load('guests')->toArray();
        $guest = $group->guests()->first();
        $this->actingAs($owner)->patch($this->url($wedding, 'invitation-groups.update', $group), [
            'label' => 'Updated couple', 'contact_email' => null, 'contact_phone' => '0917 000 0000',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Updated couple', $group->fresh()->label);
        $this->patch($this->url($wedding, 'guests.update', $group, $guest), ['name' => 'Renamed person'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed person', $guest->fresh()->name);
        $this->assertSame($group->id, $guest->fresh()->invitation_group_id);
        $this->post($this->url($wedding, 'guests.store', $group), ['name' => 'Extra person'])->assertSessionHasNoErrors();
        $this->assertSame(3, $group->guests()->count());
        $this->delete($this->url($wedding, 'guests.destroy', $group, $guest))->assertSessionHasNoErrors();
        $this->assertModelMissing($guest);
        $this->assertSame(2, $group->guests()->count());
        $this->delete($this->url($wedding, 'invitation-groups.destroy', $group))->assertSessionHasNoErrors();
        $this->assertModelMissing($group);
        $this->assertDatabaseMissing('guests', ['invitation_group_id' => $group->id]);
        $this->assertSame($original, $untouched->fresh()->load('guests')->toArray());
    }

    public function test_deleting_the_last_guest_is_rejected_without_deleting_anything(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $group = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $guest = $group->guests()->sole();
        $this->actingAs($owner)->deleteJson($this->url($wedding, 'guests.destroy', $group, $guest))
            ->assertUnprocessable()->assertJsonValidationErrors(['guest' => 'Keep at least one guest in this group.']);
        $this->assertModelExists($guest);
        $this->assertModelExists($group);
    }

    #[DataProvider('invalidGroups')]
    public function test_invalid_groups_do_not_leave_empty_or_partial_records(array $payload, string $error): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->postJson($this->url($wedding, 'invitation-groups.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('invitation_groups', 0);
        $this->assertDatabaseCount('guests', 0);
    }

    public static function invalidGroups(): array
    {
        $valid = ['label' => 'Family', 'guests' => [['name' => 'John']]];

        return [
            'missing fields' => [[], 'label'],
            'empty group' => [[...$valid, 'guests' => []], 'guests'],
            'blank name' => [[...$valid, 'guests' => [['name' => '   ']]], 'guests.0.name'],
            'long label' => [[...$valid, 'label' => str_repeat('a', 256)], 'label'],
            'long guest name' => [[...$valid, 'guests' => [['name' => str_repeat('a', 256)]]], 'guests.0.name'],
            'invalid email' => [[...$valid, 'contact_email' => 'not-email'], 'contact_email'],
            'long phone' => [[...$valid, 'contact_phone' => str_repeat('1', 51)], 'contact_phone'],
            'too many guests' => [[...$valid, 'guests' => array_fill(0, 101, ['name' => 'John'])], 'guests'],
            'guest ownership field' => [[...$valid, 'guests' => [['name' => 'John', 'id' => 1]]], 'guests.0'],
            'wedding injection' => [[...$valid, 'wedding_id' => 999], 'wedding_id'],
            'account injection' => [[...$valid, 'account_id' => 999], 'account_id'],
            'non-list guests' => [[...$valid, 'guests' => ['member' => ['name' => 'John']]], 'guests'],
        ];
    }

    public function test_invalid_updates_and_membership_fields_do_not_change_records(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $group = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $guest = $group->guests()->sole();
        $beforeGroup = $group->fresh()->getAttributes();
        $beforeGuest = $guest->getAttributes();
        $this->actingAs($owner)->patchJson($this->url($wedding, 'invitation-groups.update', $group), [
            'label' => '', 'wedding_id' => 999, 'guests' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['label', 'wedding_id', 'guests']);
        $this->patchJson($this->url($wedding, 'guests.update', $group, $guest), [
            'name' => 'Altered', 'invitation_group_id' => 999, 'wedding_id' => 999, 'account_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['invitation_group_id', 'wedding_id', 'account_id']);
        $this->postJson($this->url($wedding, 'guests.store', $group), ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->deleteJson($this->url($wedding, 'invitation-groups.destroy', $group), ['wedding_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('wedding_id');
        $this->assertSame($beforeGroup, $group->fresh()->getAttributes());
        $this->assertSame($beforeGuest, $guest->fresh()->getAttributes());
    }

    public function test_group_capacity_accepts_boundary_and_rejects_additions_without_data_loss(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->post($this->url($wedding, 'invitation-groups.store'), [
            'label' => 'Large group', 'guests' => array_fill(0, 99, ['name' => str_repeat('N', 255)]),
        ])->assertSessionHasNoErrors();
        $group = $wedding->invitationGroups()->sole();
        $this->post($this->url($wedding, 'guests.store', $group), ['name' => 'Guest 100'])->assertSessionHasNoErrors();
        $this->postJson($this->url($wedding, 'guests.store', $group), ['name' => 'Guest 101'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'A group can contain at most 100 guests.']);
        $this->assertSame(100, $group->guests()->count());
    }

    public function test_search_matches_labels_and_members_without_filtering_totals_or_leaking_other_weddings(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $first = InvitationGroup::factory()->for($wedding)->withGuests(2)->create(['label' => 'Santos Family']);
        $second = InvitationGroup::factory()->for($wedding)->withGuests()->create(['label' => 'Reyes']);
        $second->guests()->sole()->update(['name' => 'Maria Santos']);
        InvitationGroup::factory()->for($wedding)->withGuests(3)->create(['label' => 'Elsewhere']);
        [, $foreign] = $this->ownedWedding();
        InvitationGroup::factory()->for($foreign)->withGuests()->create(['label' => 'Santos private']);
        $this->actingAs($owner)->get($this->url($wedding, 'guests.index').'?search=Santos')->assertInertia(fn (Assert $page) => $page
            ->where('groups.total', 2)->where('totals.groups', 3)->where('totals.people', 6)
            ->where('groups.data.0.id', $second->id)->where('groups.data.1.id', $first->id)->has('groups.data.1.guests', 2));
        $this->get($this->url($wedding, 'guests.index').'?search=NoMatch')->assertInertia(fn (Assert $page) => $page
            ->where('groups.total', 0)->where('totals.people', 6));
    }

    public function test_search_treats_sql_wildcards_and_quotes_as_literal_text_and_paginates_groups(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        InvitationGroup::factory()->for($wedding)->withGuests()->count(26)->create(['label' => 'Normal']);
        $special = InvitationGroup::factory()->for($wedding)->withGuests()->create(['label' => "100%_! O'Neil"]);
        $this->actingAs($owner)->get($this->url($wedding, 'guests.index').'?page=2')->assertInertia(fn (Assert $page) => $page
            ->where('groups.total', 27)->where('groups.current_page', 2)->has('groups.data', 2)->where('totals.people', 27));
        $this->get($this->url($wedding, 'guests.index').'?'.http_build_query(['search' => "%_! O'Neil"]))
            ->assertInertia(fn (Assert $page) => $page->where('groups.total', 1)->where('groups.data.0.id', $special->id));
        $this->getJson($this->url($wedding, 'guests.index').'?search[]=bad')->assertUnprocessable();
    }

    #[DataProvider('deniedActors')]
    public function test_all_guest_endpoints_require_authenticated_wedding_owner(string $actor): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $group = InvitationGroup::factory()->for($wedding)->withGuests(2)->create();
        $guest = $group->guests()->first();
        if ($actor !== 'anonymous') {
            $user = User::factory()->create();
            if ($actor === 'viewer') {
                $wedding->account->users()->attach($user, ['role' => 'viewer']);
            }
            $this->actingAs($user);
        }
        foreach ([
            ['GET', 'guests.index'], ['POST', 'invitation-groups.store'], ['PATCH', 'invitation-groups.update'],
            ['DELETE', 'invitation-groups.destroy'], ['POST', 'guests.store'], ['PATCH', 'guests.update'], ['DELETE', 'guests.destroy'],
        ] as [$method, $action]) {
            $response = $this->call($method, $this->url($wedding, $action, $group, $guest), ['name' => 'Tampered']);
            if ($actor === 'anonymous') {
                $response->assertRedirect(route('login'));
            } else {
                $response->assertForbidden();
            }
        }
        $this->assertSame(2, $group->guests()->count());
        $this->assertModelExists($group);
        $this->assertNotSame('Tampered', $guest->fresh()->name);
    }

    public static function deniedActors(): array
    {
        return [['anonymous'], ['foreign user'], ['viewer']];
    }

    public function test_nested_ids_from_another_account_wedding_or_group_are_404_and_cannot_be_reassigned(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        [, $foreign] = $this->ownedWedding();
        $sibling = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Couple', 'public_slug' => 'sibling']);
        $group = InvitationGroup::factory()->for($wedding)->withGuests(2)->create();
        $siblingGroup = InvitationGroup::factory()->for($sibling)->withGuests()->create();
        $foreignGroup = InvitationGroup::factory()->for($foreign)->withGuests()->create();
        $otherGroup = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $this->actingAs($owner);
        foreach ([$siblingGroup, $foreignGroup] as $other) {
            $this->patchJson($this->url($wedding, 'invitation-groups.update', $other), ['label' => 'Stolen'])->assertNotFound();
            $this->deleteJson($this->url($wedding, 'invitation-groups.destroy', $other))->assertNotFound();
            $this->postJson($this->url($wedding, 'guests.store', $other), ['name' => 'Stolen'])->assertNotFound();
            $this->patchJson($this->url($wedding, 'guests.update', $group, $other->guests()->sole()), ['name' => 'Stolen'])->assertNotFound();
            $this->deleteJson($this->url($wedding, 'guests.destroy', $group, $other->guests()->sole()))->assertNotFound();
            $this->assertModelExists($other);
            $this->assertSame(1, $other->guests()->count());
        }
        $this->patchJson($this->url($wedding, 'guests.update', $group, $otherGroup->guests()->sole()), ['name' => 'Stolen'])->assertNotFound();
        $this->get(route('accounts.weddings.guests.index', [$wedding->account_id, $foreign]))->assertNotFound();
        $this->patchJson($this->url($wedding, 'guests.update', $group, 999999), ['name' => 'Missing'])->assertNotFound();
        $this->assertSame(2, $group->guests()->count());
    }

    public function test_failed_group_creation_rolls_back_the_group_and_every_guest(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        Guest::creating(function (Guest $guest): void {
            if ($guest->name === 'Storage failure') {
                throw new RuntimeException('Synthetic insert failure');
            }
        });
        $this->actingAs($owner)->postJson($this->url($wedding, 'invitation-groups.store'), [
            'label' => 'Rollback', 'guests' => [['name' => 'First person'], ['name' => 'Storage failure']],
        ])->assertServerError();
        $this->assertDatabaseCount('invitation_groups', 0);
        $this->assertDatabaseCount('guests', 0);
    }

    public function test_private_guests_and_contacts_never_enter_published_html_or_cms_snapshots(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $wedding->update(['wedding_date' => '2027-05-12']);
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 0, 'content' => WebsiteDraft::defaultContent()]);
        $publisher = app(WeddingPublication::class);
        $state = $publisher->summary($wedding);
        $publisher->publish($wedding, ['draft_version' => 0, 'publication_version' => 0, 'snapshot_token' => $state['snapshotToken']]);
        $snapshot = $wedding->websiteRevisions()->sole()->getAttributes();
        $group = InvitationGroup::factory()->for($wedding)->withGuests()->create(['label' => 'Secret family', 'contact_email' => 'private@example.test', 'contact_phone' => 'Private phone']);
        $group->guests()->sole()->update(['name' => 'Private guest <script>alert(1)</script>']);
        $this->get(route('public.weddings.show', ['slug' => $wedding->public_slug]))->assertOk()
            ->assertDontSee('Secret family')->assertDontSee('private@example.test')->assertDontSee('Private guest')->assertDontSee('Private phone');
        $this->assertSame($snapshot, $wedding->websiteRevisions()->sole()->getAttributes());
        $this->actingAs($owner)->get($this->url($wedding, 'guests.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    private function ownedWedding(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Test workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'public_slug' => 'test-'.fake()->uuid()]);

        return [$owner, $wedding];
    }

    private function url(Wedding $wedding, string $action, ?InvitationGroup $group = null, Guest|int|null $guest = null): string
    {
        return route('accounts.weddings.'.$action, array_filter([
            'account' => $wedding->account_id, 'wedding' => $wedding->id,
            'invitationGroup' => $group?->id, 'guest' => $guest instanceof Guest ? $guest->id : $guest,
        ]));
    }
}
