<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use DomainException;
use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class WhereBetweenClause implements CompilableLogic
{
    private(set) string $column;
    private(set) string $prefix;


    private(set) mixed $from;
    private(set) mixed $to;
    private(set) bool $not;
    private(set) BooleanOperator $boolean;


    public function __construct(
        string $column,
        mixed $from,
        mixed $to,
        string $prefix = 'where',
        bool $not = false,
        BooleanOperator $boolean = BooleanOperator::AND
    ) {
        $this->column = $column;

        if (trim($column) === '') {
            throw new DomainException('You cannot set empty column');
        }

        $this->from = $from;
        $this->to = $to;
        $this->prefix = $prefix;
        $this->boolean = $boolean;
        $this->not = $not;
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        $wrappedColumn = $grammar->wrapTable($this->column);

        $operator = $this->not ? 'NOT BETWEEN' : 'BETWEEN';

        $bindings = [];
        $bindings[ ':' . $this->prefix . "_min"] = $this->from;
        $bindings[ ':' . $this->prefix . "_max"] = $this->to;

        $preparedBindings = implode(' AND ', array_keys($bindings));
        $sql = "$wrappedColumn $operator $preparedBindings";

        return new CompiledQuery($sql, $bindings);
    }

    public function getBoolean(): BooleanOperator
    {
        return $this->boolean;
    }
}
