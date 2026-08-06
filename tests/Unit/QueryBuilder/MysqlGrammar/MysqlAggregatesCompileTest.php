<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\MysqlGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\MysqlGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

/**
 * Unit-тесты компиляции GROUP BY, HAVING для MySQL — backticks.
 *
 * ЭТОТ ФАЙЛ УЖЕ ГОТОВ. Реализуй groupBy, having, havingRaw и компиляцию.
 */
class MysqlAggregatesCompileTest extends TestCase
{
    private MysqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new MysqlGrammar();
    }

    public function testGroupBySingleColumnMysql(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->groupBy('status')
            ->compile();

        $this->assertSame('SELECT * FROM `users` GROUP BY `status`', $compiled->sql);
    }

    public function testGroupByDottedMysql(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->groupBy('users.age')
            ->compile();

        $this->assertSame(
            'SELECT * FROM `orders` GROUP BY `users`.`age`',
            $compiled->sql,
        );
    }

    public function testHavingSingleConditionMysql(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->having('total', '>', 100)
            ->compile();

        $this->assertSame(
            'SELECT user_id, SUM(amount) AS total FROM `orders` GROUP BY `user_id` HAVING `total` > :having_0',
            $compiled->sql,
        );
        $this->assertSame([':having_0' => 100], $compiled->bindings);
    }

    public function testHavingRawWithBindingsMysql(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'orders'))
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->havingRaw('SUM(amount) > ? AND SUM(amount) < ?', [100, 350])
            ->compile();

        $this->assertSame(
            'SELECT user_id, SUM(amount) AS total FROM `orders` GROUP BY `user_id` HAVING SUM(amount) > :having_raw_0_0 AND SUM(amount) < :having_raw_0_1',
            $compiled->sql,
        );
        $this->assertSame([':having_raw_0_0' => 100, ':having_raw_0_1' => 350], $compiled->bindings);
    }

    public function testFullQueryMysql(): void
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
            'SELECT user_id, COUNT(*) AS cnt FROM `orders` WHERE `status` = :where_0 GROUP BY `user_id` HAVING `cnt` > :having_0 ORDER BY `user_id` ASC LIMIT 5',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 'completed', ':having_0' => 1], $compiled->bindings);
    }
}