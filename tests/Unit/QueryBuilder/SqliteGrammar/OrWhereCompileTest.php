<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class OrWhereCompileTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    private function builder(): QueryBuilder
    {
        return new QueryBuilder($this->grammar, 'users');
    }

    public function testOrWhereWithThreeConditionsMixed(): void
    {
        $compiled = $this->builder()
            ->where('age', '>', 18)
            ->orWhere('role', '=', 'admin')
            ->where('active', '=', 1)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "age" > :where_0 OR "role" = :where_1 AND "active" = :where_2',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 18, ':where_1' => 'admin', ':where_2' => 1],
            $compiled->bindings,
        );
    }

    public function testOrWhereRawTwoConditions(): void
    {
        $compiled = $this->builder()
            ->whereRaw('age > ?', [18])
            ->orWhereRaw('name = ?', ['Alice'])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE age > :raw_0_0 OR name = :raw_1_0',
            $compiled->sql,
        );
        $this->assertSame([':raw_0_0' => 18, ':raw_1_0' => 'Alice'], $compiled->bindings);
    }

    public function testOrWhereRawMixed(): void
    {
        $compiled = $this->builder()
            ->where('active', '=', 1)
            ->orWhereRaw('age > ? OR role = ?', [18, 'admin'])
            ->where('deleted', '!=', 1)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "active" = :where_0 OR age > :raw_1_0 '
            . 'OR role = :raw_1_1 AND "deleted" != :where_2',
            $compiled->sql,
        );
    }

    public function testUpdateWithOrWhere(): void
    {
        $compiled = $this->builder()
            ->where('id', '=', 1)
            ->orWhere('email', '=', 'test@example.com')
            ->compileUpdate(['deleted_at' => '2024-01-01']);

        $this->assertSame(
            'UPDATE "users" SET "deleted_at" = :set_0 WHERE "id" = :where_0 OR "email" = :where_1',
            $compiled->sql,
        );
    }
}
