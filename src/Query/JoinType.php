<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

enum JoinType: string
{
    case Inner = 'INNER';
    case Left  = 'LEFT';
    case Right = 'RIGHT';
}
