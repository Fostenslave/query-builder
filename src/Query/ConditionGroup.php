<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

final class ConditionGroup implements CompilableLogic
{
    private(set) string $prefix;

    private(set) BooleanOperator $boolean;


    private(set) ConditionGroupBuilder $group;

    /**
     * @param callable(ConditionGroupBuilder): CompiledQuery $function
     * @param BooleanOperator $boolean
     * @param string $prefix
     */
    public function __construct(callable $function, BooleanOperator $boolean = BooleanOperator::AND, string $prefix = 'where') {
        $this->boolean = $boolean;
        $this->group = new ConditionGroupBuilder($prefix);
        $function($this->group);
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        return $this->group->compile($grammar, $sqlIndex);
    }


    public function getBoolean(): BooleanOperator
    {
       return $this->boolean;
    }
}
