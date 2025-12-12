<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionException;
use ReflectionMethod;
use Rgalstyan\LaravelAggregatedQueries\Contracts\RelationResolverInterface;
use Rgalstyan\LaravelAggregatedQueries\Exceptions\InvalidAggregationException;
use Rgalstyan\LaravelAggregatedQueries\Exceptions\UnsupportedRelationException;
use Rgalstyan\LaravelAggregatedQueries\RelationResolvers\BelongsToResolver;
use Rgalstyan\LaravelAggregatedQueries\RelationResolvers\HasManyResolver;
use Rgalstyan\LaravelAggregatedQueries\RelationResolvers\HasOneResolver;

/**
 * Inspects Eloquent relations and returns structured metadata.
 */
final class RelationAnalyzer
{
    /**
     * @var array<int, RelationResolverInterface>
     */
    private array $resolvers;

    /**
     * @param array<int, RelationResolverInterface>|null $resolvers
     */
    public function __construct(?array $resolvers = null)
    {
        $this->resolvers = $resolvers ?? [
            new BelongsToResolver(),
            new HasOneResolver(),
            new HasManyResolver(),
        ];
    }

    /**
     * Analyze the relation definition on the provided model.
     *
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function analyze(Model $model, string $relationName): array
    {
        $relationName = trim($relationName);
        if ($relationName === '') {
            throw new InvalidAggregationException('Relation name cannot be empty.');
        }

        if (!method_exists($model, $relationName)) {
            throw new InvalidAggregationException(sprintf(
                'Relation "%s" does not exist on model %s.',
                $relationName,
                $model::class
            ));
        }

        $relation = $this->invokeRelation($model, $relationName);

        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($relation)) {
                return $resolver->resolve($relation, $model, $relationName);
            }
        }

        throw new UnsupportedRelationException(sprintf(
            'Relation "%s" on %s is not supported.',
            $relationName,
            $model::class
        ));
    }

    /**
     * @throws ReflectionException
     */
    private function invokeRelation(Model $model, string $relationName): Relation
    {
        $method = new ReflectionMethod($model, $relationName);

        if ($method->getNumberOfRequiredParameters() > 0) {
            throw new InvalidAggregationException(sprintf(
                'Relation "%s" must not require parameters.',
                $relationName
            ));
        }

        $relation = $method->invoke($model);
        if (!$relation instanceof Relation) {
            throw new InvalidAggregationException(sprintf(
                'Method "%s" on %s must return a Relation instance.',
                $relationName,
                $model::class
            ));
        }

        return $relation;
    }
}
