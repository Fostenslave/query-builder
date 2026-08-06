<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\DB;

use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Database;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class DBTest extends TestCase
{
    private Database\DB $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            age INT,
            active INT
        )");

        $pdo->exec("CREATE TABLE posts (
            id INTEGER PRIMARY KEY,
            user_id INT,
            title TEXT
        )");

        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Alice', 30, 1]);
        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Bob', 16, 1]);
        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Charlie', 25, 0]);

        $pdo->prepare("INSERT INTO posts (user_id, title) VALUES (?, ?)")->execute([1, 'Hello']);
        $pdo->prepare("INSERT INTO posts (user_id, title) VALUES (?, ?)")->execute([1, 'World']);

        $this->db = new Database\DB($pdo);
    }

    public function testTableReturnsQueryBuilder(): void
    {
        $builder = $this->db->table('users');

        $this->assertInstanceOf(QueryBuilder::class, $builder);
    }

    public function testGetReturnsAllRows(): void
    {
        $rows = $this->db->table('users')->get();

        $this->assertCount(3, $rows);
        $this->assertSame(['Alice', 'Bob', 'Charlie'], array_column($rows, 'name'));
    }

    public function testGetWithWhere(): void
    {
        $rows = $this->db->table('users')
            ->where('age', '>', 18)
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Charlie'], array_column($rows, 'name'));
    }

    public function testGetReturnsEmptyArrayWhenNoRows(): void
    {
        $rows = $this->db->table('users')
            ->where('age', '>', 100)
            ->get();

        $this->assertSame([], $rows);
    }

    public function testFirstReturnsFirstRow(): void
    {
        $row = $this->db->table('users')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }

    public function testFirstReturnsNullWhenNoRows(): void
    {
        $row = $this->db->table('users')
            ->where('age', '>', 100)
            ->first();

        $this->assertNull($row);
    }

    public function testGetWithJoin(): void
    {
        $rows = $this->db->table('users')
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->orderBy('posts.title', 'ASC')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Hello', 'World'], array_column($rows, 'title'));
    }

    public function testGetWithLimit(): void
    {
        $rows = $this->db->table('users')
            ->orderBy('age', 'DESC')
            ->limit(2)
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Charlie'], array_column($rows, 'name'));
    }

    public function testChainedWhereAndOrderByAndFirst(): void
    {
        $row = $this->db->table('users')
            ->where('active', '=', 1)
            ->orderBy('age', 'DESC')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }


    public function testMultipleQueriesAreIndependent(): void
    {
        $rows1 = $this->db->table('users')->where('age', '>', 20)->get();
        $rows2 = $this->db->table('users')->get();

        $this->assertCount(2, $rows1);
        $this->assertCount(3, $rows2);
    }
}
