<?php

declare(strict_types=1);

use App\Controllers\HomeController;

/**
 * Your route table. Add routes here.
 *
 * Each entry: [method, path, handler, middleware?]
 * handler: [ControllerClass::class, 'method']
 */
return [
    ['GET', '/', [HomeController::class, 'index']],
];
