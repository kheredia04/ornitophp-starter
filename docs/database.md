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
| `hasMany`, `belongsTo` | Explicit generated methods over plain queries (see Relationships) |
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

Every model exposes a read-side builder via `::query()`. It covers: `select(...)`, chained `where()` (AND only), `whereInSub()` for N:M lookups, `orderBy()`, `limit()`, `offset()`, and terminal methods `get()`, `first()`, `count()`, and `paginate()`.

### Inspecting the SQL (teaching extras)

The builder can show you exactly what it would run — pair `toSql()` with `getBindings()` and the query becomes a transparent teaching tool:

```php
$builder = User::query()->where('active', 1)->orderBy('created_at', 'DESC');

echo $builder->toSql();        // SELECT * FROM users WHERE active = ? ORDER BY created_at DESC
print_r($builder->getBindings()); // [1]

echo $builder->toCountSql();   // SELECT COUNT(*) FROM users WHERE active = ?
```

`toPreviewSql()` renders the same query with every value inline for logs and examples — **display only, never execute it**:

```php
echo User::query()->where('email', 'pato@ornitophp.dev')->toPreviewSql();
// SELECT * FROM users WHERE email = 'pato@ornitophp.dev'
```

The preview is pure string rendering and never connects to the database.

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

### WHERE … IN (subquery)

`whereInSub($column, $subqueryTable, $selectColumn, $whereColumn, $value)` adds a `WHERE column IN (SELECT ...)` condition. This is the N:M relationship shape — the subquery stays visible in the SQL instead of hiding behind a relation loader:

```php
// Role::query()->whereInSub('id', 'role_user', 'role_id', 'user_id', 7)
// → SELECT * FROM roles WHERE id IN (SELECT role_id FROM role_user WHERE user_id = 7)
$roles = Role::query()
    ->whereInSub('id', 'role_user', 'role_id', 'user_id', $userId)
    ->get();
```

Like every `where()`, the lookup value is bound as a parameter; the four identifiers are validated against the same strict regex as table/column names.

### Supported operators

`=` `!=` `<>` `<` `>` `<=` `>=` `LIKE`

### Supported ORDER BY directions

`ASC` `DESC` (case-insensitive)

### Safety contract

- **Values** are always bound as positional parameters (`?`).
- **Identifiers** (table/column names) are validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`.
- **Operators** and **directions** come from whitelists.
- **LIMIT/OFFSET** are typed ints rejected when negative.

**OR chains and grouped conditions are out of scope by design** — when a query needs them, write the SQL by hand so it stays visible.

## Relationships without an ORM

Relationships are not magic: they are a FK (or a pivot table) plus plain queries. `create:relation` generates both, and the resulting methods are ordinary static methods you can read and customize.

```bash
php bin\ornito create:relation User has-many Post
php bin\ornito create:relation Post belongs-to User   # same physical pair
php bin\ornito create:relation User many-to-many Role
```

The generator writes an append-only migration and one explicit method per model. For has-many/belongs-to the FK lives on the second model's table (`posts.user_id` ← `users.id`):

```php
// User.php
public static function posts(int $userId): QueryBuilder
{
    return Post::query()->where('user_id', $userId);
}

// Post.php
public static function user(array $post): ?array
{
    return User::find((int) ($post['user_id'] ?? 0));
}
```

Usage:

```php
$user = User::find(7);

foreach (User::posts($user['id']) as $post) {
    echo $post['title'];
}

$owner = Post::user($post);          // the User row, or null
$roles = User::roles($user['id']);   // N:M through the role_user pivot
```

For many-to-many, the pivot is named alphabetically (`role_user`, never `user_role`) and each side gets a subquery method — `WHERE id IN (SELECT role_id FROM role_user WHERE user_id = ?)` — so the linking SQL is always explicit.

**Guard rails:** both models must exist; a relation already generated refuses to duplicate; `--force` regenerates only generated methods, never hand-written code. See `docs/console.md` → `create:relation` for the full conventions and safety contract.

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
