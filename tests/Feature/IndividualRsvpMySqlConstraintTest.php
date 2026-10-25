<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\RsvpDelegation;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

#[Group('mysql-schema')]
class IndividualRsvpMySqlConstraintTest extends TestCase
{
    public function test_mysql_nullable_uniqueness_unsigned_ranges_and_restrictive_deletion(): void
    {
        $configuration = TestDatabaseGuard::disposableMySqlConfigurationFromProcess();
        if ($configuration === null) {
            $this->markTestSkipped('Set the explicit CMS_MYSQL_TEST_* disposable InnoDB environment. No schema is provisioned or reset.');
        }
        $database = $configuration['database'];
        $this->assertMatchesRegularExpression('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database, 'Schema tests refuse development databases.');
        $this->assertTrue($this->app->environment('testing'));
        config(['database.connections.individual_rsvp_mysql_test' => $configuration]);
        $originalConnection = config('database.default');

        try {
            $connection = DB::connection('individual_rsvp_mysql_test');
            try {
                $this->assertSame($database, $connection->selectOne('SELECT DATABASE() AS name')->name);
                foreach (['accounts', 'weddings', 'invitation_groups', 'guests', 'guest_invitation_links', 'rsvp_delegations'] as $table) {
                    if (! $connection->getSchemaBuilder()->hasTable($table)) {
                        $this->markTestSkipped('The disposable database needs existing and 5C.1a migrations; this test never applies them.');
                    }
                }
                if (! $connection->getSchemaBuilder()->hasColumns('guests', ['rsvp_version', 'rsvp_access_version'])) {
                    $this->markTestSkipped('The disposable database needs both guest counters; no schema is changed by this test.');
                }
                $engines = $connection->select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?, ?, ?, ?, ?)', [
                    $database, 'accounts', 'weddings', 'invitation_groups', 'guests', 'guest_invitation_links', 'rsvp_delegations',
                ]);
                $this->assertCount(6, $engines);
                foreach ($engines as $table) {
                    $this->assertSame('InnoDB', $table->ENGINE);
                }
                $mode = $connection->selectOne('SELECT @@SESSION.sql_mode AS modes')->modes;
                $this->assertTrue(str_contains($mode, 'STRICT_TRANS_TABLES') || str_contains($mode, 'STRICT_ALL_TABLES'), 'Unsigned rejection requires strict SQL mode.');
            } catch (QueryException|PDOException) {
                $this->markTestSkipped('Prepared disposable MySQL is unavailable. Development data and schema are untouched.');
            }
            config(['database.default' => 'individual_rsvp_mysql_test']);
            $connection->beginTransaction();

            try {
                $wedding = Account::create(['name' => 'Disposable individual RSVP schema fixture'])->weddings()->create([
                    'partner_one_name' => 'Synthetic Alex', 'partner_two_name' => 'Synthetic Jamie', 'public_slug' => fake()->uuid(),
                ]);
                $group = InvitationGroup::factory()->for($wedding)->withGuests(3)->create();
                [$dependent, $representative, $other] = $group->guests->all();
                $link = GuestInvitationLink::factory()->for($dependent)->revoked()->create();
                GuestInvitationLink::factory()->for($representative)->revoked()->create();
                $this->assertSame(2, $connection->table('guest_invitation_links')->whereIn('guest_id', [$dependent->id, $representative->id])->whereNull('token_hash')->count());
                $link->token_hash = hash('sha256', 'synthetic-mysql-hash-only-no-bearer-issued');
                $link->save();
                $this->assertRejected(fn () => $connection->table('guest_invitation_links')->insert(['guest_id' => $dependent->id]), '23000');
                $this->assertRejected(fn () => $connection->table('guest_invitation_links')->insert(['guest_id' => $other->id, 'token_hash' => $link->token_hash]), '23000');
                $this->assertRejected(fn () => $connection->table('guest_invitation_links')->insert(['guest_id' => 0]), '23000');
                RsvpDelegation::factory()->for($other, 'dependent')->create(['representative_guest_id' => $representative->id]);
                $this->assertRejected(fn () => $connection->table('rsvp_delegations')->insert(['dependent_guest_id' => $other->id]), '23000');
                $this->assertRejected(fn () => $connection->table('rsvp_delegations')->insert(['dependent_guest_id' => 0]), '23000');
                $this->assertRejected(fn () => $connection->table('rsvp_delegations')->insert(['dependent_guest_id' => $dependent->id, 'representative_guest_id' => 0]), '23000');
                RsvpDelegation::factory()->for($dependent, 'dependent')->revoked()->create();

                foreach ([['guest_invitation_links', $link->id, 'version'], ['guests', $dependent->id, 'rsvp_version'], ['guests', $dependent->id, 'rsvp_access_version']] as [$table, $id, $column]) {
                    foreach ([-1, 4294967296] as $invalid) {
                        $this->assertRejected(fn () => $connection->table($table)->where('id', $id)->update([$column => $invalid]), '22003');
                    }
                    $connection->table($table)->where('id', $id)->update([$column => 4294967295]);
                    $this->assertSame(4294967295, (int) $connection->table($table)->where('id', $id)->value($column));
                }
                foreach ([$dependent, $representative, $other] as $guest) {
                    $this->assertRejected(fn () => $connection->table('guests')->where('id', $guest->id)->delete(), '23000');
                }
                $this->assertRejected(fn () => $connection->table('invitation_groups')->where('id', $group->id)->delete(), '23000');
            } finally {
                $connection->rollBack();
            }
        } finally {
            config(['database.default' => $originalConnection]);
            DB::purge('individual_rsvp_mysql_test');
        }
    }

    private function assertRejected(Closure $operation, string $sqlState): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);

            return;
        }

        $this->fail('MySQL accepted a value or deletion that should be constrained.');
    }
}
