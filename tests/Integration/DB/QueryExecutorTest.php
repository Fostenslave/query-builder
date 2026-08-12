<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\DB;

use Fostenslave\QueryBuilder\Executor\PDOQueryExecutor;
use Fostenslave\QueryBuilder\Executor\QueryExecutor;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;
use PDO;
use PHPUnit\Framework\TestCase;

class QueryExecutorTest extends TestCase
{
    private PDO $pdo;
    private QueryExecutor $executor;
    private QueryBuilder $builder;

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

        $this->pdo->exec("CREATE TABLE posts (
            id INTEGER PRIMARY KEY,
            user_id INT,
            title TEXT
        )");

        $this->pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Alice', 30, 1]);
        $this->pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Bob', 16, 1]);
        $this->pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Charlie', 25, 0]);

        $this->pdo->prepare("INSERT INTO posts (user_id, title) VALUES (?, ?)")->execute([1, 'Hello']);
        $this->pdo->prepare("INSERT INTO posts (user_id, title) VALUES (?, ?)")->execute([1, 'World']);

        // Создаём экземпляр PDOQueryExecutor, передав PDO
        $this->executor = new PDOQueryExecutor($this->pdo);

        $this->builder = new QueryBuilder(new SqliteGrammar(), 'users');
    }

    protected function tearDown(): void
    {
        unset($this->pdo);
    }

    public function testFetchAllReturnsAllRows(): void
    {
        $compiled = $this->builder->compile();

        $rows = $this->executor->fetchAll($compiled);

        $this->assertCount(3, $rows);
        $this->assertSame(['Alice', 'Bob', 'Charlie'], array_column($rows, 'name'));
    }

    public function testFetchAllWithWhereReturnsFilteredRows(): void
    {
        $compiled = $this->builder
            ->where('age', '>', 18)
            ->compile();

        $rows = $this->executor->fetchAll($compiled);

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Charlie'], array_column($rows, 'name'));
    }

    public function testFetchAllReturnsEmptyArrayWhenNoRows(): void
    {
        $compiled = $this->builder
            ->where('age', '>', 100)
            ->compile();

        $rows = $this->executor->fetchAll($compiled);

        $this->assertSame([], $rows);
    }

    public function testFetchReturnsFirstRow(): void
    {
        $compiled = $this->builder
            ->orderBy('id', 'ASC')
            ->compile();

        $row = $this->executor->fetch($compiled);

        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }

    public function testFetchReturnsNullWhenNoRows(): void
    {
        $compiled = $this->builder
            ->where('age', '>', 100)
            ->compile();

        $row = $this->executor->fetch($compiled);

        $this->assertNull($row);
    }

    public function testFetchAllWithJoin(): void
    {
        $compiled = (new QueryBuilder(new SqliteGrammar(), 'users'))
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->orderBy('posts.title', 'ASC')
            ->compile();

        $rows = $this->executor->fetchAll($compiled);

        $this->assertCount(2, $rows);
        $this->assertSame(['Hello', 'World'], array_column($rows, 'title'));
    }

    public function testFetchWithJoinAndWhere(): void
    {
        $compiled = (new QueryBuilder(new SqliteGrammar(), 'users'))
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('posts.title', '=', 'Hello')
            ->compile();

        $row = $this->executor->fetch($compiled);

        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
        $this->assertSame('Hello', $row['title']);
    }

    public function testExecutorImplementsInterface(): void
    {
        $this->assertInstanceOf(QueryExecutor::class, $this->executor);
    }

    public function testFetchAllRespectsLimit(): void
    {
        $compiled = $this->builder
            ->orderBy('age', 'DESC')
            ->limit(2)
            ->compile();

        $rows = $this->executor->fetchAll($compiled);

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Charlie'], array_column($rows, 'name'));
    }
}
