<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Generators;

use Rgalstyan\LaravelAggregatedQueries\Exceptions\InvalidAggregationException;

/**
 * PostgreSQL-specific implementation using json_build_object/json_agg helpers.
 */
final class PostgresGenerator extends AbstractSqlGenerator
{
    /**
     * Example output:
     * SELECT base.*, json_build_object('id', profile.id, 'name', profile.name) AS profile
     */
    public function generateJsonObject(array $metadata, array $columns, string $relationName): string
    {
        $alias = $metadata['alias'];
        $primaryKey = $metadata['primary_key'] ?? $metadata['owner_key'] ?? 'id';

        $pairs = [];
        foreach ($columns as $column) {
            $pairs[] = sprintf("'%s', %s.%s", $column, $alias, $column);
        }

        $expression = implode(', ', $pairs);

        return sprintf(
            'CASE WHEN %s.%s IS NULL THEN NULL ELSE json_build_object(%s) END AS %s',
            $alias,
            $primaryKey,
            $expression,
            $relationName
        );
    }

    /**
     * Example output:
     * json_agg(json_build_object('id', comments.id, 'body', comments.body)) AS comments
     */
    public function generateJsonArray(array $metadata, array $columns, string $relationName): string
    {
        $pairs = [];
        foreach ($columns as $column) {
            $pairs[] = sprintf("'%s', %s.%s", $column, $metadata['table'], $column);
        }

        $expression = implode(', ', $pairs);
        $object = sprintf('json_build_object(%s)', $expression);

        return sprintf(
            '(SELECT COALESCE(json_agg(%s), \'[]\'::json) FROM %s WHERE %s.%s = %s.%s) AS %s',
            $object,
            $metadata['table'],
            $metadata['table'],
            $metadata['foreign_key'],
            $this->baseAlias,
            $metadata['local_key'],
            $relationName
        );
    }

    /**
     * Example output:
     * (SELECT COUNT(*) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes_count
     */
    public function generateCount(array $metadata, string $relationName): string
    {
        return sprintf(
            '(SELECT COUNT(*) FROM %s WHERE %s.%s = %s.%s) AS %s',
            $metadata['table'],
            $metadata['table'],
            $metadata['foreign_key'],
            $this->baseAlias,
            $metadata['local_key'],
            $relationName
        );
    }

    /**
     * Build SELECT clause with json_build_object/json_agg expressions.
     *
     * @param array<int, string> $baseColumns
     * @param array<int, array<string, mixed>> $relations
     */
    public function buildSelectClause(array $baseColumns, array $relations): string
    {
        $selects = $baseColumns;

        foreach ($relations as $relation) {
            $mode = $relation['mode'] ?? '';
            $metadata = $relation['metadata'] ?? [];

            if ($mode === 'json') {
                $columns = $relation['columns'] ?? [];
                $selects[] = $this->generateJsonObject($metadata, $columns, $relation['select_alias']);
            }

            if ($mode === 'json_collection') {
                $columns = $relation['columns'] ?? [];
                $selects[] = $this->generateJsonArray($metadata, $columns, $relation['select_alias']);
            }

            if ($mode === 'count') {
                $selects[] = $this->generateCount($metadata, $relation['select_alias']);
            }
        }

        return implode(",\n       ", $selects);
    }

    /**
     * Build JOIN clauses with PostgreSQL syntax (same as MySQL for structure).
     *
     * @param array<int, array<string, mixed>> $relations
     */
    public function buildJoinClause(array $relations): string
    {
        $clauses = [];

        foreach ($relations as $relation) {
            if (($relation['mode'] ?? '') !== 'json') {
                continue;
            }

            $metadata = $relation['metadata'];
            $alias = $metadata['alias'];
            $table = $metadata['table'];
            $relationType = $metadata['relation'];

            if ($relationType === 'belongsTo') {
                $clauses[] = sprintf(
                    'LEFT JOIN %s %s ON %s.%s = %s.%s',
                    $table,
                    $alias,
                    $alias,
                    $metadata['owner_key'],
                    $this->baseAlias,
                    $metadata['foreign_key']
                );
                continue;
            }

            if ($relationType === 'hasOne') {
                $clauses[] = sprintf(
                    'LEFT JOIN %s %s ON %s.%s = %s.%s',
                    $table,
                    $alias,
                    $alias,
                    $metadata['foreign_key'],
                    $this->baseAlias,
                    $metadata['local_key']
                );
                continue;
            }

            throw new InvalidAggregationException(sprintf(
                'Relation type %s is not supported for JSON joins.',
                $relationType
            ));
        }

        return implode("\n", $clauses);
    }
}
