<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Partner;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Promocode;
use Rgalstyan\LaravelAggregatedQueries\Tests\TestCase;

final class WithCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipIfRequiredExtensionMissing();
        parent::setUp();
    }

    /** @test */
    public function it_adds_count_for_relation(): void
    {
        $partner = Partner::query()->create(['name' => 'Partner Count']);
        Promocode::query()->create(['partner_id' => $partner->id, 'code' => 'A']);
        Promocode::query()->create(['partner_id' => $partner->id, 'code' => 'B']);

        $result = Partner::aggregatedQuery()
            ->withCount('promocodes')
            ->get()
            ->firstWhere('name', 'Partner Count');

        self::assertSame(2, $result['promocodes_count'] ?? null);
    }
}
