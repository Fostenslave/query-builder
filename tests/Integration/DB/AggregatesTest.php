<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\DB;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Database\DB;

/**
 * Тесты агрегатов: GROUP BY, HAVING, sum/avg/min/max.
 *
 * ЭТОТ ФАЙЛ УЖЕ ГОТОВ. Реализуй методы в QueryBuilder и Grammar.
 */
class AggregatesTest extends TestCase
{
    private DB $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec("CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            amount REAL,
            status TEXT
        )");

        $rows = [
            [1, 100.0, 'completed'],
            [1, 200.0, 'completed'],
            [2, 50.0,  'pending'],
            [2, 150.0, 'completed'],
            [3, 300.0, 'cancelled'],
        ];
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, amount, status) VALUES (?, ?, ?)");
        foreach ($rows as [$uid, $amount, $status]) {
            $stmt->execute([$uid, $amount, $status]);
        }

        $this->db = new DB($pdo);
    }

    // ── TERMINAL AGGREGATES ────────────────────────────────

    public function testSumReturnsTotal(): void
    {
        $this->assertSame(800.0, $this->db->table('orders')->sum('amount'));
    }

    public function testSumWithWhere(): void
    {
        $sum = $this->db->table('orders')
            ->where('status', '=', 'completed')
            ->sum('amount');
        $this->assertSame(450.0, $sum);
    }

    public function testAvgReturnsAverage(): void
    {
        $this->assertSame(160.0, $this->db->table('orders')->avg('amount'));
    }

    public function testMinReturnsMinimum(): void
    {
        $this->assertSame(50.0, $this->db->table('orders')->min('amount'));
    }

    public function testMaxReturnsMaximum(): void
    {
        $this->assertSame(300.0, $this->db->table('orders')->max('amount'));
    }

    public function testSumReturnsNullWhenNoRows(): void
    {
        $this->assertNull($this->db->table('orders')->where('id', '=', 999)->sum('amount'));
    }

    // ── GROUP BY ────────────────────────────────────────────

    public function testGroupByWithCount(): void
    {
        $rows = $this->db->table('orders')
            ->selectRaw('user_id, COUNT(*) AS order_count')
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame(2, (int) $rows[0]['order_count']);
        $this->assertSame(2, (int) $rows[1]['order_count']);
        $this->assertSame(1, (int) $rows[2]['order_count']);
    }

    public function testGroupByMultipleColumns(): void
    {
        $rows = $this->db->table('orders')
            ->selectRaw('user_id, status, COUNT(*) AS cnt')
            ->groupBy('user_id', 'status')
            ->orderBy('user_id')
            ->orderBy('status')
            ->get();

        $this->assertCount(4, $rows);
    }

    public function testGroupByWithSum(): void
    {
        $rows = $this->db->table('orders')
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get();

        $this->assertSame(300.0, (float) $rows[0]['total']);
        $this->assertSame(200.0, (float) $rows[1]['total']);
        $this->assertSame(300.0, (float) $rows[2]['total']);
    }

    // ── HAVING ─────────────────────────────────────────────

    public function testHavingWithCount(): void
    {
        $rows = $this->db->table('orders')
            ->selectRaw('user_id, COUNT(*) AS cnt')
            ->groupBy('user_id')
            ->having('cnt', '>', 1)
            ->orderBy('user_id')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(1, (int) $rows[0]['user_id']);
        $this->assertSame(2, (int) $rows[1]['user_id']);
    }

    public function testHavingRawWithBindings(): void
    {
        $rows = $this->db->table('orders')
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->havingRaw('SUM(amount) > ?', [100])
            ->orderBy('user_id')
            ->get();

        $this->assertCount(3, $rows);

        foreach ($rows as $row) {
            $this->assertGreaterThan(100, $row['total']);
        }
    }

    public function testHavingRawWithMultipleBindings(): void
    {
        [$lowerBound, $higherBound] = [200, 350];
        $rows = $this->db
            ->table('orders')
            ->selectRaw('user_id, SUM(amount) AS total')
            ->groupBy('user_id')
            ->havingRaw('SUM(amount) >= ? AND SUM(amount) < ?', [$lowerBound, $higherBound])
            ->orderBy('user_id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertEquals(1, (int) $rows[0]['user_id']);
        $this->assertEquals(2, (int) $rows[1]['user_id']);
        $this->assertEquals(3, (int) $rows[2]['user_id']);

        foreach ($rows as $row) {
            $this->logicalAnd(
                $this->assertGreaterThanOrEqual($lowerBound, $row['total']),
                $this->assertLessThanOrEqual($higherBound, $row['total']),
        );

        }
    }

    public function testHavingCombinedWithWhere(): void
    {
        $rows = $this->db
            ->table('orders')
            ->selectRaw('user_id, COUNT(*) AS cnt')
            ->where('status', '=', 'completed')
            ->groupBy('user_id')
            ->having('cnt', '>=', 1)
            ->orderBy('user_id')
            ->get();


        $this->assertCount(2, $rows);
    }
}