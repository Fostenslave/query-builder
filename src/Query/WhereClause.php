<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use DomainException;
use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class WhereClause implements CompilableLogic
{
    private const array ALLOWED_OPERATORS = ['=', '<', '>', '>=', '<=', '<>', '!='];
    private(set) string $column;
    private(set) string $operator;
    private(set) string $prefix;

    private(set) mixed $value;
    private(set) BooleanOperator $boolean;
    public function __construct(string $column, string|int $operator, mixed $value, string $prefix = 'where', BooleanOperator $boolean = BooleanOperator::AND)
    {
        $this->column = $column;
        if (trim($column) === '') {
            throw new DomainException('You cannot set empty column');
        }

        if (!$this->isOperator($operator)) {
            $allowedOperators = implode(', ', self::ALLOWED_OPERATORS);
            throw new DomainException("Operator must be one of values $allowedOperators");
        }

        $this->operator = $operator;
        $this->value = $value;
        $this->prefix = $prefix;
        $this->boolean = $boolean;
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
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

    public function getBoolean(): BooleanOperator
    {
        return $this->boolean;
    }
}
