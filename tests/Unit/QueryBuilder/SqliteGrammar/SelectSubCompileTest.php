<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class SelectSubCompileTest extends TestCase
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

    public function testSelectSubBasic(): void
    {

        $compiled = $this->builder()
            ->select('name')
            ->selectSub(
                $this
                    ->builder('orders')
                    ->selectRaw('COUNT(*)'),
                'order_count'
            )
            ->compile();

        $this->assertSame(
            'SELECT "name", (SELECT COUNT(*) FROM "orders") AS "order_count" FROM "users"',
            $compiled->sql,
        );
        $this->assertSame([], $compiled->bindings);
    }

    public function testSelectSubWithSubqueryBindings(): void
    {
        $sub = $this->builder('orders')
            ->where('total', '>', 100)
            ->selectRaw('COUNT(*)');

        $compiled = $this->builder()
            ->select('name')
            ->selectSub($sub, 'big_orders_count')
            ->compile();

        $this->assertSame(
            'SELECT "name", (SELECT COUNT(*) FROM "orders" WHERE "total" > :sub_0_where_0) AS "big_orders_count" FROM "users"',
            $compiled->sql,
        );
        $this->assertSame(
            [':sub_0_where_0' => 100],
            $compiled->bindings,
        );
    }

    public function testMultipleSelectSub(): void
    {
        $sub1 = $this->builder('orders')
            ->where('status', '=', 'pending')
            ->selectRaw('COUNT(*)');

        $sub2 = $this->builder('orders')
            ->where('status', '=', 'done')
            ->selectRaw('COUNT(*)');

        $compiled = $this->builder()
            ->select('name')
            ->selectSub($sub1, 'pending_count')
            ->selectSub($sub2, 'done_count')
            ->compile();

        $this->assertSame(
            'SELECT "name", (SELECT COUNT(*) FROM "orders" WHERE "status" = :sub_0_where_0) AS "pending_count", (SELECT COUNT(*) FROM "orders" WHERE "status" = :sub_1_where_0) AS "done_count" FROM "users"',
            $compiled->sql,
        );
        $this->assertSame(
            [':sub_0_where_0' => 'pending', ':sub_1_where_0' => 'done'],
            $compiled->bindings,
        );
    }

    public function testSelectSubWithOuterWhere(): void
    {
        $sub = $this->builder('orders')
            ->where('user_id', '=', 1)
            ->selectRaw('COUNT(*)');

        $compiled = $this->builder()
            ->select('name')
            ->selectSub($sub, 'order_count')
            ->where('active', '=', 1)
            ->compile();

        $this->assertSame(
            'SELECT "name", (SELECT COUNT(*) FROM "orders" WHERE "user_id" = :sub_0_where_0) AS "order_count" FROM "users" WHERE "active" = :where_0',
            $compiled->sql,
        );
        $this->assertSame(
            [':sub_0_where_0' => 1, ':where_0' => 1],
            $compiled->bindings,
        );
    }

    public function testSelectSubAsOnlyColumn(): void
    {
        $sub = $this->builder('orders')->selectRaw('COUNT(*)');

        $compiled = $this->builder()
            ->selectSub($sub, 'total')
            ->compile();

        $this->assertSame(
            'SELECT (SELECT COUNT(*) FROM "orders") AS "total" FROM "users"',
            $compiled->sql,
        );
        $this->assertSame([], $compiled->bindings);
    }

    public function testSelectSubWithWhereAndWhereIn(): void
    {
        $sub = $this->builder('orders')
            ->where('total', '>', 100)
            ->whereIn('status', ['active', 'pending'])
            ->selectRaw('COUNT(*)');

        $compiled = $this->builder()
            ->selectSub($sub, 'count')
            ->compile();

        $this->assertSame(
            'SELECT (SELECT COUNT(*) FROM "orders" WHERE "total" > :sub_0_where_0 AND "status" IN (:sub_0_where_1_0, :sub_0_where_1_1)) AS "count" FROM "users"',
            $compiled->sql,
        );
        $this->assertSame(
            [':sub_0_where_0' => 100, ':sub_0_where_1_0' => 'active', ':sub_0_where_1_1' => 'pending'],
            $compiled->bindings,
        );
    }
}
