<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class FromSubCompileTest extends TestCase
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

    public function testFromSubBasic(): void
    {
        $sub = $this->builder('orders');

        $compiled = $this->builder()
            ->fromSub($sub, 'o')
            ->compile();

        $this->assertSame(
            'SELECT * FROM (SELECT * FROM "orders") AS "o"',
            $compiled->sql,
        );
        $this->assertSame([], $compiled->bindings);
    }

    public function testFromSubWithSubqueryBindings(): void
    {
        $sub = $this->builder('orders')
            ->where('total', '>', 100);

        $compiled = $this->builder()
            ->fromSub($sub, 'big_orders')
            ->compile();

        $this->assertSame(
            'SELECT * FROM (SELECT * FROM "orders" WHERE "total" > :from_0_where_0) AS "big_orders"',
            $compiled->sql,
        );
        $this->assertSame(
            [':from_0_where_0' => 100],
            $compiled->bindings,
        );
    }

    public function testFromSubWithOuterWhere(): void
    {
        $sub = $this->builder('orders')
            ->where('total', '>', 100);

        $compiled = $this->builder()
            ->fromSub($sub, 'o')
            ->where('status', '=', 'active')
            ->compile();

        $this->assertSame(
            'SELECT * FROM (SELECT * FROM "orders" WHERE "total" > :from_0_where_0) AS "o" WHERE "status" = :where_0',
            $compiled->sql,
        );
        $this->assertSame(
            [':from_0_where_0' => 100, ':where_0' => 'active'],
            $compiled->bindings,
        );
    }

    public function testFromSubWithSelectColumns(): void
    {
        $sub = $this->builder('orders');

        $compiled = $this->builder()
            ->fromSub($sub, 'o')
            ->select('o.user_id', 'o.total')
            ->compile();

        $this->assertSame(
            'SELECT "o"."user_id", "o"."total" FROM (SELECT * FROM "orders") AS "o"',
            $compiled->sql,
        );
        $this->assertSame([], $compiled->bindings);
    }

    public function testFromSubWithSelectSub(): void
    {
        $fromSub = $this->builder('orders')
            ->where('total', '>', 50);

        $selectSub = $this->builder('products')
            ->where('status', '=', 'active')
            ->selectRaw('COUNT(*)');

        $compiled = $this->builder()
            ->fromSub($fromSub, 'o')
            ->selectSub($selectSub, 'product_count')
            ->where('category', '=', 'premium')
            ->compile();

        $this->assertSame(
            'SELECT (SELECT COUNT(*) FROM "products" WHERE "status" = :sub_0_where_0) AS "product_count" FROM (SELECT * FROM "orders" WHERE "total" > :from_0_where_0) AS "o" WHERE "category" = :where_0',
            $compiled->sql,
        );
        $this->assertSame(
            [':from_0_where_0' => 50, ':sub_0_where_0' => 'active', ':where_0' => 'premium'],
            $compiled->bindings,
        );
    }

    public function testFromSubWithJoin(): void
    {
        $sub = $this->builder('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->where('users.active', '=', 1);

        $compiled = $this->builder()
            ->fromSub($sub, 'o')
            ->select('o.id', 'o.name')
            ->compile();

        $this->assertSame(
            'SELECT "o"."id", "o"."name" FROM (SELECT * FROM "orders" INNER JOIN "users" ON "orders"."user_id" = "users"."id" WHERE "users"."active" = :from_0_where_0) AS "o"',
            $compiled->sql,
        );
        $this->assertSame(
            [':from_0_where_0' => 1],
            $compiled->bindings,
        );
    }
}
