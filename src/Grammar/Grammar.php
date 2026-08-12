<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Grammar;

use Fostenslave\QueryBuilder\Query\CompiledQuery;
use Fostenslave\QueryBuilder\Query\JoinClause;
use Fostenslave\QueryBuilder\Query\OrderByClause;
use Fostenslave\QueryBuilder\Query\QueryBuilderContract;
use Fostenslave\QueryBuilder\Query\RawClause;
use Fostenslave\QueryBuilder\Query\Expression;
use Fostenslave\QueryBuilder\Query\SubQuery;
use Fostenslave\QueryBuilder\Query\WhereClause;

/**
 * Dialect interface for different database realizations
 * Realization examples: SqliteGrammar.php, MysqlGrammar.php
 */
interface Grammar
{
    /**
     * @param string|null $table
     * @param SubQuery|null $fromSub
     * @param array<Expression|SubQuery> $columns
     * @param array<JoinClause> $joins
     * @param array<WhereClause|RawClause> $wheres
     * @param array<OrderByClause> $orderBys
     * @param array<Expression> $groupBys
     * @param array<Expression> $havings
     * @param int|null $limit
     * @param int|null $offset
     * @return CompiledQuery
     */
    public function compileSelect(
        string|null $table,
        SubQuery|null $fromSub,
        array $columns,
        array $joins,
        array $wheres,
        array $orderBys,
        array $groupBys,
        array $havings,
        ?int $limit,
        ?int $offset,
    ): CompiledQuery;

    /**
     * @param string $table
     * @param array<string, mixed> $values  column => value
     */
    public function compileInsert(string $table, array $values): CompiledQuery;

    /**
     * @param string $table
     * @param array<string, mixed> $values
     * @param array<WhereClause> $wheres
     */
    public function compileUpdate(string $table, array $values, array $wheres): CompiledQuery;

    /**
     * @param string $table
     * @param array<WhereClause> $wheres
     */
    public function compileDelete(string $table, array $wheres): CompiledQuery;

    public function getCountExpression(): string;

    public function wrapTable(string $table): string;
}
