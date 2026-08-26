# Database

OrnitoPHP uses MySQL via PDO with prepared statements for every query. The `Connection` class provides a lazy singleton — the database is only connected when first queried.

## Configuration

Set in `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ornito
DB_USERNAME=root
DB_PASSWORD=
```

## Migrations

SQL migration files live in `database/migrations/` and run once each. Applied file names are tracked in a `schema_migrations` table.

```bash
composer db:migrate       # creates the database + applies pending migrations
composer db:seed          # creates or refreshes the demo user
composer db:fresh --force # DROPS ALL TABLES, then migrates + seeds
```

Or via the console:

```bash
php bin\ornito migrate
php bin\ornito db:seed
php bin\ornito db:fresh --force
```

Migration files are named `{NNNN}_create_{table}.sql` (e.g., `0001_create_users.sql`). They are append-only — once applied, they are never modified.

### `db:fresh` safety

Without `--force`, it asks on STDOUT:

```
This will DROP ALL TABLES in ornito. Type yes to continue:
```

Only the exact answer `yes` proceeds. Anything else aborts with exit code 1 and leaves the database untouched. There is no undo.

## The Model

OrnitoPHP has no ORM. Your model **is** the table. One line of configuration (`$table`) gives you all CRUD operations. No magic, no lazy loading, no hidden queries.

### How it works

```php
declare(strict_types=1);

namespace App\Models;

use Ornito\Model;

final class User extends Model
{
    protected static string $table = 'users';  // ← this is everything you need
}
```

That's it. Extend `Ornito\Model`, declare the table, and you get:

| Method | SQL generated |
|---|---|
| `User::all()` | `SELECT * FROM users` |
| `User::find(1)` | `SELECT * FROM users WHERE id = 1` |
| `User::where('email', 'x')` | `SELECT * FROM users WHERE email = :value` |
| `User::insert([...])` | `INSERT INTO users (...) VALUES (...)` |
| `User::update(1, [...])` | `UPDATE users SET ... WHERE id = 1` |
| `User::delete(1)` | `DELETE FROM users WHERE id = 1` |
| `User::query()` | Fluent QueryBuilder (see below) |

All values are bound as prepared statement parameters — never interpolated into SQL.

### Why no ORM?

An ORM (like Eloquent) maps database rows to PHP objects. It adds convenience — relationships, lazy loading, scopes — but also adds **abstraction**. When something goes wrong, you debug the ORM, not the SQL.

OrnitoPHP does the opposite: **SQL is honest**. What you write is what runs. No hidden queries, no N+1 problems, no lazy loading surprises. You learn how databases actually work.

| Eloquent (ORM) | OrnitoPHP (no ORM) |
|---|---|
| `User::where('active', true)->get()` | `User::query()->where('active', '=', true)->get()` |
| Returns `User` objects | Returns **arrays** |
| `hasMany`, `belongsTo` | No relationships — write the query |
| `$user->save()` | `User::update($id, $data)` |
| Hidden joins and subqueries | Every query is visible |

### Adding custom queries

When you need something beyond basic CRUD, add methods to your model:

```php
final class Animal extends Model
{
    protected static string $table = 'animales';

    // Fluent query builder
    public static function findMamiferos(): array
    {
        return static::query()
            ->where('mamifero', '=', 1)
            ->orderBy('nombre', 'ASC')
            ->get();
    }

    // Raw SQL for complex queries (joins, OR, grouping)
    public static function countByTipo(): array
    {
        $stmt = static::prepare(
            'SELECT tipo, COUNT(*) as total FROM animales GROUP BY tipo'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
```

**Rule of thumb:** Use the query builder for simple reads. Use raw SQL for joins, OR chains, grouping, or anything the builder can't express.

### CRUD operations

```php
// Read all rows
$users = User::all();

// Read one row by id
$user = User::find(42);

// Read rows by column value
$admins = User::where('role', 'admin');

// Insert a row (returns the new id)
$id = User::insert([
    'name' => 'Pato',
    'email' => 'pato@ornitophp.dev',
    'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
]);

// Update a row by id
User::update(42, ['name' => 'Perrito']);

// Delete a row by id
User::delete(42);
```

## Fluent Query Builder

Every model exposes a read-side builder via `::query()`. It covers: `select(...)`, chained `where()` (AND only), `orderBy()`, `limit()`, `offset()`, and terminal methods `get()`, `first()`, `count()`, and `paginate()`.

### Basic queries

```php
// Select specific columns
$emails = User::query()
    ->select('id', 'email')
    ->get();

// Where with equality
$admins = User::query()
    ->where('role', 'admin')
    ->get();

// Where with operator
$recent = User::query()
    ->where('created_at', '>', '2024-01-01')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// First matching row (or null)
$user = User::query()
    ->where('email', 'pato@ornitophp.dev')
    ->first();

// Count matching rows
$count = User::query()
    ->where('role', 'admin')
    ->count();
```

### Supported operators

`=` `!=` `<>` `<` `>` `<=` `>=` `LIKE`

### Supported ORDER BY directions

`ASC` `DESC` (case-insensitive)

### Safety contract

- **Values** are always bound as positional parameters (`?`).
- **Identifiers** (table/column names) are validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`.
- **Operators** and **directions** come from whitelists.
- **LIMIT/OFFSET** are typed ints rejected when negative.

**OR chains, grouped conditions, and relations are out of scope by design** — when a query needs them, write the SQL by hand so it stays visible.

## Pagination

`paginate()` returns a structured array with the page's data and metadata:

```php
$result = User::query()
    ->where('role', 'admin')
    ->orderBy('created_at', 'DESC')
    ->paginate(page: 2, perPage: 15);

// $result = [
//     'data'       => [...rows...],
//     'total'      => 42,
//     'page'       => 2,
//     'per_page'   => 15,
//     'last_page'  => 3,
// ]
```

| Parameter | Default | Description |
|---|---|---|
| `page` | `1` | 1-based page number (values < 1 are clamped to 1) |
| `perPage` | `15` | Rows per page (values < 1 are clamped to 1) |

The `last_page` is derived from `ceil(total / per_page)` so consumers can render pagination controls without a second query.

### Using paginate in a controller

```php
public function index(Request $request): Response
{
    $page = max(1, (int) ($request->input('page') ?? 1));

    $result = User::query()
        ->orderBy('created_at', 'DESC')
        ->paginate(page: $page, perPage: 20);

    return Response::json($result);
}
```

## Raw SQL

When the query builder cannot express what you need (joins, subqueries, OR chains), use PDO directly through `Connection::pdo()`:

```php
use Ornito\Database\Connection;

$pdo = Connection::pdo();
$statement = $pdo->prepare('SELECT * FROM users WHERE role = :role OR email = :email');
$statement->execute(['role' => 'admin', 'email' => 'pato@ornitophp.dev']);
$users = $statement->fetchAll();
```

Always use prepared statements. Never interpolate user input into SQL strings.
