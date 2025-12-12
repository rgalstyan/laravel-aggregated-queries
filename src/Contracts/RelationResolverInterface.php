<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

interface RelationResolverInterface
{
    /**
     * Determine if the resolver supports the provided relation.
     */
    public function supports(Relation $relation): bool;

    /**
     * Resolve metadata for the relation that is necessary for SQL generation.
     *
     * @return array<string, string>
     */
    public function resolve(Relation $relation, Model $model, string $relationName): array;
}
