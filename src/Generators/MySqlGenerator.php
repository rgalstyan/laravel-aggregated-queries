<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Generators;

use Rgalstyan\LaravelAggregatedQueries\Exceptions\InvalidAggregationException;

/**
 * Generates MySQL specific SQL using JSON aggregation helpers.
 */
final class MySqlGenerator extends AbstractSqlGenerator
{
    /**
     * Example output:
     * SELECT base.*, JSON_OBJECT('id', profile.id, 'name', profile.name) AS profile
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
            'CASE WHEN %s.%s IS NULL THEN NULL ELSE JSON_OBJECT(%s) END AS %s',
            $alias,
            $primaryKey,
            $expression,
            $relationName
        );
    }

    /**
     * Example output:
     * JSON_ARRAYAGG(JSON_OBJECT('id', comments.id, 'body', comments.body)) AS comments
     */
    public function generateJsonArray(array $metadata, array $columns, string $relationName): string
    {
        $pairs = [];
        foreach ($columns as $column) {
            $pairs[] = sprintf("'%s', %s.%s", $column, $metadata['table'], $column);
        }

        $expression = implode(', ', $pairs);
        $object = sprintf('JSON_OBJECT(%s)', $expression);

        return sprintf(
            '(SELECT COALESCE(JSON_ARRAYAGG(%s), JSON_ARRAY()) FROM %s WHERE %s.%s = %s.%s) AS %s',
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
     * Example output:
     * SELECT base.*, JSON_OBJECT('id', profile.id) AS profile, (SELECT COUNT(*) ...) AS promocodes_count
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
     * Example output:
     * LEFT JOIN profiles profile ON profile.id = base.profile_id
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
