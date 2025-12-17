<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Unit\Hydrators;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rgalstyan\LaravelAggregatedQueries\Hydrators\ArrayHydrator;

final class ArrayHydratorTest extends TestCase
{
    #[Test]
    public function it_hydrates_and_decodes_json_relations(): void
    {
        $hydrator = new ArrayHydrator();
        $model = new class extends Model {
            protected $table = 'partners';
            protected $guarded = [];
        };

        $results = [[
            'id' => 1,
            'name' => 'Partner A',
            'profile' => json_encode(['id' => 10, 'name' => 'John Doe'], JSON_THROW_ON_ERROR),
        ]];

        $relations = [
            'profile' => ['relation' => 'belongsTo'],
        ];

        $collection = $hydrator->hydrate($results, $model, $relations);

        self::assertSame(1, $collection->count(), 'Hydrated collection should contain a single record.');
        self::assertSame([
            'id' => 1,
            'name' => 'Partner A',
            'profile' => ['id' => 10, 'name' => 'John Doe'],
        ], $collection->first(), 'JSON relation must be decoded to associative array.');
    }
}
