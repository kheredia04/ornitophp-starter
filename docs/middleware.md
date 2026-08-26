# Middleware

Middleware wraps every request in an onion-style pipeline. Global middleware runs on ALL routes; per-route middleware runs only on specific routes, closer to the handler.

## How the pipeline works

```
Request → StartSession → VerifyCsrfToken → [Route Middleware] → Handler → Response
```

Each middleware receives a `Request` and a `Closure $next`. It can:
1. **Pass through**: return `$next($request)` — the request continues inward.
2. **Short-circuit**: return a `Response` directly — the handler never runs.
3. **Modify**: change the request before calling `$next`, or change the response after.

## Built-in middleware

### StartSession (global)

Starts the native PHP session before any route handler runs. Applied automatically by `Application`.

### VerifyCsrfToken (global)

Protects state-changing requests (POST, PUT, PATCH, DELETE) against Cross-Site Request Forgery. The token must be present as:

- A form field: `<input type="hidden" name="_token" value="...">` (use `csrf_field()` helper)
- A header: `X-CSRF-TOKEN: ...`

**CSRF-exempt paths**: paths under `/api` are skipped by default (stateless API clients carry no session cookie). The exemption list is set in `public/index.php`:

```php
VerifyCsrfToken::$exemptPrefixes = ['/api', '/webhooks'];
```

Segment-aware matching: `/api` covers `/api` and `/api/anything` but NOT `/apifoo`.

### Authenticate (per-route)

Route guard for authenticated-only pages. Redirects guests to `/login` and stores the intended URL in the session (`url.intended`). After login, the controller sends the user back to that URL.

```php
['GET', '/dashboard', [DashboardController::class, 'index'], [Authenticate::class]],
```

### VerifyJwtToken (per-route)

API middleware for token-based authentication. Parses `Authorization: Bearer <token>`, verifies the JWT, and stores the decoded payload in `JwtRegistry` for downstream controllers.

```php
['GET', '/api/me', [ApiController::class, 'me'], [VerifyJwtToken::class]],
```

See [jwt-auth.md](jwt-auth.md) for the full JWT documentation.

## Writing custom middleware

Implement `Ornito\Middleware\MiddlewareInterface`:

```php
declare(strict_types=1);

namespace App\Middleware;

use Closure;
use Ornito\Http\Request;
use Ornito\Http\Response;
use Ornito\Middleware\MiddlewareInterface;

final class LogRequest implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before: run something before the handler
        error_log("[{$request->method}] {$request->uri}");

        $response = $next($request);

        // After: run something after the handler
        error_log("Response status: {$response->status()}");

        return $response;
    }
}
```

Register it as global middleware in `src/Application.php`:

```php
$this->middleware = [
    StartSession::class,
    VerifyCsrfToken::class,
    LogRequest::class,        // ← added here
];
```

Or per-route:

```php
['GET', '/debug', [DebugController::class, 'index'], [LogRequest::class]],
```

## Middleware execution order

1. **Global middleware** runs first (outermost), in the order added to `Application::$middleware`.
2. **Per-route middleware** runs next, in the order specified in the route definition.
3. **Handler** runs last (innermost).

This means global middleware like `StartSession` runs before per-route middleware like `Authenticate` — which is correct, because authentication depends on the session being started.
