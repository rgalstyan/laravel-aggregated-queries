<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\RelationResolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Rgalstyan\LaravelAggregatedQueries\Contracts\RelationResolverInterface;

final class HasManyResolver implements RelationResolverInterface
{
    public function supports(Relation $relation): bool
    {
        return $relation instanceof HasMany;
    }

    public function resolve(Relation $relation, Model $model, string $relationName): array
    {
        /** @var HasMany $relation */

        return [
            'relation' => 'hasMany',
            'table' => $relation->getRelated()->getTable(),
            'alias' => $relationName,
            'foreign_key' => $relation->getForeignKeyName(),
            'local_key' => $relation->getLocalKeyName(),
            'primary_key' => $relation->getRelated()->getKeyName(),
            'related_model' => $relation->getRelated()::class,
        ];
    }
}
