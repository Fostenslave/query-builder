<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class SubQuery implements Compilable
{

    public function __construct(
        private(set) QueryBuilderContract $builder,
        private(set) string $alias,
        private(set) int $currentSubIndex = 0,
        private(set) string $prefix = 'sub',
    ) {
        if (empty($this->alias)) {
            throw new \InvalidArgumentException('Alias argument should be non empty');
        }
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        $compiled = $this->recompileWithNewBindings($this->builder->compile(), $this->currentSubIndex);
        $wrappedAlias = $grammar->wrapTable($this->alias);
        return new CompiledQuery("($compiled->sql) AS $wrappedAlias", $compiled->bindings);
    }

    private function recompileWithNewBindings(CompiledQuery $query, int $sqlIndex): CompiledQuery
    {
        if (count($query->bindings) === 0) {
            return $query;
        }

        $newBindings = [];
        $replacements = [];

        foreach ($query->bindings as $key => $value) {
            $newKey = ":{$this->prefix}_{$sqlIndex}_" . substr($key, 1);
            $replacements[$key] = $newKey;
            $newBindings[$newKey] = $value;
        }
        $newSql = strtr($query->sql, $replacements);

        return new CompiledQuery($newSql, $newBindings);
    }
}
