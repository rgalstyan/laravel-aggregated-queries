<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Partner;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Profile;
use Rgalstyan\LaravelAggregatedQueries\Tests\TestCase;

final class BelongsToRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipIfRequiredExtensionMissing();
        parent::setUp();
    }

    /** @test */
    public function it_handles_null_belongs_to_relation(): void
    {
        Partner::query()->create(['name' => 'Orphan Partner']);

        $result = Partner::aggregatedQuery()
            ->withJsonRelation('profile', ['id', 'name'])
            ->get()
            ->firstWhere('name', 'Orphan Partner');

        self::assertNull($result['profile'], 'Relation should be null when no record exists.');
    }

    /** @test */
    public function it_selects_specific_columns_from_relation(): void
    {
        $profile = Profile::query()->create(['name' => 'Jane', 'avatar' => 'avatar.png']);
        Partner::query()->create(['name' => 'Selective', 'profile_id' => $profile->id]);

        $result = Partner::aggregatedQuery()
            ->withJsonRelation('profile', ['id'])
            ->get()
            ->firstWhere('name', 'Selective');

        self::assertSame(['id' => $profile->id], $result['profile']);
    }
}
