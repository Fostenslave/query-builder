<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Integration\DB;

use PDO;
use PHPUnit\Framework\TestCase;
use SimpleORM\Database\DB;

/**
 * @TODO Переименовать, разделить на несколько файлов
 */
class CountExistsRawQueriesTest extends TestCase
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
        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Dave', 40, 1]);
        $pdo->prepare("INSERT INTO users (name, age, active) VALUES (?, ?, ?)")->execute(['Eve', 28, 0]);

        $this->db = new DB($pdo);
    }

    public function testCountReturnsTotalRows(): void
    {
        $this->assertSame(5, $this->db->table('users')->count());
    }

    public function testCountWithWhere(): void
    {
        $this->assertSame(3, $this->db->table('users')->where('active', '=', 1)->count());
    }

    public function testCountWithMultipleWheres(): void
    {
        $count = $this->db->table('users')
            ->where('active', '=', 1)
            ->where('age', '>', 20)
            ->count();

        $this->assertSame(2, $count);
    }

    public function testCountReturnsZeroWhenNoRows(): void
    {
        $this->assertSame(0, $this->db->table('users')->where('age', '>', 100)->count());
    }

    public function testExistsReturnsTrueWhenRowsExist(): void
    {
        $this->assertTrue($this->db->table('users')->where('id', '=', 1)->exists());
    }

    public function testExistsWithSelectingColumns(): void
    {
        $this->assertTrue($this->db->table('users')
            ->select('id')
            ->where('id', '=', 1)
            ->exists());
    }

    public function testExistsReturnsFalseWhenNoRows(): void
    {
        $this->assertFalse($this->db->table('users')->where('id', '=', 999)->exists());
    }


    public function testPaginateFirstPage(): void
    {
        $rows = $this->db->table('users')
            ->orderBy('id')
            ->paginate(page: 1, perPage: 2);

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Bob'], array_column($rows, 'name'));
    }

    public function testPaginateSecondPage(): void
    {
        $rows = $this->db->table('users')
            ->orderBy('id', 'ASC')
            ->paginate(page: 2, perPage: 2);

        $this->assertCount(2, $rows);
        $this->assertSame(['Charlie', 'Dave'], array_column($rows, 'name'));
    }

    public function testPaginateLastPagePartial(): void
    {
        $rows = $this->db->table('users')
            ->orderBy('id', 'ASC')
            ->paginate(page: 3, perPage: 2);

        $this->assertCount(1, $rows);
        $this->assertSame(['Eve'], array_column($rows, 'name'));
    }

    public function testPaginateBeyondLastPageReturnsEmpty(): void
    {
        $rows = $this->db->table('users')
            ->orderBy('id', 'ASC')
            ->paginate(page: 10, perPage: 2);

        $this->assertSame([], $rows);
    }

    public function testPaginateWithWhere(): void
    {
        $rows = $this->db->table('users')
            ->where('active', '=', 1)
            ->orderBy('id', 'ASC')
            ->paginate(page: 1, perPage: 2);

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Bob'], array_column($rows, 'name'));
    }


    public function testSelectRawCount(): void
    {
        $row = $this->db->table('users')
            ->selectRaw('COUNT(*) AS total')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(5, (int) $row['total']);
    }

    public function testSelectRawWithWhere(): void
    {
        $row = $this->db->table('users')
            ->selectRaw('COUNT(*) AS total')
            ->where('active', '=', 1)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row['total']);
    }

    public function testWhereRawSimple(): void
    {
        $rows = $this->db->table('users')
            ->whereRaw('age > 20')
            ->orderBy('id', 'ASC')
            ->get();

        $this->assertCount(4, $rows);
    }

    public function testWhereRawCombinedWithWhere(): void
    {
        $rows = $this->db->table('users')
            ->where('active', '=', 1)
            ->whereRaw('age > 20')
            ->orderBy('id', 'ASC')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Dave'], array_column($rows, 'name'));
    }

    public function testWhereRawWithBindingSingleParam(): void
    {
        $rows = $this->db->table('users')
            ->whereRaw('age > ?', [20])
            ->orderBy('id', 'ASC')
            ->get();

        $this->assertCount(4, $rows);
        $this->assertSame(['Alice', 'Charlie', 'Dave', 'Eve'], array_column($rows, 'name'));
    }

    public function testWhereRawWithMultipleBindings(): void
    {
        $rows = $this->db->table('users')
            ->whereRaw('age > ? AND age < ?', [20, 29])
            ->orderBy('id', 'ASC')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Charlie', 'Eve'], array_column($rows, 'name'));
    }

    public function testWhereRawBindingMixedWithRegularWhere(): void
    {
        $rows = $this->db->table('users')
            ->where('active', '=', 1)
            ->whereRaw('age > ?', [20])
            ->orderBy('id', 'ASC')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Alice', 'Dave'], array_column($rows, 'name'));
    }

    public function testWhereRawWithBindingPreventsSqlInjection(): void
    {
        $rows = $this->db->table('users')
            ->whereRaw('age > ?', ["18; DROP TABLE users; --"])
            ->get();

        // Таблица не удалена — значение не вклеилось в SQL
        $this->assertSame(5, $this->db->table('users')->count());
        // Сравнение age > "18..." в SQLite даёт false — нет rows
        $this->assertCount(0, $rows);
    }

    public function testWhereRawWithoutBackwardCompat(): void
    {
        // Старый вызов без bindings — должен работать как раньше
        $rows = $this->db->table('users')
            ->whereRaw('age > 20')
            ->orderBy('id', 'ASC')
            ->get();

        $this->assertCount(4, $rows);
    }
}
