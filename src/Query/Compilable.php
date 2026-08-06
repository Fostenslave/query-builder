<?php

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

interface Compilable
{
    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery;
}