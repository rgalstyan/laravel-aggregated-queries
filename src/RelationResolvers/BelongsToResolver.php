<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\RelationResolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Rgalstyan\LaravelAggregatedQueries\Contracts\RelationResolverInterface;

final class BelongsToResolver implements RelationResolverInterface
{
    public function supports(Relation $relation): bool
    {
        return $relation instanceof BelongsTo;
    }

    public function resolve(Relation $relation, Model $model, string $relationName): array
    {
        /** @var BelongsTo $relation */

        return [
            'relation' => 'belongsTo',
            'table' => $relation->getRelated()->getTable(),
            'alias' => $relationName,
            'foreign_key' => $relation->getForeignKeyName(),
            'owner_key' => $relation->getOwnerKeyName(),
            'local_key' => $relation->getParent()->getKeyName(),
            'primary_key' => $relation->getRelated()->getKeyName(),
            'related_model' => $relation->getRelated()::class,
        ];
    }
}
