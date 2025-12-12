<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\RelationResolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Rgalstyan\LaravelAggregatedQueries\Contracts\RelationResolverInterface;

final class HasOneResolver implements RelationResolverInterface
{
    public function supports(Relation $relation): bool
    {
        return $relation instanceof HasOne;
    }

    public function resolve(Relation $relation, Model $model, string $relationName): array
    {
        /** @var HasOne $relation */

        return [
            'relation' => 'hasOne',
            'table' => $relation->getRelated()->getTable(),
            'alias' => $relationName,
            'foreign_key' => $relation->getForeignKeyName(),
            'local_key' => $relation->getLocalKeyName(),
            'primary_key' => $relation->getRelated()->getKeyName(),
            'related_model' => $relation->getRelated()::class,
        ];
    }
}
