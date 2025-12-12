<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Rgalstyan\LaravelAggregatedQueries\Contracts\SqlGeneratorInterface;
use Rgalstyan\LaravelAggregatedQueries\Support\SqlGeneratorFactory;

final class AggregatedQueryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register bindings and merge configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/aggregated-queries.php', 'aggregated-queries');

        $this->app->singleton(SqlGeneratorInterface::class, static function (): SqlGeneratorInterface {
            $driver = DB::connection()->getDriverName();

            return SqlGeneratorFactory::make($driver, '', 'base');
        });
    }

    /**
     * Bootstrap configuration publishing hooks.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/aggregated-queries.php' => $this->app->configPath('aggregated-queries.php'),
        ], 'aggregated-queries-config');

        $this->registerSqliteJsonFunctions();
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [];
    }

    private function registerSqliteJsonFunctions(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        $pdo->sqliteCreateFunction('JSON_OBJECT', static function (...$args): string {
            $result = [];
            $count = count($args);
            for ($i = 0; $i < $count; $i += 2) {
                $key = (string) ($args[$i] ?? '');
                $value = $args[$i + 1] ?? null;
                $result[$key] = $value;
            }

            return json_encode($result, JSON_THROW_ON_ERROR);
        }, -1);

        $pdo->sqliteCreateFunction('JSON_ARRAY', static function (...$args): string {
            return json_encode($args, JSON_THROW_ON_ERROR);
        }, -1);

        $pdo->sqliteCreateAggregate(
            'JSON_ARRAYAGG',
            static function (?array &$context, mixed $value): void {
                $context ??= [];
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                $context[] = $decoded ?? $value;
            },
            static function (?array &$context): string {
                return json_encode($context ?? [], JSON_THROW_ON_ERROR);
            },
            1
        );
    }
}
