<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use SimpleORM\Grammar\SqliteGrammar;
use SimpleORM\Query\QueryBuilder;

/**
 * Unit-тесты компиляции GROUP BY, HAVING, havingRaw.
 *
 * ЭТОТ ФАЙЛ УЖЕ ГОТОВ. Реализуй groupBy, having, havingRaw и компиляцию.
 */
class AggregatesCompileTest extends TestCase
{
    private SqliteGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    // ── GROUP BY ────────────────────────────────────────────

    public function testGroupBySingleColumn(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->groupBy('status')
            ->compile();

        $this->assertSame('SELECT * FROM "users" GROUP BY "status"', $compiled->sql);
        $this->assertSame([], $compiled->bindings);
    }

    public function testGroupByMultipleColumns(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'products'))
            ->groupBy('category', 'status')
            ->compile();

        $this->assertSame(
            'SELECT * FROM "products" GROUP BY "category", "status"',
            $compiled->sql,
        );
        $this->assertSame([], $compiled->bindings);
    }

    public function testGroupByDottedColumnIsWrapped(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->groupBy('users.age')
            ->compile();

        $this->assertSame(
            'SELECT * FROM "orders" GROUP BY "users"."age"',
            $compiled->sql,
        );
    }

    public function testGroupByWithSelectRaw(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, COUNT(*) AS cnt')
            ->groupBy('user_id')
            ->compile();

        $this->assertSame(
            'SELECT user_id, COUNT(*) AS cnt FROM "orders" GROUP BY "user_id"',
            $compiled->sql,
        );
    }

    // ── HAVING ──────────────────────────────────────────────

    public function testHavingSingleCondition(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, COUNT(*) AS cnt')
            ->groupBy('user_id')
            ->having('cnt', '>', 1)
            ->compile();

        $this->assertSame(
            'SELECT user_id, COUNT(*) AS cnt FROM "orders" GROUP BY "user_id" HAVING "cnt" > :having_0',
            $compiled->sql,
        );
        $this->assertSame([':having_0' => 1], $compiled->bindings);
    }

    public function testHavingMultipleConditions(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->having('total', '>', 100)
            ->having('total', '<', 500)
            ->compile();

        $this->assertSame(
            'SELECT user_id, SUM(amount) AS total FROM "orders" GROUP BY "user_id" HAVING "total" > :having_0 AND "total" < :having_1',
            $compiled->sql,
        );
        $this->assertSame([':having_0' => 100, ':having_1' => 500], $compiled->bindings);
    }

    public function testHavingWithoutGroupBy(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('SUM(amount) AS total')
            ->having('total', '>', 100)
            ->compile();

        $this->assertSame(
            'SELECT SUM(amount) AS total FROM "orders" HAVING "total" > :having_0',
            $compiled->sql,
        );
        $this->assertSame([':having_0' => 100], $compiled->bindings);
    }

    // ── HAVING RAW ──────────────────────────────────────────

    public function testHavingRawWithoutBindings(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, COUNT(*) AS cnt')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->compile();

        $this->assertSame(
            'SELECT user_id, COUNT(*) AS cnt FROM "orders" GROUP BY "user_id" HAVING COUNT(*) > 1',
            $compiled->sql,
        );
        $this->assertSame([], $compiled->bindings);
    }

    public function testHavingRawWithSingleBinding(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->havingRaw('SUM(amount) > ?', [100])
            ->compile();

        $this->assertSame(
            'SELECT user_id, SUM(amount) AS total FROM "orders" GROUP BY "user_id" HAVING SUM(amount) > :having_raw_0_0',
            $compiled->sql,
        );
        $this->assertSame([':having_raw_0_0' => 100], $compiled->bindings);
    }

    public function testHavingRawWithMultipleBindings(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->havingRaw('SUM(amount) > ? AND SUM(amount) < ?', [100, 350])
            ->compile();

        $this->assertSame(
            'SELECT user_id, SUM(amount) AS total FROM "orders" GROUP BY "user_id" HAVING SUM(amount) > :having_raw_0_0 AND SUM(amount) < :having_raw_0_1',
            $compiled->sql,
        );
        $this->assertSame([':having_raw_0_0' => 100, ':having_raw_0_1' => 350], $compiled->bindings);
    }

    public function testHavingRawKeysUniqueAcrossMixedHavings(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->having('total', '>', 50)           // :having_0
            ->havingRaw('COUNT(*) > ?', [1])     // :having_raw_1_0
            ->compile();

        $this->assertSame(
            'SELECT user_id, SUM(amount) AS total FROM "orders" GROUP BY "user_id" HAVING "total" > :having_0 AND COUNT(*) > :having_raw_1_0',
            $compiled->sql,
        );
        $this->assertSame([':having_0' => 50, ':having_raw_1_0' => 1], $compiled->bindings);
    }

    // ── FULL QUERY ──────────────────────────────────────────

    public function testFullQueryWithGroupByHavingAndOrderBy(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, COUNT(*) AS cnt')
            ->where('status', '=', 'completed')
            ->groupBy('user_id')
            ->having('cnt', '>', 1)
            ->orderBy('user_id', 'ASC')
            ->limit(5)
            ->compile();

        $this->assertSame(
            'SELECT user_id, COUNT(*) AS cnt FROM "orders" WHERE "status" = :where_0 GROUP BY "user_id" HAVING "cnt" > :having_0 ORDER BY "user_id" ASC LIMIT 5',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 'completed', ':having_0' => 1], $compiled->bindings);
    }

    public function testSumCompile(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->where('status', '=', 'completed')
            ->orderBy('user_id')
            ->selectRaw('SUM(amount)')
            ->compile();

        $this->assertSame(
            'SELECT SUM(amount) FROM "orders" WHERE "status" = :where_0 ORDER BY "user_id" ASC',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 'completed'], $compiled->bindings);
    }
}