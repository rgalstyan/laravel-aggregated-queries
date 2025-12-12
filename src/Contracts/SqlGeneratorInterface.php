<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Contracts;

interface SqlGeneratorInterface
{
    /**
     * Build SQL for selecting a JSON object representing a single related record.
     *
     * @param array<string, string> $metadata
     * @param array<int, string> $columns
     */
    public function generateJsonObject(array $metadata, array $columns, string $relationName): string;

    /**
     * Build SQL for selecting a JSON array representing multiple related records.
     *
     * @param array<string, string> $metadata
     * @param array<int, string> $columns
     */
    public function generateJsonArray(array $metadata, array $columns, string $relationName): string;

    /**
     * Build SQL for counting related records.
     *
     * @param array<string, string> $metadata
     */
    public function generateCount(array $metadata, string $relationName): string;

    /**
     * @param array<int, string> $baseColumns
     * @param array<int, array<string, mixed>> $relations
     */
    public function buildSelectClause(array $baseColumns, array $relations): string;

    /**
     * @param array<int, array<string, mixed>> $relations
     */
    public function buildJoinClause(array $relations): string;
}
