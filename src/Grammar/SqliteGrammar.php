<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Grammar;

use Fostenslave\QueryBuilder\Query\CompiledQuery;
use Fostenslave\QueryBuilder\Query\JoinClause;
use Fostenslave\QueryBuilder\Query\OrderByClause;
use Fostenslave\QueryBuilder\Query\RawClause;
use Fostenslave\QueryBuilder\Query\WhereClause;


class SqliteGrammar extends BaseGrammar implements Grammar
{

    protected function wrapValue(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }


    public function getCountExpression(): string
    {
        return 'count(*)';
    }
}
