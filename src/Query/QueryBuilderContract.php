<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;


interface QueryBuilderContract
{
    /**
     * Указывает колонки для выборки. Пустой массив или без вызова = SELECT *.
     *
     * @param string ...$columns 'id', 'name', 'age'
     */
    public function select(string ...$columns): static;

    /**
     * Добавляет подзапрос в качестве select
     * @param QueryBuilderContract $builder
     * @param string $alias
     * @return static
     */
    public function selectSub(QueryBuilderContract $builder, string $alias): static;

    /**
     * Добавляет INNER JOIN.
     *
     * @param string $table    Присоединяемая таблица ('posts').
     * @param string $left     Колонка левой таблицы ('users.id').
     * @param string $operator '=', '!=', '>', '<', '>=', '<=', '<>'.
     * @param string $right    Колонка правой таблицы ('posts.user_id').
     */
    public function join(string $table, string $left, string $operator, string $right): static;

    /**
     * Добавляет LEFT JOIN. Аналог join() с типом LEFT.
     */
    public function leftJoin(string $table, string $left, string $operator, string $right): static;

    /**
     * Добавляет RIGHT JOIN. Аналог join() с типом RIGHT.
     *
     * Внимание: SQLite поддерживает RIGHT JOIN только с версии 3.39 (2022).
     */
    public function rightJoin(string $table, string $left, string $operator, string $right): static;

    /**
     * Добавляет условие WHERE (через AND).
     *
     * @param string|callable $column   Имя колонки ИЛИ callable для группы условий: where(function($g) { ... })
     * @param string          $operator '=', '!=', '>', '<', '>=', '<='
     * @param mixed           $value    Значение (передаётся через плейсхолдер, не вклеивается в SQL).
     */
    public function where(string|callable $column, string $operator = '', mixed $value = null): static;

    /**
     * Добавляет условие WHERE через OR.
     *
     * @param string|callable $column Имя колонки или callable для группы условий: where(function($g) { ... })
     * @param string $operator '=', '!=', '>', '<', '>=', '<='
     * @param mixed $value Значение (через плейсхолдер).
     */
    public function orWhere(string|callable $column, string $operator, mixed $value): static;

    /**
     * Добавляет сортировку ORDER BY.
     *
     * @param string $column   Имя колонки.
     * @param string $direction 'ASC' или 'DESC'.
     */
    public function orderBy(string $column, string $direction = 'ASC'): static;

    /**
     * Ограничивает количество строк (LIMIT).
     */
    public function limit(int $limit): static;

    /**
     * Смещение (OFFSET). Без limit() не имеет смысла в SQLite.
     */
    public function offset(int $offset): static;

    /**
     * Собирает и возвращает запрос.
     *
     * @return CompiledQuery SQL-строка + массив параметров.
     */
    public function compile(): CompiledQuery;

    /**
     * Выполняет запрос и возвращает все строки.
     *
     * Терминальный метод — вызывает QueryExecutor под капотом.
     * Требует, чтобы QueryBuilder был создан с Executor (через Database::table()).
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array;

    /**
     * Выполняет запрос и возвращает первую строку.
     *
     * Терминальный метод — вызывает QueryExecutor под капотом.
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array;

    /**
     * Компилирует INSERT-запрос.
     *
     * @param array<string, mixed> $values  column => value
     */
    public function compileInsert(array $values): CompiledQuery;

    /**
     * Компилирует UPDATE-запрос. Использует накопленные where().
     *
     * @param array<string, mixed> $values  column => value (SET clause)
     */
    public function compileUpdate(array $values): CompiledQuery;

    /**
     * Компилирует DELETE-запрос. Использует накопленные where().
     */
    public function compileDelete(): CompiledQuery;

    /**
     * Выполняет INSERT и возвращает ID последней вставленной записи.
     *
     * Терминальный метод.
     *
     * @param array<string, mixed> $values  column => value
     */
    public function insert(array $values): int;

    /**
     * Выполняет UPDATE и возвращает количество затронутых строк.
     *
     * Терминальный метод. Использует накопленные where().
     *
     * @param array<string, mixed> $values  column => value (SET clause)
     */
    public function update(array $values): int;

