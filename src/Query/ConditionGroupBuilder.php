<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

final class ConditionGroupBuilder implements CompilableLogic
{

    /**
     * @var array<CompilableLogic> $wheres
     */
    private(set) array $wheres;

    public function __construct(
        private(set) readonly string $prefix = 'where',
        private(set) readonly BooleanOperator $boolean = BooleanOperator::AND
    ) {
        $this->wheres = [];
    }

    public function where(callable|string $column, string $operator = '', mixed $value = null): ConditionGroupBuilder
    {

        if (is_callable($column)) {
            $prefixCount = count($this->wheres);
            $this->wheres[] = new ConditionGroup($column, BooleanOperator::AND, $this->prefix . "_$prefixCount");
            return $this;
        }

        $this->wheres[] = new WhereClause($column, $operator, $value, $this->prefix, BooleanOperator::AND);
        return $this;
    }

    public function orWhere(callable|string $column, string $operator = '', mixed $value = null): ConditionGroupBuilder
    {
        if (is_callable($column)) {
            $prefixCount = count($this->wheres);
            $this->wheres[] = new ConditionGroup($column, BooleanOperator::OR, $this->prefix . "_$prefixCount");
            return $this;
        }

        $this->wheres[] = new WhereClause($column, $operator, $value,  $this->prefix, BooleanOperator::OR);
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): ConditionGroupBuilder
    {

        $this->wheres[] = new RawClause($sql, $bindings, $this->prefix);
        return $this;
    }

    public function orWhereRaw(string $sql, array $bindings = []): ConditionGroupBuilder
    {
        $this->wheres[] = new RawClause($sql, $bindings, $this->prefix, BooleanOperator::OR);
        return $this;
    }

    public function compile(Grammar $grammar, $sqlIndex = 0): CompiledQuery
    {
        $resultConditions = '';
        $bindings = [];
        foreach ($this->wheres as $key => $where) {
            $compiled = $where->compile($grammar, $key);
            $bindings = array_merge_recursive($bindings, $compiled->bindings);
            $boolean = $where->getBoolean();
            if ($key === 0) {
                $resultConditions .= "$compiled->sql";
            } else {
                $resultConditions .= " $boolean->value $compiled->sql";
            }
        }

        return new CompiledQuery("($resultConditions)", $bindings);
    }


    public function getBoolean(): BooleanOperator
    {
       return $this->boolean;
    }
}
