<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use Rgalstyan\LaravelAggregatedQueries\Generators\MySqlGenerator;

final class MySqlGeneratorTest extends TestCase
{
    private MySqlGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new MySqlGenerator('partners', 'base');
    }

    /** @test */
    public function it_generates_json_object_for_belongs_to(): void
    {
        $metadata = [
            'table' => 'profiles',
            'alias' => 'profile',
            'primary_key' => 'id',
            'owner_key' => 'id',
        ];
        $sql = $this->generator->generateJsonObject($metadata, ['id', 'name'], 'profile');

        $expected = "CASE WHEN profile.id IS NULL THEN NULL ELSE JSON_OBJECT('id', profile.id, 'name', profile.name) END AS profile";
        self::assertSame($expected, $sql, 'Generated JSON object SQL should match MySQL syntax.');
    }

    /** @test */
    public function it_generates_count_subquery(): void
    {
        $metadata = [
            'table' => 'promocodes',
            'foreign_key' => 'partner_id',
            'local_key' => 'id',
        ];
        $sql = $this->generator->generateCount($metadata, 'promocodes_count');

        $expected = "(SELECT COUNT(*) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes_count";
        self::assertSame($expected, $sql, 'COUNT subquery must reference base alias and foreign key.');
    }

    /** @test */
    public function it_generates_json_array_subquery_for_collections(): void
    {
        $metadata = [
            'table' => 'promocodes',
            'foreign_key' => 'partner_id',
            'local_key' => 'id',
        ];

        $sql = $this->generator->generateJsonArray($metadata, ['id', 'code'], 'promocodes');

        $expected = "(SELECT COALESCE(JSON_ARRAYAGG(JSON_OBJECT('id', promocodes.id, 'code', promocodes.code)), JSON_ARRAY()) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes";
        self::assertSame($expected, $sql, 'HasMany collections should be represented by subqueries with JSON_ARRAYAGG.');
    }

    /** @test */
    public function it_builds_select_clause_with_relations(): void
    {
        $relations = [
            [
                'mode' => 'json',
                'select_alias' => 'profile',
                'columns' => ['id', 'name'],
                'metadata' => [
                    'table' => 'profiles',
                    'alias' => 'profile',
                    'relation' => 'belongsTo',
                    'foreign_key' => 'profile_id',
                    'owner_key' => 'id',
                    'local_key' => 'id',
                    'primary_key' => 'id',
                ],
            ],
            [
                'mode' => 'json_collection',
                'select_alias' => 'promocodes',
                'columns' => ['id', 'code'],
                'metadata' => [
                    'table' => 'promocodes',
                    'alias' => 'promocodes',
                    'relation' => 'hasMany',
                    'foreign_key' => 'partner_id',
                    'local_key' => 'id',
                    'primary_key' => 'id',
                ],
            ],
            [
                'mode' => 'count',
                'select_alias' => 'promocodes_count',
                'metadata' => [
                    'table' => 'promocodes',
                    'alias' => 'promocodes',
                    'relation' => 'hasMany',
                    'foreign_key' => 'partner_id',
                    'local_key' => 'id',
                ],
            ],
        ];

        $sql = $this->generator->buildSelectClause(['base.*'], $relations);

        $expected = "base.*,"
            . "\n       CASE WHEN profile.id IS NULL THEN NULL ELSE JSON_OBJECT('id', profile.id, 'name', profile.name) END AS profile,"
            . "\n       (SELECT COALESCE(JSON_ARRAYAGG(JSON_OBJECT('id', promocodes.id, 'code', promocodes.code)), JSON_ARRAY()) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes,"
            . "\n       (SELECT COUNT(*) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes_count";
        self::assertSame($expected, $sql, 'Select clause should concatenate base columns with relation projections.');
    }

    /** @test */
    public function it_builds_join_clause_for_belongs_to(): void
    {
        $relations = [
            [
                'mode' => 'json',
                'metadata' => [
                    'table' => 'profiles',
                    'alias' => 'profile',
                    'relation' => 'belongsTo',
                    'foreign_key' => 'profile_id',
                    'owner_key' => 'id',
                    'local_key' => 'id',
                    'primary_key' => 'id',
                ],
            ],
        ];

        $sql = $this->generator->buildJoinClause($relations);

        $expected = 'LEFT JOIN profiles profile ON profile.id = base.profile_id';
        self::assertSame($expected, $sql, 'Join clause must map belongsTo relation to left join.');
    }
}
