<?php

namespace Fostenslave\QueryBuilder\Query;

interface CompilableLogic extends Compilable
{
    public function getBoolean(): BooleanOperator;
}