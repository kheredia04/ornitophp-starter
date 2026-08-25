<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use Ornito\Http\Request;
use Ornito\Http\Response;
use Ornito\Middleware\MiddlewareInterface;
use Ornito\Session\Session;

/**
 * Route middleware guarding authenticated-only pages: guests are bounced
 * to the login screen, everyone else flows through to the handler.
 *
 * Before bouncing, the guest's target (path + query string) is remembered in
 * the session as 'url.intended' so AuthController can return them there
 * after a successful login instead of a fixed default.
 */
final class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Session::get('user_id') === null) {
            Session::set('url.intended', self::fullUri($request));

            return Response::redirect('/login');
        }

        return $next($request);
    }

    /**
     * Path plus query string ('/dashboard', '/search?q=ornito&page=2').
     */
    private static function fullUri(Request $request): string
    {
        if ($request->query === []) {
            return $request->uri;
        }

        return $request->uri . '?' . http_build_query($request->query);
    }
}
