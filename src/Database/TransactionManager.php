<?php

declare(strict_types=1);

namespace SimpleORM\Database;

use Closure;
use Throwable;

/**
 * Интерфейс управления транзакциями.
 *
 * Транзакции живут на уровне точки входа (DB), делегируя в PDO.
 */
interface TransactionManager
{
    /**
     * Открывает транзакцию.
     */
    public function beginTransaction(): void;

    /**
     * Фиксирует транзакцию.
     */
    public function commit(): void;

    /**
     * Откатывает транзакцию.
     */
    public function rollBack(): void;

    /**
     * Выполняет замыкание внутри транзакции.
     *
     * Автоматически делает commit при успехе и rollBack при исключении.
     * Возвращает результат замыкания.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     *
     * @throws Throwable Если $callback бросил исключение (после rollBack).
     */
    public function transaction(Closure $callback): mixed;
}
