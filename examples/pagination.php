<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Partner;

/**
 * Paginate aggregated results just like a normal Eloquent query.
 */
$paginator = Partner::aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name'])
    ->withJsonCollection('promocodes', ['id'])
    ->orderBy('partners.created_at', 'desc')
    ->paginate(15);

echo sprintf("Page %d of %d\n", $paginator->currentPage(), $paginator->lastPage());

foreach ($paginator->items() as $partner) {
    echo $partner['name'] . PHP_EOL;
}
