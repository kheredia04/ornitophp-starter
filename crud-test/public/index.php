<?php

declare(strict_types=1);

use Ornito\Application;

// ORNITO_ROOT tells the framework where the PROJECT lives (not vendor/).
define('ORNITO_ROOT', dirname(__DIR__));

require dirname(__DIR__) . '/vendor/autoload.php';

$application = new Application();
$application->boot();
$application->run();
