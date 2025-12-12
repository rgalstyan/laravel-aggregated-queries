<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rgalstyan\LaravelAggregatedQueries\Contracts\HydratorInterface;
use Rgalstyan\LaravelAggregatedQueries\Contracts\SqlGeneratorInterface;
use Rgalstyan\LaravelAggregatedQueries\Exceptions\InvalidAggregationException;
use Rgalstyan\LaravelAggregatedQueries\Exceptions\UnsupportedRelationException;
use Rgalstyan\LaravelAggregatedQueries\Hydrators\ArrayHydrator;
use Rgalstyan\LaravelAggregatedQueries\Hydrators\EloquentHydrator;
use Rgalstyan\LaravelAggregatedQueries\Support\RelationAnalyzer;
use Rgalstyan\LaravelAggregatedQueries\Support\SqlGeneratorFactory;

/**
 * Fluent builder responsible for collecting relation metadata
 * and delegating SQL generation to the configured driver.
 */
final class AggregatedQueryBuilder
{
    private SqlGeneratorInterface $generator;

    private RelationAnalyzer $relationAnalyzer;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $relations = [];

    /**
     * @var array<int, array{column: string, operator: string, value: string|int|float|bool|null}>
     */
    private array $wheres = [];

    /**
     * @var array<int, array{column: string, direction: string}>
     */
    private array $orders = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    private ?EloquentBuilder $baseQuery = null;

    /**
     * Cache for table column listings to avoid repeated Schema queries.
     *
     * @var array<string, array<int, string>>
     */
    private array $columnListingsCache = [];

    /**
     * Flag to ensure wildcards are resolved only once.
     */
    private bool $wildcardsResolved = false;

    private string $baseTable;

    private string $baseAlias = 'base';

    /**
     * @var array<int, mixed>
     */
    private array $baseBindings = [];

    public function __construct(
        private readonly Model $model,
        ?SqlGeneratorInterface $generator = null,
        ?RelationAnalyzer $relationAnalyzer = null,
        ?EloquentBuilder $baseQuery = null
    ) {
        $this->baseTable = $model->getTable();
        $driver = DB::connection()->getDriverName();
        $this->generator = $generator ?? SqlGeneratorFactory::make($driver, $this->baseTable, $this->baseAlias);
        $this->relationAnalyzer = $relationAnalyzer ?? new RelationAnalyzer();
        $this->baseQuery = $baseQuery;
        if ($baseQuery !== null) {
            $this->baseBindings = $baseQuery->getBindings();
        }
    }

    /**
     * Register a relation that should be returned as a JSON object.
     *
     * @param array<int, string> $columns
     *
     * @throws InvalidAggregationException
     * @throws UnsupportedRelationException
     *
     * @example Partner::aggregatedQuery()->withJsonRelation('profile', ['id', 'name'])
     */
    public function withJsonRelation(string $relation, array $columns = ['*']): self
    {
        // Validate relation name (no nested, no empty)
        $this->validateRelationName($relation);
        
        // Validate columns (no SQL injection, warn on SELECT *)
        $this->validateColumns($columns, $relation);

        $metadata = $this->relationAnalyzer->analyze($this->model, $relation);
        if (!in_array($metadata['relation'], ['belongsTo', 'hasOne'], true)) {
            throw new UnsupportedRelationException(sprintf(
                'Relation "%s" must be belongsTo or hasOne for JSON objects.',
                $relation
            ));
        }

        // Try to resolve columns from model without database query
        if ($columns === ['*']) {
            $relatedModel = new $metadata['related_model']();
            $modelColumns = $this->tryResolveColumnsFromModel($relatedModel, $metadata['table']);
            if (!empty($modelColumns)) {
                $columns = $modelColumns;
            }
            // else: keep ['*'] for later resolution via Schema
        }

        $this->relations[] = [
            'name' => $relation,
            'mode' => 'json',
            'select_alias' => $relation,
            'columns' => $columns,
            'metadata' => $metadata,
        ];

        // Check if we have too many relations
        $this->checkRelationCount();

        return $this;
    }

