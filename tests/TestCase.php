<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Rgalstyan\LaravelAggregatedQueries\AggregatedQueryServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AggregatedQueryServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $driver = env('TEST_DB_CONNECTION', env('DB_CONNECTION', 'mysql'));

        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', match ($driver) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('TEST_DB_HOST', env('DB_HOST', '127.0.0.1')),
                'port' => env('TEST_DB_PORT', env('DB_PORT', 3306)),
                'database' => env('TEST_DB_DATABASE', env('DB_DATABASE', 'test_db')),
                'username' => env('TEST_DB_USERNAME', env('DB_USERNAME', 'root')),
                'password' => env('TEST_DB_PASSWORD', env('DB_PASSWORD', 'root')),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/Migrations');
    }

    protected function skipIfRequiredExtensionMissing(): void
    {
        $driver = env('TEST_DB_CONNECTION', env('DB_CONNECTION', 'mysql'));
        $extension = match ($driver) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            default => 'pdo_sqlite',
        };

        if (!extension_loaded($extension)) {
            $this->markTestSkipped(sprintf('%s extension is required for %s tests.', $extension, $driver));
        }
    }
}
