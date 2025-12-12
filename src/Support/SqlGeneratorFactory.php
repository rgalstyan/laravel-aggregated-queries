<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Support;

use Rgalstyan\LaravelAggregatedQueries\Contracts\SqlGeneratorInterface;
use Rgalstyan\LaravelAggregatedQueries\Exceptions\DatabaseNotSupportedException;
use Rgalstyan\LaravelAggregatedQueries\Generators\MySqlGenerator;
use Rgalstyan\LaravelAggregatedQueries\Generators\PostgresGenerator;

final class SqlGeneratorFactory
{
    public static function make(string $driver, string $baseTable, string $baseAlias = 'base'): SqlGeneratorInterface
    {
        return match ($driver) {
            'mysql', 'sqlite' => new MySqlGenerator($baseTable, $baseAlias),
            'pgsql', 'postgres', 'postgresql' => new PostgresGenerator($baseTable, $baseAlias),
            default => throw new DatabaseNotSupportedException(sprintf('Database driver "%s" is not supported.', $driver)),
        };
    }
}
