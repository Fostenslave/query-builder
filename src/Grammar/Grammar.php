<?php

declare(strict_types=1);

namespace SimpleORM\Grammar;

use SimpleORM\Query\CompiledQuery;
use SimpleORM\Query\JoinClause;
use SimpleORM\Query\OrderByClause;
use SimpleORM\Query\RawClause;
use SimpleORM\Query\Expression;
use SimpleORM\Query\WhereClause;

/**
 * Точка расширения для разных СУБД.
 * Каждая реализация транслирует generic-структуру запроса в диалект конкретной БД.
 *
 * Реализация: SqliteGrammar (в src/Grammar/SqliteGrammar.php).
 * Будущее: MysqlGrammar, PostgresGrammar — без изменения ядра.
 */
interface Grammar
{
    /**
     * @param string $table
     * @param array<Expression>  $columns
     * @param array<JoinClause> $joins
     * @param array<WhereClause|RawClause> $wheres
     * @param array<OrderByClause> $orderBys
     * @param array<Expression> $groupBys
     * @param array<Expression> $havings
     * @param int|null $limit
     * @param int|null $offset
     */
    public function compileSelect(
        string $table,
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
     * @param array<string, mixed> $values  column => value (SET clause)
     * @param array<WhereClause> $wheres
     */
    public function compileUpdate(string $table, array $values, array $wheres): CompiledQuery;

    /**
     * @param string $table
     * @param array<WhereClause> $wheres
     */
    public function compileDelete(string $table, array $wheres): CompiledQuery;

    public function getCountExpression(): string;
}
