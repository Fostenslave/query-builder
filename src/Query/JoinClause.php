<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class JoinClause implements Compilable
{
    private const array ALLOWED_OPERATORS = ['=', '<', '>', '>=', '<=', '<>', '!='];

    public function __construct(
        public string $table,
        public JoinType $type,
        public string $left,
        public string $operator,
        public string $right,
    ) {
        if (!in_array($this->operator, self::ALLOWED_OPERATORS, true)) {
            throw new \DomainException(
                'Invalid ON operator "' . $this->operator
                . '". Allowed: ' . implode(', ', self::ALLOWED_OPERATORS),
            );
        }
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        $joinType = $this->type->value;
        $wrappedTable = $grammar->wrapTable($this->table);

        return new CompiledQuery("$joinType JOIN $wrappedTable ON $this->left $this->operator $this->right", []);
    }
}
