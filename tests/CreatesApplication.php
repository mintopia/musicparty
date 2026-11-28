<?php

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        self::applyTestEnvironment();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        self::assertSafeDatabase($app['config']);
        self::forbidNonSqliteConnections($app['config']);

        return $app;
    }

    /**
     * Pest's parallel runner boots the app before phpunit.xml env values are applied,
     * so .env would otherwise win and leak into the worker processes.
     */
    public static function applyTestEnvironment(): void
    {
        $environment = [
            'APP_ENV' => 'testing',
            'BCRYPT_ROUNDS' => '4',
            'CACHE_STORE' => 'array',
            ...(self::usesMariaDb()
                ? ['DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => (string) getenv('TEST_DB_DATABASE'), 'DB_URL' => '']
                : ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '']),
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * The CI MariaDB job opts in with TEST_DB_DRIVER=mariadb and TEST_DB_DATABASE (a name ending in _test), since phpunit.xml forces DB_DATABASE.
     */
    public static function usesMariaDb(): bool
    {
        return getenv('TEST_DB_DRIVER') === 'mariadb';
    }

    private static function isSafeConnection(array $connection): bool
    {
        if (self::usesMariaDb()) {
            return ($connection['driver'] ?? null) === 'mariadb'
                && str_ends_with((string) ($connection['database'] ?? ''), '_test');
        }

        return ($connection['driver'] ?? null) === 'sqlite' && ($connection['database'] ?? null) === ':memory:';
    }

    public static function assertSafeDatabase(Repository $config): void
    {
        $default = $config->get('database.default');
        $connection = $config->get("database.connections.{$default}", []);

        if (! self::isSafeConnection($connection)) {
            throw new RuntimeException(
                "Refusing to run tests: default connection [{$default}] must be SQLite :memory: (or a *_test MariaDB database when TEST_DB_DRIVER=mariadb)."
            );
        }
    }

    public static function forbidNonSqliteConnections(Repository $config): void
    {
        foreach ($config->get('database.connections', []) as $name => $connection) {
            if ($name !== $config->get('database.default') && ! self::isSafeConnection($connection)) {
                $config->set("database.connections.{$name}", ['driver' => 'forbidden-in-tests', 'name' => $name, 'database' => null]);
            }
        }
    }
}
