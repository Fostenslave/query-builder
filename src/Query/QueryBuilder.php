<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Query;

use Fostenslave\QueryBuilder\Executor\QueryExecutor;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use InvalidArgumentException;
use RuntimeException;

class QueryBuilder implements QueryBuilderContract
{
    /**
     * @var array<Expression|SubQuery>
     */
    private(set) array $columns = [];

    /**
     * @var array<Expression>
     */
    private(set) array $groupBys = [];

    /**
     * @var array<Expression>
     */
    private(set) array $havings = [];

    /**
     * @var array<JoinClause> $joins
     */
    private(set) array $joins = [];

    /**
     * @var array<WhereClause|RawClause> $wheres
     */
    private(set) array $wheres = [];

    /**
     * @var array<OrderByClause> $orderBys
     */
    private(set) array $orderBys = [];

    private(set) ?int $limitValue = null;
    private(set) ?int $offsetValue = null;

    private(set) SubQuery|null $fromSub = null;

    public function __construct(
        private(set) readonly Grammar $grammar,
        private(set) readonly string|null $table = null,
        private readonly ?QueryExecutor $executor = null,
    ) {
    }

    public function from(string $tableName): static
    {
        return clone($this, [
            'table' => $tableName,
            'fromSub' => null,
        ]);
    }

    public function fromSub(QueryBuilderContract $builder, string $alias): static
    {
        return clone ($this, [
            "fromSub" => new SubQuery(clone $builder, $alias, 0, 'from'),
            'table' => null,
        ]);
    }

    public function select(string ...$columns): static
    {
        $builder = clone($this);
        foreach ($columns as $column) {
            $builder->columns[] = new Expression($column);
        }

        return $builder;
    }

    public function selectRaw(string $expression): static
    {
        $builder = clone $this;

        $builder->columns[] = new Expression(expression: $expression, isRaw: true);
        return $builder;
    }



    public function selectSub(QueryBuilderContract $builder, string $alias): static
    {
        $newBuilder = clone $this;
        $subQueriesCount = count(array_filter($newBuilder->columns, fn ($column) => $column instanceof SubQuery));
        $newBuilder->columns[] = new SubQuery($builder, $alias, $subQueriesCount);
        return $newBuilder;
    }

    public function where(callable|string $column, string $operator = '', mixed $value = null): static
    {
        $builder = clone $this;

        if (is_callable($column)) {
            $builder->wheres[] = new ConditionGroup($column, BooleanOperator::AND, $this->getWherePrefix());
            return $builder;
        }

        $builder->wheres[] = new WhereClause($column, $operator, $value, 'where', BooleanOperator::AND);
        return $builder;
    }

    public function whereNull(string $column): static
    {
        return $this->where($column, '=');
    }

    public function whereNotNull(string $column): static
    {
        return $this->where($column, '!=');
    }

    public function whereEquals(string $column, mixed $value): static
    {
        return $this->where($column, '=', $value);
    }

    /**
     * @param string $sql
     * @param array<mixed> $bindings
     * @return static
     */
    public function whereRaw(string $sql, array $bindings = []): static
    {
        $builder = clone $this;
        $builder->wheres[] = new RawClause($sql, $bindings);
        return $builder;
    }

    public function orWhere(string|callable $column, string $operator = '', mixed $value = null): static
    {
        $builder = clone $this;

        if (is_callable($column)) {
            $builder->wheres[] = new ConditionGroup($column, BooleanOperator::OR, $this->getWherePrefix());
            return $builder;
        }

        $builder->wheres[] = new WhereClause($column, $operator, $value, 'where', BooleanOperator::OR);
        return $builder;
    }

