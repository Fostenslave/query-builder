<?php

declare(strict_types=1);

namespace SimpleORM\Query;

final readonly class OrderByClause
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
}
