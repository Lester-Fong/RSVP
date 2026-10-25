<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    public function test_expected_in_memory_sqlite_test_environment_is_accepted(): void
    {
        TestDatabaseGuard::assertIsolatedDefaultEnvironment($this->isolatedEnvironment());

        $this->assertSame('wedding_rsvp_cms_test', TestDatabaseGuard::disposableMySqlConfiguration($this->disposableEnvironment())['database']);
    }

    #[DataProvider('unsafeDefaultEnvironments')]
    public function test_unsafe_default_database_environment_is_rejected(array $changes): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertIsolatedDefaultEnvironment(array_replace($this->isolatedEnvironment(), $changes));
    }

    public static function unsafeDefaultEnvironments(): array
    {
        return [
            'development mysql' => [['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'wedding_rsvp']],
            'development database with sqlite driver' => [['DB_DATABASE' => 'wedding_rsvp']],
            'database url override' => [['DATABASE_URL' => 'mysql://host/wedding_rsvp']],
            'wrong runtime' => [['APP_ENV' => 'local']],
        ];
    }

    #[DataProvider('unsafeDisposableMySqlEnvironments')]
    public function test_disposable_mysql_requires_explicit_target_and_independent_credentials(array $changes): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::disposableMySqlConfiguration(array_replace($this->disposableEnvironment(), $changes));
    }

    public static function unsafeDisposableMySqlEnvironments(): array
    {
        return [
            'development schema name' => [['CMS_MYSQL_TEST_DATABASE' => 'wedding_rsvp']],
            'generic application user reused' => [['CMS_MYSQL_TEST_USERNAME' => 'app-user', 'DB_USERNAME' => 'app-user']],
            'generic application password reused' => [['CMS_MYSQL_TEST_PASSWORD' => 'app-secret', 'DB_PASSWORD' => 'app-secret']],
            'non-loopback host' => [['CMS_MYSQL_TEST_HOST' => 'db.example.test']],
            'missing independent password' => [['CMS_MYSQL_TEST_PASSWORD' => '']],
        ];
    }

    private function isolatedEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'DATABASE_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'SESSION_PATH' => '/',
        ];
    }

    private function disposableEnvironment(): array
    {
        return array_merge($this->isolatedEnvironment(), [
            'CMS_MYSQL_TEST_ENABLED' => 'true',
            'CMS_MYSQL_TEST_DATABASE' => 'wedding_rsvp_cms_test',
            'CMS_MYSQL_TEST_HOST' => '127.0.0.1',
            'CMS_MYSQL_TEST_PORT' => '3306',
            'CMS_MYSQL_TEST_USERNAME' => 'isolated-test-user',
            'CMS_MYSQL_TEST_PASSWORD' => 'isolated-test-password',
        ]);
    }
}
