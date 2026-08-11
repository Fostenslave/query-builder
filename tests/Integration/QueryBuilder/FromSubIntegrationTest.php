<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\QueryBuilder;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class FromSubIntegrationTest extends TestCase
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

    public function testFromSubBasic(): void
    {
        $sub = $this->builder('orders');

        $rows = $this->execute(
            $this->builder()
                ->fromSub($sub, 'o')
                ->select('o.user_id', 'o.total')
                ->orderBy('o.total', 'ASC')
        );

        $this->assertCount(5, $rows);
        $this->assertSame([50, 100, 150, 200, 300], array_column($rows, 'total'));
    }

    public function testFromSubWithInnerWhere(): void
    {
        $sub = $this->builder('orders')
            ->where('total', '>', 100);

        $rows = $this->execute(
            $this->builder()
                ->fromSub($sub, 'o')
                ->select('o.user_id', 'o.total')
                ->orderBy('o.total', 'ASC')
        );

        $this->assertCount(3, $rows);
        $this->assertSame([150, 200, 300], array_column($rows, 'total'));
    }

    public function testFromSubWithOuterWhere(): void
    {
        $sub = $this->builder('orders');

        $rows = $this->execute(
            $this->builder()
                ->fromSub($sub, 'o')
                ->select('o.user_id', 'o.total')
                ->where('o.total', '>', 100)
                ->orderBy('o.total', 'ASC')
        );

        $this->assertCount(3, $rows);
        $this->assertSame([150, 200, 300], array_column($rows, 'total'));
    }
}
