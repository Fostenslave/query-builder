# Query Builder

Immutable, PDO SQL query builder with compile-time dialect support.

- Dialects extensible via `Grammar` interface
- Built-in SQLITE, MySql grammars
- WHERE IN / WHERE NOT IN / WHERE BETWEEN / WHERE NOT BETWEEN
- Subqueries in SELECT (`selectSub`) and FROM (`fromSub`)
- Nested condition groups via `where(callable)`
- Raw SQL with parameterised bindings
- PDO prepared statements with typed bindings
- Transactions via callback or manual begin/commit/rollBack

## Installation

```bash
composer require fostenslave/query-builder
```

## Examples


### Query builder initialization with basic select

```php
use Fostenslave\QueryBuilder\Database\DB;

$pdo = new PDO('sqlite:app.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db = new DB($pdo);

$users = $db->table('users')
    ->select('id', 'name', 'email')
    ->where('active', '=', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

$user = $db->table('users')->where('id', '=', 42)->first();
```
### Joins

```php
$db->table('users')
    ->select('users.name', 'posts.title', 'issues.title')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->join('issues', 'users.id', '=', 'issues.user_id')
    ->get();

$db->table('users')
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->rightJoin('issues', 'users.id', '=', 'issues.user_id')
    ->get();
```

### INSERT / UPDATE / DELETE

```php
$id = $db->table('users')->insert(['name' => 'Alice', 'age' => 30]);

$affected = $db->table('users')->where('id', '=', 1)->update(['name' => 'Bob']);

$affected = $db->table('users')->where('id', '=', 1)->delete();
```

### WHERE IN / WHERE BETWEEN

```php
$db->table('users')
    ->whereIn('role', ['admin', 'moderator'])
    ->get();

$db->table('users')
    ->whereNotIn('status', ['deleted', 'banned'])
    ->get();

$db->table('users')
    ->whereBetween('age', 18, 65)
    ->get();

$db->table('users')
    ->whereNotBetween('score', 0, 50)
    ->orWhereBetween('score', 80, 100)
    ->get();
```

### Nested condition groups (where callback)

```php
$db->table('users')
    ->where('active', '=', 1)
    ->where(function ($g) {
        $g->where('role', '=', 'admin')
          ->orWhere('role', '=', 'moderator');
    })
    ->get();
// SELECT * FROM "users"
//   WHERE "active" = 1 AND ("role" = 'admin' OR "role" = 'moderator')
```

### Subqueries (selectSub / fromSub)

```php
$sub = $db->table('orders')
    ->selectRaw('COUNT(*)')
    ->whereRaw('orders.user_id = users.id');

$users = $db->table('users')
    ->select('name')
    ->selectSub($sub, 'order_count')
    ->get();
// SELECT "name", (SELECT COUNT(*) FROM "orders"
//   WHERE orders.user_id = users.id) AS "order_count" FROM "users"

$fromSub = $db->table('orders')
    ->where('total', '>', 100);

$bigOrders = $db->table('users')
    ->fromSub($fromSub, 'o')
    ->select('o.user_id', 'o.total')
    ->orderBy('o.total', 'ASC')
    ->get();
// SELECT "o"."user_id", "o"."total"
//   FROM (SELECT * FROM "orders" WHERE "total" > ?) AS "o"
//   ORDER BY "o"."total" ASC
```

### GROUP BY / HAVING / Aggregates

```php
$db->table('orders')
    ->selectRaw('user_id, SUM(amount) AS total')
    ->groupBy('user_id')
    ->having('total', '>', 100)
    ->havingRaw('COUNT(*) > ?', [1])
    ->get();

$total = $db->table('orders')->sum('amount');
$avg   = $db->table('orders')->avg('amount');
$min   = $db->table('orders')->min('amount');
$max   = $db->table('orders')->max('amount');
$count = $db->table('orders')->where('status', '=', 'completed')->count();
$has   = $db->table('orders')->where('id', '=', 42)->exists();
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

### Transactions example

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

### MySQL grammar usage example

```php
use Fostenslave\QueryBuilder\Grammar\MysqlGrammar;

$mysqlPdo = new PDO('mysql:host=127.0.0.1;dbname=app', 'user', 'pass');
$db = new DB($mysqlPdo, new MysqlGrammar());
```

## Immutability

Every query builder method returns a **clone** — the original builder is never modified:

```php
$base = $db->table('users');
$active = $base->where('active', '=', 1);
$admins = $base->where('role', '=', 'admin');

$active->get();
$admins->get();
```

## Architecture and development

See [DEVELOPMENT.md](DEVELOPMENT.md) for the class model, query execution
flow, development setup, testing commands, and extension recommendations.


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
