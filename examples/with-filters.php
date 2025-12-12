<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Partner;

/**
 * Apply filters and ordering just like the query builder.
 */
$results = Partner::aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name'])
    ->withJsonCollection('promocodes', ['id'])
    ->where('partners.status', 'active')
    ->where('partners.created_at', '>=', now()->subMonth())
    ->orderBy('partners.name')
    ->get();

$results->each(function (array $partner): void {
    printf("%s -> %d codes\n", $partner['name'], count($partner['promocodes'] ?? []));
});
