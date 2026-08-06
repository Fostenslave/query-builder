<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;


class WhereRawBindingsCompileTest extends TestCase
{
    private SqliteGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    public function testWhereRawWithoutBindingsProducesRawSql(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->whereRaw('age > 20')
            ->compile();

        $this->assertSame('SELECT * FROM "users" WHERE age > 20', $compiled->sql);
        $this->assertSame([], $compiled->bindings);
    }

    public function testWhereRawWithSingleBinding(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->whereRaw('age > ?', [20])
            ->compile();

        $this->assertSame('SELECT * FROM "users" WHERE age > :raw_0_0', $compiled->sql);
        $this->assertSame([':raw_0_0' => 20], $compiled->bindings);
    }

    public function testWhereRawWithMultipleBindings(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->whereRaw('age > ? AND age < ?', [18, 65])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE age > :raw_0_0 AND age < :raw_0_1',
            $compiled->sql,
        );
        $this->assertSame([':raw_0_0' => 18, ':raw_0_1' => 65], $compiled->bindings);
    }

    public function testWhereRawBindingKeysAreUniqueAcrossMixedWheres(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->where('active', '=', 1)           // :where_0
            ->whereRaw('age > ?', [20])         // :raw_1_0 (second where's index is 1)
            ->where('name', '=', 'Alice')       // :where_2
            ->compile();

        // bindings: :where_0, :raw_1_0, :where_2 — все уникальны
        $this->assertSame('SELECT * FROM "users" WHERE "active" = :where_0 AND age > :raw_1_0 AND "name" = :where_2', $compiled->sql);
        $this->assertSame(
            [':where_0' => 1, ':raw_1_0' => 20, ':where_2' => 'Alice'],
            $compiled->bindings,
        );
    }

    public function testWhereRawTwoRawClausesHaveUniqueKeys(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->whereRaw('age > ?', [18])
            ->whereRaw('age < ?', [65])
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE age > :raw_0_0 AND age < :raw_1_0',
            $compiled->sql,
        );
        $this->assertSame([':raw_0_0' => 18, ':raw_1_0' => 65], $compiled->bindings);
    }

    public function testWhereRawBindingsStringValues(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->whereRaw('name LIKE ?', ['%alice%'])
            ->compile();

        $this->assertSame('SELECT * FROM "users" WHERE name LIKE :raw_0_0', $compiled->sql);
        $this->assertSame([':raw_0_0' => '%alice%'], $compiled->bindings);
    }
}