<?php

declare(strict_types=1);

namespace SimpleORM\Query;

/**
 * Выполняет собранный запрос через PDO и возвращает результат.
 *
 * Граница с БД — точка побочных эффектов.
 */
interface QueryExecutor
{
    /**
     * Выполняет SELECT-запрос и возвращает все строки.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(CompiledQuery $query): array;

    /**
     * Выполняет SELECT-запрос и возвращает первую строку.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(CompiledQuery $query): ?array;

    /**
     * Выполняет INSERT/UPDATE/DELETE и возвращает количество затронутых строк.
     */
    public function execute(CompiledQuery $query): int;

    /**
     * Возвращает ID последней вставленной записи (PDO::lastInsertId).
     */
    public function lastInsertId(): string;
}