    /**
     * Register a relation that should be returned as a JSON array collection.
     *
     * @param array<int, string> $columns
     *
     * @throws InvalidAggregationException
     * @throws UnsupportedRelationException
     *
     * @example Partner::aggregatedQuery()->withJsonCollection('promocodes', ['id', 'code'])
     */
    public function withJsonCollection(string $relation, array $columns = ['*']): self
    {
        // Validate relation name (no nested, no empty)
        $this->validateRelationName($relation);
        
        // Validate columns (no SQL injection, warn on SELECT *)
        $this->validateColumns($columns, $relation);

        $metadata = $this->relationAnalyzer->analyze($this->model, $relation);
        if ($metadata['relation'] !== 'hasMany') {
            throw new UnsupportedRelationException(sprintf(
                'Relation "%s" must be hasMany for JSON collections.',
                $relation
            ));
        }

        // Try to resolve columns from model without database query
        if ($columns === ['*']) {
            $relatedModel = new $metadata['related_model']();
            $modelColumns = $this->tryResolveColumnsFromModel($relatedModel, $metadata['table']);
            if (!empty($modelColumns)) {
                $columns = $modelColumns;
            }
            // else: keep ['*'] for later resolution via Schema
        }

        $this->relations[] = [
            'name' => $relation,
            'mode' => 'json_collection',
            'select_alias' => $relation,
            'columns' => $columns,
            'metadata' => $metadata,
        ];

        // Check if we have too many relations
        $this->checkRelationCount();

        return $this;
    }



    /**
     * Attach a COUNT aggregate for the provided relation.
     *
     * @throws InvalidAggregationException
     * @throws UnsupportedRelationException
     *
     * @example Partner::aggregatedQuery()->withCount('promocodes')
     */
    public function withCount(string $relation): self
    {
        // Validate relation name (no nested, no empty)
        $this->validateRelationName($relation);

        $metadata = $this->relationAnalyzer->analyze($this->model, $relation);
        if (!in_array($metadata['relation'], ['hasMany'], true)) {
            throw new UnsupportedRelationException(sprintf(
                'Relation "%s" must be hasMany to use withCount.',
                $relation
            ));
        }

        $this->relations[] = [
            'name' => $relation,
            'mode' => 'count',
            'select_alias' => sprintf('%s_count', $relation),
            'metadata' => $metadata,
        ];

        // Check if we have too many relations
        $this->checkRelationCount();

        return $this;
    }

