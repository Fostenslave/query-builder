<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Unit\QueryBuilder\SqliteGrammar;

use PHPUnit\Framework\TestCase;
use SimpleORM\Grammar\Grammar;
use SimpleORM\Grammar\SqliteGrammar;
use SimpleORM\Query\QueryBuilder;

class QueryBuilderJoinCompileTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new SqliteGrammar();
    }

    public function testInnerJoin(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" INNER JOIN "posts" ON users.id = posts.user_id',
            $compiled->sql,
        );
        $this->assertSame([], $compiled->bindings);
    }

    public function testLeftJoin(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" LEFT JOIN "profiles" ON users.id = profiles.user_id',
            $compiled->sql,
        );
    }

    public function testRightJoin(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->rightJoin('roles', 'users.role_id', '=', 'roles.id')
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" RIGHT JOIN "roles" ON users.role_id = roles.id',
            $compiled->sql,
        );
    }

    public function testMultipleJoins(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users"'
            . ' INNER JOIN "posts" ON users.id = posts.user_id'
            . ' LEFT JOIN "comments" ON posts.id = comments.post_id',
            $compiled->sql,
        );
    }

    public function testJoinWithWhereAndOrderBy(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.age', '>', 18)
            ->orderBy('posts.created_at', 'DESC')
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users"'
            . ' INNER JOIN "posts" ON users.id = posts.user_id'
            . ' WHERE "users"."age" > :where_0'
            . ' ORDER BY "posts"."created_at" DESC',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 18], $compiled->bindings);
    }

    public function testJoinWithColumns(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->compile();

        $this->assertSame(
            'SELECT "users"."name", "posts"."title" FROM "users" INNER JOIN "posts" ON users.id = posts.user_id',
            $compiled->sql,
        );
    }

    public function testJoinWithLimit(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->limit(10)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" INNER JOIN "posts" ON users.id = posts.user_id LIMIT 10',
            $compiled->sql,
        );
    }

    public function testInvalidJoinOperatorThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid ON operator');

        new QueryBuilder($this->grammar, 'users')
            ->join('posts', 'users.id', 'INVALID', 'posts.user_id');
    }
}
