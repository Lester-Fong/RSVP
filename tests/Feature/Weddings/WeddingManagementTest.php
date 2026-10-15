<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeddingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_first_wedding_and_become_account_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('weddings.store'), [
            'partner_one_name' => 'Alex Rivera',
            'partner_two_name' => 'Jamie Santos',
            'wedding_date' => '2027-05-12',
            'wedding_time' => '16:30',
            'ceremony_venue' => 'Manila Cathedral',
            'reception_venue' => 'Intramuros Hall',
        ]);

        $account = Account::sole();
        $wedding = Wedding::sole();

        $response->assertRedirect(route('accounts.weddings.show', [
            'account' => $account->id,
            'wedding' => $wedding->id,
        ]));
        $this->assertDatabaseHas('account_members', [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertSame($account->id, $wedding->account_id);
        $this->assertSame('planning', $wedding->status);
        $this->assertStringStartsWith('alex-rivera-jamie-santos-', $wedding->public_slug);
    }

    public function test_wedding_date_and_venues_can_be_left_blank(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('weddings.store'), [
            'partner_one_name' => 'Alex',
            'partner_two_name' => 'Jamie',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('weddings', [
            'partner_one_name' => 'Alex',
            'partner_two_name' => 'Jamie',
            'wedding_date' => null,
            'wedding_time' => null,
            'ceremony_venue' => null,
            'reception_venue' => null,
        ]);
    }

    public function test_repeated_couple_names_receive_distinct_unique_public_slugs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $attributes = [
            'partner_one_name' => 'Alex Rivera',
            'partner_two_name' => 'Jamie Santos',
        ];

        $this->post(route('weddings.store'), $attributes)->assertSessionHasNoErrors();
        $this->post(route('weddings.store'), $attributes)->assertSessionHasNoErrors();

        $this->assertSame(2, Wedding::query()->count());
        $this->assertSame(2, Wedding::query()->distinct()->count('public_slug'));
        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_authenticated_owner_can_view_dashboard_and_wedding_details(): void
    {
        $user = User::factory()->create();
        $account = $this->createOwnedAccount($user);
        $wedding = $this->createWedding($account);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->get(route('accounts.weddings.show', [
            'account' => $account->id,
            'wedding' => $wedding->id,
        ]))->assertOk();
    }

    public function test_authenticated_owner_can_update_wedding_details(): void
    {
        $user = User::factory()->create();
        $account = $this->createOwnedAccount($user);
        $wedding = $this->createWedding($account);

        $response = $this->actingAs($user)->put(route('accounts.weddings.update', [
            'account' => $account->id,
            'wedding' => $wedding->id,
        ]), [
            'partner_one_name' => 'Alex Rivera',
            'partner_two_name' => 'Jamie Santos',
            'wedding_date' => '2027-05-12',
            'wedding_time' => '17:15',
            'ceremony_venue' => 'Updated Ceremony Venue',
            'reception_venue' => 'Updated Reception Venue',
        ]);

        $response->assertRedirect(route('accounts.weddings.show', [
            'account' => $account->id,
            'wedding' => $wedding->id,
        ]));
        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'partner_one_name' => 'Alex Rivera',
            'wedding_date' => '2027-05-12',
            'ceremony_venue' => 'Updated Ceremony Venue',
        ]);
    }

    public function test_invalid_wedding_input_returns_validation_errors_without_creating_an_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('weddings.store'), [
            'partner_one_name' => '',
            'partner_two_name' => str_repeat('x', 256),
            'wedding_date' => 'not-a-date',
            'wedding_time' => '25:61',
            'ceremony_venue' => str_repeat('x', 501),
        ])->assertSessionHasErrors([
            'partner_one_name',
            'partner_two_name',
            'wedding_date',
            'wedding_time',
            'ceremony_venue',
        ]);

        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('weddings', 0);
    }

    public function test_guests_cannot_access_dashboard_or_wedding_creation(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('weddings.create'))->assertRedirect(route('login'));
        $this->post(route('weddings.store'), [])->assertRedirect(route('login'));
    }

    public function test_member_of_another_account_cannot_view_or_modify_a_wedding(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownerAccount = $this->createOwnedAccount($owner);
        $otherAccount = $this->createOwnedAccount($otherUser);
        $wedding = $this->createWedding($ownerAccount);

        $this->actingAs($otherUser)
            ->get(route('accounts.weddings.show', [
                'account' => $ownerAccount->id,
                'wedding' => $wedding->id,
            ]))
            ->assertForbidden();

        $this->put(route('accounts.weddings.update', [
            'account' => $ownerAccount->id,
            'wedding' => $wedding->id,
        ]), [
            'partner_one_name' => 'Changed',
            'partner_two_name' => 'By another user',
        ])->assertForbidden();

        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'partner_one_name' => 'Partner One',
            'account_id' => $ownerAccount->id,
        ]);
        $this->assertNotSame($ownerAccount->id, $otherAccount->id);
    }

    public function test_wedding_route_cannot_be_scoped_to_a_different_account(): void
    {
        $user = User::factory()->create();
        $firstAccount = $this->createOwnedAccount($user, 'First account');
        $secondAccount = $this->createOwnedAccount($user, 'Second account');
        $wedding = $this->createWedding($firstAccount);

        $this->actingAs($user)
            ->get(route('accounts.weddings.show', [
                'account' => $secondAccount->id,
                'wedding' => $wedding->id,
            ]))
            ->assertNotFound();
    }

    public function test_wedding_cannot_be_reassigned_by_submitting_an_account_id(): void
    {
        $user = User::factory()->create();
        $ownerAccount = $this->createOwnedAccount($user, 'Owner account');
        $otherAccount = Account::create(['name' => 'Another account']);
        $wedding = $this->createWedding($ownerAccount);

        $this->actingAs($user)->put(route('accounts.weddings.update', [
            'account' => $ownerAccount->id,
            'wedding' => $wedding->id,
        ]), [
            'partner_one_name' => 'Partner One',
            'partner_two_name' => 'Partner Two',
            'account_id' => $otherAccount->id,
            'public_slug' => 'attacker-chosen-slug',
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'account_id' => $ownerAccount->id,
            'public_slug' => $wedding->public_slug,
            'status' => 'planning',
        ]);
    }

    private function createOwnedAccount(User $user, string $name = 'Customer workspace'): Account
    {
        $account = Account::create(['name' => $name]);
        $account->users()->attach($user->id, ['role' => 'owner']);

        return $account;
    }

    private function createWedding(Account $account): Wedding
    {
        return $account->weddings()->create([
            'partner_one_name' => 'Partner One',
            'partner_two_name' => 'Partner Two',
            'public_slug' => 'partner-one-partner-two-'.uniqid(),
        ]);
    }
}
