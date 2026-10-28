<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\RsvpResponse;
use App\Services\WeddingAttendance;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class IndividualRsvpMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_upgrade_preserves_old_guests_legacy_credentials_and_response_timestamps(): void
    {
        $migrations = $this->foundationMigrations();
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $wedding = Account::create(['name' => 'Synthetic pre-upgrade workspace'])->weddings()->create([
            'partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'public_slug' => fake()->uuid(),
        ]);
        $group = InvitationGroup::factory()->for($wedding)->withGuests(3)->create();
        $group->invitation_token = 'synthetic-legacy-migration-fixture';
        $group->invitation_token_hash = hash('sha256', $group->invitation_token);
        $group->invitation_link_version = 3;
        $group->rsvp_version = 7;
        $group->save();
        [$attending, $declined] = $group->guests->all();
        RsvpResponse::factory()->for($attending)->create(['responded_at' => '2026-10-01 12:00:00']);
        RsvpResponse::factory()->for($declined)->create(['status' => 'declined', 'responded_at' => '2026-10-02 12:00:00']);
        $oldGuests = DB::table('guests')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $oldResponses = DB::table('rsvp_responses')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $oldGroup = (array) DB::table('invitation_groups')->where('id', $group->id)->first();
        $oldWedding = $wedding->fresh()->getAttributes();

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->assertSame($oldResponses, DB::table('rsvp_responses')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($oldGroup, (array) DB::table('invitation_groups')->where('id', $group->id)->first());
        $this->assertSame($oldWedding, $wedding->fresh()->getAttributes());
        $this->assertSame($oldGuests, DB::table('guests')->select(array_keys($oldGuests[0]))->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        foreach ($group->guests()->get() as $guest) {
            $this->assertSame(0, $guest->rsvp_version);
            $this->assertSame(0, $guest->rsvp_access_version);
        }
        $this->assertSame(['invited' => 3, 'attending' => 1, 'declined' => 1, 'pending' => 1], app(WeddingAttendance::class)->summary($wedding));
        $this->assertDatabaseCount('guest_invitation_links', 0);
        $this->assertDatabaseCount('rsvp_delegations', 0);
    }

    public function test_mysql_ddl_compiles_unsigned_counters_unique_lookups_and_restrictive_foreign_keys_without_connecting(): void
    {
        $connection = new MySqlConnection(fn () => throw new RuntimeException('DDL review must not connect to MySQL.'), 'synthetic_ddl_review', '', [
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB',
        ]);
        $original = Schema::getFacadeRoot();

        try {
            Schema::swap($connection->getSchemaBuilder());
            $queries = $connection->pretend(function (): void {
                foreach ($this->foundationMigrations() as $migration) {
                    $migration->up();
                }
            });
        } finally {
            Schema::swap($original);
        }
        $ddl = implode(";\n", array_column($queries, 'query'));

        $this->assertStringContainsString('`version` int unsigned not null default \'1\'', $ddl);
        $this->assertStringContainsString('`rsvp_version` int unsigned not null default \'0\'', $ddl);
        $this->assertStringContainsString('`rsvp_access_version` int unsigned not null default \'0\'', $ddl);
        $this->assertStringContainsString('`token_hash` char(64) null', $ddl);
        $this->assertStringContainsString('`token` text null', $ddl);
        $this->assertStringContainsString('unique `guest_invitation_links_guest_id_unique`(`guest_id`)', $ddl);
        $this->assertStringContainsString('unique `guest_invitation_links_token_hash_unique`(`token_hash`)', $ddl);
        $this->assertStringContainsString('unique `rsvp_delegations_dependent_guest_id_unique`(`dependent_guest_id`)', $ddl);
        $this->assertStringContainsString('index `rsvp_delegations_representative_guest_id_index`(`representative_guest_id`)', $ddl);
        $this->assertSame(3, substr_count($ddl, 'references `guests` (`id`) on delete restrict'));
        $this->assertStringNotContainsString('delete cascade', $ddl);
        $this->assertStringNotContainsString('invitation_groups', $ddl);
        $this->assertStringNotContainsString('withdrawn', $ddl);
    }

    private function foundationMigrations(): array
    {
        return array_map(fn (string $file) => require database_path('migrations/'.$file), [
            '2026_10_19_073458_create_guest_invitation_links_table.php',
            '2026_10_19_073459_create_rsvp_delegations_table.php',
            '2026_10_19_073459_add_rsvp_versions_to_guests_table.php',
        ]);
    }
}
