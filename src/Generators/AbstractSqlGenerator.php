<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Generators;

use Rgalstyan\LaravelAggregatedQueries\Contracts\SqlGeneratorInterface;

/**
 * Base SQL generator that stores contextual information about the root table.
 */
abstract class AbstractSqlGenerator implements SqlGeneratorInterface
{
    protected string $baseTable;
    protected string $baseAlias;

    public function __construct(string $baseTable, string $baseAlias = 'base')
    {
        $this->baseTable = $baseTable;
        $this->baseAlias = $baseAlias;
    }

    /**
     * Build the SELECT clause that contains root columns and aggregated relations.
     *
     * @param array<int, string> $baseColumns
     * @param array<int, array<string, mixed>> $relations
     */
    abstract public function buildSelectClause(array $baseColumns, array $relations): string;

    /**
     * Build JOIN clauses needed for aggregating relations.
     *
     * @param array<int, array<string, mixed>> $relations
     */
    abstract public function buildJoinClause(array $relations): string;
}
