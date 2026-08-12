<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\QueryBuilder;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class WhereBetweenIntegrationTest extends TestCase
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
            age INT,
            active INT
        )");

        $stmt = $this->pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)");
        $stmt->execute(['Alice', 30, 1]);
        $stmt->execute(['Bob', 16, 1]);
        $stmt->execute(['Charlie', 25, 0]);
        $stmt->execute(['Dave', 40, 1]);
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new SqliteGrammar(), 'users');
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

    public function testWhereBetweenFiltersRows(): void
    {
        $rows = $this->execute(
            $this->builder()->whereBetween('age', 20, 35)
        );

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Charlie'], array_column($rows, 'name'));
    }

    public function testWhereNotBetweenExcludesRows(): void
    {
        $rows = $this->execute(
            $this->builder()->whereNotBetween('age', 20, 35)
        );

        $this->assertCount(2, $rows);
        $this->assertSame(['Bob', 'Dave'], array_column($rows, 'name'));
    }

    public function testOrWhereBetweenWithWhere(): void
    {
        $rows = $this->execute(
            $this->builder()
                ->where('active', '=', 1)
                ->orWhereBetween('age', 20, 25)
        );

        // active=1: Alice(30), Bob(16), Dave(40)
        // age 20-25: Charlie(25, active=0)
        $this->assertCount(4, $rows);
        $this->assertSame(['Alice', 'Bob', 'Charlie', 'Dave'], array_column($rows, 'name'));
    }

    public function testWhereBetweenWithAdditionalWhere(): void
    {
        $rows = $this->execute(
            $this->builder()
                ->whereBetween('age', 20, 50)
                ->where('active', '=', 1)
        );

        // age 20-50: Alice(30), Charlie(25), Dave(40)
        // AND active=1: Alice(30), Dave(40)
        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Dave'], array_column($rows, 'name'));
    }
}
