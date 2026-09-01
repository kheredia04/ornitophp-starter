# Console

`bin/ornito` is the framework's console entry point. It is plain PHP invoked through the interpreter — the shebang is only there for unix `chmod +x` convenience.

## Usage

```bash
# Windows
php bin\ornito <command> [arguments] [options]

# Unix
./bin/ornito <command> [arguments] [options]
```

## Available commands

| Command | Description |
|---|---|
| `help` | Show all available commands |
| `create:model <Name> [col:type ...] [--table=] [--force]` | Generate a model + migration |
| `create:controller <Name> [--force]` | Generate a controller |
| `create:relation <Owner> <has-many\|belongs-to\|many-to-many> <Other> [--force]` | Generate a FK/pivot migration + relationship methods |
| `explain "<SELECT sql>"` | Show how MySQL would run a SELECT (read-only; inline literals) |
| `migrate` | Create database + apply pending migrations |
| `db:seed [--force]` | Create or refresh the demo user (refuses in production) |
| `db:fresh [--force]` | Drop all tables, migrate, seed |
| `show:auth-module` | Expose login/register/logout/dashboard routes |
| `hide:auth-module` | Hide the auth demo routes |

## Composer aliases

Some commands have Composer script shortcuts:

```bash
composer db:migrate    # → php bin\ornito migrate
composer db:seed       # → php bin\ornito db:seed
composer db:fresh      # → php bin\ornito db:fresh
composer test          # → php vendor\bin\phpunit
composer serve         # → php -S localhost:8080 -t public
```

## create:model

Generates two files: a model class and a SQL migration.

```bash
php bin\ornito create:model Animal nombre:string tipo:string cantidad:int mamifero:boolean
```

This creates:
- `app/Models/Animal.php` — model class with `$table = 'animales'`
- `database/migrations/0002_create_animales.sql` — migration with your columns

The migration file looks like this:

```sql
CREATE TABLE IF NOT EXISTS `animales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(255) NOT NULL,
    `tipo` VARCHAR(255) NOT NULL,
    `cantidad` INT NOT NULL,
    `mamifero` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Column types

Each column is specified as `name:type`. The type maps to a SQL fragment:

| Type | SQL fragment | Example |
|---|---|---|
| `string` | `VARCHAR(255) NOT NULL` | `nombre:string` → `nombre VARCHAR(255) NOT NULL` |
| `text` | `TEXT NULL` | `descripcion:text` → `descripcion TEXT NULL` |
| `int` | `INT NOT NULL` | `cantidad:int` → `cantidad INT NOT NULL` |
| `boolean` | `TINYINT(1) NOT NULL DEFAULT 0` | `activo:boolean` → `activo TINYINT(1) NOT NULL DEFAULT 0` |
| `decimal` | `DECIMAL(10,2) NULL` | `precio:decimal` → `precio DECIMAL(10,2) NULL` |
| `datetime` | `DATETIME NULL` | `fecha:datetime` → `fecha DATETIME NULL` |

Every table also gets `id` (auto-increment primary key) and `created_at` (timestamp) automatically.

### Options

| Option | Description |
|---|---|
| `--table=<name>` | Override the auto-derived table name |
| `--force` | Overwrite an existing model file |

### Table name derivation

PascalCase → snake_case + `s`:

| Model name | Table name |
|---|---|
| `Producto` | `productos` |
| `BlogPost` | `blog_posts` |
| `User` | `users` |

Irregular plurals may be wrong (`luz` → `luzs`, not `luces`). Pass `--table` for those:

```bash
php bin\ornito create:model Persona --table=gente
```

### Safety

- An existing model refuses to regenerate unless `--force` is given.
- `--force` overwrites **only** the model file. Migrations are append-only history and are never touched.

## create:controller

Generates a controller with an `index()` method stub.

```bash
php bin\ornito create:controller Producto
# → app/Controllers/ProductoController.php

php bin\ornito create:controller UserAuth
# → app/Controllers/UserAuthController.php
```

Both `Producto` and `ProductoController` produce the same file — the command is idempotent about the `Controller` suffix.

### Options

| Option | Description |
|---|---|
| `--force` | Overwrite an existing controller file |

## create:relation

Generates a relationship between two existing models WITHOUT an ORM: an append-only migration that creates the physical link (a FK column for has-many/belongs-to, a pivot table for many-to-many) plus one explicit static method per model.

