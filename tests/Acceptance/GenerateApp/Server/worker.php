<?php

declare(strict_types=1);

use Spiral\Tests\Acceptance\GenerateApp\Server\Kernel;

require \dirname(__DIR__, 4) . '/vendor/autoload.php';
require \dirname(__DIR__) . '/autoload.php';

$app = Kernel::create(
    directories: [
        'root' => __DIR__,
        'app' => __DIR__,
        'runtime' => \getenv('ACCEPTANCE_RUNTIME') . '/',
    ],
    handleErrors: false,
)->run();

$app?->serve();
