<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Grammar;

use Fostenslave\QueryBuilder\Query\Compilable;
use Fostenslave\QueryBuilder\Query\CompilableLogic;
use Fostenslave\QueryBuilder\Query\CompiledQuery;
use Fostenslave\QueryBuilder\Query\Expression;

abstract class BaseGrammar implements Grammar
{

    public function compileSelect(
        string $table,
        array $columns,
        array $joins,
        array $wheres,
        array $orderBys,
        array $groupBys,
        array $havings,
        ?int $limit,
        ?int $offset,
    ): CompiledQuery {

        $sql = "SELECT " . $this->compileSelects($columns) ." FROM " . $this->wrapTable($table);
        $compiledWheres = $this->compileWheres($wheres);
        $compiledHavings = $this->compileHavings($havings);
        $sql .= $this->compileJoins($joins)->sql;
        $sql .= $compiledWheres->sql;
        $sql .= $this->compileGroupBys($groupBys)->sql;
        $sql .= $compiledHavings->sql;
        $sql .= $this->compileOrderBys($orderBys)->sql;

        $bindings = array_merge_recursive($compiledWheres->bindings, $compiledHavings->bindings);

        if (isset($limit) && $limit >= 0) {
            $sql .= " LIMIT $limit";
        }

        if (isset($offset) && $offset >= 0) {
            $sql .= " OFFSET $offset";
        }

        return new CompiledQuery($sql, $bindings);
    }

    public function compileSelects(array $columns): string
    {
        $compiledColumns = array_map(fn($column) =>  $column->compile($this)->sql, $columns);
        $compiledColumns = array_unique($compiledColumns);
        return count($compiledColumns) > 0 ? implode(', ',$compiledColumns
        ) : '*';
    }

    /**
     * @param string $table
     * @param array<string, mixed> $values
     * @return CompiledQuery
     */
    public function compileInsert(string $table, array $values): CompiledQuery
    {
        $table = $this->wrapTable($table);
        $bindings = [];
        $counter = 0;
        $columns = $this->wrapIdentifiers(array_keys($values));
        $columnsString = '(' . implode(', ', $columns) . ')';
        foreach ($values as  $value) {
            $bindings[":val_$counter"] = $value;
            $counter+=1;
        }

        $valuesString = '(' . implode(', ', array_keys($bindings)) . ')';

        return new CompiledQuery(
            "INSERT INTO $table $columnsString VALUES $valuesString",
            $bindings,
        );
    }

    public function compileUpdate(string $table, array $values, array $wheres): CompiledQuery
    {
        $table = $this->wrapTable($table);

        $bindings = [];
        $counter = 0;

        $preparedValueStrings = [];
        foreach ($values as  $column => $value) {
            $bindingKey = ":set_$counter";
            $bindings[$bindingKey] = $value;
            $preparedColumn = $this->wrapTable($column);
            $preparedValueStrings[] = "$preparedColumn = $bindingKey";
            $counter+=1;
        }

        $valuesString = implode(', ', $preparedValueStrings);

        $wheresCompiled = $this->compileWheres($wheres);
        $wheresCompiledString = $wheresCompiled->sql;

        $bindings = array_merge_recursive($bindings, $wheresCompiled->bindings);

        return new CompiledQuery(
            "UPDATE $table SET $valuesString" . $wheresCompiledString,
            $bindings,
        );
    }

    public function compileDelete(string $table, array $wheres): CompiledQuery
    {
        $table = $this->wrapTable($table);

        $wheresCompiled = $this->compileWheres($wheres);

        return new CompiledQuery(
            "DELETE FROM $table" . $wheresCompiled->sql,
            $wheresCompiled->bindings,
        );
    }
    public function getCountExpression(): string
    {
        return 'count(*)';
    }

    abstract protected function wrapValue(string $value): string;

    public function wrapTable(string $table): string
    {
        if (stripos($table, ' as ') !== false) {
            $segments = preg_split('/\s+as\s+/i', $table, 2);

            return $this->wrapTable($segments[0])
                . ' as '
                . $this->wrapValue($segments[1]);
        }

        if (str_contains($table, '.')) {
            return explode('.', $table)
                    |> (fn($x) => array_map($this->wrapValue(...), $x))
                    |> (fn($x) => implode('.', $x));
        }

        return $this->wrapValue($table);
    }

    public function wrapIdentifiers(array $columns): array
    {
        return array_map(fn($column) => $this->wrapTable($column), $columns);
    }

    /**
     * @param array<Compilable> $joins
     * @return CompiledQuery
     */
    private function compileJoins(array $joins): CompiledQuery
    {
        if (count($joins) === 0) {
            return new CompiledQuery('', []);
        }

        $joinSqlStrings = [];

        foreach ($joins as $join) {
            $joinSqlStrings[] = $join->compile($this)->sql;
        }

        return new CompiledQuery(
             ' ' . implode(' ', $joinSqlStrings),
            []
        );
    }

    /**
     * @param array<CompilableLogic> $wheres
     */
    private function compileWheres(array $wheres): CompiledQuery {
        if (count($wheres) === 0) {
            return new CompiledQuery('', []);
        }

        $bindings = [];

        $resultConditions = '';
        foreach ($wheres as $key => $where) {
            $compiled = $where->compile($this, $key);
            $bindings = array_merge_recursive($bindings, $compiled->bindings);
            $boolean = $where->getBoolean();
            if ($key === 0) {
                $resultConditions .= "$compiled->sql";
            } else {
                $resultConditions .= " $boolean->value $compiled->sql";
            }
        }

        return new CompiledQuery(
            ' WHERE ' . $resultConditions,
            $bindings
        );
    }

    private function compileGroupBys(array $groupBys): CompiledQuery {
        if (count($groupBys) === 0) {
            return new CompiledQuery('', []);
        }


        return new CompiledQuery(
            ' GROUP BY ' . implode(', ', array_map(fn (Expression $groupBy) => $groupBy->compile($this, 0)->sql, $groupBys)
        ));
    }

    /**
     * @param array<Compilable> $havings
     */
    private function compileHavings(array $havings): CompiledQuery {
        if (count($havings) === 0) {
            return new CompiledQuery('', []);
        }

        $havingSqlStrings = [];
        $bindings = [];

        foreach ($havings as $key => $having) {
            $compiled = $having->compile($this, $key);
            $havingSqlStrings[] = $compiled->sql;
            $bindings = array_merge_recursive($bindings, $compiled->bindings);
        }

        return new CompiledQuery(
            ' HAVING ' . implode(' AND ', $havingSqlStrings),
            $bindings
        );
    }

    /**
     * @param array<Compilable> $orderBys
     */
    private function compileOrderBys(array $orderBys): CompiledQuery
    {
        if (count($orderBys) === 0) {
            return new CompiledQuery('', []);
        }

        $orderBySqlStrings = [];

        foreach ($orderBys as $orderBy) {
            $orderBySqlStrings[] = $orderBy->compile($this)->sql;
        }

        return new CompiledQuery(
            ' ORDER BY ' . implode(',', $orderBySqlStrings),
            []
        );
    }
}
