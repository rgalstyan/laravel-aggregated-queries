<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Partner;

/**
 * Load multiple relations and counts in a single query.
 */
$partners = Partner::aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name'])
    ->withJsonRelation('country', ['id', 'name', 'code'])
    ->withJsonCollection('promocodes', ['id', 'code'])
    ->withCount('promocodes')
    ->orderBy('partners.created_at', 'desc')
    ->get();

$partners->each(function (array $partner): void {
    printf("%s (%s) -> %d promocodes\n", $partner['name'], $partner['country']['code'] ?? 'n/a', $partner['promocodes_count'] ?? 0);
});
