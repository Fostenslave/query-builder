<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class Expression implements Compilable
{
    public function __construct(
        private(set) string $expression,
        private(set) bool   $isRaw = false,
    ) {
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        if ($this->isRaw) {
            return new CompiledQuery($this->expression, []);
        }

        return new CompiledQuery($grammar->wrapTable($this->expression));
    }
}