<?php

declare(strict_types=1);

use Runearc\Tests\Support\Environment;

require dirname(__DIR__) . '/vendor/autoload.php';

Environment::start();
register_shutdown_function([Environment::class, 'stop']);
