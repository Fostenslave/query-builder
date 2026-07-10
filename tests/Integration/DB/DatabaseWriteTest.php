<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Integration\DB;

use PDO;
use PHPUnit\Framework\TestCase;
use SimpleORM\Database\DB;


class DatabaseWriteTest extends TestCase
{
    private DB $db;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            age INT,
            active INT
        )");

        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Alice', 30, 1]);
        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Bob', 16, 1]);
        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Charlie', 25, 0]);

        $this->db = new DB($pdo);
    }

    public function testInsertReturnsLastInsertId(): void
    {
        $id = $this->db->table('users')->insert([
            'name'   => 'Dave',
            'age'    => 40,
            'active' => 1,
        ]);

        $this->assertGreaterThan(0, $id);
    }

    public function testInsertActuallyInsertsRow(): void
    {
        $id = $this->db->table('users')->insert([
            'name'   => 'Eve',
            'age'    => 28,
            'active' => 1,
        ]);

        $row = $this->db->table('users')->where('id', '=', $id)->first();

        $this->assertNotNull($row);
        $this->assertSame('Eve', $row['name']);
        $this->assertSame(28, $row['age']);
    }

    public function testInsertIncreasesRowCountViaGet(): void
    {
        $this->assertCount(3, $this->db->table('users')->get());

        $this->db->table('users')->insert(['name' => 'Frank', 'age' => 50, 'active' => 0]);

        $this->assertCount(4, $this->db->table('users')->get());
    }

    public function testUpdateReturnsAffectedRows(): void
    {
        $affected = $this->db->table('users')
            ->where('id', '=', 1)
            ->update(['name' => 'Alice Updated']);

        $this->assertSame(1, $affected);
    }

    public function testUpdateActuallyChangesData(): void
    {
        $this->db->table('users')
            ->where('id', '=', 1)
            ->update(['name' => 'Alice Updated']);

        $row = $this->db->table('users')->where('id', '=', 1)->first();

        $this->assertSame('Alice Updated', $row['name']);
    }

    public function testUpdateMultipleRows(): void
    {
        $affected = $this->db->table('users')
            ->where('active', '=', 1)
            ->update(['active' => 0]);

        $this->assertSame(2, $affected);
    }

    public function testUpdateWithMultipleColumns(): void
    {
        $this->db->table('users')
            ->where('id', '=', 1)
            ->update(['name' => 'Alice2', 'age' => 31]);

        $row = $this->db->table('users')->where('id', '=', 1)->first();

        $this->assertSame('Alice2', $row['name']);
        $this->assertSame(31, $row['age']);
    }

    public function testUpdateWithoutWhereAffectsAllRows(): void
    {
        $affected = $this->db->table('users')
            ->update(['active' => 0]);

        $this->assertSame(3, $affected);
    }


    public function testDeleteReturnsAffectedRows(): void
    {
        $affected = $this->db->table('users')
            ->where('id', '=', 1)
            ->delete();

        $this->assertSame(1, $affected);
    }

    public function testDeleteActuallyRemovesRow(): void
    {
        $this->db->table('users')->where('id', '=', 1)->delete();

        $rows = $this->db->table('users')->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Bob', 'Charlie'], array_column($rows, 'name'));
    }

    public function testDeleteMultipleRows(): void
    {
        $affected = $this->db->table('users')
            ->where('active', '=', 1)
            ->delete();

        $this->assertSame(2, $affected);
    }

    public function testDeleteWithoutWhereRemovesAllRows(): void
    {
        $affected = $this->db->table('users')->delete();

        $this->assertSame(3, $affected);
        $this->assertCount(0, $this->db->table('users')->get());
    }
}
