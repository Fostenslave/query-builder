<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Grammar;

class MysqlGrammar extends BaseGrammar implements Grammar
{

    protected function wrapValue(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    public function getCountExpression(): string
    {
        return 'count(*)';
    }
}
