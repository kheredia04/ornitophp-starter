# Getting Started

OrnitoPHP is a tiny educational PHP framework for PHP 8.4 — the spiritual successor of **sprinf**, rebuilt from scratch on modern standards. It keeps sprinf's good instincts (single front controller, declarative route table, base model/controller) while fixing its structural mistakes: controllers return real `Response` objects that are sent exactly once, every database query uses prepared statements, and all input flows through one `Request` value object instead of raw superglobals.

## Installation

```bash
composer install
copy .env.example .env   # optional — defaults apply without it
```

## Running the server

```bash
composer serve           # php -S localhost:8080 -t public
```

Open <http://localhost:8080>.

### Serving with Laragon

Laragon's auto-vhost (`OrnitoPHP.test`) already points its DocumentRoot at this project's `public/`, so there is nothing to configure: start Apache from the Laragon window and open <http://OrnitoPHP.test>.

The rewrite rules live in `public/.htaccess` and nowhere else on purpose: since the vhost DocumentRoot IS `public/`, a root-level `.htaccess` would never be read by Apache — adding one would be dead configuration.

## Running the test suite

```bash
composer test
```

## Environment variables

OrnitoPHP uses [phpdotenv](https://github.com/vlucas/phpdotenv) to load `.env` into `$_ENV`. Configuration files in `config/` read from it via `Env::get()`:

```env
APP_NAME=OrnitoPHP
APP_ENV=production
APP_DEBUG=false
APP_STYLE=app              # 'app' (dark), 'bootstrap' (CDN), or 'tailwind' (CDN)

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ornito
DB_USERNAME=root
DB_PASSWORD=
```

## Directory structure

```
OrnitoPHP/
├── public/
│   ├── index.php          # Single front controller — boots and runs the kernel
│   ├── .htaccess           # Apache rewrite rules
│   └── assets/             # CSS, images, JS
├── bin/
│   └── ornito             # Console entry point (generators, migrations, seeding)
├── routes/
│   ├── web.php            # Declarative route table for pages
│   └── api.php            # Route table for /api/* machine clients
├── config/
│   ├── app.php            # Application configuration
│   ├── auth.php           # Login throttling limits
│   └── session.php        # Session cookie flags
├── src/                   # Framework kernel (Ornito\ namespace)
│   ├── Application.php    # Kernel: boot, dispatch, error render
│   ├── Controller.php     # Base controller (view() renders templates)
│   ├── Model.php          # Abstract base model (CRUD + query builder)
│   ├── Console/           # CLI commands
│   ├── Database/          # Connection, QueryBuilder
│   ├── Http/              # Request, Response, ErrorRenderer, HttpException
│   ├── Middleware/         # Pipeline, CSRF, Session, JWT
│   ├── Routing/           # Router, exceptions
│   ├── Security/          # RateLimiter, Jwt
│   ├── Session/           # Session wrapper
│   ├── Support/           # Env, helpers
│   └── Validation/        # Validator, ValidationException
├── app/                   # Userland application (App\ namespace)
│   ├── Controllers/       # AuthController, HomeController
│   ├── Middleware/         # Authenticate, SampleMiddleware
│   └── Models/            # User model
├── database/
│   ├── migrations/        # SQL migration files (run once, tracked)
│   ├── migrate.php        # Migration runner
│   └── seed.php           # Seeder runner
├── views/                 # PHP templates (inside shared layout)
│   └── errors/            # Branded error pages (404, 500, etc.)
├── storage/
│   ├── logs/              # Log output
│   └── ratelimit/         # Login throttle counters
├── docs/                  # This documentation
└── tests/                 # PHPUnit test suite
```

## Your first route

Routes are declared as arrays in `routes/web.php`. Each entry is:

```php
[$method, $path, $handler, $middleware?]
```

```php
// routes/web.php
use Ornito\Http\Request;
use Ornito\Http\Response;

return [
    ['GET', '/', static fn (Request $request): Response => Response::html('<h1>Hello world</h1>')],
];
```

Handlers receive the `Request` as the first argument, followed by any dynamic route segment values. They MUST return a `Response` — no echoing, no void returns.

## Your first controller

For anything beyond a one-liner, use a controller:

```php
// app/Controllers/GreetingController.php
declare(strict_types=1);

namespace App\Controllers;

use Ornito\Controller;
use Ornito\Http\Request;
use Ornito\Http\Response;

final class GreetingController extends Controller
{
    public function hello(Request $request, string $name): Response
    {
        return Response::html("<h1>Hello, {$name}</h1>");
    }
}
```

Register it in `routes/web.php`:

```php
['GET', '/hello/{name}', [App\Controllers\GreetingController::class, 'hello']],
```

## Design rules

- **Prepared statements only** — values are always bound as parameters; identifiers are validated against a strict regex as defense in depth.
- **Controllers return `Response` objects** — the kernel calls `send()` exactly once; no controller ever echoes.
- **Single front controller** — every request enters through `public/index.php`.
- **Middleware pipeline** — route middleware wraps the handler in an onion-style pipeline.
- **Superglobals are quarantined** — only `Request::capture()` may read them.
