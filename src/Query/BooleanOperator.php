<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

enum BooleanOperator: string
{
    case AND = 'AND';
    case OR  = 'OR';
}
