<?php

declare(strict_types=1);

namespace Rgalstyan\LaravelAggregatedQueries\Tests\Unit\Generators;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rgalstyan\LaravelAggregatedQueries\Generators\PostgresGenerator;

final class PostgresGeneratorTest extends TestCase
{
    private PostgresGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PostgresGenerator('partners', 'base');
    }

    #[Test]
    public function it_generates_json_object_using_postgres_functions(): void
    {
        $metadata = [
            'table' => 'profiles',
            'alias' => 'profile',
            'primary_key' => 'id',
            'owner_key' => 'id',
        ];
        $sql = $this->generator->generateJsonObject($metadata, ['id', 'name'], 'profile');

        $expected = "CASE WHEN profile.id IS NULL THEN NULL ELSE json_build_object('id', profile.id, 'name', profile.name) END AS profile";
        self::assertSame($expected, $sql, 'Postgres JSON object should rely on json_build_object.');
    }

    #[Test]
    public function it_generates_count_subquery_for_postgres(): void
    {
        $metadata = [
            'table' => 'promocodes',
            'foreign_key' => 'partner_id',
            'local_key' => 'id',
        ];
        $sql = $this->generator->generateCount($metadata, 'promocodes_count');

        $expected = "(SELECT COUNT(*) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes_count";
        self::assertSame($expected, $sql, 'COUNT syntax should match SQL standard.');
    }

    #[Test]
    public function it_generates_json_array_subquery_for_has_many(): void
    {
        $metadata = [
            'table' => 'promocodes',
            'foreign_key' => 'partner_id',
            'local_key' => 'id',
        ];

        $sql = $this->generator->generateJsonArray($metadata, ['id'], 'promocodes');

        $expected = "(SELECT COALESCE(json_agg(json_build_object('id', promocodes.id)), '[]'::json) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes";
        self::assertSame($expected, $sql, 'Postgres collections should use json_agg.');
    }

    #[Test]
    public function it_builds_select_clause_with_json_build_object(): void
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
                'columns' => ['id'],
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
            . "\n       CASE WHEN profile.id IS NULL THEN NULL ELSE json_build_object('id', profile.id, 'name', profile.name) END AS profile,"
            . "\n       (SELECT COALESCE(json_agg(json_build_object('id', promocodes.id)), '[]'::json) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes,"
            . "\n       (SELECT COUNT(*) FROM promocodes WHERE promocodes.partner_id = base.id) AS promocodes_count";
        self::assertSame($expected, $sql, 'Select clause should use Postgres JSON helpers.');
    }

    #[Test]
    public function it_builds_join_clause_for_belongs_to_relations(): void
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
        self::assertSame($expected, $sql, 'Join clause should be dialect agnostic.');
    }
}