    /**
     * Выполняет DELETE и возвращает количество затронутых строк.
     *
     * Терминальный метод. Использует накопленные where().
     */
    public function delete(): int;

    /**
     * Вставляет сырой SQL-фрагмент в список колонок SELECT.
     *
     * Для выражений, которые не нужно кавычить: COUNT(*), SUM(amount), raw aliases.
     *
     * @param string $expression  Сырое SQL-выражение ('COUNT(*)', 'SUM(amount) AS total')
     */
    public function selectRaw(string $expression): static;

    /**
     * Добавляет условие WHERE с сырым SQL-выражением.
     *
     * Для динамических значений используйте "?" и $bindings:
     * whereRaw('age > ?', [$minAge])
     *
     * @param string $sql       Сырой SQL ('age > ?', 'age > ? AND active = ?').
     * @param array<mixed>  $bindings  Значения для ? и :name — в порядке следования.
     */
    public function whereRaw(string $sql, array $bindings = []): static;

    /**
     * Добавляет условие WHERE через OR с сырым SQL-выражением.
     *
     * @param string $sql       Сырой SQL ('role = ?').
     * @param array<mixed>  $bindings  Значения для ?.
     */
    public function orWhereRaw(string $sql, array $bindings = []): static;

    /**
     * Добавляет условие WHERE column IN (values) (через AND).
     *
     * @param string $column Имя колонки.
     * @param array<int, mixed>  $values Массив значений.
     */
    public function whereIn(string $column, array $values): static;

    /**
     * Добавляет условие WHERE column NOT IN (values) (через AND).
     *
     * @param string $column Имя колонки.
     * @param array<int, mixed>  $values Массив значений.
     */
    public function whereNotIn(string $column, array $values): static;

    /**
     * Добавляет условие WHERE column IN (values) через OR.
     *
     * @param string $column Имя колонки.
     * @param array<int, mixed> $values Массив значений.
     */
    public function orWhereIn(string $column, array $values): static;

    /**
     * Добавляет условие WHERE column NOT IN (values) через OR.
     *
     * @param string $column Имя колонки.
     * @param array<int, mixed>  $values Массив значений.
     */
    public function orWhereNotIn(string $column, array $values): static;

    /**
     * Возвращает количество строк запроса (SELECT COUNT(*)).
     *
     * Терминальный метод.
     */
    public function count(): int;

    /**
     * Проверяет, есть ли хотя бы одна строка по условию.
     *
     * Терминальный метод.
     */
    public function exists(): bool;

    /**
     * Пагинация: страница и количество на страницу.
     *
     * @param int $page    Номер страницы (1-based).
     * @param int $perPage Количество строк на страницу.
     * @return array<int, array<string, mixed>>
     */
    public function paginate(int $page, int $perPage): array;

    /**
     * Добавляет GROUP BY по указанным колонкам.
     *
     * @param string ...$columns
     */
    public function groupBy(string ...$columns): static;


    /**
     * Добавляет GROUP BY по сырому выражению
     *
     * @param string $expression
     */
    public function groupByRaw(string $expression): static;

    /**
     * Добавляет условие HAVING (через AND).
     *
     * @param string $column   Имя колонки или агрегатное выражение ('COUNT(*)', 'SUM(amount)').
     * @param string $operator '=', '!=', '>', '<', '>=', '<='
     * @param mixed  $value    Значение (через плейсхолдер).
     */
    public function having(string $column, string $operator, mixed $value): static;

    /**
     * Добавляет сырое условие HAVING с плейсхолдерами.
     *
     * @param string $sql       Сырой SQL ('SUM(amount) > ?').
     * @param array<mixed>  $bindings  Значения для ?.
     */
    public function havingRaw(string $sql, array $bindings = []): static;

    /**
     * Возвращает SUM колонки.
     *
     * Терминальный метод.
     */
    public function sum(string $column): mixed;

    /**
     * Возвращает AVG колонки.
     *
     * Терминальный метод.
     */
    public function avg(string $column): mixed;

    /**
     * Возвращает MIN колонки.
     *
     * Терминальный метод.
     */
    public function min(string $column): mixed;

    /**
     * Возвращает MAX колонки.
     *
     * Терминальный метод.
     */
    public function max(string $column): mixed;
}
