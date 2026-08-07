<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\SqliteGrammar;

use Fostenslave\QueryBuilder\Query\ConditionGroupBuilder;
use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;

class WhereCallbackCompileTest extends TestCase
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

    public function testWhereGroupWithMultipleCallbackGroups(): void
    {
        $compiled = $this->builder()
            ->where('status', '=', 'enabled')
            ->where(function(ConditionGroupBuilder $g) {
                $g->where('role', '=', 'admin')
                  ->orWhere('role', '=', 'moderator');
            })
            ->orWhere(function(ConditionGroupBuilder $g) {
                $g->where('name', '=', 'administrator')
                    ->orWhere('name', '=', 'moderator652');
            })
            ->where(function(ConditionGroupBuilder $g) {
                $g->where('id', '>', 1)
                    ->where('id', '<', 100);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "status" = :where_0 AND ("role" = :where_1_0 OR "role" = :where_1_1) OR ("name" = :where_2_0 OR "name" = :where_2_1) AND ("id" > :where_3_0 AND "id" < :where_3_1)',
            $compiled->sql,
        );
        $this->assertSame(
            [
                ':where_0' => 'enabled',
                ':where_1_0' => 'admin',
                ':where_1_1' => 'moderator',
                ':where_2_0' => 'administrator',
                ':where_2_1' => 'moderator652',
                ':where_3_0' => 1,
                ':where_3_1' => 100
            ],
            $compiled->bindings,
        );
    }


    public function testOrWhereGroup(): void
    {
        $compiled = $this->builder()
            ->where('active', '=', 1)
            ->orWhere(function(ConditionGroupBuilder $g) {
                $g->where('role', '=', 'admin')
                  ->where('banned', '=', 0);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "active" = :where_0 OR ("role" = :where_1_0 AND "banned" = :where_1_1)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0' => 1, ':where_1_0' => 'admin', ':where_1_1' => 0],
            $compiled->bindings,
        );
    }

    public function testWhereRawInsideGroup(): void
    {
        $compiled = $this->builder()
            ->where(function(ConditionGroupBuilder $g) {
                $g->whereRaw('age > ?', [18])
                  ->orWhereRaw('name = ?', ['Alice']);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE (age > :where_0_0_0 OR name = :where_0_1_0)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0_0' => 18, ':where_0_1_0' => 'Alice'],
            $compiled->bindings,
        );
    }

    public function testMixedBindingsInsideGroup(): void
    {
        $compiled = $this->builder()
            ->where(function(ConditionGroupBuilder $g) {
                $g->where('active', '=', 1)
                  ->whereRaw('age > ?', [18]);
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("active" = :where_0_0 AND age > :where_0_1_0)',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 1, ':where_0_1_0' => 18],
            $compiled->bindings,
        );
    }

    public function testNestedGroups(): void
    {
        $compiled = $this->builder()
            ->where(function(ConditionGroupBuilder $g) {
                $g->where('active', '=', 1)
                  ->where(function(ConditionGroupBuilder $h) {
                      $h->where('role', '=', 'admin')
                        ->orWhere('role', '=', 'moderator');
                  });
            })
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("active" = :where_0_0 AND ("role" = :where_0_1_0 OR "role" = :where_0_1_1))',
            $compiled->sql,
        );
        $this->assertSame(
            [':where_0_0' => 1, ':where_0_1_0' => 'admin', ':where_0_1_1' => 'moderator'],
            $compiled->bindings,
        );
    }

    public function testGroupBindingKeysDoNotCollideWithTopLevel(): void
    {
        $compiled = $this->builder()
            ->where('x', '=', 1)
            ->where(function(ConditionGroupBuilder $g) {
                $g->where('y', '=', 2);
            })
            ->where('z', '=', 3)
            ->compile();

        $this->assertSame(
            'SELECT * FROM "users" WHERE "x" = :where_0 AND ("y" = :where_1_0) AND "z" = :where_2',
            $compiled->sql,
        );
        $this->assertCount(3, $compiled->bindings);
    }

    public function testUpdateWithGroup(): void
    {
        $compiled = $this->builder()
            ->where(function($g) {
                $g->where('role', '=', 'admin')
                  ->orWhere('role', '=', 'moderator');
            })
            ->compileUpdate(['active' => 0]);

        $this->assertSame(
            'UPDATE "users" SET "active" = :set_0 WHERE ("role" = :where_0_0 OR "role" = :where_0_1)',
            $compiled->sql,
        );
    }

    public function testDeleteWithOrWhere(): void
    {
        $compiled = $this->builder()
            ->where('expired', '=', 1)
            ->orWhere(function($g) {
                $g->where('role', '=', 'banned')
                  ->where('active', '=', 0);
            })
            ->compileDelete();

        $this->assertSame(
            'DELETE FROM "users" WHERE "expired" = :where_0 OR ("role" = :where_1_0 AND "active" = :where_1_1)',
            $compiled->sql,
        );
    }
}
