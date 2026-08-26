# Routing

Routes are declared as arrays in `routes/web.php` (pages) and `routes/api.php` (machine clients). Both files return the same array shape and are loaded by `Application::boot()`.

## Route format

Each route is a numerically-indexed array:

```php
[$method, $path, $handler, $middleware?]
```

| Element | Type | Required | Description |
|---|---|---|---|
| `$method` | `string` | Yes | HTTP method: `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| `$path` | `string` | Yes | URI pattern, optionally with `{param}` segments |
| `$handler` | `callable\|array` | Yes | Closures or `[Class::class, 'method']` arrays |
| `$middleware` | `array` | No | Per-route middleware classes (outermost first) |

## Dynamic segments

Curly braces capture URI segments and pass them as method arguments:

```php
['GET', '/users/{id}', [UserController::class, 'show']],
```

```php
public function show(Request $request, string $id): Response
{
    // $id is the captured segment value
}
```

Segment names must be valid PHP identifiers (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`). The framework validates them at registration time.

## Route files

| File | Purpose |
|---|---|
| `routes/web.php` | Page routes — HTML responses, session-based auth |
| `routes/api.php` | API routes — JSON responses, token-based auth |

Both files are loaded automatically by `Application::boot()`. Add new files to the `$routeFiles` array in `src/Application.php` if you need more.

## Middleware per route

Pass an array of middleware classes as the fourth element. They run in order (outermost first) after the global stack:

```php
['GET', '/dashboard', [DashboardController::class, 'index'], [Authenticate::class]],
```

```php
['POST', '/api/data', [ApiController::class, 'store'], [VerifyJwtToken::class]],
```

See [middleware.md](middleware.md) for the full middleware documentation.

## Route matching

The `Router` matches requests by HTTP method and URI. When no route matches:

- No matching path → `RouteNotFoundException` → **404** branded page
- Path matches but method is wrong → `MethodNotAllowedException` → **405** with `Allow` header

Both exceptions are subclasses of `HttpException` and are rendered through the normal error pipeline.

## Example: full route table

```php
// routes/web.php
use App\Controllers\HomeController;
use App\Controllers\UserController;
use App\Middleware\Authenticate;
use Ornito\Http\Request;
use Ornito\Http\Response;

return [
    // Simple closure
    ['GET', '/', static fn (Request $r): Response => Response::html('<h1>Home</h1>')],
    
    // Controller with dynamic segment
    ['GET', '/users/{id}', [UserController::class, 'show']],
    
    // Protected route
    ['GET', '/dashboard', [HomeController::class, 'dashboard'], [Authenticate::class]],
];
```

```php
// routes/api.php
use App\Controllers\Api\UserApiController;
use Ornito\Http\Request;
use Ornito\Http\Response;

return [
    ['GET', '/api/hello/{name}', static fn (Request $r, string $n): Response => Response::json(['hello' => $n])],
    ['GET', '/api/users/{id}', [UserApiController::class, 'show']],
];
```
