<?php

declare(strict_types=1);

namespace Fostenslave\QueryBuilder\Executor;
use Fostenslave\QueryBuilder\Query\CompiledQuery;

readonly class PDOQueryExecutor implements QueryExecutor
{
    public function __construct(private \PDO $pdo) {}

    public function fetchAll(CompiledQuery $query): array {
        $stmt = $this->pdo->prepare($query->sql);
        $stmt = $this->bindAndExecute($stmt, $query->bindings);
        return $stmt->fetchAll();
    }

    public function fetch(CompiledQuery $query): ?array {
        $stmt = $this->pdo->prepare($query->sql);
        $stmt = $this->bindAndExecute($stmt, $query->bindings);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function execute(CompiledQuery $query): int
    {
        $stmt = $this->pdo->prepare($query->sql);
        $stmt = $this->bindAndExecute($stmt, $query->bindings);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    private function bindAndExecute(\PDOStatement $stmt, array $bindings): \PDOStatement
    {
        foreach ($bindings as $key => $value) {
            $type = match (true) {
                is_int($value), is_bool($value) => \PDO::PARAM_INT,
                is_null($value) => \PDO::PARAM_NULL,
                default         => \PDO::PARAM_STR,
            };
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();


        return $stmt;
    }


}
