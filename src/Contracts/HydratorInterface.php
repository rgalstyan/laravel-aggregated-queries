<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface HydratorInterface
{
    /**
     * Transform raw aggregated rows into the desired structure.
     *
     * @param array<int, array<string, string|int|float|bool|null>> $results
     * @param array<string, array<string, string|int|float|bool|null>> $relations
     */
    public function hydrate(array $results, Model $model, array $relations): Collection;
}
