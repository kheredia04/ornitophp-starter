<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Ornito\Controller;
use Ornito\Http\Request;
use Ornito\Http\Response;
use Ornito\Security\LoginThrottle;
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
 * - Login attempts are throttled by LAYERS (LoginThrottle): account|ip,
 *   pure ip, pure account. A single "email|ip" key lets an attacker rotate
 *   emails for a fresh bucket; the ip and account layers cannot be rotated.
 * - Post-login redirects go through safeTarget(): the remembered URL is
 *   used only when it is a same-site absolute path (open-redirect guard).
 * - Login timing is equalized: password_verify() runs even when the account
 *   does not exist (fixed dummy hash), so response time cannot reveal which
 *   emails are registered.
 * - Registration probes are throttled per-IP under a dedicated "r:" bucket:
 *   the taken-email answer is observable by necessity, the volume is not.
 */
final class AuthController extends Controller
{
    /** Fallback landing spot when no (or an unsafe) intended URL is stored. */
    private const DEFAULT_TARGET = '/dashboard';

    /**
     * Fixed bcrypt hash (cost 12 — the PHP 8.4 password_hash() default)
     * verified against unknown accounts so login timing cannot reveal
     * registration state. Must match the cost the app uses: a cheaper dummy
     * would leak again.
     */
    private const DUMMY_HASH = '$2y$12$jMMelCcFZxPwK3.fzPVX3.mwjqNtLiAmTwOU2H478eV6wVppnRUFK';

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

        // Throttle BEFORE credential verification. Three independent buckets
        // are checked (see Ornito\Security\LoginThrottle): account|ip, pure
        // ip and pure account. Rotating emails or rotating IPs rotates the
        // first, but the ip and account buckets stay put — the attack that
        // dodges a single "email|ip" key cannot dodge all three.
        $throttle = LoginThrottle::fromConfig(
            $data['email'],
            $request->ip(),
            new RateLimiter(storage_path('ratelimit')),
            (array) config('auth.login.throttle', []),
        );

        if ($throttle->tooManyAttempts()) {
            Session::flash('error', sprintf(
                'Too many login attempts. Please try again in %d seconds.',
                $throttle->availableIn(),
            ));

            return Response::redirect('/login');
        }

        $throttle->hit();

        $user = User::findByEmail($data['email']);

        // Timing equalizer: password_verify() runs even when the account
        // does not exist. A short-circuit ($user === null || ...) would SKIP
        // the hash work for unknown emails, and the response-time difference
        // would tell attackers which addresses are registered. The dummy
        // hash costs the same as a real one, so both paths behave alike.
        $passwordHash = is_string($user['password_hash'] ?? null)
            ? $user['password_hash']
            : self::DUMMY_HASH;
        $passwordValid = password_verify($data['password'], $passwordHash);

        if ($user === null || !$passwordValid) {
            Session::flash('error', 'Invalid credentials.');
            Session::flash('email', $data['email']);

            return Response::redirect('/login');
        }

        // Successful authentication: forget the per-account failure history
        // (account|ip and account buckets). The pure ip bucket persists on
        // purpose — see LoginThrottle for the reasoning.
        $throttle->clear();

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

        // Registration may reveal that an email exists — the uniqueness claim
        // makes taken/taken-not observable by necessity. The VOLUME is
        // controlled here: probes are capped per-IP under a dedicated "r:"
        // bucket, so /register hammering never shares (or mutates) the login
        // counters and vice versa. Per-email buckets would be rotatable, and
        // there is no account to throttle before it exists.
        $registerThrottle = self::registerThrottle($request);

        if ($registerThrottle->tooManyAttempts()) {
            Session::flash('error', sprintf(
                'Too many registration attempts. Please try again in %d seconds.',
                $registerThrottle->availableIn(),
            ));
            Session::flash('name', $data['name']);
            Session::flash('email', $data['email']);

            return Response::redirect('/register');
        }

        $registerThrottle->hit();

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
     * Per-IP registration throttle. The dedicated "r:" bucket keeps this
     * counter separate from the login-shaped keys, and successful auths
     * never clear it (see LoginThrottle::clear()).
     */
    private static function registerThrottle(Request $request): LoginThrottle
    {
        $ip = (array) (config('auth.register.throttle', [])['ip'] ?? []);

        if (($ip['enabled'] ?? true) === false) {
            return LoginThrottle::fromBuckets(new RateLimiter(storage_path('ratelimit')), []);
        }

        return LoginThrottle::fromBuckets(
            new RateLimiter(storage_path('ratelimit')),
            [
                [
                    'key' => sprintf('r:%s', $request->ip()),
                    'max_attempts' => (int) ($ip['max_attempts'] ?? 20),
                    'decay_seconds' => (int) ($ip['decay_seconds'] ?? 3600),
                ],
            ],
        );
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
