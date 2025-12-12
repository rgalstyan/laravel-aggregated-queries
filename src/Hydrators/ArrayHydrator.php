<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Hydrators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Rgalstyan\LaravelAggregatedQueries\Contracts\HydratorInterface;

/**
 * Hydrates aggregated rows into associative arrays with decoded JSON fields.
 */
final class ArrayHydrator implements HydratorInterface
{
    /**
     * @param array<int, array<string, string|int|float|bool|null>> $results
     * @param array<string, array<string, string|int|float|bool|null>> $relations
     */
    public function hydrate(array $results, Model $model, array $relations): Collection
    {
        return Collection::make($results)->map(function (array $row) use ($relations): array {
            foreach ($relations as $relation => $config) {
                if (!array_key_exists($relation, $row)) {
                    continue;
                }

                $row[$relation] = $this->decodeJsonValue($row[$relation] ?? null, $config);
            }

            return $row;
        });
    }

    /**
     * @param array<string, string|int|float|bool|null> $config
     */
    private function decodeJsonValue(string|int|float|bool|null $value, array $config): array|string|int|float|bool|null
    {
        if (!is_string($value)) {
            // Ensure json_collection always returns array, never null
            if ($value === null && ($config['mode'] ?? '') === 'json_collection') {
                return [];
            }
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Extra safety for json_collection
            if ($decoded === null && ($config['mode'] ?? '') === 'json_collection') {
                return [];
            }
            return $decoded;
        }

        return $value;
    }
}
