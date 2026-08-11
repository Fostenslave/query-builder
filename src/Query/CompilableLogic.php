<?php

namespace Fostenslave\QueryBuilder\Query;

/**
 * Interface for compilable expression with logical operator (OR, AND)
 */
interface CompilableLogic extends Compilable
{
    public function getBoolean(): BooleanOperator;
}