<?php

declare(strict_types=1);

namespace SimpleORM\Query;

final readonly class JoinClause
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
}
