<?php

declare(strict_types=1);

namespace SimpleORM\Query;

final readonly class WhereClause
{
    // Разрешенные операторы по идее могут быть разные для разных SQL Grammar, но есть же стандарт SQL

    private const array ALLOWED_OPERATORS = ['=', '<', '>', '>=', '<=', '<>', '!='];
    private(set) string $column;
    private(set) string $operator;

    private(set) mixed $value;
    public function __construct(string $column, string|int $operator, mixed $value) {
        $this->column = $column;

        if (trim($column) === '') {
            throw new \DomainException('You cannot set empty column');
        }

        if (!$this->isOperator($operator)) {
            $allowedOperators = implode(', ',self::ALLOWED_OPERATORS);
            throw new \DomainException("Operator must be one of values $allowedOperators");
        }

        $this->operator = $operator;
        $this->value = $value;
    }

    private function isOperator(string|int $string): bool
    {
        return in_array($string, self::ALLOWED_OPERATORS);
    }
}
