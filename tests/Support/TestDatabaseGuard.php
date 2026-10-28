<?php

namespace Tests\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final class TestDatabaseGuard
{
    /**
     * @param  array<string, string|false|null>  $environment
     */
    public static function assertIsolatedDefaultEnvironment(array $environment): void
    {
        $expected = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DATABASE_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'SESSION_PATH' => '/',
        ];

        foreach ($expected as $key => $value) {
            if (($environment[$key] ?? false) !== $value) {
                throw new RuntimeException("Unsafe PHPUnit environment: {$key} must use the isolated test value.");
            }
        }
    }

    /**
     * @param  array<string, string|false|null>  $environment
     */
    public static function disposableMySqlConfiguration(array $environment, bool $workerConnection = false): ?array
    {
        if (($environment['CMS_MYSQL_TEST_ENABLED'] ?? '') !== 'true') {
            return null;
        }

        if (! $workerConnection) {
            self::assertIsolatedDefaultEnvironment($environment);
        }

        $keys = [
            'CMS_MYSQL_TEST_DATABASE',
            'CMS_MYSQL_TEST_HOST',
            'CMS_MYSQL_TEST_PORT',
            'CMS_MYSQL_TEST_USERNAME',
            'CMS_MYSQL_TEST_PASSWORD',
        ];

        foreach ($keys as $key) {
            if (! is_string($environment[$key] ?? null) || $environment[$key] === '') {
                throw new RuntimeException("Disposable MySQL opt-in requires {$key}.");
            }
        }

        $database = (string) $environment['CMS_MYSQL_TEST_DATABASE'];
        $host = (string) $environment['CMS_MYSQL_TEST_HOST'];
        $port = filter_var($environment['CMS_MYSQL_TEST_PORT'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $username = (string) $environment['CMS_MYSQL_TEST_USERNAME'];
        $password = (string) $environment['CMS_MYSQL_TEST_PASSWORD'];

        if (! preg_match('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database)) {
            throw new RuntimeException('Disposable MySQL tests only accept a wedding_rsvp_cms_test database name.');
        }

        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || $port === false) {
            throw new RuntimeException('Disposable MySQL tests only accept an explicit loopback host and valid port.');
        }

        if (! $workerConnection && ($username === (string) ($environment['DB_USERNAME'] ?? '')
            || ($password !== '' && $password === (string) ($environment['DB_PASSWORD'] ?? '')))) {
            throw new RuntimeException('Disposable MySQL tests require credentials independent from the application database credentials.');
        }

        if ($workerConnection && (($environment['DB_CONNECTION'] ?? '') !== 'mysql'
            || ($environment['DB_DATABASE'] ?? '') !== $database
            || ! in_array($environment['DB_URL'] ?? '', ['', '(null)'], true)
            || ($environment['DATABASE_URL'] ?? '') !== ''
            || ($environment['DB_HOST'] ?? '') !== $host
            || ($environment['DB_PORT'] ?? '') !== (string) $port
            || ($environment['DB_USERNAME'] ?? '') !== $username
            || ($environment['DB_PASSWORD'] ?? '') !== $password)) {
            throw new RuntimeException('The disposable MySQL worker connection must match its explicit test-only credentials and target.');
        }

        return [
            'driver' => 'mysql',
            'url' => null,
            'host' => $host,
            'port' => (string) $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ];
    }

    public static function disposableMySqlConfigurationFromProcess(bool $workerConnection = false): ?array
    {
        return self::disposableMySqlConfiguration(self::runtimeEnvironment(), $workerConnection);
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, string>
     */
    public static function workerEnvironment(array $configuration): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => (string) $configuration['database'],
            'DB_URL' => '(null)',
            'DATABASE_URL' => '',
            'DB_HOST' => (string) $configuration['host'],
            'DB_PORT' => (string) $configuration['port'],
            'DB_USERNAME' => (string) $configuration['username'],
            'DB_PASSWORD' => (string) $configuration['password'],
            'DB_SOCKET' => (string) $configuration['unix_socket'],
            'CMS_MYSQL_TEST_ENABLED' => 'true',
            'CMS_MYSQL_TEST_DATABASE' => (string) $configuration['database'],
            'CMS_MYSQL_TEST_HOST' => (string) $configuration['host'],
            'CMS_MYSQL_TEST_PORT' => (string) $configuration['port'],
            'CMS_MYSQL_TEST_USERNAME' => (string) $configuration['username'],
            'CMS_MYSQL_TEST_PASSWORD' => (string) $configuration['password'],
        ];
    }

    public static function assertApplicationUsesIsolatedSqlite(Application $application): void
    {
        $violations = [];

        if (! $application->environment('testing')) {
            $violations[] = 'APP_ENV';
        }

        if (config('database.default') !== 'sqlite') {
            $violations[] = 'database.default';
        }

        if (config('database.connections.sqlite.database') !== ':memory:') {
            $violations[] = 'database.connections.sqlite.database';
        }

        if (! in_array(config('database.connections.sqlite.url'), [null, '', '(null)'], true)) {
            $violations[] = 'database.connections.sqlite.url';
        }

        if ($violations !== []) {
            throw new RuntimeException('Unsafe PHPUnit application database configuration: '.implode(', ', $violations).'.');
        }
    }

    public static function bootstrap(): void
    {
        self::assertIsolatedDefaultEnvironment(self::runtimeEnvironment());

        $safeEnvironment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DATABASE_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'SESSION_PATH' => '/',
        ];

        foreach ($safeEnvironment as $key => $value) {
            self::setEnvironmentValue($key, $value);
        }

        $configCache = 'bootstrap/cache/phpunit-config-'.bin2hex(random_bytes(16)).'.php';
        $configCachePath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $configCache);

        if (file_exists($configCachePath)) {
            throw new RuntimeException('Could not reserve an unused PHPUnit config-cache path.');
        }

        self::setEnvironmentValue('APP_CONFIG_CACHE', $configCache);
    }

    /** @return array<string, string|false|null> */
    private static function runtimeEnvironment(): array
    {
        $keys = [
            'APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'DATABASE_URL', 'DB_HOST', 'DB_PORT',
            'CACHE_STORE', 'SESSION_DRIVER', 'SESSION_PATH', 'DB_USERNAME', 'DB_PASSWORD',
            'CMS_MYSQL_TEST_ENABLED', 'CMS_MYSQL_TEST_DATABASE', 'CMS_MYSQL_TEST_HOST',
            'CMS_MYSQL_TEST_PORT', 'CMS_MYSQL_TEST_USERNAME', 'CMS_MYSQL_TEST_PASSWORD',
        ];
        $environment = [];

        foreach ($keys as $key) {
            $value = getenv($key);
            $environment[$key] = $value === false ? ($_ENV[$key] ?? $_SERVER[$key] ?? false) : $value;
        }

        return $environment;
    }

    private static function setEnvironmentValue(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
