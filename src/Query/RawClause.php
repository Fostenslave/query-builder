<?php

declare(strict_types=1);

namespace SimpleORM\Query;

final readonly class RawClause
{
    public function __construct(private(set) string $rawClause) {
    }

}