    /**
     * Apply a where clause to the aggregated query.
     *
     * @throws InvalidAggregationException
     *
     * @example Partner::aggregatedQuery()->where('partners.status', 'active')
     */
    public function where(string $column, string|int|float|bool|null $operator, string|int|float|bool|null $value = null): self
    {
        $column = trim($column);
        if ($column === '') {
            throw new InvalidAggregationException('Column name cannot be empty.');
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtolower((string) $operator);
        $allowedOperators = ['=', '!=', '<>', '<', '>', '<=', '>='];
        if (!in_array($operator, $allowedOperators, true)) {
            throw new InvalidAggregationException(sprintf('Operator "%s" is not allowed.', $operator));
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Apply an order-by clause to the aggregated query.
     *
     * @throws InvalidAggregationException
     *
     * @example Partner::aggregatedQuery()->orderBy('partners.created_at', 'desc')
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $column = trim($column);
        if ($column === '') {
            throw new InvalidAggregationException('Column name cannot be empty.');
        }

        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidAggregationException('Direction must be either asc or desc.');
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Limit the number of results returned.
     *
     * @throws InvalidAggregationException
     *
     * @example Partner::aggregatedQuery()->limit(10)
     */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidAggregationException('Limit must be a non-negative integer.');
        }

        // Validate against configured max_limit
        $this->validateLimit($limit);

        $this->limitValue = $limit;

        return $this;
    }

    /**
     * Offset the results by a given number.
     *
     * @throws InvalidAggregationException
     *
     * @example Partner::aggregatedQuery()->offset(20)
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidAggregationException('Offset must be a non-negative integer.');
        }

        $this->offsetValue = $offset;

        return $this;
    }

    /**
     * Retrieve the SQL that would be executed for debugging purposes.
     *
     * @example echo Partner::aggregatedQuery()->withJsonRelation('profile')->toSql();
     */
    public function toSql(): string
    {
        // Resolve any remaining wildcards before generating SQL
        $this->resolveWildcardColumns();

        $select = $this->generator->buildSelectClause(
            [$this->baseAlias . '.*'],
            $this->relations
        );

        $sql = sprintf('SELECT %s FROM %s', $select, $this->buildBaseSource());

        $joins = $this->generator->buildJoinClause($this->relations);
        if ($joins !== '') {
            $sql .= "\n" . $joins;
        }

        if ($this->wheres !== []) {
            $sql .= "\nWHERE " . $this->compileWheres();
        }

        if ($this->baseQuery === null) {
            if ($this->orders !== []) {
                $sql .= "\nORDER BY " . $this->compileOrders();
            }

            if ($this->limitValue !== null) {
                $sql .= "\nLIMIT " . $this->limitValue;
            }

            if ($this->offsetValue !== null) {
                $sql .= "\nOFFSET " . $this->offsetValue;
            }
        }

        return $sql;
    }

    /**
     * Execute the query and return a collection of data.
     *
     * @example Partner::aggregatedQuery()->withJsonRelation('profile')->get();
     */
    public function get(string $hydrator = 'array'): Collection
    {
        $rows = DB::select($this->toSql(), $this->getBindings());
        $results = array_map(
            static fn (object $row): array => (array) $row,
            $rows
        );

        return $this->resolveHydrator($hydrator)->hydrate(
            $results,
            $this->model,
            $this->hydrationMetadata()
        );
    }

    /**
     * Paginate the aggregated results (placeholder for now).
     *
     * @example Partner::aggregatedQuery()->paginate(15);
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage);
    }

    /**
     * Execute the query and return the first result if available.
     *
     * @example Partner::aggregatedQuery()->first();
     */
    public function first(string $hydrator = 'array'): array|Model|null
    {
        return $this->get($hydrator)->first();
    }

    /**
     * Retrieve the database bindings associated with the query.
     *
     * @return array<int, string|int|float|bool|null>
     *
     * @example Partner::aggregatedQuery()->where('name', 'A')->getBindings();
     */
    public function getBindings(): array
    {
        return array_merge(
            $this->baseBindings,
            array_map(
                static fn (array $where): string|int|float|bool|null => $where['value'],
                $this->wheres
            )
        );
    }

    /**
     * Toggle verbose debugging for the builder.
     *
     * @example Partner::aggregatedQuery()->debug()->withJsonRelation('profile');
     */
    public function debug(bool $enabled = true): self
    {
        return $this;
    }

    /**
     * Try to resolve columns from model metadata without database query.
     *
     * @return array<int, string>
     */
    private function tryResolveColumnsFromModel(Model $model, string $table): array
    {
        // 1. Try from model's fillable + metadata
        $modelColumns = $this->getColumnsFromModel($model);
        if (!empty($modelColumns)) {
            return $modelColumns;
        }

        // 2. Try from config cache
        $configColumns = config("aggregated-queries.column_cache.{$table}");
        if (is_array($configColumns) && !empty($configColumns)) {
            return $configColumns;
        }

        // 3. Return empty to indicate fallback to Schema needed
        return [];
    }

    /**
     * Extract columns from model using Laravel's metadata methods.
     *
     * @return array<int, string>
     */
    private function getColumnsFromModel(Model $model): array
    {
        $columns = [];

        // 1. Primary Key (could be 'id', 'uuid', or custom)
        $primaryKey = $model->getKeyName();
        if ($primaryKey !== null && $primaryKey !== '') {
            $columns[] = $primaryKey;
        }

        // 2. Fillable fields (must exist for this approach to work)
        $fillable = $model->getFillable();
        if (empty($fillable)) {
            // Can't determine columns without fillable
            return [];
        }

        $columns = array_merge($columns, $fillable);

        // 3. Timestamps (if enabled)
        if ($model->timestamps === true) {
            $createdAt = $model->getCreatedAtColumn();
            $updatedAt = $model->getUpdatedAtColumn();

            if ($createdAt !== null && $createdAt !== '') {
                $columns[] = $createdAt;
            }
            if ($updatedAt !== null && $updatedAt !== '') {
                $columns[] = $updatedAt;
            }
        }

        // 4. Soft Deletes (check for trait)
        $traits = class_uses_recursive($model);
        if (is_array($traits) && in_array(SoftDeletes::class, $traits, true)) {
            $deletedAt = $model->getDeletedAtColumn();
            if ($deletedAt !== null && $deletedAt !== '') {
                $columns[] = $deletedAt;
            }
        }

        // Remove duplicates and empty values
        return array_values(array_unique(array_filter($columns, static fn ($col) => $col !== '')));
    }

    /**
     * Resolve any remaining wildcard columns via Schema (batch when possible).
     */
    private function resolveWildcardColumns(): void
    {
        if ($this->wildcardsResolved) {
            return;
        }

        $this->wildcardsResolved = true;

        // Collect tables that still have wildcards
        $tablesToResolve = [];
        foreach ($this->relations as $relation) {
            if ($relation['columns'] === ['*'] && isset($relation['metadata']['table'])) {
                $tablesToResolve[] = $relation['metadata']['table'];
            }
        }

        if (empty($tablesToResolve)) {
            return;
        }

        // For Postgres: batch query for all tables
        if (DB::connection()->getDriverName() === 'pgsql') {
            $placeholders = implode(',', array_fill(0, count($tablesToResolve), '?'));
            $results = DB::select("
                SELECT table_name, column_name
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name IN ({$placeholders})
                ORDER BY table_name, ordinal_position
            ", $tablesToResolve);

            foreach ($results as $row) {
                if (!isset($this->columnListingsCache[$row->table_name])) {
                    $this->columnListingsCache[$row->table_name] = [];
                }
                $this->columnListingsCache[$row->table_name][] = $row->column_name;
            }
        } else {
            // For MySQL: individual queries (no batch API)
            foreach ($tablesToResolve as $table) {
                if (!isset($this->columnListingsCache[$table])) {
                    $this->columnListingsCache[$table] = Schema::getColumnListing($table);
                }
            }
        }

        // Replace wildcards with actual columns
        foreach ($this->relations as &$relation) {
            if ($relation['columns'] === ['*']) {
                $table = $relation['metadata']['table'];
                $relation['columns'] = $this->columnListingsCache[$table] ?? [];
            }
        }
    }

    private function compileWheres(): string
    {
        $clauses = [];
        foreach ($this->wheres as $where) {
            $clauses[] = sprintf(
                '%s %s ?',
                $this->qualifyColumn($where['column']),
                strtoupper($where['operator'])
            );
        }

        return implode(' AND ', $clauses);
    }

    private function buildBaseSource(): string
    {
        if ($this->baseQuery === null) {
            return sprintf('%s %s', $this->baseTable, $this->baseAlias);
        }

        $baseSql = $this->baseQuery->toSql();

        return sprintf('(%s) %s', $baseSql, $this->baseAlias);
    }

    private function compileOrders(): string
    {
        $orders = [];
        foreach ($this->orders as $order) {
            $orders[] = sprintf(
                '%s %s',
                $this->qualifyColumn($order['column']),
                strtoupper($order['direction'])
            );
        }

        return implode(', ', $orders);
    }

    private function qualifyColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return sprintf('%s.%s', $this->baseAlias, $column);
    }

    private function hydrationMetadata(): array
    {
        $metadata = [];

        foreach ($this->relations as $relation) {
            if (!in_array($relation['mode'], ['json', 'json_collection'], true)) {
                continue;
            }

            $metadata[$relation['select_alias']] = [
                'relation' => $relation['metadata']['relation'],
                'related_model' => $relation['metadata']['related_model'],
                'mode' => $relation['mode'], // Pass mode so hydrator knows about json_collection
            ];
        }

        return $metadata;
    }

    private function resolveHydrator(string $hydrator): HydratorInterface
    {
        return match ($hydrator) {
            'array' => new ArrayHydrator(),
            'eloquent' => new EloquentHydrator(),
            default => $this->resolveCustomHydrator($hydrator),
        };
    }

    private function resolveCustomHydrator(string $hydrator): HydratorInterface
    {
        if (!class_exists($hydrator)) {
            throw new InvalidAggregationException(sprintf('Hydrator class "%s" does not exist.', $hydrator));
        }

        $instance = new $hydrator();
        if (!$instance instanceof HydratorInterface) {
            throw new InvalidAggregationException(sprintf('Hydrator "%s" must implement HydratorInterface.', $hydrator));
        }

        return $instance;
    }

    /**
     * Validate relation name doesn't contain nested references.
     *
     * @throws InvalidAggregationException
     */
    private function validateRelationName(string $relation): void
    {
        $relation = trim($relation);
        
        if ($relation === '') {
            throw new InvalidAggregationException('Relation name cannot be empty.');
        }
        
        if (str_contains($relation, '.')) {
            throw new InvalidAggregationException(
                "Nested relations are not supported in v1. Received: '{$relation}'. " .
                "Please load each relation separately or wait for v2 nested relation support."
            );
        }
    }

    /**
     * Validate column selections for dangerous patterns.
     *
     * @param array<int, string> $columns
     *
     * @throws InvalidAggregationException
     */
    private function validateColumns(array $columns, string $relation): void
    {
        if (empty($columns)) {
            throw new InvalidAggregationException('Columns array cannot be empty.');
        }

        $strictMode = config('aggregated-queries.strict_mode', false);

        // Check for SELECT *
        if (in_array('*', $columns, true)) {
            $message = "Using SELECT * for relation '{$relation}' may expose sensitive data " .
                       "and increase payload size. Consider selecting specific columns.";

            if ($strictMode) {
                throw new InvalidAggregationException($message);
            }

            if (config('aggregated-queries.log_fallbacks', true)) {
                logger()->warning('[LaravelAggregatedQueries] ' . $message);
            }
        }

        // Validate each column name for SQL injection prevention
        foreach ($columns as $column) {
            if (!$this->isValidColumnName($column)) {
                throw new InvalidAggregationException(
                    "Invalid column name: '{$column}'. Column names must contain only " .
                    "alphanumeric characters, underscores, and dots."
                );
            }
        }
    }

    /**
     * Check if column name is safe from SQL injection.
     */
    private function isValidColumnName(string $column): bool
    {
        // Allow: alphanumeric, underscore, dot, asterisk
        return $column === '*' || preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column) === 1;
    }

