<?php

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

/**
 * Interface for compilable expression
 */
interface Compilable
{
    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery;
}