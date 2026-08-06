<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Unit\QueryBuilder;

use PHPUnit\Framework\TestCase;
use SimpleORM\Grammar\Grammar;
use SimpleORM\Grammar\SqliteGrammar;
use SimpleORM\Query\QueryBuilder;
use SimpleORM\Query\SelectColumn;


class QueryBuilderTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }


    public function testSelect()
    {
        $qb = new QueryBuilder($this->grammar, 'users');

        $qb = $qb->select('column1', 'column2', 'column3');
        $this->assertEquals(['column1', 'column2', 'column3'], array_map(fn(SelectColumn $c) => $c->expression, $qb->columns));
    }

    public function testWheres(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $this->assertEquals('users', $qb->table);
        $qb = $qb
            ->where('name', '=' , 'John Doe')
            ->where('age', '>', 20)
            ->whereEquals('email', 'test@domain.com');

        $this->assertCount(3, $qb->wheres);

        $this->assertEquals('name', $qb->wheres[0]->column);
        $this->assertEquals('=', $qb->wheres[0]->operator);
        $this->assertEquals('John Doe', $qb->wheres[0]->value);

        $this->assertEquals('age', $qb->wheres[1]->column);
        $this->assertEquals('>', $qb->wheres[1]->operator);
        $this->assertEquals(20, $qb->wheres[1]->value);

        $this->assertEquals('email', $qb->wheres[2]->column);
        $this->assertEquals('=', $qb->wheres[2]->operator);
        $this->assertEquals('test@domain.com', $qb->wheres[2]->value);
    }
    public function testOrderBy(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $this->assertEquals('users', $qb->table);
        $qb = $qb
            ->orderBy('age', 'DESC')
            ->orderBy('number');

        $this->assertCount(2, $qb->orderBys);
        $ageOrderBy = $qb->orderBys[0];
        $numberOrderBy = $qb->orderBys[1];
        $this->assertEquals('age', $ageOrderBy->column);
        $this->assertEquals('DESC', $ageOrderBy->direction);

        $this->assertEquals('number', $numberOrderBy->column);
        $this->assertEquals('ASC', $numberOrderBy->direction);
    }

    public function testInvalidSortDirection()
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $this->assertEquals('users', $qb->table);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid direction value - you should use "ASC" or "DESC"');

        $qb->orderBy('age', 'INVALID');
    }

    public function testLimit(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb = $qb->limit(10);
        $this->assertEquals(10, $qb->limitValue);
    }

    public function testLimitAndOffset(): void
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $qb = $qb->limit(10)->offset(20);
        $this->assertEquals(10, $qb->limitValue);
        $this->assertEquals(20, $qb->offsetValue);
    }

    public function testFullQuery()
    {
        $qb = new QueryBuilder($this->grammar, 'users');
        $this->assertEquals('users', $qb->table);
        $qb = $qb->select('column1', 'column2', 'column3')
            ->selectRaw('lower(column2)')
            ->where('name', '=' , 'John Doe')
            ->where('age', '>', 20)
            ->whereEquals('email', 'test@domain.com')
            ->orderBy('age', 'DESC')
            ->orderBy('number')
            ->limit(10)
            ->offset(20);

        $this->assertEquals(['column1', 'column2', 'column3', 'lower(column2)'], array_map(fn(SelectColumn $c) => $c->expression, $qb->columns));
        $this->assertCount(3, $qb->wheres);
        $this->assertCount(2, $qb->orderBys);

        $this->assertEquals('name', $qb->wheres[0]->column);
        $this->assertEquals('=', $qb->wheres[0]->operator);
        $this->assertEquals('John Doe', $qb->wheres[0]->value);

        $this->assertEquals('age', $qb->wheres[1]->column);
        $this->assertEquals('>', $qb->wheres[1]->operator);
        $this->assertEquals(20, $qb->wheres[1]->value);

        $this->assertEquals('email', $qb->wheres[2]->column);
        $this->assertEquals('=', $qb->wheres[2]->operator);
        $this->assertEquals('test@domain.com', $qb->wheres[2]->value);

        $this->assertEquals('age', $qb->orderBys[0]->column);
        $this->assertEquals('DESC', $qb->orderBys[0]->direction);

        $this->assertEquals('number', $qb->orderBys[1]->column);
        $this->assertEquals('ASC', $qb->orderBys[1]->direction);
    }

}