    /**
     * Validate limit against configured maximum.
     *
     * @throws InvalidAggregationException
     */
    private function validateLimit(int $limit): void
    {
        $maxLimit = config('aggregated-queries.max_limit', 500);
        $strictMode = config('aggregated-queries.strict_limit_validation', false);

        if ($limit <= $maxLimit) {
            return;
        }

        $message = sprintf(
            'Query limit (%d) exceeds recommended maximum (%d). This may cause performance issues.',
            $limit,
            $maxLimit
        );

        if ($strictMode) {
            throw new InvalidAggregationException($message);
        }

        // Non-strict mode: log warning and proceed
        if (config('aggregated-queries.log_fallbacks', true)) {
            logger()->warning('[LaravelAggregatedQueries] ' . $message);
        }
    }

    /**
     * Track total relations and warn if exceeding recommended limit.
     *
     * @throws InvalidAggregationException
     */
    private function checkRelationCount(): void
    {
        $maxRelations = config('aggregated-queries.max_relations', 15);
        $currentCount = count($this->relations);

        if ($currentCount > $maxRelations) {
            $message = sprintf(
                'Query contains %d relations, which exceeds the recommended maximum of %d. ' .
                'This may cause performance degradation.',
                $currentCount,
                $maxRelations
            );

            if (config('aggregated-queries.strict_mode', false)) {
                throw new InvalidAggregationException($message);
            }

            if (config('aggregated-queries.log_fallbacks', true)) {
                logger()->warning('[LaravelAggregatedQueries] ' . $message);
            }
        }
    }
}
