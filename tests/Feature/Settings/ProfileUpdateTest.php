<?php

namespace Tests\Feature\Settings;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_user_cannot_delete_profile_if_they_are_the_only_owner_of_a_wedding_account()
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Wedding workspace']);
        $account->users()->attach($user->id, ['role' => 'owner']);
        $wedding = $account->weddings()->create([
            'partner_one_name' => 'Alex',
            'partner_two_name' => 'Jamie',
            'public_slug' => 'alex-jamie-'.uniqid(),
        ]);

        $this->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertSessionHasErrors('account_deletion')
            ->assertRedirect('/settings/profile');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
        $this->assertDatabaseHas('account_members', [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('weddings', ['id' => $wedding->id]);
    }

    public function test_user_can_delete_profile_when_another_owner_can_access_the_wedding_account()
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $account = Account::create(['name' => 'Shared wedding workspace']);
        $account->users()->attach($user->id, ['role' => 'owner']);
        $account->users()->attach($otherOwner->id, ['role' => 'owner']);
        $wedding = $account->weddings()->create([
            'partner_one_name' => 'Alex',
            'partner_two_name' => 'Jamie',
            'public_slug' => 'alex-jamie-'.uniqid(),
        ]);

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('account_members', [
            'account_id' => $account->id,
            'user_id' => $otherOwner->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('weddings', ['id' => $wedding->id]);
    }
}
