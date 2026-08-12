<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\QueryBuilder;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class QueryBuilderJoinIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role_id INT)");
        $this->pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INT, title TEXT)");
        $this->pdo->exec("CREATE TABLE profiles (id INTEGER PRIMARY KEY, user_id INT, bio TEXT)");
        $this->pdo->exec("CREATE TABLE roles (id INTEGER PRIMARY KEY, label TEXT)");

        // users: Alice (role 1), Bob (role NULL)
        $this->pdo->prepare("INSERT INTO users (name, role_id) VALUES (?, ?)")->execute(['Alice', 1]);
        $this->pdo->prepare("INSERT INTO users (name, role_id) VALUES (?, ?)")->execute(['Bob', null]);

        // posts: 2 от Alice, 0 от Bob
        $this->pdo->prepare("INSERT INTO posts (user_id, title) VALUES (?, ?)")->execute([1, 'Hello']);
        $this->pdo->prepare("INSERT INTO posts (user_id, title) VALUES (?, ?)")->execute([1, 'World']);

        // profiles: только у Alice
        $this->pdo->prepare("INSERT INTO profiles (user_id, bio) VALUES (?, ?)")->execute([1, 'Dev']);

        // roles: role 1 = admin
        $this->pdo->prepare("INSERT INTO roles (label) VALUES (?)")->execute(['admin']);
    }

    protected function tearDown(): void
    {
        unset($this->pdo);
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new SqliteGrammar(), 'users');
    }

    public function testInnerJoinReturnsOnlyMatchingRows(): void
    {
        $compiled = $this->builder()
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertSame(['Hello', 'World'], array_column($rows, 'title'));
    }

    public function testLeftJoinReturnsAllLeftRows(): void
    {
        $compiled = $this->builder()
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(3, $rows);
    }

    public function testLeftJoinWithWhereFiltersJoinedColumn(): void
    {
        $compiled = $this->builder()
            ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
            ->where('profiles.bio', '=', 'Dev')
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        // только Alice с профилем
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testRightJoinReturnsAllRightRows(): void
    {
        $compiled = $this->builder()
            ->rightJoin('roles', 'users.role_id', '=', 'roles.id')
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        // role 'admin' связан с Alice; но также все roles (даже без user)
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    public function testJoinWithSelectColumns(): void
    {
        $compiled = $this->builder()
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->orderBy('posts.title', 'ASC')
            ->compile();

        $stmt = $this->pdo->prepare($compiled->sql);
        $stmt->execute($compiled->bindings);
        $rows = $stmt->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertSame(['Hello', 'World'], array_column($rows, 'title'));
        $this->assertSame(['Alice', 'Alice'], array_column($rows, 'name'));
    }
}
