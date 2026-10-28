<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    public function test_laravel_boots_against_the_forced_in_memory_sqlite_connection(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertContains(config('database.connections.sqlite.url'), [null, '']);
        $this->assertFalse($this->app->configurationIsCached());
    }
}
