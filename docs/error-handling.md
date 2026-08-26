# Error Handling

OrnitoPHP funnels every unhandled failure into one renderer: `Ornito\Http\ErrorRenderer`. App code never catches its own exceptions to render error pages — the kernel does it.

## The error pipeline

```
Any Throwable → ErrorRenderer → branded HTML page or JSON response
```

This applies to:
- Routing exceptions (`RouteNotFoundException` → 404, `MethodNotAllowedException` → 405)
- Application exceptions (`HttpException` with any status)
- Unexpected errors (anything else → 500)

## HttpException

The primary tool for refusing a request with an honest HTTP status:

```php
use Ornito\Http\HttpException;

throw new HttpException(404, 'User not found.');
throw new HttpException(403, 'CSRF token mismatch.');
throw new HttpException(429, 'Slow down.', ['Retry-After' => '30']);
```

| Parameter | Type | Required | Description |
|---|---|---|---|
| `$status` | `int` | Yes | HTTP status code |
| `$message` | `string` | No | Human-readable message |
| `$headers` | `array` | No | Extra response headers |

### The `abort()` helper

A shortcut for the most common case — throwing an `HttpException` without extra headers:

```php
abort(404);
abort(403, 'CSRF token mismatch.');
```

`abort()` is typed as `never` — it always throws, so PHP knows control doesn't continue past it.

## ErrorRenderer

Renders the error as either a **branded HTML page** or a **JSON response**, depending on the request:

| Condition | Response |
|---|---|
| `Accept: application/json` header | JSON |
| Path under `/api` | JSON |
| Everything else | HTML |

### HTML errors

The renderer looks for a view file matching the status code:

```
views/errors/404.php
views/errors/501.php
```

If no view exists for the status, it falls back to a generic error page. All error views are rendered inside the shared layout.

### JSON errors

```json
{
    "error": "User not found.",
    "status": 404
}
```

`HttpException` headers (like `Allow` on 405) are preserved in the JSON response.

### Message policy

| `APP_DEBUG` | 5xx messages | 4xx messages |
|---|---|---|
| `true` | Full exception message + trace | Full message |
| `false` | Generic "Something went wrong." | Friendly per-status copy |

Raw exception messages **never** leak to visitors in production. Implementation details appear only when `APP_DEBUG=true`.

## Custom error pages

Create a view file named after the status code in `views/errors/`:

```php
<!-- views/errors/403.php -->
<h1>Access Denied</h1>
<p>You don't have permission to view this page.</p>
```

The renderer automatically uses your custom view when that status code is thrown.

## Throwing from middleware

Middleware can short-circuit the pipeline by throwing an `HttpException`:

```php
public function handle(Request $request, Closure $next): Response
{
    if (!Session::get('user_id')) {
        abort(401, 'Authentication required.');
    }

    return $next($request);
}
```

The kernel catches it and renders the branded 401 page (or JSON, for API requests).

## Example: full error flow

```php
// routes/web.php
['GET', '/users/{id}', [UserController::class, 'show']],
```

```php
final class UserController
{
    public function show(Request $request, string $id): Response
    {
        $user = User::find((int) $id);

        if ($user === null) {
            abort(404, 'User not found.');
        }

        return Response::json($user);
    }
}
```

When the user is not found:
1. `abort(404)` throws `HttpException(404)`.
2. `Application::renderError()` catches it.
3. `ErrorRenderer` checks the request: if `Accept: application/json` → `{"error": "User not found.", "status": 404}`. Otherwise → `views/errors/404.php`.

## Unencodable payloads

If `Response::json()` receives data that cannot be encoded (invalid UTF-8, NaN, resources), it throws `JsonException` **before any output happens**. The kernel catches it and renders a 500 through the normal error pipeline.
