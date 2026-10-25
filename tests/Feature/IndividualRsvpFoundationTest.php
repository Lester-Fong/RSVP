<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\RsvpDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndividualRsvpFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_defaults_and_lookup_indexes_are_available_without_issuing_credentials(): void
    {
        $guests = $this->group()->guests;

        $this->assertTrue(Schema::hasColumns('guest_invitation_links', ['id', 'guest_id', 'token_hash', 'token', 'version', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('rsvp_delegations', ['id', 'dependent_guest_id', 'representative_guest_id', 'created_at', 'updated_at']));
        foreach ($guests as $guest) {
            $this->assertSame(0, $guest->rsvp_version);
            $this->assertSame(0, $guest->rsvp_access_version);
            $this->assertNull($guest->invitationLink);
            $this->assertNull($guest->rsvpDelegation);
        }
        $this->assertDatabaseCount('guest_invitation_links', 0);
        $this->assertDatabaseCount('rsvp_delegations', 0);
        $this->assertTrue(Schema::hasIndex('guest_invitation_links', ['guest_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('guest_invitation_links', ['token_hash'], 'unique'));
        $this->assertTrue(Schema::hasIndex('rsvp_delegations', ['dependent_guest_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('rsvp_delegations', ['representative_guest_id']));
        $this->assertSame('restrict', Schema::getForeignKeys('guest_invitation_links')[0]['on_delete']);
        foreach (Schema::getForeignKeys('rsvp_delegations') as $foreignKey) {
            $this->assertSame('guests', $foreignKey['foreign_table']);
            $this->assertSame('restrict', $foreignKey['on_delete']);
        }
    }

    public function test_a_guest_cannot_have_duplicate_credentials(): void
    {
        $guest = $this->group()->guests->first();
        GuestInvitationLink::factory()->for($guest)->revoked()->create();

        $this->expectException(QueryException::class);
        GuestInvitationLink::factory()->for($guest)->create();
    }

    public function test_nonnull_lookup_hashes_are_unique(): void
    {
        [$first, $second] = $this->group()->guests->all();
        GuestInvitationLink::factory()->for($first)->withSyntheticToken()->create();

        $this->expectException(QueryException::class);
        GuestInvitationLink::factory()->for($second)->withSyntheticToken()->create();
    }

    public function test_multiple_null_hashes_and_null_representatives_preserve_retained_slots(): void
    {
        foreach ($this->group()->guests as $guest) {
            GuestInvitationLink::factory()->for($guest)->revoked()->create();
            RsvpDelegation::factory()->for($guest, 'dependent')->revoked()->create();
        }

        $this->assertDatabaseCount('guest_invitation_links', 2);
        $this->assertDatabaseCount('rsvp_delegations', 2);
        $this->assertSame(2, GuestInvitationLink::whereNull('token_hash')->whereNull('token')->count());
        $this->assertSame(2, RsvpDelegation::whereNull('representative_guest_id')->count());
    }

    public function test_first_credential_row_defaults_to_version_one_and_zero_remains_representable(): void
    {
        $guest = $this->group()->guests->first();
        $id = DB::table('guest_invitation_links')->insertGetId(['guest_id' => $guest->id]);
        $link = GuestInvitationLink::findOrFail($id);

        $this->assertSame(1, $link->version);
        $link->version = 0;
        $link->save();
        $this->assertSame(0, $link->fresh()->version);
    }

    public function test_a_dependent_cannot_have_duplicate_slots_even_if_revoked(): void
    {
        $dependent = $this->group()->guests->first();
        RsvpDelegation::factory()->for($dependent, 'dependent')->revoked()->create();

        $this->expectException(QueryException::class);
        RsvpDelegation::factory()->for($dependent, 'dependent')->revoked()->create();
    }

    #[DataProvider('missingParents')]
    public function test_credential_and_delegation_foreign_keys_reject_missing_guests(string $table, string $column): void
    {
        $guest = $this->group()->guests->first();
        $attributes = $table === 'guest_invitation_links'
            ? ['guest_id' => $guest->id]
            : ['dependent_guest_id' => $guest->id, 'representative_guest_id' => null];
        $attributes[$column] = $guest->id + 100000;

        $this->expectException(QueryException::class);
        DB::table($table)->insert($attributes);
    }

    public static function missingParents(): array
    {
        return [
            'credential guest' => ['guest_invitation_links', 'guest_id'],
            'dependent' => ['rsvp_delegations', 'dependent_guest_id'],
            'representative' => ['rsvp_delegations', 'representative_guest_id'],
        ];
    }

    #[DataProvider('retainedReferences')]
    public function test_guest_and_parent_group_deletion_cannot_cascade_away_retained_records(string $reference, bool $deleteGroup): void
    {
        $group = $this->group();
        [$dependent, $representative] = $group->guests->all();
        if ($reference === 'credential') {
            GuestInvitationLink::factory()->for($dependent)->revoked()->create();
        } else {
            RsvpDelegation::factory()->for($dependent, 'dependent')->create([
                'representative_guest_id' => $reference === 'representative' ? $representative->id : null,
            ]);
        }
        $guest = $reference === 'representative' ? $representative : $dependent;

        $this->expectException(QueryException::class);
        DB::table($deleteGroup ? 'invitation_groups' : 'guests')->where('id', $deleteGroup ? $group->id : $guest->id)->delete();
    }

    public static function retainedReferences(): array
    {
        return [
            'revoked personal guest' => ['credential', false],
            'group with revoked credential' => ['credential', true],
            'revoked dependent' => ['dependent', false],
            'group with revoked delegation' => ['dependent', true],
            'representative' => ['representative', false],
            'group with representative' => ['representative', true],
        ];
    }

    public function test_relationships_and_fixture_states_keep_sensitive_material_out_of_nested_serialization(): void
    {
        [$representative, $independent] = $this->group()->guests->all();
        $token = 'synthetic-personal-fixture-not-a-real-bearer';
        $link = GuestInvitationLink::factory()->for($representative)->withSyntheticToken($token)->create();
        $delegation = RsvpDelegation::factory()->representedBy($representative)->create();
        $raw = DB::table('guest_invitation_links')->where('id', $link->id)->first();

        $this->assertSame($token, $link->fresh()->token);
        $this->assertNotSame($token, $raw->token);
        $this->assertSame($token, Crypt::decryptString($raw->token));
        $this->assertSame(hash('sha256', $token), $raw->token_hash);
        $this->assertTrue($link->guest->is($representative));
        $this->assertTrue($representative->invitationLink->is($link));
        $this->assertTrue($delegation->dependent->rsvpDelegation->is($delegation));
        $this->assertTrue($delegation->representative->is($representative));
        $this->assertSame($representative->invitation_group_id, $delegation->dependent->invitation_group_id);
        $this->assertTrue($representative->representedDelegations()->sole()->is($delegation));
        $this->assertNull($independent->invitationLink);
        $this->assertNull($independent->rsvpDelegation);
        $this->assertSame(0, $representative->fresh()->rsvp_access_version);
        $serialized = $delegation->dependent->load('rsvpDelegation.representative.invitationLink')->toJson();
        foreach ([$token, $raw->token_hash, $raw->token, 'token_hash', '"token"'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
    }

    #[DataProvider('guardedModels')]
    public function test_persistence_models_reject_mass_assigned_credential_or_membership_fields(string $model, array $input): void
    {
        $this->expectException(MassAssignmentException::class);

        (new $model)->fill($input);
    }

    public static function guardedModels(): array
    {
        return [
            'credential' => [GuestInvitationLink::class, ['guest_id' => 1, 'token' => 'not-authorized', 'token_hash' => 'not-authorized', 'version' => 50]],
            'delegation' => [RsvpDelegation::class, ['dependent_guest_id' => 1, 'representative_guest_id' => 2]],
        ];
    }

    public function test_existing_portal_props_do_not_expose_credentials_even_when_future_records_exist(): void
    {
        $this->withoutVite();
        $group = $this->group();
        $wedding = $group->wedding;
        $owner = User::factory()->create();
        $wedding->account->users()->attach($owner, ['role' => 'owner']);
        $link = GuestInvitationLink::factory()->for($group->guests->first())->withSyntheticToken()->create();
        $raw = $link->getAttributes();

        $this->actingAs($owner)->get(route('accounts.weddings.guests.index', [$wedding->account_id, $wedding]))
            ->assertOk()->assertDontSee($link->token, false)->assertDontSee($raw['token_hash'], false)->assertDontSee($raw['token'], false)
            ->assertDontSee('token_hash')->assertDontSee('invitation_link');
    }

    public function test_legacy_credentials_cannot_grant_group_response_authority(): void
    {
        $this->withoutVite();
        $group = $this->group();
        $token = str_repeat('a', 64);
        $group->forceFill(['invitation_token' => $token, 'invitation_token_hash' => hash('sha256', $token), 'invitation_link_version' => 1])->save();
        $this->get('/invite/'.$token)->assertNotFound();
        $this->postJson('/invite/'.$token.'/rsvp', ['version' => 0, 'responses' => []])->assertNotFound();
        $this->assertDatabaseCount('rsvp_responses', 0);
        $this->assertDatabaseCount('guest_invitation_links', 0);
    }

    private function group(): InvitationGroup
    {
        $wedding = Account::create(['name' => 'Synthetic foundation workspace'])->weddings()->create([
            'partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'public_slug' => fake()->uuid(),
        ]);

        return InvitationGroup::factory()->for($wedding)->withGuests(2)->create();
    }
}
