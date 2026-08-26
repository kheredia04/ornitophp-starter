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
| `migrate` | Create database + apply pending migrations |
| `db:seed` | Create or refresh the demo user |
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
php bin\ornito create:model Producto nombre:string precio:decimal stock:int activo:boolean
```

This creates:
- `app/Models/Producto.php` — model class with `$table = 'productos'`
- `database/migrations/0002_create_productos.sql` — migration with your columns

### Column types

| Type | SQL fragment |
|---|---|
| `string` | `` `{col}` VARCHAR(255) NOT NULL`` |
| `text` | `` `{col}` TEXT NULL`` |
| `int` | `` `{col}` INT NOT NULL`` |
| `boolean` | `` `{col}` TINYINT(1) NOT NULL DEFAULT 0`` |
| `decimal` | `` `{col}` DECIMAL(10,2) NULL`` |
| `datetime` | `` `{col}` DATETIME NULL`` |

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

## migrate

Creates the database if it doesn't exist and applies all pending migrations:

```bash
php bin\ornito migrate
```

Migrations are tracked in a `schema_migrations` table. Each file runs at most once.

## db:seed

Creates or refreshes the demo user:

```bash
php bin\ornito db:seed
```

The seeder is idempotent — it uses `INSERT IGNORE` or equivalent so running it multiple times is safe.

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
