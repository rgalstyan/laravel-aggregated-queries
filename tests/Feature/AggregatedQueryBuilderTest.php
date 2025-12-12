<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Country;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Partner;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Profile;
use Rgalstyan\LaravelAggregatedQueries\Tests\Fixtures\Models\Promocode;
use Rgalstyan\LaravelAggregatedQueries\Tests\TestCase;

final class AggregatedQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipIfRequiredExtensionMissing();
        parent::setUp();

        $profile = Profile::query()->create(['name' => 'John Doe', 'avatar' => 'avatar.jpg']);
        $country = Country::query()->create(['name' => 'USA', 'code' => 'US']);

        $partner = Partner::query()->create([
            'name' => 'Partner A',
            'profile_id' => $profile->id,
            'country_id' => $country->id,
        ]);

        Promocode::query()->create(['partner_id' => $partner->id, 'code' => 'PROMO1']);
        Promocode::query()->create(['partner_id' => $partner->id, 'code' => 'PROMO2']);

        Partner::query()->create(['name' => 'Partner B']);
    }

    /** @test */
    public function it_loads_belongs_to_relation_as_json(): void
    {
        $result = Partner::aggregatedQuery()
            ->withJsonRelation('profile', ['id', 'name'])
            ->get()
            ->first();

        self::assertIsArray($result, 'Result row should be an array.');
        self::assertIsArray($result['profile'], 'BelongsTo relation must be decoded to array.');
        self::assertEquals('John Doe', $result['profile']['name']);
    }

    /** @test */
    public function it_loads_multiple_relations(): void
    {
        $result = Partner::aggregatedQuery()
            ->withJsonRelation('profile', ['id'])
            ->withJsonRelation('country', ['id', 'name'])
            ->get()
            ->first();

        self::assertArrayHasKey('country', $result);
        self::assertEquals('USA', $result['country']['name']);
    }

    /** @test */
    public function it_loads_has_many_collection(): void
    {
        $result = Partner::aggregatedQuery()
            ->withJsonCollection('promocodes', ['id', 'code'])
            ->get()
            ->first();

        self::assertIsArray($result['promocodes'], 'HasMany collection should decode to array.');
        self::assertCount(2, $result['promocodes']);
    }

    /** @test */
    public function it_applies_where_clauses(): void
    {
        $results = Partner::aggregatedQuery()
            ->withJsonRelation('profile', ['id'])
            ->where('name', 'Partner B')
            ->get();

        self::assertCount(1, $results);
        self::assertSame('Partner B', $results->first()['name']);
    }

    /** @test */
    public function it_accepts_existing_base_query(): void
    {
        Partner::query()->create(['name' => 'Partner Filtered']);

        $baseQuery = Partner::query()
            ->where('name', '!=', 'Partner B')
            ->whereHas('profile', static function ($query): void {
                $query->where('name', 'John Doe');
            });

        $results = Partner::aggregatedQuery($baseQuery)
            ->withJsonRelation('profile', ['id', 'name'])
            ->get();

        self::assertNotEmpty($results);
        self::assertSame('Partner A', $results->first()['name']);
        self::assertSame('John Doe', $results->first()['profile']['name']);
    }

    /** @test */
    public function it_applies_order_by(): void
    {
        $results = Partner::aggregatedQuery()
            ->orderBy('name', 'desc')
            ->get();

        self::assertSame('Partner B', $results->first()['name']);
    }

    /** @test */
    public function it_applies_limit(): void
    {
        Partner::query()->create(['name' => 'Partner C']);
        Partner::query()->create(['name' => 'Partner D']);

        $results = Partner::aggregatedQuery()
            ->limit(2)
            ->get();

        self::assertCount(2, $results);
    }

    /** @test */
    public function it_applies_offset(): void
    {
        $results = Partner::aggregatedQuery()
            ->orderBy('name', 'asc')
            ->offset(1)
            ->get();

        self::assertSame('Partner B', $results->first()['name']);
    }

    /** @test */
    public function it_applies_limit_and_offset(): void
    {
        Partner::query()->create(['name' => 'Partner C']);
        Partner::query()->create(['name' => 'Partner D']);

        $results = Partner::aggregatedQuery()
            ->orderBy('name', 'asc')
            ->offset(1)
            ->limit(2)
            ->get();

        self::assertCount(2, $results);
        self::assertSame('Partner B', $results->first()['name']);
    }

    /** @test */
    public function it_loads_all_columns_with_asterisk(): void
    {
        $result = Partner::aggregatedQuery()
            ->withJsonRelation('profile')
            ->get()
            ->first();

        self::assertIsArray($result['profile'], 'Profile relation should be decoded to array.');
        self::assertArrayHasKey('id', $result['profile']);
        self::assertArrayHasKey('name', $result['profile']);
        self::assertArrayHasKey('avatar', $result['profile']);
        self::assertSame('John Doe', $result['profile']['name']);
        self::assertSame('avatar.jpg', $result['profile']['avatar']);
    }

    /** @test */
    public function it_executes_minimal_queries_with_multiple_relations(): void
    {
        // Enable query logging
        \DB::enableQueryLog();
        \DB::flushQueryLog();

        // Execute aggregated query with multiple relations
        $results = Partner::aggregatedQuery()
            ->withJsonRelation('profile')
            ->withJsonRelation('country')
            ->withJsonCollection('promocodes', ['id', 'code'])
            ->get();

        $queries = \DB::getQueryLog();

        // Count actual SELECT queries (excluding metadata queries)
        $selectQueries = array_filter($queries, function ($query) {
            $sql = strtolower($query['query']);
            // Exclude schema/metadata queries
            return str_starts_with($sql, 'select')
                && !str_contains($sql, 'information_schema')
                && !str_contains($sql, 'pg_catalog')
                && !str_contains($sql, 'sqlite_master');
        });

        // Should be exactly 1 SELECT query (the aggregated one)
        self::assertCount(
            1,
            $selectQueries,
            sprintf(
                'Expected exactly 1 SELECT query, but got %d queries. Queries: %s',
                count($selectQueries),
                json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
            )
        );

        // Verify results are correct
        self::assertNotEmpty($results);
        $first = $results->first();
        self::assertArrayHasKey('profile', $first);
        self::assertArrayHasKey('country', $first);
        self::assertArrayHasKey('promocodes', $first);

        \DB::disableQueryLog();
    }
}
