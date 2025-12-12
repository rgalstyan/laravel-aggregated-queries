<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Partner;

/**
 * Basic usage: hydrate a belongsTo relation via JSON aggregation.
 */
$partners = Partner::aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name'])
    ->get();

foreach ($partners as $partner) {
    printf("%s -> %s\n", $partner['name'], $partner['profile']['name'] ?? 'n/a');
}
