# JWT Authentication

OrnitoPHP ships with JWT (JSON Web Token) support via `firebase/php-jwt`. This is the API counterpart to session-based authentication — stateless, no cookies, no server-side state.

## How it works

1. Client sends credentials to `/api/login`.
2. Server verifies credentials, issues a signed JWT.
3. Client includes the token in every subsequent request: `Authorization: Bearer <token>`.
4. `VerifyJwtToken` middleware validates the token and makes the payload available to controllers.

## Configuration

Set the signing secret in `.env`:

```env
JWT_SECRET=your-secret-key-here-at-least-32-bytes
```

Initialize the secret in `Application::boot()` (or in a service provider):

```php
use Ornito\Security\Jwt;

Jwt::setSecret((string) config('auth.jwt.secret'));
```

If no secret is configured, a random one is generated per request — tokens will NOT verify across requests. This is a safe dev default.

## The Jwt helper

### Issue a token

```php
use Ornito\Security\Jwt;

$token = Jwt::issue(
    userId: 42,
    data: ['role' => 'admin', 'email' => 'pato@ornitophp.dev'],
    expiry: 3600,  // optional, defaults to 1 hour
);
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$userId` | `int` | — | Subject claim — the authenticated user's id |
| `$data` | `array` | `[]` | Extra claims carried inside the token |
| `$expiry` | `int\|null` | `3600` | Seconds until expiry |

### Verify a token

```php
try {
    $payload = Jwt::verify($token);

    $userId = Jwt::getUserId($payload);   // int
    $data = Jwt::getData($payload);       // array
} catch (\InvalidArgumentException $e) {
    // Invalid or tampered token
} catch (\RuntimeException $e) {
    // Expired token
}
```

## The VerifyJwtToken middleware

Apply it to API routes that require authentication:

```php
// routes/api.php
['GET', '/api/me', [ApiController::class, 'me'], [VerifyJwtToken::class]],
```

The middleware:
1. Parses `Authorization: Bearer <token>` from the request header.
2. Verifies the token via `Jwt::verify()`.
3. Stores the decoded payload in `JwtRegistry`.
4. Returns 401 JSON on failure — the handler never runs.

## JwtRegistry

A static registry that stores the decoded JWT payload for downstream controllers:

```php
use Ornito\Middleware\JwtRegistry;

// In a controller after VerifyJwtToken middleware:
$userId = JwtRegistry::userId();    // int or null
$data = JwtRegistry::data();        // array
$payload = JwtRegistry::payload();  // full object or null
```

Clear state at the end of a request or in tests:

```php
JwtRegistry::clear();
```

## Full example: API login

```php
// routes/api.php
['POST', '/api/login', [AuthApiController::class, 'login']],
```

```php
declare(strict_types=1);

namespace App\Controllers;

use Ornito\Http\Request;
use Ornito\Http\Response;
use Ornito\Security\Jwt;
use App\Models\User;

final class AuthApiController
{
    public function login(Request $request): Response
    {
        $email = $request->body['email'] ?? '';
        $password = $request->body['password'] ?? '';

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return Response::json(['error' => 'Invalid credentials.'], 401);
        }

        $token = Jwt::issue(
            userId: (int) $user['id'],
            data: ['email' => $user['email']],
        );

        return Response::json(['token' => $token]);
    }
}
```

## Full example: protected API route

```php
// routes/api.php
['GET', '/api/me', [ApiController::class, 'me'], [VerifyJwtToken::class]],
```

```php
use Ornito\Middleware\JwtRegistry;

final class ApiController
{
    public function me(Request $request): Response
    {
        $userId = JwtRegistry::userId();

        $user = User::find($userId);

        if (!$user) {
            return Response::json(['error' => 'User not found.'], 404);
        }

        return Response::json([
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ]);
    }
}
```

## Security notes

- **HMAC-SHA256** (symmetric) is used by default — one shared secret for sign and verify. Asymmetric keys (RSA/ECDSA) are supported by `firebase/php-jwt` but add setup complexity.
- Tokens carry a `sub` claim (user id), `iat` (issued at), and `exp` (expiry). Custom data goes in the `data` bag.
- **Never hard-code the secret** — always read from `.env` via `config()`.
- The secret must be at least 32 bytes for HS256. Shorter keys throw `DomainException`.
