<?php

declare(strict_types=1);

namespace SimpleORM\Query;

enum JoinType: string
{
    case Inner = 'INNER';
    case Left  = 'LEFT';
    case Right = 'RIGHT';
}
