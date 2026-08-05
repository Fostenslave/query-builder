<?php

namespace SimpleORM\Query;

use SimpleORM\Grammar\Grammar;

interface Compilable
{
    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery;
}