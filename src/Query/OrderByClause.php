<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class OrderByClause implements Compilable
{
    private const array ALLOWED_DIRECTIONS = ['ASC', 'DESC'];
    public function __construct(
        private(set) string $column,
        private(set) string $direction = 'ASC',
    ) {
        if (!in_array(strtoupper($this->direction), self::ALLOWED_DIRECTIONS)) {
            throw new \DomainException('Invalid direction value - you should use "ASC" or "DESC" values');
        }
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        $wrappedColumn = $grammar->wrapTable($this->column);

        return new CompiledQuery("$wrappedColumn $this->direction", []);
    }
}
