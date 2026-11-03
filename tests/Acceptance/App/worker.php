<?php

declare(strict_types=1);

use Spiral\Tests\Acceptance\App\Kernel;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

# STDOUT carries the goridge frames: a PHP warning printed there kills the worker.
\ini_set('display_errors', 'stderr');

$app = Kernel::create(
    directories: [
        'root' => __DIR__,
        'app' => __DIR__,
        'runtime' => \getenv('ACCEPTANCE_RUNTIME') . '/',
    ],
    handleErrors: false,
)->run();

$app?->serve();
