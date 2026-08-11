<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\QueryBuilder;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class WhereInIntegrationTest extends TestCase
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
            role TEXT,
            active INT
        )");

        $stmt = $this->pdo->prepare("INSERT INTO users (name, role, active) VALUES (?, ?, ?)");
        $stmt->execute(['Alice', 'admin', 1]);
        $stmt->execute(['Bob', 'moderator', 1]);
        $stmt->execute(['Charlie', 'user', 0]);
        $stmt->execute(['Dave', 'admin', 0]);
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

    public function testWhereInReturnsMatchingRows(): void
    {
        $rows = $this->execute(
            $this->builder()->whereIn('role', ['admin', 'moderator'])
        );

        $this->assertCount(3, $rows);
        $this->assertSame(['Alice', 'Bob', 'Dave'], array_column($rows, 'name'));
    }

    public function testWhereNotInExcludesRows(): void
    {
        $rows = $this->execute(
            $this->builder()->whereNotIn('role', ['admin', 'moderator'])
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Charlie', $rows[0]['name']);
    }

    public function testWhereInWithOtherConditions(): void
    {
        $rows = $this->execute(
            $this->builder()
                ->whereIn('role', ['admin', 'moderator'])
                ->where('active', '=', 1)
        );

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Bob'], array_column($rows, 'name'));
    }

    public function testOrWhereInCombinesWithWhere(): void
    {
        $rows = $this->execute(
            $this->builder()
                ->where('active', '=', 1)
                ->orWhereIn('role', ['admin'])
        );

        $this->assertCount(3, $rows);
        $this->assertSame(['Alice', 'Bob', 'Dave'], array_column($rows, 'name'));
    }

    public function testWhereInSingleValue(): void
    {
        $rows = $this->execute(
            $this->builder()->whereIn('role', ['admin'])
        );

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Dave'], array_column($rows, 'name'));
    }
}
