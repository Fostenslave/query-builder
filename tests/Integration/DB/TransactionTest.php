<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Integration\DB;

use Exception;
use PDO;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Database\DB;

class TransactionTest extends TestCase
{
    private PDO $pdo;
    private DB $db;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            balance INT
        )");

        $this->pdo->prepare("INSERT INTO users (name, balance) VALUES (?, ?)")->execute(['Alice', 100]);
        $this->pdo->prepare("INSERT INTO users (name, balance) VALUES (?, ?)")->execute(['Bob', 50]);

        $this->db = new DB($this->pdo);
    }


    public function testBeginTransactionAndCommit(): void
    {
        $this->db->beginTransaction();

        $this->db->table('users')
            ->where('id', '=', 1)
            ->update(['balance' => 200]);

        $this->db->commit();

        $row = $this->db->table('users')->where('id', '=', 1)->first();
        $this->assertSame(200, $row['balance']);
    }

    public function testBeginTransactionAndRollBack(): void
    {
        $this->db->beginTransaction();

        $this->db->table('users')
            ->where('id', '=', 1)
            ->update(['balance' => 200]);

        $this->db->rollBack();

        $row = $this->db->table('users')->where('id', '=', 1)->first();
        $this->assertSame(100, $row['balance']);
    }


    public function testTransactionCommitsOnSuccess(): void
    {
        $result = $this->db->transaction(function (DB $db) {
            $db->table('users')->where('id', '=', 1)->update(['balance' => 200]);
            $db->table('users')->where('id', '=', 2)->update(['balance' => 150]);
            return 'done';
        });

        $this->assertSame('done', $result);

        $alice = $this->db->table('users')->where('id', '=', 1)->first();
        $bob = $this->db->table('users')->where('id', '=', 2)->first();
        $this->assertSame(200, $alice['balance']);
        $this->assertSame(150, $bob['balance']);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->db->transaction(function (DB $db) {
                $db->table('users')->where('id', '=', 1)->update(['balance' => 999]);
                throw new Exception('boom');
            });
            $this->fail('Exception should have been thrown');
        } catch (Exception $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $alice = $this->db->table('users')->where('id', '=', 1)->first();
        $this->assertSame(100, $alice['balance']);
    }

    public function testTransactionReThrowsExceptionAfterRollBack(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('boom');

        $this->db->transaction(function (DB $db) {
            $db->table('users')->insert(['name' => 'Charlie', 'balance' => 0]);
            throw new Exception('boom');
        });
    }

    public function testTransactionReturnsClosureResult(): void
    {
        $result = $this->db->transaction(function (DB $db) {
            return $db->table('users')->get();
        });

        $this->assertCount(2, $result);
    }

    public function testTransactionNestedCallsWork(): void
    {
        $result = $this->db->transaction(function (DB $db) {
            $db->table('users')->insert(['name' => 'Charlie', 'balance' => 0]);
            $db->table('users')->insert(['name' => 'Dave', 'balance' => 0]);
            return $db->table('users')->get();
        });

        $this->assertCount(4, $result);
    }

    public function testMultipleUpdatesAtomically(): void
    {
        $this->db->transaction(function (DB $db) {
            $db->table('users')->where('id', '=', 1)->update(['balance' => 50]);
            $db->table('users')->where('id', '=', 2)->update(['balance' => 100]);
        });

        $alice = $this->db->table('users')->where('id', '=', 1)->first();
        $bob = $this->db->table('users')->where('id', '=', 2)->first();

        $this->assertSame(50, $alice['balance']);
        $this->assertSame(100, $bob['balance']);
    }

    public function testFailedTransferRollsBackBoth(): void
    {
        $aliceId = 1;
        $bobId = 2;

        $this->expectExceptionMessage('transfer failed');

        $this->db->transaction(function (DB $db) use ($aliceId) {
            $db->table('users')->where('id', '=', $aliceId)->update(['balance' => 350]);
            throw new Exception('transfer failed');
        });

        $alice = $this->db->table('users')->where('id', '=', $aliceId)->first();
        $bob = $this->db->table('users')->where('id', '=', $bobId)->first();

        $this->assertSame(100, $alice['balance']);
        $this->assertSame(50, $bob['balance']);
    }
}
