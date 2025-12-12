<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Hydrators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Rgalstyan\LaravelAggregatedQueries\Contracts\HydratorInterface;

/**
 * Hydrates aggregated rows directly into Eloquent model instances.
 */
final class EloquentHydrator implements HydratorInterface
{
    /**
     * @param array<int, array<string, string|int|float|bool|null>> $results
     * @param array<string, array<string, string|int|float|bool|null>> $relations
     */
    public function hydrate(array $results, Model $model, array $relations): Collection
    {
        return Collection::make($results)->map(function (array $row) use ($model, $relations): Model {
            $attributes = $this->filterBaseAttributes($row, array_keys($relations));
            $instance = $model->newInstance($attributes, true);

            foreach ($relations as $relation => $config) {
                if (!array_key_exists($relation, $row)) {
                    $instance->setRelation($relation, null);
                    continue;
                }

                $decoded = $this->decodeJsonValue($row[$relation]);
                if ($decoded === null) {
                    $instance->setRelation($relation, null);
                    continue;
                }

                $instance->setRelation($relation, $this->hydrateRelation($config, $decoded));
            }

            return $instance;
        });
    }

    /**
     * @param array<int, string> $relationKeys
     *
     * @return array<string, string|int|float|bool|null>
     */
    private function filterBaseAttributes(array $row, array $relationKeys): array
    {
        foreach ($relationKeys as $relationKey) {
            unset($row[$relationKey]);
        }

        return $row;
    }

    private function decodeJsonValue(string|int|float|bool|null $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * @param array<string, string|int|float|bool|null> $config
     */
    private function hydrateRelation(array $config, mixed $decoded): mixed
    {
        $relationType = $config['relation'] ?? '';
        $relatedClass = $config['related_model'] ?? null;

        if ($relatedClass === null) {
            return $decoded;
        }

        if ($relationType === 'hasMany') {
            return Collection::make(is_array($decoded) ? $decoded : [])->map(
                static fn (array $item): Model => (new $relatedClass())->newInstance($item, true)
            );
        }

        if (!is_array($decoded)) {
            return null;
        }

        /** @var Model $related */
        $related = new $relatedClass();

        return $related->newInstance($decoded, true);
    }
}
