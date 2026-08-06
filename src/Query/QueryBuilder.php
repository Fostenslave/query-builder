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
     * @var array<Expression>
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

    public function __construct(
        private(set) readonly Grammar   $grammar,
        private(set) readonly string    $table,
        private readonly ?QueryExecutor $executor = null,
    )
    {
    }

    public function from(string $tableName): static
    {
        return clone($this, [
            "table" => $tableName
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


    public function where(string $column, string $operator, mixed $value = null): static
    {
        $builder = clone $this;
        $builder->wheres[] = new WhereClause($column, $operator, $value);
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
            columns: $this->columns,
            joins: $this->joins,
            wheres: $this->wheres,
            orderBys: $this->orderBys,
            groupBys: $this->groupBys,
            havings: $this->havings,
            limit: $this->limitValue,
            offset: $this->offsetValue);
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
        $this->throwExceptionIfExecutorNotExists();
        return $this->executor->fetchAll($this->compile());
    }

    public function first(): ?array
    {
        $this->throwExceptionIfExecutorNotExists();
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
        $this->throwExceptionIfExecutorNotExists();
        $this->executor->execute($this->compileInsert($values));
        return (int)$this->executor->lastInsertId();

    }

    public function update(array $values): int
    {
        $this->throwExceptionIfExecutorNotExists();
        return $this->executor->execute($this->compileUpdate($values));

    }

    public function delete(): int
    {
        $this->throwExceptionIfExecutorNotExists();
        return $this->executor->execute($this->compileDelete());
    }

    private function throwExceptionIfExecutorNotExists(): void
    {
        if (!$this->executor) {
            throw new RuntimeException('You need to pass QueryExecutor parameter to call this function');
        }
    }


    public function whereRaw(string $sql, array $bindings = []): static
    {
        $builder = clone $this;
        $builder->wheres[] = new RawClause($sql, $bindings);
        return $builder;
    }

    public function count(): int
    {
        $this->throwExceptionIfExecutorNotExists();
        $builder = clone $this;
        $builder = $builder->selectRaw($this->grammar->getCountExpression());
        $row = $this->executor->fetch($builder->compile());
        return $row ? (int) array_first($row) : 0;
    }

    public function exists(): bool
    {
        $this->throwExceptionIfExecutorNotExists();

        $builder = clone($this, [
            "columns" => [new Expression('1')],
            'limitValue' => 1,
        ]);
        return $builder->executor->fetch($builder->compile()) !== null;
    }

    public function paginate(int $page, int $perPage): array
    {
        $this->throwExceptionIfExecutorNotExists();
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

    private function getAggregatedValue(string $column, string $functionName)
    {
        $this->throwExceptionIfExecutorNotExists();
        $builder = clone($this, [
            'columns' => [],
        ]);
        $wrappedColumn = $this->grammar->wrapTable($column);
        $builder = $builder->selectRaw("$functionName($wrappedColumn)");
        $row = $this->executor->fetch($builder->compile());
        return $row ? array_first($row) : 0;
    }
}