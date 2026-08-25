<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Middleware\Authenticate;

/**
 * Your route table. Add routes here.
 *
 * Each entry: [method, path, handler, middleware?]
 * handler: [ControllerClass::class, 'method']
 */
$routes = [
    ['GET', '/', [HomeController::class, 'index']],
];

// Auth module: opt-in via `php bin/ornito show:auth-module`.
if (auth_module_enabled()) {
    array_push(
        $routes,
        ['GET', '/login', [AuthController::class, 'showLogin']],
        ['POST', '/login', [AuthController::class, 'login']],
        ['GET', '/register', [AuthController::class, 'showRegister']],
        ['POST', '/register', [AuthController::class, 'register']],
        ['POST', '/logout', [AuthController::class, 'logout']],
        ['GET', '/dashboard', [HomeController::class, 'dashboard'], [Authenticate::class]],
    );
}

return $routes;
