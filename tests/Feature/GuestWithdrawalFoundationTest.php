<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Guest;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\RsvpDelegation;
use App\Models\RsvpResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class GuestWithdrawalFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_default_to_active_and_withdrawal_cannot_be_mass_assigned(): void
    {
        $guest = $this->group()->guests->first()->fresh();
        $column = collect(Schema::getColumns('guests'))->firstWhere('name', 'withdrawn_at');

        $this->assertTrue($column['nullable']);
        $this->assertNull($column['default']);
        $this->assertArrayHasKey('withdrawn_at', $guest->getAttributes());
        $this->assertNull($guest->withdrawn_at);
        $this->assertSame(0, $guest->rsvp_version);
        $this->assertSame(0, $guest->rsvp_access_version);

        $guest->fill(['withdrawn_at' => '2026-10-21 12:00:00'])->save();

        $this->assertNull($guest->fresh()->withdrawn_at);
        $this->assertDatabaseCount('guest_invitation_links', 0);
        $this->assertDatabaseCount('rsvp_delegations', 0);
    }

    public function test_invitation_scopes_are_explicit_and_preserve_group_scoping_and_unfiltered_members(): void
    {
        $group = $this->group(0);
        $active = Guest::factory()->for($group)->active()->create();
        $withdrawn = Guest::factory()->for($group)->withdrawn()->create();
        $otherGroup = $this->group(0);
        Guest::factory()->for($otherGroup)->active()->create();
        Guest::factory()->for($otherGroup)->withdrawn()->create();

        $this->assertSame([$active->id], $group->guests()->activeInvitation()->pluck('id')->all());
        $this->assertSame([$withdrawn->id], $group->guests()->withdrawnInvitation()->pluck('id')->all());
        $this->assertSame([$active->id, $withdrawn->id], $group->guests()->orderBy('id')->pluck('id')->all());
        $this->assertSame(4, Guest::query()->count());
        $this->assertSame(2, Guest::query()->activeInvitation()->count());
        $this->assertSame(2, Guest::query()->withdrawnInvitation()->count());
        $this->assertSame([$withdrawn->id], $group->guests()
            ->join('guests as compared_guest', 'compared_guest.id', '=', 'guests.id')
            ->withdrawnInvitation()->pluck('guests.id')->all());
    }

    public function test_withdrawn_factory_timestamp_is_immutable_and_active_state_can_override_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00', 'UTC'));
        $group = $this->group(0);
        $guest = Guest::factory()->for($group)->withdrawn()->create()->fresh();

        $this->assertInstanceOf(CarbonImmutable::class, $guest->withdrawn_at);
        $this->assertSame('2026-10-21 12:00:00', $guest->withdrawn_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-22', $guest->withdrawn_at->addDay()->format('Y-m-d'));
        $this->assertSame('2026-10-21', $guest->withdrawn_at->format('Y-m-d'));
        $this->assertNull(Guest::factory()->for($group)->withdrawn()->active()->create()->fresh()->withdrawn_at);
    }

    public function test_additive_upgrade_preserves_existing_guests_responses_credentials_delegations_and_constraints(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $migration = $this->withdrawalMigration();
        $migration->down();

        $group = $this->group(3);
        [$representative, $dependent, $pending] = $group->guests->all();
        $representative->forceFill(['rsvp_version' => 4, 'rsvp_access_version' => 7])->save();
        $dependent->forceFill(['rsvp_version' => 2, 'rsvp_access_version' => 3])->save();
        GuestInvitationLink::factory()->for($representative)->withSyntheticToken()->create();
        GuestInvitationLink::factory()->for($dependent)->revoked()->create();
        RsvpDelegation::factory()->for($dependent, 'dependent')->create(['representative_guest_id' => $representative->id]);
        RsvpResponse::factory()->for($representative)->create(['responded_at' => '2026-10-01 12:00:00']);
        RsvpResponse::factory()->for($dependent)->create(['status' => 'declined', 'responded_at' => '2026-10-02 13:00:00']);
        $tables = ['weddings', 'invitation_groups', 'guests', 'rsvp_responses', 'guest_invitation_links', 'rsvp_delegations'];
        $before = $this->rows($tables);
        $constraints = [];
        foreach ($tables as $table) {
            $constraints[$table] = ['indexes' => Schema::getIndexes($table), 'foreignKeys' => Schema::getForeignKeys($table)];
        }

        $migration->up();

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->select(array_keys($before[$table][0]))->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
            $this->assertSame($constraints[$table]['indexes'], Schema::getIndexes($table));
            $this->assertSame($constraints[$table]['foreignKeys'], Schema::getForeignKeys($table));
        }
        $this->assertSame([$representative->id, $dependent->id, $pending->id], $group->guests()->activeInvitation()->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, $group->guests()->withdrawnInvitation()->count());
        $this->assertNull($pending->fresh()->rsvpResponse);
        $this->assertNull($this->group()->guests->first()->fresh()->withdrawn_at);
    }

    public function test_storing_only_a_timestamp_does_not_mutate_versions_or_related_records(): void
    {
        $group = $this->group(2);
        [$representative, $dependent] = $group->guests->all();
        GuestInvitationLink::factory()->for($representative)->withSyntheticToken()->create();
        RsvpDelegation::factory()->for($dependent, 'dependent')->create(['representative_guest_id' => $representative->id]);
        RsvpResponse::factory()->for($representative)->create(['responded_at' => '2026-10-01 12:00:00']);
        $before = $this->rows(['guest_invitation_links', 'rsvp_delegations', 'rsvp_responses']);

        $representative->forceFill(['withdrawn_at' => '2026-10-21 12:00:00'])->save();

        $this->assertSame($before, $this->rows(['guest_invitation_links', 'rsvp_delegations', 'rsvp_responses']));
        $this->assertSame(0, $representative->fresh()->rsvp_version);
        $this->assertSame(0, $representative->fresh()->rsvp_access_version);
        $this->assertSame('2026-10-21 12:00:00', $representative->fresh()->withdrawn_at->format('Y-m-d H:i:s'));
    }

    public function test_reduced_selects_do_not_fabricate_loaded_invitation_state(): void
    {
        $guest = Guest::factory()->for($this->group(0))->withdrawn()->create();
        $partial = Guest::query()->select(['id', 'name'])->findOrFail($guest->id);

        $this->assertArrayNotHasKey('withdrawn_at', $partial->getAttributes());
        $this->assertArrayNotHasKey('withdrawn_at', $partial->toArray());
        $this->assertNotNull($guest->fresh()->withdrawn_at);
    }

    public function test_mysql_ddl_only_adds_a_nullable_timestamp_without_connecting(): void
    {
        $connection = new MySqlConnection(fn () => throw new RuntimeException('DDL review must not connect to MySQL.'), 'synthetic_withdrawal_ddl', '', [
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB',
        ]);
        $original = Schema::getFacadeRoot();
        try {
            Schema::swap($connection->getSchemaBuilder());
            $queries = $connection->pretend(fn () => $this->withdrawalMigration()->up());
        } finally {
            Schema::swap($original);
        }

        $this->assertSame(['alter table `guests` add `withdrawn_at` timestamp null'], array_column($queries, 'query'));
    }

    private function group(int $count = 1): InvitationGroup
    {
        $wedding = Account::create(['name' => 'Synthetic withdrawal foundation'])->weddings()->create([
            'partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'public_slug' => fake()->uuid(),
        ]);

        return InvitationGroup::factory()->for($wedding)->withGuests($count)->create();
    }

    /** @param array<int, string> $tables */
    private function rows(array $tables): array
    {
        $rows = [];
        foreach ($tables as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        }

        return $rows;
    }

    private function withdrawalMigration(): Migration
    {
        return require database_path('migrations/2026_10_21_142258_add_withdrawn_at_to_guests_table.php');
    }
}
