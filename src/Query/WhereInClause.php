<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use DomainException;
use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class WhereInClause implements CompilableLogic
{
    private(set) string $column;
    private(set) string $prefix;

    /**
     * @var array<int, mixed>
     */
    private(set) array $values;
    private(set) bool $not;
    private(set) BooleanOperator $boolean;

    /**
     * @param array<int, mixed> $values
     */
    public function __construct(string $column, array $values, string $prefix = 'where', bool $not = false, BooleanOperator $boolean = BooleanOperator::AND)
    {
        $this->column = $column;
        if (trim($column) === '') {
            throw new DomainException('You cannot set empty column');
        }

        if (count($values) === 0) {
            throw new \InvalidArgumentException('You should provide non empty list of values');
        }


        $this->values = $values;
        $this->prefix = $prefix;
        $this->boolean = $boolean;
        $this->not = $not;
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        $wrappedColumn = $grammar->wrapTable($this->column);

        $operator = $this->not ? 'NOT IN' : 'IN';

        $bindings = [];
        foreach ($this->values as $key => $value) {
            $bindingKey = ':' . $this->prefix . "_$key";
            $bindings[$bindingKey] = $value;
        }
        $preparedBindings = implode(', ', array_keys($bindings));
        $sql = "$wrappedColumn $operator ($preparedBindings)";

        return new CompiledQuery($sql, $bindings);
    }

    public function getBoolean(): BooleanOperator
    {
        return $this->boolean;
    }
}
