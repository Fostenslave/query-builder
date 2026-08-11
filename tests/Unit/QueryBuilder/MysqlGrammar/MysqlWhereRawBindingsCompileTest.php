<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Tests\Unit\QueryBuilder\MysqlGrammar;

use PHPUnit\Framework\TestCase;
use Fostenslave\QueryBuilder\Grammar\MysqlGrammar;
use Fostenslave\QueryBuilder\Query\QueryBuilder;


class MysqlWhereRawBindingsCompileTest extends TestCase
{
    private MysqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new MysqlGrammar();
    }


    public function testWhereRawMultipleBindingsMysql(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->whereRaw('age > ? AND age < ?', [18, 65])
            ->whereRaw('custom_column = ? and custom_column2 = ?', ['33', '665'])
            ->whereRaw('name_column = :name', ['name' => 'Alex'])
            ->compile();

        $this->assertSame(
            'SELECT * FROM `users` WHERE age > :raw_0_0 AND age < :raw_0_1 AND custom_column = :raw_1_0 and custom_column2 = :raw_1_1 AND name_column = :name',
            $compiled->sql,
        );

        $this->assertSame([
            ':raw_0_0' => 18,
            ':raw_0_1' => 65,
            ':raw_1_0' => '33',
            ':raw_1_1' => '665',
            ':name' => 'Alex',
        ], $compiled->bindings);
    }

    public function testWhereRawMixedMysql(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->where('active', '=', 1)
            ->whereRaw('age > ?', [20])
            ->compile();

        $this->assertSame(
            'SELECT * FROM `users` WHERE `active` = :where_0 AND age > :raw_1_0',
            $compiled->sql,
        );
        $this->assertSame([':where_0' => 1, ':raw_1_0' => 20], $compiled->bindings);
    }

    public function testWhereRawNamedPlaceholders(): void
    {
        $compiled = new QueryBuilder($this->grammar, 'users')
            ->where('active', '=', 1)
            ->whereRaw('age > :age AND name = :name', ['age' => 20, 'name' => 'Alice'])
            ->compile();

        $this->assertSame(
            'SELECT * FROM `users` WHERE `active` = :where_0 AND age > :age AND name = :name',
            $compiled->sql,
        );
    }

    public function testWhereRawBindingsCountLessThanPlaceholdersCount(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('The number of placeholders and bindings must match placeholders: 1 bindings: 0');

        new QueryBuilder($this->grammar, 'users')
            ->where('active', '=', 1)
            ->whereRaw('age > ?')
            ->compile();

        $this->expectExceptionMessage('The number of placeholders and bindings must match placeholders: 2 bindings: 1');

        new QueryBuilder($this->grammar, 'users')
            ->where('active', '=', 1)
            ->whereRaw('age > ? and order < ?', [3])
            ->compile();

        $this->expectExceptionMessage('The number of placeholders and bindings must match placeholders: 0 bindings: 3');

        new QueryBuilder($this->grammar, 'users')
            ->where('active', '=', 1)
            ->whereRaw('age > 15', [3, 'Alice', 15])
            ->compile();
    }

}