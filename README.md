# SimpleORM Query Builder

Immutable, PDO SQL query builder with compile-time dialect support.

- Dialects extensible via `Grammar` interface
- Raw SQL with parameterised bindings
- PDO prepared statements with typed bindings
- Transactions via callback or manual begin/commit/rollBack

## Installation

```bash
composer require simple-orm/query-builder
```

## Quick start

```php
use SimpleORM\Database\DB;

$pdo = new PDO('sqlite:app.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db = new DB($pdo);
```

### SELECT

```php
$users = $db->table('users')
    ->select('id', 'name', 'email')
    ->where('active', '=', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

$user = $db->table('users')->where('id', '=', 42)->first();
```

### JOIN

```php
$db->table('users')
    ->select('users.name', 'posts.title')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->get();

$db->table('users')
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();
```

### INSERT / UPDATE / DELETE

```php
$id = $db->table('users')->insert(['name' => 'Alice', 'age' => 30]);

$affected = $db->table('users')->where('id', '=', 1)->update(['name' => 'Bob']);

$affected = $db->table('users')->where('id', '=', 1)->delete();
```

### GROUP BY / HAVING / Aggregates

```php
$db->table('orders')
    ->selectRaw('user_id, SUM(amount) AS total')
    ->groupBy('user_id')
    ->having('total', '>', 100)
    ->havingRaw('COUNT(*) > ?', [1])
    ->get();

$total = $db->table('orders')->sum('amount');  // → float|int|null
$avg   = $db->table('orders')->avg('amount');
$min   = $db->table('orders')->min('amount');
$max   = $db->table('orders')->max('amount');
$count = $db->table('orders')->where('status', '=', 'completed')->count();
$has   = $db->table('orders')->where('id', '=', 42)->exists();  // → bool
```

### Raw expressions with bindings

```php
$db->table('users')
    ->selectRaw('YEAR(created_at) AS yr, COUNT(*) AS cnt')
    ->whereRaw('age > ?', [18])
    ->whereRaw('name LIKE :pattern', ['pattern' => '%alice%'])
    ->get();
```

### Pagination

```php
$db->table('users')
    ->orderBy('id', 'ASC')
    ->paginate(page: 2, perPage: 10);
```

### Transactions

```php
$db->transaction(function (DB $db) {
    $db->table('users')->insert(['name' => 'Alice']);
    $db->table('profiles')->insert(['user_id' => $db->lastInsertId()]);
});

$db->beginTransaction();
try {
    $db->table('users')->insert([...]);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

### MySQL

```php
use SimpleORM\Grammar\MysqlGrammar;

$mysqlPdo = new PDO('mysql:host=127.0.0.1;dbname=app', 'user', 'pass');
$db = new DB($mysqlPdo, new MysqlGrammar());
```

## Immutability

Every fluent method returns a **clone** — the original builder is never modified:

```php
$base = $db->table('users');
$active = $base->where('active', '=', 1);
$admins = $base->where('role', '=', 'admin');

$active->get();
$admins->get();
```

## Architecture

```
QueryBuilder (fluent API, state)  →  Grammar (SQL dialect compiler)  →  CompiledQuery (sql + bindings)
                                                                                    ↓
                                                                            QueryExecutor (PDO)

DB — entry point, factory, transaction manager
```

## Testing

```bash
git clone https://github.com/simple-orm/query-builder.git
cd query-builder
make install
```


## Extending — custom Grammar

```php
class PostgresGrammar extends BaseGrammar
{
    protected function wrapValue(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}

$db = new DB($pgsqlPdo, new PostgresGrammar());
```

## Requirements

- PHP 8.5+
- PDO extension
- For tests: `ext-pdo_sqlite`

Optional:
- `ext-pdo_mysql` for MySQL dialect
- `ext-pdo_pgsql` for PostgreSQL dialect
