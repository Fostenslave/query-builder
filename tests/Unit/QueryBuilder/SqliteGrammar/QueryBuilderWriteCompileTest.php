<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;


class QueryBuilderWriteCompileTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    // ── INSERT ──────────────────────────────────────────────

    public function testInsertSingleRow(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->compileInsert(['name' => 'Alice', 'age' => 30]);

        $this->assertSame(
            'INSERT INTO "users" ("name", "age") VALUES (:val_0, :val_1)',
            $compiled->sql,
        );
        $this->assertSame([':val_0' => 'Alice', ':val_1' => 30], $compiled->bindings);
    }

    public function testInsertWithThreeColumns(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->compileInsert(['name' => 'Bob', 'age' => 25, 'active' => 1]);

        $this->assertSame(
            'INSERT INTO "users" ("name", "age", "active") VALUES (:val_0, :val_1, :val_2)',
            $compiled->sql,
        );
        $this->assertSame(
            [':val_0' => 'Bob', ':val_1' => 25, ':val_2' => 1],
            $compiled->bindings,
        );
    }

    // ── UPDATE ──────────────────────────────────────────────

    public function testUpdateWithWhere(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->where('id', '=', 1)
            ->compileUpdate(['name' => 'Bob']);

        $this->assertSame(
            'UPDATE "users" SET "name" = :set_0 WHERE "id" = :where_0',
            $compiled->sql,
        );
        $this->assertSame(
            [':set_0' => 'Bob', ':where_0' => 1],
            $compiled->bindings,
        );
    }

    public function testUpdateMultipleColumnsWithMultipleWheres(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->where('age', '>', 18)
            ->where('active', '=', true)
            ->compileUpdate(['name' => 'Bob', 'age' => 25]);

        $this->assertSame(
            'UPDATE "users" SET "name" = :set_0, "age" = :set_1 WHERE "age" > :where_0 AND "active" = :where_1',
            $compiled->sql,
        );
        $this->assertSame(
            [':set_0' => 'Bob', ':set_1' => 25, ':where_0' => 18, ':where_1' => true],
            $compiled->bindings,
        );
    }

    public function testUpdateWithoutWhere(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->compileUpdate(['name' => 'Bob']);

        $this->assertSame(
            'UPDATE "users" SET "name" = :set_0',
            $compiled->sql,
        );
        $this->assertSame([':set_0' => 'Bob'], $compiled->bindings);
    }

    public function testDeleteWithWhere(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->where('id', '=', 1)
            ->compileDelete();

        $this->assertSame(
            'DELETE FROM "users" WHERE "id" = :where_0',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 1], $compiled->bindings);
    }

    public function testDeleteWithMultipleWheres(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->where('age', '<', 18)
            ->where('active', '=', false)
            ->compileDelete();

        $this->assertSame(
            'DELETE FROM "users" WHERE "age" < :where_0 AND "active" = :where_1',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 18, ':where_1' => false],
            $compiled->bindings,
        );
    }

    public function testDeleteWithoutWhere(): void
    {
        $compiled = (new QueryBuilder($this->grammar, 'users'))
            ->compileDelete();

        $this->assertSame('DELETE FROM "users"', $compiled->sql);
        $this->assertSame([], $compiled->bindings);
    }
}
