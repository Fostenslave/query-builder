<?php

declare(strict_types=1);

namespace SimpleORM\Grammar;

use SimpleORM\Query\CompiledQuery;
use SimpleORM\Query\JoinClause;
use SimpleORM\Query\OrderByClause;
use SimpleORM\Query\WhereClause;


class SqliteGrammar implements Grammar
{

    public function compileSelect(
        string $table,
        array $columns,
        array $joins,
        array $wheres,
        array $orderBys,
        ?int $limit,
        ?int $offset,
    ): CompiledQuery {
        $columns = array_unique($columns);
        $columnsString = count($columns) > 0 ? implode(', ', $columns) : '*';
        $sql = "SELECT $columnsString FROM " . $this->wrapTable($table);
        $compiledWheres = $this->compileWheres($wheres);
        $compiledOrderBys = $this->compileOrderBys($orderBys);
        $compiledJoins = $this->compileJoins($joins);
        $sql .= $compiledJoins->sql;
        $sql .= $compiledWheres->sql;
        $sql .= $compiledOrderBys->sql;

        $bindings = $compiledWheres->bindings;

        if (isset($limit) && $limit > 0) {
            $sql .= " LIMIT $limit";
        }

        if (isset($offset) && $offset > 0) {
            $sql .= " OFFSET $offset";
        }

        return new CompiledQuery($sql, $bindings);
    }

    /**
     * @param array<JoinClause> $joins
     * @return CompiledQuery
     */
    private function compileJoins(array $joins): CompiledQuery
    {
        if (count($joins) === 0) {
            return new CompiledQuery('', []);
        }

        $joinStrings = [];
        foreach ($joins as $join) {
            $joinType = $join->type->value;
            $wrappedTable = $this->wrapTable($join->table);
            $joinStrings[] = "$joinType JOIN $wrappedTable ON $join->left $join->operator $join->right";
        }

        return new CompiledQuery(
             ' ' . implode(' ', $joinStrings),
            []
        );
    }

    /**
     * @param array<WhereClause> $wheres
     */
    private function compileWheres(array $wheres): CompiledQuery {
        if (count($wheres) === 0) {
            return new CompiledQuery('', []);
        }

        $conditions = [];
        $bindings = [];

        foreach ($wheres as $key => $where) {
            $bindingKey = ":where_$key";
            $bindings[$bindingKey] = $where->value;
            $conditions[] = "$where->column $where->operator $bindingKey";
        }

        return new CompiledQuery(
            ' WHERE ' . implode(' AND ', $conditions),
            $bindings
        );
    }

    /**
     * @param array<OrderByClause> $orderBys
     */
    private function compileOrderBys(array $orderBys): CompiledQuery
    {
        if (count($orderBys) === 0) {
            return new CompiledQuery('', []);
        }

        $orders = [];

        foreach ($orderBys as $orderBy) {
            $orders[] = "$orderBy->column $orderBy->direction";
        }

        return new CompiledQuery(
            ' ORDER BY ' . implode(',', $orders),
            []
        );
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
        $columnsString = '(' . implode(', ', array_keys($values)) . ')';
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
            $preparedValueStrings[] = "$column = $bindingKey";
            $counter+=1;
        }

        $valuesString = implode(', ', $preparedValueStrings);

        $wheresCompiled = $this->compileWheres($wheres);
        $wheresCompiledString = $wheresCompiled->sql;

        $bindings = array_merge($bindings, $wheresCompiled->bindings);

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

    private function wrapValue(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function wrapTable(string $table): string
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

}
