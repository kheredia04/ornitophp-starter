<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Ornito\Controller;
use Ornito\Http\Request;
use Ornito\Http\Response;
use Ornito\Security\RateLimiter;
use Ornito\Session\Session;
use Ornito\Validation\Validator;

/**
 * Login, logout and self-registration flow.
 *
 * Security notes (each one closes a sprinf bug):
 * - password_verify() over the stored hash — never md5.
 * - Session::regenerate() on login — kills session fixation.
 * - The POST routes are protected by VerifyCsrfToken — no more
 *   cross-site form posts.
 * - Wrong email and wrong password produce the SAME generic message,
 *   so responses never leak which accounts exist.
 * - Login attempts are throttled per email+IP (RateLimiter) before
 *   credentials are ever checked, blunting online brute force.
 * - Post-login redirects go through safeTarget(): the remembered URL is
 *   used only when it is a same-site absolute path (open-redirect guard).
 */
final class AuthController extends Controller
{
    /** Fallback landing spot when no (or an unsafe) intended URL is stored. */
    private const DEFAULT_TARGET = '/dashboard';

    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', [
            'title' => 'Log in',
            'errors' => (array) Session::getFlash('errors', []),
            'errorMessage' => Session::getFlash('error'),
            'oldEmail' => (string) Session::getFlash('email', ''),
        ]);
    }

    public function login(Request $request): Response
    {
        $data = [
            'email' => (string) ($request->input('email') ?? ''),
            'password' => (string) ($request->input('password') ?? ''),
        ];

        $errors = Validator::validate($data, [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('email', $data['email']);

            return Response::redirect('/login');
        }

        // Throttle BEFORE credential verification: the counter key binds the
        // submitted email to the client IP, so rotating emails does not
        // rotate buckets and rotating IPs does not either.
        $key = sha1(strtolower($data['email']) . '|' . $request->ip());
        $limiter = new RateLimiter(storage_path('ratelimit'));
        $maxAttempts = (int) config('auth.login.max_attempts', 5);
        $decaySeconds = (int) config('auth.login.decay_seconds', 60);

        if ($limiter->tooManyAttempts($key, $maxAttempts)) {
            Session::flash('error', sprintf(
                'Too many login attempts. Please try again in %d seconds.',
                $limiter->availableIn($key),
            ));

            return Response::redirect('/login');
        }

        $limiter->hit($key, $decaySeconds);

        $user = User::findByEmail($data['email']);
        $passwordHash = is_string($user['password_hash'] ?? null) ? $user['password_hash'] : '';

        if ($user === null || !password_verify($data['password'], $passwordHash)) {
            Session::flash('error', 'Invalid credentials.');
            Session::flash('email', $data['email']);

            return Response::redirect('/login');
        }

        // Successful authentication: forget the failure history for this key.
        $limiter->clear($key);

        return self::startSessionFor($user);
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth/register', [
            'title' => 'Create account',
            'errors' => (array) Session::getFlash('errors', []),
            'errorMessage' => Session::getFlash('error'),
            'oldName' => (string) Session::getFlash('name', ''),
            'oldEmail' => (string) Session::getFlash('email', ''),
        ]);
    }

    public function register(Request $request): Response
    {
        $data = [
            'name' => trim((string) ($request->input('name') ?? '')),
            'email' => trim((string) ($request->input('email') ?? '')),
            'password' => (string) ($request->input('password') ?? ''),
            'password_confirmation' => (string) ($request->input('password_confirmation') ?? ''),
        ];

        $errors = Validator::validate($data, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:150',
            'password' => 'required|min:8',
        ]);

        // Confirmation compares TWO fields, so it cannot live in the
        // per-field rule engine — check it only once those rules passed.
        if ($errors === []) {
            if ($data['password'] !== $data['password_confirmation']) {
                $errors['password_confirmation'] = 'Password confirmation does not match.';
            }
        }

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('name', $data['name']);
            Session::flash('email', $data['email']);

            return Response::redirect('/register');
        }

        // Unlike login, registration may reveal that an email exists — a
        // uniqueness claim makes the taken/taken-not answer observable by
        // necessity (the visitor can just try to log in to confirm).
        if (User::findByEmail($data['email']) !== null) {
            Session::flash('errors', ['email' => 'That email address is already registered.']);
            Session::flash('name', $data['name']);
            Session::flash('email', $data['email']);

            return Response::redirect('/register');
        }

        $id = User::insert([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        // Registration signs the user straight in — no separate login step.
        $row = [
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        return self::startSessionFor($row);
    }

    public function logout(Request $request): Response
    {
        Session::destroy();

        return Response::redirect('/login');
    }

    /**
     * Shared tail of every successful authentication (login AND registration):
     * regenerate the session id (anti-fixation), store user_id/user from a
     * row-shaped array (id/name/email keys), then honor url.intended if the
     * Authenticate middleware remembered one and redirect through safeTarget().
     *
     * @param array<string, mixed> $user
     */
    private static function startSessionFor(array $user): Response
    {
        // New id for the now-authenticated session (anti-fixation).
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('user', [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ]);

        // Send the user where Authenticate caught them, else to the default.
        $intended = Session::get('url.intended');
        Session::remove('url.intended');

        return Response::redirect(self::safeTarget(is_string($intended) ? $intended : null));
    }

    /**
     * Open-redirect guard: honor the remembered URL ONLY when it is a
     * same-site absolute path ("/something"). Anything else — absolute URLs,
     * protocol-relative "//host", or "/\host" (browsers normalize backslashes
     * into slashes, turning it into "//host" and off we go to evil.com) —
     * falls back to the dashboard instead of leaving the site.
     */
    public static function safeTarget(?string $intended): string
    {
        if ($intended !== null && preg_match('~^/(?!/|\\\\)~', $intended) === 1) {
            return $intended;
        }

        return self::DEFAULT_TARGET;
    }
}
