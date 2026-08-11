<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\QueryBuilder;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class SelectSubIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            active INT
        )");

        $this->pdo->exec("CREATE TABLE orders (
            id INTEGER PRIMARY KEY,
            user_id INT,
            total INT
        )");

        $this->pdo->exec("CREATE TABLE order_items (
            id INTEGER PRIMARY KEY,
            order_id INT,
            product TEXT,
            quantity INT
        )");

        $stmt = $this->pdo->prepare("INSERT INTO users (name, active) VALUES (?, ?)");
        $stmt->execute(['Alice', 1]);
        $stmt->execute(['Bob', 1]);
        $stmt->execute(['Charlie', 0]);

        $stmt = $this->pdo->prepare("INSERT INTO orders (user_id, total) VALUES (?, ?)");
        $stmt->execute([1, 100]);
        $stmt->execute([1, 200]);
        $stmt->execute([2, 50]);
        $stmt->execute([3, 300]);
        $stmt->execute([1, 150]);

        $stmt = $this->pdo->prepare("INSERT INTO order_items (order_id, product, quantity) VALUES (?, ?, ?)");
        $stmt->execute([1, 'Widget', 2]);
        $stmt->execute([1, 'Gadget', 1]);
        $stmt->execute([2, 'Widget', 4]);
        $stmt->execute([3, 'Gadget', 1]);
        $stmt->execute([4, 'Widget', 5]);
        $stmt->execute([4, 'Gadget', 2]);
        $stmt->execute([5, 'Widget', 3]);
    }

    private function builder(string $table = 'users'): QueryBuilder
    {
        return new QueryBuilder(new SqliteGrammar(), $table);
    }

    /**
     * @param QueryBuilder $builder
     * @return array<mixed>
     */
    private function execute(QueryBuilder $builder): array
    {
        $compiled = $builder->compile();
        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        return $stmt->fetchAll();
    }

    public function testSelectSubCorrelatedCount(): void
    {
        $sub = $this->builder('orders')
            ->whereRaw('orders.user_id = users.id')
            ->selectRaw('COUNT(*)');

        $rows = $this->execute(
            $this->builder()
                ->select('name')
                ->selectSub($sub, 'order_count')
                ->orderBy('name', 'ASC')
        );

        $this->assertCount(3, $rows);
        $this->assertSame([3, 1, 1], array_column($rows, 'order_count'));
    }

    public function testSelectSubCorrelatedSum(): void
    {
        $sub = $this->builder('orders')
            ->whereRaw('orders.user_id = users.id')
            ->selectRaw('SUM(total)');

        $rows = $this->execute(
            $this->builder()
                ->select('name')
                ->selectSub($sub, 'total_spent')
                ->orderBy('name', 'ASC')
        );

        $this->assertCount(3, $rows);
        array_column($rows, 'total_spent')
            |> (fn($x) => array_map('intval', $x))
            |> (fn($x) => $this->assertSame([450, 50, 300], $x));
    }

    public function testSelectSubWithOuterWhere(): void
    {
        $sub = $this->builder('orders')
            ->whereRaw('orders.user_id = users.id')
            ->where('total', '>', 100)
            ->selectRaw('COUNT(*)');

        $rows = $this->execute(
            $this->builder()
                ->select('name')
                ->selectSub($sub, 'big_orders')
                ->where('active', '=', 1)
                ->orderBy('name', 'ASC')
        );

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Bob'], array_column($rows, 'name'));
        $this->assertSame([2, 0], array_column($rows, 'big_orders'));
    }

    public function testMultipleSelectSub(): void
    {
        $sub1 = $this->builder('orders')
            ->whereRaw('orders.user_id = users.id')
            ->selectRaw('COUNT(*)');

        $sub2 = $this->builder('orders')
            ->whereRaw('orders.user_id = users.id')
            ->selectRaw('SUM(total)');

        $rows = $this->execute(
            $this->builder()
                ->select('name')
                ->selectSub($sub1, 'order_count')
                ->selectSub($sub2, 'total_spent')
                ->orderBy('name', 'ASC')
        );

        $this->assertCount(3, $rows);
        $this->assertSame(3, $rows[0]['order_count']);
        $this->assertSame(1, $rows[1]['order_count']);
        $this->assertSame(1, $rows[2]['order_count']);
        $this->assertSame(450, (int) $rows[0]['total_spent']);
        $this->assertSame(50, (int) $rows[1]['total_spent']);
        $this->assertSame(300, (int) $rows[2]['total_spent']);
    }

    public function testSelectSubAsOnlyColumn(): void
    {
        $sub = $this->builder('orders')->selectRaw('COUNT(*)');

        $rows = $this->execute(
            $this->builder()->selectSub($sub, 'total_orders')
        );

        $this->assertCount(3, $rows);
        $this->assertSame(5, $rows[0]['total_orders']);
        $this->assertSame(5, $rows[1]['total_orders']);
        $this->assertSame(5, $rows[2]['total_orders']);
    }

    public function testSelectSubComplexWithJoinWhereHaving(): void
    {
        $sub = $this->builder('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->whereRaw('orders.user_id = users.id')
            ->where('order_items.product', '=', 'Widget')
            ->where('order_items.quantity', '>', 0)
            ->havingRaw('SUM(order_items.quantity) > 2')
            ->selectRaw('COUNT(*)');

        $rows = $this->execute(
            $this->builder()
                ->select('name')
                ->selectSub($sub, 'big_widget_orders')
                ->orderBy('name', 'ASC')
        );

        $this->assertCount(3, $rows);
        $this->assertSame(['Alice', 'Bob', 'Charlie'], array_column($rows, 'name'));
        $this->assertSame(3, $rows[0]['big_widget_orders']);
        $this->assertNull($rows[1]['big_widget_orders']);
        $this->assertSame(1, $rows[2]['big_widget_orders']);
    }
}
