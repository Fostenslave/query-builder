<?php

declare(strict_types=1);

namespace SimpleORM\Query;

use DomainException;
use SimpleORM\Grammar\Grammar;

final readonly class WhereClause implements Compilable
{
    // Разрешенные операторы по идее могут быть разные для разных SQL Grammar, но есть же стандарт SQL
    private const array ALLOWED_OPERATORS = ['=', '<', '>', '>=', '<=', '<>', '!='];
    private(set) string $column;
    private(set) string $operator;
    private(set) string $prefix;

    private(set) mixed $value;
    public function __construct(string $column, string|int $operator, mixed $value, string $prefix = 'where') {
        $this->column = $column;
        if (trim($column) === '') {
            throw new DomainException('You cannot set empty column');
        }

        if (!$this->isOperator($operator)) {
            $allowedOperators = implode(', ',self::ALLOWED_OPERATORS);
            throw new DomainException("Operator must be one of values $allowedOperators");
        }

        $this->operator = $operator;
        $this->value = $value;
        $this->prefix = $prefix;
    }

    public function compile(Grammar $grammar, $sqlIndex = 0): CompiledQuery
    {
        $prefix = $this->prefix;
        $bindingKey = ":{$prefix}_$sqlIndex";
        $wrappedColumn = $grammar->wrapTable($this->column);

        if ($this->operator === '=' && $this->value === null) {
            return new CompiledQuery("$wrappedColumn is null", []);
        }

        if (in_array($this->operator, ['!=', '<>']) && $this->value === null) {
            return new CompiledQuery("$wrappedColumn is not null", []);
        }

        $sql = "$wrappedColumn $this->operator $bindingKey";

        return new CompiledQuery($sql, [$bindingKey => $this->value]);
    }

    private function isOperator(string|int $string): bool
    {
        return in_array($string, self::ALLOWED_OPERATORS);
    }
}
