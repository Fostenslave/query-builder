<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use DomainException;
use Fostenslave\QueryBuilder\Grammar\Grammar;

final readonly class RawClause implements Compilable
{


    public function __construct(
        private(set) string $rawClause,
        private(set) array  $bindings = [],
        private(set) string $prefix = 'raw',
    ) {
    }

    public function compile(Grammar $grammar, int $sqlIndex = 0): CompiledQuery
    {
        $prefix = $this->prefix;

        $preparedBindings = [];
        preg_match_all('/(\?)/', $this->rawClause, $placeholdersMatches, PREG_OFFSET_CAPTURE);
        preg_match_all('/(:[A-z]+)/', $this->rawClause, $namedPlaceholdersMatches, PREG_OFFSET_CAPTURE);
        $placeholdersCount = count($placeholdersMatches[1]);

        if ($placeholdersCount === 0) {
            $placeholdersCount = count($namedPlaceholdersMatches[1]);
        }

        $bindingsCount = count($this->bindings);

        if ($placeholdersCount !== $bindingsCount) {
            throw new DomainException("The number of placeholders and bindings must match placeholders: $placeholdersCount bindings: $bindingsCount");
        }

        if (!count($this->bindings)) {
            return new CompiledQuery($this->rawClause, $preparedBindings);
        }

        foreach ($this->bindings as $bindingKey => $value) {
            $preparedKey = ":$bindingKey";

            if (is_numeric($bindingKey)) {
                $preparedKey = ":{$prefix}_{$sqlIndex}_$bindingKey";
            }

            $preparedBindings[$preparedKey] = $value;
        }

        $rawClause = $this->rawClause;
        $bindingKeys = array_keys($preparedBindings);

        for ($i = count($placeholdersMatches[1]) - 1; $i >= 0; $i--) {
            $pos = (int) $placeholdersMatches[1][$i][1];
            $rawClause = substr_replace($rawClause, $bindingKeys[$i], $pos, 1);
        }

        return new CompiledQuery($rawClause, $preparedBindings);
    }



}
