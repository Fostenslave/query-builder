<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use SimpleORM\Grammar\Grammar;
use SimpleORM\Grammar\SqliteGrammar;
use SimpleORM\Query\QueryBuilder;


class QueryBuilderCompileTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    public function testSelectAllFromTable(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');

        $compiled = $qb->compile();

        $this->assertSame('SELECT * FROM "users"', $compiled->sql);
        $this->assertSame([], $compiled->bindings);
    }

    public function testSelectSpecificColumns(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->select('id', 'name', 'age');

        $compiled = $qb->compile();

        $this->assertSame('SELECT id, name, age FROM "users"', $compiled->sql);
        $this->assertSame([], $compiled->bindings);
    }

    public function testWhereSingleCondition(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->where('id', '=', 1);

        $compiled = $qb->compile();

        $this->assertSame('SELECT * FROM "users" WHERE id = :where_0', $compiled->sql);
        $this->assertSame([':where_0' => 1], $compiled->bindings);
    }

    public function testWhereMultipleConditionsAreAnded(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->where('age', '>', 18)
           ->where('name', '=', 'Alice');

        $compiled = $qb->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE age > :where_0 AND name = :where_1',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 18, ':where_1' => 'Alice'], $compiled->bindings);
    }

    public function testOrderByAsc(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->orderBy('name', 'ASC');

        $compiled = $qb->compile();

        $this->assertSame('SELECT * FROM "users" ORDER BY name ASC', $compiled->sql);
    }

    public function testOrderByDesc(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->orderBy('age', 'DESC');

        $compiled = $qb->compile();

        $this->assertSame('SELECT * FROM "users" ORDER BY age DESC', $compiled->sql);
    }

    public function testLimit(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->limit(10);

        $compiled = $qb->compile();

        $this->assertSame('SELECT * FROM "users" LIMIT 10', $compiled->sql);
    }

    public function testLimitAndOffset(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->limit(10)->offset(20);

        $compiled = $qb->compile();

        $this->assertSame('SELECT * FROM "users" LIMIT 10 OFFSET 20', $compiled->sql);
    }

    public function testFullQuery(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb->select('id', 'name')
           ->where('age', '>=', 18)
           ->where('active', '=', true)
           ->orderBy('name', 'ASC')
           ->limit(5)
           ->offset(10);

        $compiled = $qb->compile();

        $this->assertSame(
            'SELECT id, name FROM "users" WHERE age >= :where_0 AND active = :where_1 ORDER BY name ASC LIMIT 5 OFFSET 10',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 18, ':where_1' => true], $compiled->bindings);
    }
}
