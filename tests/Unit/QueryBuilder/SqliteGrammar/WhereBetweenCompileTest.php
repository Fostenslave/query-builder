<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class WhereBetweenCompileTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    private function builder(string $table = 'users'): QueryBuilder
    {
        return new QueryBuilder($this->grammar, $table);
    }

    public function testWhereBetweenBasic(): void
    {
        $compiled = $this->builder()
            ->whereBetween('age', 18, 65)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "age" BETWEEN :where_0_min AND :where_0_max',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_min' => 18, ':where_0_max' => 65],
            $compiled->bindings,
        );
    }

    public function testWhereNotBetween(): void
    {
        $compiled = $this->builder()
            ->whereNotBetween('age', 18, 65)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "age" NOT BETWEEN :where_0_min AND :where_0_max',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_min' => 18, ':where_0_max' => 65],
            $compiled->bindings,
        );
    }

    public function testOrWhereBetween(): void
    {
        $compiled = $this->builder()
            ->where('name', '=', 'Alice')
            ->orWhereBetween('age', 18, 65)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "name" = :where_0 OR "age" BETWEEN :where_1_min AND :where_1_max',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 'Alice', ':where_1_min' => 18, ':where_1_max' => 65],
            $compiled->bindings,
        );
    }

    public function testOrWhereNotBetween(): void
    {
        $compiled = $this->builder()
            ->where('name', '=', 'Alice')
            ->orWhereNotBetween('age', 18, 65)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "name" = :where_0 OR "age" NOT BETWEEN :where_1_min AND :where_1_max',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 'Alice', ':where_1_min' => 18, ':where_1_max' => 65],
            $compiled->bindings,
        );
    }

    public function testWhereBetweenInGroup(): void
    {
        $compiled = $this->builder()
            ->where(function ($g) {
                $g->whereBetween('age', 18, 65);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("age" BETWEEN :where_0_0_min AND :where_0_0_max)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0_min' => 18, ':where_0_0_max' => 65],
            $compiled->bindings,
        );
    }

    public function testTwoWhereBetweenInSameGroup(): void
    {
        $compiled = $this->builder()
            ->where(function ($g) {
                $g->whereBetween('a', 1, 10)
                  ->whereBetween('b', 20, 30);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("a" BETWEEN :where_0_0_min AND :where_0_0_max '
            . 'AND "b" BETWEEN :where_0_1_min AND :where_0_1_max)',
            $compiled->sql,
        );
        $this->assertCount(4, $compiled->bindings);
        $this->assertSame(1, $compiled->bindings[':where_0_0_min']);
        $this->assertSame(10, $compiled->bindings[':where_0_0_max']);
        $this->assertSame(20, $compiled->bindings[':where_0_1_min']);
        $this->assertSame(30, $compiled->bindings[':where_0_1_max']);
    }

    public function testWhereBetweenWithOtherWheres(): void
    {
        $compiled = $this->builder()
            ->where('name', '=', 'Alice')
            ->whereBetween('age', 18, 65)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "name" = :where_0 AND "age" BETWEEN :where_1_min AND :where_1_max',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 'Alice', ':where_1_min' => 18, ':where_1_max' => 65],
            $compiled->bindings,
        );
    }

    public function testUpdateWithWhereBetween(): void
    {
        $compiled = $this->builder()
            ->whereBetween('age', 18, 65)
            ->compileUpdate(['active' => 1]);

        $this->assertSame(
            'UPDATE "users" SET "active" = :set_0 WHERE "age" BETWEEN :where_0_min AND :where_0_max',
            $compiled->sql,
        );
        $this->assertSame(
            [':set_0' => 1, ':where_0_min' => 18, ':where_0_max' => 65],
            $compiled->bindings,
        );
    }

    public function testDeleteWithWhereNotBetween(): void
    {
        $compiled = $this->builder()
            ->whereNotBetween('age', 0, 17)
            ->compileDelete();

        $this->assertSame(
            'DELETE FROM "users" WHERE "age" NOT BETWEEN :where_0_min AND :where_0_max',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_min' => 0, ':where_0_max' => 17],
            $compiled->bindings,
        );
    }
}
