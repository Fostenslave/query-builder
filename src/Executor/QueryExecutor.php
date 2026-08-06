<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Executor;


use Fostenslave\QueryBuilder\Query\CompiledQuery;

/**
 * Execute CompiledQuery and returns result
 */
interface QueryExecutor
{
    /**
     * Execute compiled select query and returns array of rows
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(CompiledQuery $query): array;

    /**
     * Execute compiled select query and returns first row as array
     *
     * @return array<string, mixed>|null
     */
    public function fetch(CompiledQuery $query): ?array;

    /**
     * Execute INSERT/UPDATE/DELETE and returns affected rows count
     */
    public function execute(CompiledQuery $query): int;

    /**
     * Returns id of last inserted row (PDO::lastInsertId in PDO realization).
     */
    public function lastInsertId(): string;
}
