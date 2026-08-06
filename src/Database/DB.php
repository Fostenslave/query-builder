<?php

namespace Fostenslave\QueryBuilder\Database;

use Closure;
use PDO;
use Fostenslave\QueryBuilder\Grammar\Grammar;
use Fostenslave\QueryBuilder\Grammar\SqliteGrammar;
use Fostenslave\QueryBuilder\Query\PDOQueryExecutor;
use Fostenslave\QueryBuilder\Query\QueryBuilder;
use Fostenslave\QueryBuilder\Query\QueryBuilderContract;
use Fostenslave\QueryBuilder\Query\QueryExecutor;
use Throwable;

class DB implements TransactionManager
{
    public function __construct(
        private readonly PDO     $pdo,
        private readonly Grammar  $grammar = new SqliteGrammar(),
        private ?QueryExecutor    $executor = null,
    )
    {
        if ($this->executor === null) {
            $this->executor = new PDOQueryExecutor($this->pdo);
        }
    }

    public function table(string $tableName): QueryBuilderContract
    {
        return new QueryBuilder($this->grammar, $tableName, $this->executor);
    }

    public function from(string $tableName): QueryBuilderContract
    {
        return $this->table($tableName);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function transaction(Closure $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}