```bash
php bin\ornito create:relation User has-many Post
php bin\ornito create:relation Post belongs-to User   # same pair, other side
php bin\ornito create:relation User many-to-many Role
```

### What it generates

**has-many / belongs-to** — the FK lives on the second model's table:

```sql
-- database/migrations/0002_relate_posts_to_users.sql
ALTER TABLE `posts`
    ADD COLUMN `user_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_posts_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE;
```

And the model methods:

```php
// app/Models/User.php — many side
public static function posts(int $userId): QueryBuilder
{
    return Post::query()->where('user_id', $userId);
}

// app/Models/Post.php — belongs-to side
public static function user(array $post): ?array
{
    return User::find((int) ($post['user_id'] ?? 0));
}
```

**many-to-many** — an alphabetically-named pivot table plus a subquery method on each side:

```sql
-- database/migrations/0002_create_role_user.sql
CREATE TABLE `role_user` (
    `role_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `user_id`),
    CONSTRAINT `fk_role_user_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_user_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```php
// app/Models/User.php
public static function roles(int $userId): QueryBuilder
{
    return Role::query()->whereInSub('id', 'role_user', 'role_id', 'user_id', $userId);
}
```

The subquery keeps the SQL explicit — `WHERE id IN (SELECT ...)` is visible in the query, not hidden behind a relation loader.

### Conventions

- FK column: `{singular_owner}_id` (snake_case of the owner class name).
- Pivot table: the two singular names joined alphabetically (`role_user`, never `user_role`).
- Pivot columns and FK references use the same INT UNSIGNED type as generated `id`s.
- Both FK constraints use `ON DELETE CASCADE`.

### Safety

- Both model files must exist first (use `create:model`).
- A relation whose migration already exists refuses to regenerate — running the same pair twice can never duplicate a FK/pivot (and `belongs-to` and `has-many` are the same pair).
- `--force` regenerates **only** the model methods and only when they carry the generated marker. Hand-written methods are never replaced.
- Every generated identifier is validated (strict regex + MySQL 64-character limit) before anything is written.

After generating, apply the migration:

```bash
composer db:migrate
```

## migrate

Creates the database if it doesn't exist and applies all pending migrations:

```bash
php bin\ornito migrate
```

Migrations are tracked in a `schema_migrations` table. Each file runs at most once.

## db:seed

Creates or refreshes the demo user (`pato@ornitophp.dev` / `platypus123`):

```bash
php bin\ornito db:seed
```

The seeder is idempotent — it updates or inserts the demo user instead of duplicating it, so running it multiple times is safe.

**Production firewall.** The demo password is publicly known, so seeding in production would hijack whoever owns `pato@ornitophp.dev`. When `APP_ENV=production`, the command refuses and exits with code 1:

```
Refusing to seed: APP_ENV=production and the demo seeder writes a publicly-known
password. Run with --force to seed anyway.
```

Pass `--force` to seed anyway in production:

```bash
php bin\ornito db:seed --force
```

An unset `APP_ENV` defaults to `local` so the create-project installer can still seed; real deployments must set `APP_ENV=production` (see `.env.example`).

## db:fresh

**DESTRUCTIVE.** Drops every table, then migrates + seeds from scratch:

```bash
php bin\ornito db:fresh --force
```

Without `--force`, it asks for confirmation:

```
This will DROP ALL TABLES in ornito. Type yes to continue:
```

Only the exact answer `yes` proceeds. Anything else aborts with exit code 1.

## show:auth-module / hide:auth-module

The auth demo module (login, register, logout, dashboard) ships **hidden**. These commands toggle its visibility:

```bash
php bin\ornito show:auth-module    # exposes the routes
php bin\ornito hide:auth-module    # hides them again
```

The command writes a marker file (`storage/auth-module.enabled`). The route table checks for this file before registering auth routes. When hidden, those paths return 404.

## Quiet contract

Every command follows the same contract: `execute()` collects tagged messages (stdout/stderr), and the entry point flushes them. Commands stay silent under test — no output unless there is something to report.

## Extending the console

New commands live in `src/Console/Commands/`. Each command:

1. Extends `Ornito\Console\Command` (or implements a command interface).
2. Has an `execute(array $args)` method.
3. Registers itself in `bin/ornito`'s command list.

The bootstrap is deliberately minimal: just the Composer autoloader, no `Application::boot()`. Commands that need the database or config must boot those explicitly.
