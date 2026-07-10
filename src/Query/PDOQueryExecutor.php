<?php

declare(strict_types=1);

namespace SimpleORM\Query;
readonly class PDOQueryExecutor implements QueryExecutor
{
    public function __construct(private \PDO $pdo)
    {

    }

    public function fetchAll(CompiledQuery $query): array {
        $stmt = $this->pdo->prepare($query->sql);
        $stmt->execute($query->bindings);
        return $stmt->fetchAll();
    }

    public function fetch(CompiledQuery $query): ?array {
        $stmt = $this->pdo->prepare($query->sql);
        $stmt->execute($query->bindings);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function execute(CompiledQuery $query): int
    {
        $stmt = $this->pdo->prepare($query->sql);
        $stmt->execute($query->bindings);

        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
