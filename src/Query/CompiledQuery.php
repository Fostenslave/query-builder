<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

final readonly class CompiledQuery
{
    /**
     * @param string $sql
     * @param array<string, mixed> $bindings
     */
    public function __construct(
        private(set) string $sql,
        private(set) array $bindings = [],
    ) {
    }
}
