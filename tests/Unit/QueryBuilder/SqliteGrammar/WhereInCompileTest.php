<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class WhereInCompileTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder($this->grammar, 'users');
    }


    public function testWhereInMultipleValues(): void
    {
        $compiled = $this->builder()
            ->whereIn('status', ['active', 'pending'])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "status" IN (:where_0_0, :where_0_1)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 'active', ':where_0_1' => 'pending'],
            $compiled->bindings,
        );
    }

    public function testWhereNotInMultipleValues(): void
    {
        $compiled = $this->builder()
            ->whereNotIn('status', ['deleted', 'banned'])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "status" NOT IN (:where_0_0, :where_0_1)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 'deleted', ':where_0_1' => 'banned'],
            $compiled->bindings,
        );
    }

    public function testWhereInSingleValue(): void
    {
        $compiled = $this->builder()
            ->whereIn('id', [5])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "id" IN (:where_0_0)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 5],
            $compiled->bindings,
        );
    }

    public function testWhereInEmptyArrayThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You should provide non empty list of values');

        $this->builder()->whereIn('id', [])->compile();
    }

    public function testWhereNotInEmptyArrayThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->builder()->whereNotIn('id', [])->compile();
    }


    public function testOrWhereInWithWhere(): void
    {
        $compiled = $this->builder()
            ->where('active', '=', 1)
            ->orWhereIn('role', ['admin', 'moderator'])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "active" = :where_0 OR "role" IN (:where_1_0, :where_1_1)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 1, ':where_1_0' => 'admin', ':where_1_1' => 'moderator'],
            $compiled->bindings,
        );
    }

    public function testOrWhereNotIn(): void
    {
        $compiled = $this->builder()
            ->whereIn('a', [1, 2])
            ->orWhereNotIn('b', [3, 4])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "a" IN (:where_0_0, :where_0_1) OR "b" NOT IN (:where_1_0, :where_1_1)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 1, ':where_0_1' => 2, ':where_1_0' => 3, ':where_1_1' => 4],
            $compiled->bindings,
        );
    }

    public function testWhereInWithRegularWheres(): void
    {
        $compiled = $this->builder()
            ->where('active', '=', 1)
            ->whereIn('role', ['admin', 'moderator'])
            ->where('age', '>', 18)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "active" = :where_0 AND "role" IN (:where_1_0, :where_1_1) '
            . 'AND "age" > :where_2',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 1, ':where_1_0' => 'admin', ':where_1_1' => 'moderator', ':where_2' => 18],
            $compiled->bindings,
        );
    }


    public function testWhereInInsideGroup(): void
    {
        $compiled = $this->builder()
            ->where(function ($g) {
                $g->whereIn('role', ['admin', 'moderator']);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("role" IN (:where_0_0_0, :where_0_0_1))',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0_0' => 'admin', ':where_0_0_1' => 'moderator'],
            $compiled->bindings,
        );
    }

    public function testWhereInInsideGroupWithTopLevel(): void
    {
        $compiled = $this->builder()
            ->where(function ($g) {
                $g->whereIn('role', ['admin', 'moderator']);
            })
            ->where('active', '=', 1)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("role" IN (:where_0_0_0, :where_0_0_1)) AND "active" = :where_1',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0_0' => 'admin', ':where_0_0_1' => 'moderator', ':where_1' => 1],
            $compiled->bindings,
        );
    }

    public function testWhereInInsideNestedGroup(): void
    {
        $compiled = $this->builder()
            ->where(function ($g) {
                $g->where('active', '=', 1)
                  ->where(function ($h) {
                      $h->whereIn('role', ['admin', 'moderator']);
                  });
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("active" = :where_0_0 AND ("role" IN (:where_0_1_0_0, :where_0_1_0_1)))',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 1, ':where_0_1_0_0' => 'admin', ':where_0_1_0_1' => 'moderator'],
            $compiled->bindings,
        );
    }

    public function testWhereInDottedColumnWrapped(): void
    {
        $compiled = $this->builder()
            ->whereIn('users.status', ['active', 'pending'])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "users"."status" IN (:where_0_0, :where_0_1)',
            $compiled->sql,
        );
    }

    public function testUpdateWithWhereIn(): void
    {
        $compiled = $this->builder()
            ->whereIn('id', [1, 2, 3])
            ->compileUpdate(['active' => 0]);

        $this->assertSame(
            'UPDATE "users" SET "active" = :set_0 WHERE "id" IN (:where_0_0, :where_0_1, :where_0_2)',
            $compiled->sql,
        );
        $this->assertSame(
            [':set_0' => 0, ':where_0_0' => 1, ':where_0_1' => 2, ':where_0_2' => 3],
            $compiled->bindings,
        );
    }

    public function testDeleteWithWhereNotIn(): void
    {
        $compiled = $this->builder()
            ->whereNotIn('status', ['deleted', 'banned'])
            ->compileDelete();

        $this->assertSame(
            'DELETE FROM "users" WHERE "status" NOT IN (:where_0_0, :where_0_1)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 'deleted', ':where_0_1' => 'banned'],
            $compiled->bindings,
        );
    }

    public function testWhereInWithMixedTypes(): void
    {
        $compiled = $this->builder()
            ->whereIn('col', [1, 'two', 3.0])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "col" IN (:where_0_0, :where_0_1, :where_0_2)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 1, ':where_0_1' => 'two', ':where_0_2' => 3.0],
            $compiled->bindings,
        );
    }

    public function testTwoWhereInSameGroup(): void
    {
        $compiled = $this->builder()
            ->where(function ($g) {
                $g->whereIn('a', [1, 2])
                  ->whereIn('b', [3, 4]);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("a" IN (:where_0_0_0, :where_0_0_1) AND "b" IN (:where_0_1_0, :where_0_1_1))',
            $compiled->sql,
        );
        $this->assertCount(4, $compiled->bindings);
        $this->assertSame(1, $compiled->bindings[':where_0_0_0']);
        $this->assertSame(2, $compiled->bindings[':where_0_0_1']);
        $this->assertSame(3, $compiled->bindings[':where_0_1_0']);
        $this->assertSame(4, $compiled->bindings[':where_0_1_1']);
    }
}
