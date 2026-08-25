<?php

declare(strict_types=1);

use Ornito\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'My OrnitoPHP App'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', false) === true,
    'style' => Env::get('APP_STYLE', 'app'),
];
