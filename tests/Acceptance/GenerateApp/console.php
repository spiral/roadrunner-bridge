<?php

declare(strict_types=1);

/**
 * The console of an application rooted in `GENERATE_ROOT`: its `config/grpc.php` drives `grpc:generate`.
 */

use Spiral\Tests\Acceptance\GenerateApp\ConsoleKernel;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

$root = (string) \getenv('GENERATE_ROOT');
\is_dir($root) or throw new \RuntimeException('Set GENERATE_ROOT to the application root.');

$app = ConsoleKernel::create(
    directories: [
        'root' => $root,
        'app' => $root . '/app/',
        'config' => $root . '/config/',
        'runtime' => $root . '/runtime/',
    ],
)->run();

# Null when the kernel failed to boot: it has already printed the error.
exit($app === null ? 1 : (int) $app->serve());
