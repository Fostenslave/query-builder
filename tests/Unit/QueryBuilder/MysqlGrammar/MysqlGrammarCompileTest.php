<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Unit\QueryBuilder\MysqlGrammar;

use PHPUnit\Framework\TestCase;
use SimpleORM\Grammar\MysqlGrammar;
use SimpleORM\Query\JoinType;
use SimpleORM\Query\JoinClause;
use SimpleORM\Query\Expression;
use SimpleORM\Query\WhereClause;


class MysqlGrammarCompileTest extends TestCase
{
    private MysqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new MysqlGrammar();
    }

    public function testCompileSelectWrapsTableWithBackticks(): void
    {
        $compiled = $this->grammar->compileSelect(
            table: 'users',
            columns: [],
            joins: [],
            wheres: [],
            orderBys: [],
            groupBys: [],
            havings: [],
            limit: null,
            offset: null,
        );

        $this->assertSame('SELECT * FROM `users`', $compiled->sql);
    }

    public function testCompileSelectWrapsAliasedTableWithBackticks(): void
    {
        $compiled = $this->grammar->compileSelect(
            table: 'users as u',
            columns: [],
            joins: [],
            wheres: [],
            orderBys: [],
            groupBys: [],
            havings: [],
            limit: null,
            offset: null,
        );

        $this->assertSame('SELECT * FROM `users` as `u`', $compiled->sql);
    }

    public function testCompileSelectWrapsDottedTableWithBackticks(): void
    {
        $compiled = $this->grammar->compileSelect(
            table: 'public.users',
            columns: [],
            joins: [],
            wheres: [],
            orderBys: [],
            groupBys: [],
            havings: [],
            limit: null,
            offset: null,
        );

        $this->assertSame('SELECT * FROM `public`.`users`', $compiled->sql);
    }

    public function testCompileSelectWithWhereAndLimit(): void
    {
        $compiled = $this->grammar->compileSelect(
            table: 'users',
            columns: [
                new Expression('id'),
                new Expression('name'),
                new Expression('lower(name) as my_name', isRaw: true)
            ],
            joins: [],
            wheres: [new WhereClause('age', '>', 18)],
            orderBys: [],
            groupBys: [],
            havings: [],
            limit: 10,
            offset: null,
        );

        $this->assertSame('SELECT `id`, `name`, lower(name) as my_name FROM `users` WHERE `age` > :where_0 LIMIT 10', $compiled->sql);
        $this->assertSame([':where_0' => 18], $compiled->bindings);
    }

    public function testCompileSelectWithJoin(): void
    {
        $compiled = $this->grammar->compileSelect(
            table: 'users',
            columns: [],
            joins: [new JoinClause('posts', JoinType::Inner, 'users.id', '=', 'posts.user_id')],
            wheres: [],
            orderBys: [],
            groupBys: [],
            havings: [],
            limit: null,
            offset: null,
        );

        $this->assertSame('SELECT * FROM `users` INNER JOIN `posts` ON users.id = posts.user_id', $compiled->sql);
    }

    public function testCompileInsertWrapsTableWithBackticks(): void
    {
        $compiled = $this->grammar->compileInsert('users', ['name' => 'Alice', 'age' => 30]);

        $this->assertSame('INSERT INTO `users` (`name`, `age`) VALUES (:val_0, :val_1)', $compiled->sql);
        $this->assertSame([':val_0' => 'Alice', ':val_1' => 30], $compiled->bindings);
    }

    public function testCompileUpdateWrapsTableWithBackticks(): void
    {
        $compiled = $this->grammar->compileUpdate(
            'users',
            ['name' => 'Bob'],
            [new WhereClause('id', '=', 1)],
        );

        $this->assertSame('UPDATE `users` SET `name` = :set_0 WHERE `id` = :where_0', $compiled->sql);
        $this->assertSame([':set_0' => 'Bob', ':where_0' => 1], $compiled->bindings);
    }

    public function testCompileDeleteWrapsTableWithBackticks(): void
    {
        $compiled = $this->grammar->compileDelete(
            'users',
            [new WhereClause('id', '=', 1)],
        );

        $this->assertSame('DELETE FROM `users` WHERE `id` = :where_0', $compiled->sql);
        $this->assertSame([':where_0' => 1], $compiled->bindings);
    }


    public function testGetCountExpression(): void
    {
        $this->assertSame('count(*)', $this->grammar->getCountExpression());
    }

    public function testCompileSelectEscapesBacktickInTableName(): void
    {
        $compiled = $this->grammar->compileSelect(
            table: 'na`ve',
            columns: [],
            joins: [],
            wheres: [],
            orderBys: [],
            groupBys: [],
            havings: [],
            limit: null,
            offset: null,
        );

        $this->assertSame('SELECT * FROM `na``ve`', $compiled->sql);
    }
}