    /**
     * @param string $sql
     * @param array<mixed> $bindings
     * @return static
     */
    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        $builder = clone $this;
        $builder->wheres[] = new RawClause($sql, $bindings, 'raw', BooleanOperator::OR);
        return $builder;
    }

    /**
     * @param string $column
     * @param array<mixed> $values
     * @return static
     */
    public function whereIn(string $column, array $values): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereInClause(column: $column, values: $values, prefix: $this->getWherePrefix(), not: false, boolean: BooleanOperator::AND);
        return $builder;
    }

    /**
     * @param string $column
     * @param array<mixed> $values
     * @return static
     */
    public function whereNotIn(string $column, array $values): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereInClause(column: $column, values: $values, prefix: $this->getWherePrefix(), not: true, boolean: BooleanOperator::AND);
        return $builder;
    }

    /**
     * @param string $column
     * @param array<mixed> $values
     * @return static
     */
    public function orWhereIn(string $column, array $values): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereInClause(column: $column, values: $values, prefix: $this->getWherePrefix(), not: false, boolean: BooleanOperator::OR);
        return $builder;
    }

    /**
     * @param string $column
     * @param array<mixed> $values
     * @return static
     */
    public function orWhereNotIn(string $column, array $values): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereInClause(column: $column, values: $values, prefix: $this->getWherePrefix(), not: true, boolean: BooleanOperator::OR);
        return $builder;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereBetweenClause(column: $column, from: $from, to: $to, prefix: $this->getWherePrefix(), not: false);
        return $builder;
    }

    public function whereNotBetween(string $column, mixed $from, mixed $to): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereBetweenClause(column: $column, from: $from, to: $to, prefix: $this->getWherePrefix(), not: true);
        return $builder;
    }

    public function orWhereBetween(string $column, mixed $from, mixed $to): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereBetweenClause(column: $column, from: $from, to: $to, prefix: $this->getWherePrefix(), not: false, boolean: BooleanOperator::OR);
        return $builder;
    }

    public function orWhereNotBetween(string $column, mixed $from, mixed $to): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereBetweenClause(column: $column, from: $from, to: $to, prefix: $this->getWherePrefix(), not: true, boolean: BooleanOperator::OR);
        return $builder;
    }


    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $builder = clone $this;
        $builder->orderBys[] = new OrderByClause($column, $direction);
        return $builder;
    }

    public function limit(int $limit): static
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit value should be greater or equals zero');
        }

        return clone($this, [
            "limitValue" => $limit
        ]);
    }

    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset value should be greater or equals zero');
        }

        return clone($this, [
            "offsetValue" => $offset
        ]);
    }

    public function compile(): CompiledQuery
    {
        return $this->grammar->compileSelect(
            table: $this->table,
            fromSub: $this->fromSub,
            columns: $this->columns,
            joins: $this->joins,
            wheres: $this->wheres,
            orderBys: $this->orderBys,
            groupBys: $this->groupBys,
            havings: $this->havings,
            limit: $this->limitValue,
            offset: $this->offsetValue
        );
    }

    public function join(string $table, string $left, string $operator, string $right): static
    {
        $builder = clone($this);
        $builder->joins[] = new JoinClause($table, JoinType::Inner, $left, $operator, $right);
        return $builder;
    }

    public function leftJoin(string $table, string $left, string $operator, string $right): static
    {
        $builder = clone($this);
        $builder->joins[] = new JoinClause($table, JoinType::Left, $left, $operator, $right);
        return $builder;
    }

    public function rightJoin(string $table, string $left, string $operator, string $right): static
    {
        $builder = clone($this);
        $builder->joins[] = new JoinClause($table, JoinType::Right, $left, $operator, $right);
        return $builder;
    }

    public function get(): array
    {
        $this->ensureQueryExecutorExists();
        return $this->executor->fetchAll($this->compile());
    }

    public function first(): ?array
    {
        $this->ensureQueryExecutorExists();
        return $this->executor->fetch(clone($this)->limit(1)->compile());
    }

    public function compileInsert(array $values): CompiledQuery
    {
        return $this->grammar->compileInsert($this->table, $values);
    }

    public function compileUpdate(array $values): CompiledQuery
    {
        return $this->grammar->compileUpdate($this->table, $values, $this->wheres);
    }

    public function compileDelete(): CompiledQuery
    {
        return $this->grammar->compileDelete($this->table, $this->wheres);
    }

    public function insert(array $values): int
    {
        $this->ensureTableExists();
        $this->ensureQueryExecutorExists();
        $this->executor->execute($this->compileInsert($values));
        return (int)$this->executor->lastInsertId();
    }

    public function update(array $values): int
    {
        $this->ensureTableExists();
        $this->ensureQueryExecutorExists();
        return $this->executor->execute($this->compileUpdate($values));
    }

    public function delete(): int
    {
        $this->ensureTableExists();
        $this->ensureQueryExecutorExists();
        return $this->executor->execute($this->compileDelete());
    }

    private function ensureQueryExecutorExists(): void
    {
        if (!$this->executor) {
            throw new RuntimeException('You need to pass QueryExecutor parameter to call this function');
        }
    }

    private function ensureTableExists(): void
    {
        if (!$this->table) {
            throw new RuntimeException(
                'Cannot perform insert/update/delete: fromSub() replaces the table with a subquery.'
                . ' Use table() or from() instead.'
            );
        }
    }


    public function count(): int
    {
        $this->ensureQueryExecutorExists();
        $builder = clone $this;
        $builder = $builder->selectRaw($this->grammar->getCountExpression());
        $row = $this->executor->fetch($builder->compile());
        return $row ? (int) array_first($row) : 0;
    }

    public function exists(): bool
    {
        $this->ensureQueryExecutorExists();

        $builder = clone($this, [
            "columns" => [new Expression('1')],
            'limitValue' => 1,
        ]);
        return $builder->executor->fetch($builder->compile()) !== null;
    }

    public function paginate(int $page, int $perPage): array
    {
        $this->ensureQueryExecutorExists();
        $builder = clone($this)->limit($perPage)->offset(($page - 1) * $perPage);
        return $builder->executor->fetchAll($builder->compile());
    }

    public function groupBy(string ...$columns): static
    {
        $builder = clone($this);
        foreach ($columns as $column) {
            $builder->groupBys[] = new Expression($column);
        }
        return $builder;
    }

    public function groupByRaw(string $expression): static
    {
        $builder = clone($this);
        $builder->groupBys[] = new Expression($expression, isRaw: true);
        return $builder;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $builder = clone($this);
        $builder->havings[] = new WhereClause($column, $operator, $value, 'having');
        return $builder;
    }

    /**
     * @param string $sql
     * @param array<mixed> $bindings
     * @return static
     */
    public function havingRaw(string $sql, array $bindings = []): static
    {
        $builder = clone $this;
        $builder->havings[] = new RawClause($sql, $bindings, 'having_raw');
        return $builder;
    }

    public function sum(string $column): mixed
    {
        return $this->getAggregatedValue($column, 'sum');
    }

    public function avg(string $column): mixed
    {
        return $this->getAggregatedValue($column, 'avg');
    }

    public function min(string $column): mixed
    {
        return $this->getAggregatedValue($column, 'min');
    }

    public function max(string $column): mixed
    {
        return $this->getAggregatedValue($column, 'max');
    }

    private function getAggregatedValue(string $column, string $functionName): mixed
    {
        $this->ensureQueryExecutorExists();
        $builder = clone($this, [
            'columns' => [],
        ]);
        $wrappedColumn = $this->grammar->wrapTable($column);
        $builder = $builder->selectRaw("$functionName($wrappedColumn)");
        $row = $this->executor->fetch($builder->compile());
        return $row ? array_first($row) : 0;
    }

    private function getWherePrefix(): string
    {
        $prefixCount = count($this->wheres);
        return "where_$prefixCount";
    }
}
