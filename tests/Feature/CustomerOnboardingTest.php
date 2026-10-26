<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\Wedding;
use App\Services\CustomerWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class CustomerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_onboarding_before_the_dashboard_and_keeps_authentication_routes_accessible(): void
    {
        $this->post('/register', ['name' => 'Customer', 'email' => 'onboarding@example.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/onboarding');
        $this->get('/weddings/create')->assertRedirect('/onboarding');
        $this->get('/onboarding')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->component('weddings/Onboarding'));
        $this->get('/settings/profile')->assertOk();
        $this->get('/w/nonexistent')->assertNotFound();
        $this->get('/rsvp/'.str_repeat('a', 64))->assertNotFound();
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('weddings', 0);
        $this->post('/logout')->assertRedirect('/');
        $this->get('/onboarding')->assertRedirect('/login');
    }

    public function test_required_names_and_invalid_optional_details_do_not_create_partial_records(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/weddings', ['partner_one_name' => '  ', 'partner_two_name' => '', 'wedding_date' => 'invalid', 'wedding_time' => '25:99', 'ceremony_venue' => str_repeat('a', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors(['partner_one_name', 'partner_two_name', 'wedding_date', 'wedding_time', 'ceremony_venue']);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('account_members', 0);
        $this->assertDatabaseCount('weddings', 0);
        $this->get('/onboarding/congratulations')->assertRedirect('/dashboard');
    }

    public function test_first_save_accepts_optional_details_and_acknowledges_only_the_saved_names_once(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/weddings', [...$this->names(), 'wedding_date' => '2028-04-16', 'wedding_time' => '15:30', 'ceremony_venue' => 'Chapel', 'reception_venue' => 'Garden', 'account_id' => 999, 'status' => 'published', 'public_slug' => 'chosen'])
            ->assertRedirect('/onboarding/congratulations');
        $wedding = Wedding::sole();
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('account_members', 1);
        $this->assertDatabaseHas('weddings', ['id' => $wedding->id, 'wedding_date' => '2028-04-16', 'ceremony_venue' => 'Chapel', 'reception_venue' => 'Garden', 'status' => 'planning']);
        $this->assertNotSame('chosen', $wedding->public_slug);
        $this->get('/onboarding/congratulations')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->component('weddings/Congratulations')->where('partnerOne', 'Alex')->where('partnerTwo', 'Jamie')->has('receipt'));
        $this->get('/onboarding/congratulations')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect($this->overview($wedding));
        $this->get('/onboarding')->assertRedirect('/dashboard');
        $this->get($this->overview($wedding))->assertOk()->assertInertia(fn (Assert $page) => $page->component('weddings/Show')->where('wedding.id', $wedding->id));
    }

    public function test_retries_and_stale_user_instances_preserve_exactly_one_workspace_and_wedding_without_success_replay(): void
    {
        $user = User::factory()->create();
        $staleUser = User::findOrFail($user->id);
        $this->actingAs($user)->post('/weddings', $this->names())->assertRedirect('/onboarding/congratulations');
        $before = Wedding::sole()->getAttributes();
        $this->post('/weddings', ['partner_one_name' => 'Changed', 'partner_two_name' => 'Retry'])->assertRedirect('/dashboard')->assertSessionMissing('onboarding_created');
        $retry = app(CustomerWorkspace::class)->create($staleUser, $this->names());
        $this->assertFalse($retry['created']);
        $this->assertSame($before, Wedding::sole()->getAttributes());
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('weddings', 1);
        $this->get('/onboarding/congratulations')->assertRedirect('/dashboard');
    }

    public function test_a_valid_long_registration_name_fits_the_workspace_title_without_changing_the_customer_name(): void
    {
        $name = str_repeat('N', 255);
        $user = User::factory()->create(['name' => $name]);
        $this->actingAs($user)->post('/weddings', $this->names())->assertRedirect('/onboarding/congratulations');
        $this->assertLessThanOrEqual(255, mb_strlen(Account::sole()->name));
        $this->assertSame($name, $user->fresh()->name);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('weddings', 1);
    }

    public function test_an_existing_empty_owned_workspace_is_reused_and_its_second_wedding_endpoints_are_blocked(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->actingAs($user)->get('/accounts/'.$account->id.'/weddings/create')->assertRedirect('/onboarding');
        $this->post('/accounts/'.$account->id.'/weddings', $this->names())->assertRedirect('/onboarding/congratulations');
        $this->assertSame($account->id, Wedding::sole()->account_id);
        $this->get('/accounts/'.$account->id.'/weddings/create')->assertForbidden();
        $this->postJson('/accounts/'.$account->id.'/weddings', $this->names())->assertConflict();
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('weddings', 1);
    }

    public function test_other_tenants_and_non_owner_members_cannot_create_in_or_manage_the_workspace(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $other = User::factory()->create();
        $this->actingAs($other)->get('/accounts/'.$account->id.'/weddings/create')->assertForbidden();
        $this->postJson('/accounts/'.$account->id.'/weddings', $this->names())->assertForbidden();
        $account->users()->attach($other, ['role' => 'editor']);
        $this->postJson('/accounts/'.$account->id.'/weddings', $this->names())->assertForbidden();
        $wedding = $account->weddings()->create([...$this->names(), 'public_slug' => 'legacy-one']);
        $this->get($this->overview($wedding))->assertForbidden();
        $this->putJson($this->overview($wedding), $this->names())->assertForbidden();
        $this->post('/weddings', $this->names())->assertRedirect('/onboarding/congratulations');
        $this->assertDatabaseCount('accounts', 2);
        $this->assertSame(1, $other->accounts()->wherePivot('role', 'owner')->count());
    }

    public function test_legacy_multiple_weddings_remain_accessible_but_creation_is_blocked_and_review_is_required(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $weddings = collect([1, 2])->map(fn ($id) => $account->weddings()->create([...$this->names(), 'public_slug' => 'legacy-'.$id]));
        $before = Wedding::query()->orderBy('id')->get()->map->getAttributes()->all();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard')->has('workspaces', 1)->has('workspaces.0.weddings', 2));
        foreach ($weddings as $wedding) {
            $this->get($this->overview($wedding))->assertOk();
        }
        $this->postJson('/weddings', $this->names())->assertConflict();
        $this->postJson('/accounts/'.$account->id.'/weddings', $this->names())->assertConflict();
        $this->assertSame($before, Wedding::query()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('weddings', 2);
    }

    public function test_multiple_historical_owned_accounts_are_all_listed_without_selecting_or_creating_a_workspace(): void
    {
        $user = User::factory()->create();
        $first = $this->account($user);
        $second = $this->account($user);
        $wedding = $second->weddings()->create([...$this->names(), 'public_slug' => 'legacy-second']);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('workspaces', 2)->where('workspaces.0.id', $first->id)->where('workspaces.1.id', $second->id));
        $this->get('/onboarding')->assertRedirect('/dashboard');
        $this->get($this->overview($wedding))->assertOk();
        $this->postJson('/weddings', $this->names())->assertConflict();
        $this->postJson('/accounts/'.$first->id.'/weddings', $this->names())->assertConflict();
        $this->assertDatabaseCount('accounts', 2);
        $this->assertDatabaseCount('weddings', 1);
    }

    public function test_shared_owner_retries_still_create_only_one_wedding_in_the_shared_workspace(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $account = $this->account($one);
        $account->users()->attach($two, ['role' => 'owner']);
        $this->actingAs($one)->post('/weddings', $this->names())->assertRedirect('/onboarding/congratulations');
        $this->actingAs($two)->post('/weddings', $this->names())->assertRedirect('/dashboard');
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('weddings', 1);
    }

    public function test_failed_wedding_insert_rolls_back_the_account_and_membership_and_has_no_congratulations(): void
    {
        Event::listen('eloquent.creating: '.Wedding::class, fn () => throw new RuntimeException('Synthetic onboarding failure'));
        $this->actingAs(User::factory()->create())->postJson('/weddings', $this->names())->assertServerError()->assertSessionMissing('onboarding_created');
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('account_members', 0);
        $this->assertDatabaseCount('weddings', 0);
        $this->get('/onboarding/congratulations')->assertRedirect('/dashboard');
    }

    private function names(): array
    {
        return ['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie'];
    }

    private function account(User $user): Account
    {
        $account = Account::create(['name' => 'QA customer workspace']);
        $account->users()->attach($user, ['role' => 'owner']);

        return $account;
    }

    private function overview(Wedding $wedding): string
    {
        return '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id;
    }
}
