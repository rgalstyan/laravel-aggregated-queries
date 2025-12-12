<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

trait HasAggregatedQueries
{
    /**
     * Begin an aggregated query for the model.
     */
    public static function aggregatedQuery(?EloquentBuilder $baseQuery = null): AggregatedQueryBuilder
    {
        /** @var Model $instance */
        $instance = new static();

        return new AggregatedQueryBuilder($instance, baseQuery: $baseQuery);
    }
}
