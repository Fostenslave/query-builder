<?php
declare(strict_types=1);

namespace SimpleORM\Query;

use RuntimeException;
use SimpleORM\Grammar\Grammar;

class QueryBuilder implements QueryBuilderContract
{

    private(set) array $columns = [];
    private(set) array $rawColumns = [];

    /**
     * @var array<JoinClause> $joins
     */
    private(set) array $joins = [];

    /**
     * @var array<WhereClause> $wheres
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
        private(set) string             $table,
        private readonly ?QueryExecutor $executor = null,
    )
    {
    }

    public function from(string $tableName): static
    {
        $this->table = $tableName;
        return $this;
    }

    public function select(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->columns[] = $column;
        }
        
        return $this;
    }

    public function selectRaw(string $expression): static
    {
        $this->rawColumns[] = $expression;
        return $this;
    }


    public function where(string $column, string $operator, mixed $value = null): static
    {
        $this->wheres[] = new WhereClause($column, $operator, $value);
        return $this;
    }

    public function whereEquals(string $column, mixed $value): static
    {
        return $this->where($column, '=', $value);
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBys[] = new OrderByClause($column, $direction);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function compile(): CompiledQuery
    {
        return $this->grammar->compileSelect(
            table: $this->table,
            columns: $this->columns,
            joins: $this->joins,
            wheres: $this->wheres,
            orderBys: $this->orderBys,
            limit: $this->limitValue,
            offset: $this->offsetValue);
    }

    public function join(string $table, string $left, string $operator, string $right): static
    {
        $this->joins[] = new JoinClause($table, JoinType::Inner, $left, $operator, $right);
        return $this;
    }

    public function leftJoin(string $table, string $left, string $operator, string $right): static
    {
        $this->joins[] = new JoinClause($table, JoinType::Left, $left, $operator, $right);
        return $this;
    }

    public function rightJoin(string $table, string $left, string $operator, string $right): static
    {
        $this->joins[] = new JoinClause($table, JoinType::Right, $left, $operator, $right);
        return $this;
    }

    public function get(): array
    {
        $this->throwExceptionIfExecutorNotExists();
        return $this->executor->fetchAll($this->compile());
    }

    public function first(): ?array
    {
        $this->throwExceptionIfExecutorNotExists();
        return $this->executor->fetch($this->compile());
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


    public function whereRaw(string $sql): static
    {
        // TODO: Implement whereRaw() method.
    }

    public function count(): int
    {
        // TODO: Implement count() method.
    }

    public function exists(): bool
    {
        // TODO: Implement exists() method.
    }

    public function paginate(int $page, int $perPage): array
    {
        // TODO: Implement paginate() method.
    }
}