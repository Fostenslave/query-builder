# Architecture and development

## Architecture

The architecture separates query construction, SQL dialect compilation, and
database side effects. Interfaces define the extension points; concrete classes
provide the default implementations.

```mermaid
classDiagram
    class Grammar {
        <<interface>>
        +compileSelect(...) CompiledQuery
        +compileInsert(...) CompiledQuery
        +compileUpdate(...) CompiledQuery
        +compileDelete(...) CompiledQuery
    }

    class BaseGrammar {
        <<abstract>>
        +compileSelect(...) CompiledQuery
        +compileInsert(...) CompiledQuery
        +compileUpdate(...) CompiledQuery
        +compileDelete(...) CompiledQuery
    }

    class SqliteGrammar
    class MysqlGrammar

    class QueryBuilderContract {
        <<interface>>
    }

    class QueryBuilder {
        <<class>>
        +select(...) QueryBuilderContract
        +where(...) QueryBuilderContract
        +get() array
        +insert(...) int
        +update(...) int
        +delete() int
        +compile() CompiledQuery
    }

    class QueryExecutor {
        <<interface>>
        +fetchAll(CompiledQuery) array
        +fetch(CompiledQuery) array
        +execute(CompiledQuery) int
    }

    class PDOQueryExecutor {
        <<class>>
        +fetchAll(CompiledQuery) array
        +fetch(CompiledQuery) array
        +execute(CompiledQuery) int
    }

    class TransactionManager {
        <<interface>>
        +beginTransaction() void
        +commit() void
        +rollBack() void
        +transaction(Closure) mixed
    }

    class DB {
        <<class>>
        +table(string) QueryBuilderContract
        +transaction(Closure) mixed
    }

    class CompiledQuery {
        <<class>>
        +sql string
        +bindings array
    }

    class Compilable {
        <<interface>>
        +compile(Grammar) CompiledQuery
    }

    class Expression
    class JoinClause
    class WhereClause
    class RawClause
    class OrderByClause

    Grammar <|.. BaseGrammar
    BaseGrammar <|-- SqliteGrammar
    BaseGrammar <|-- MysqlGrammar

    QueryBuilderContract <|.. QueryBuilder
    QueryExecutor <|.. PDOQueryExecutor
    TransactionManager <|.. DB

    Compilable <|.. Expression
    Compilable <|.. JoinClause
    Compilable <|.. WhereClause
    Compilable <|.. RawClause
    Compilable <|.. OrderByClause

    DB ..> QueryBuilder : creates
    QueryBuilder --> Grammar : uses
    QueryBuilder --> QueryExecutor : delegates execution
    QueryBuilder ..> CompiledQuery : produces
    QueryExecutor --> CompiledQuery : executes
    PDOQueryExecutor --> PDO : calls
```

### Query execution flow

```mermaid
sequenceDiagram
    participant App as Application
    participant DB as DB
    participant Builder as QueryBuilder
    participant Grammar as Grammar
    participant Executor as QueryExecutor
    participant PDO

    App->>DB: table("users")
    DB-->>App: QueryBuilder
    App->>Builder: where(...).get()
    Builder->>Grammar: compile()
    Grammar-->>Builder: CompiledQuery(sql, bindings)
    Builder->>Executor: fetchAll(CompiledQuery)
    Executor->>PDO: prepare(sql)
    Executor->>PDO: bind and execute(bindings)
    PDO-->>Executor: rows
    Executor-->>Builder: result
    Builder-->>App: array of rows
```

## Development and testing

### Required programs

- Docker and Docker Compose version 2 or later
- make
- git

### Setup

```bash
git clone https://github.com/fostenslave/query-builder.git
cd query-builder
make init
```

The `make init` command builds the development container and installs Composer
dependencies.

### Common commands

```bash
make test
make composer CMD="validate --strict"
make clean
```

Run the test suite before committing changes

When adding a new SQL dialect, implement the `Grammar` contract. Extend
`BaseGrammar` when the shared compilation behavior is reusable, and add unit
tests for dialect-specific SQL generation.

When adding a new executable database integration, implement `QueryExecutor`
instead of coupling query construction to a concrete PDO adapter.
