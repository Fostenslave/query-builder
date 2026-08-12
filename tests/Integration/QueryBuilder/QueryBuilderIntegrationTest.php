<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\QueryBuilder;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class QueryBuilderIntegrationTest extends TestCase
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

        $this->pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")
            ->execute(['Alice', 30, 1]);
        $this->pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")
            ->execute(['Bob', 16, 1]);
        $this->pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")
            ->execute(['Charlie', 25, 0]);
    }

    protected function tearDown(): void
    {
        unset($this->pdo);
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new SqliteGrammar(), 'users');
    }

    public function testSelectAllReturnsAllRows(): void
    {
        $compiled = $this->builder()->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(3, $rows);
    }

    public function testWhereFiltersRows(): void
    {
        $compiled = $this->builder()
            ->where('age', '>', 18)
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Charlie'], array_column($rows, 'name'));
    }

    public function testWhereWithBooleanValue(): void
    {
        $compiled = $this->builder()
            ->where('active', '=', true)
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(2, $rows);
    }

    public function testOrderByDesc(): void
    {
        $compiled = $this->builder()
            ->orderBy('age', 'DESC')
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertSame(['Alice', 'Charlie', 'Bob'], array_column($rows, 'name'));
    }

    public function testLimit(): void
    {
        $compiled = $this->builder()
            ->orderBy('age', 'DESC')
            ->limit(2)
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(2, $rows);
    }

    public function testLimitAndOffset(): void
    {
        $compiled = $this->builder()
            ->orderBy('age', 'DESC')
            ->limit(2)
            ->offset(1)
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertSame(['Charlie', 'Bob'], array_column($rows, 'name'));
    }
}
