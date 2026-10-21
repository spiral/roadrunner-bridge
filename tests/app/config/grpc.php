<?php

declare(strict_types=1);

// The test application root is `tests/`, while `vendor/bin/dload get` puts the binaries into the repository root.
$binary = static fn(string $name): string => \dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $name
    . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

return [
    'bufPath' => $binary('buf'),
    'protocPath' => $binary('protoc'),
    'pluginPath' => $binary('protoc-gen-php-grpc'),

    'generatedPath' => null,
    'namespace' => null,
    'servicesBasePath' => directory('app') . 'proto',

    'services' => [
        directory('app') . 'proto/service.proto',
        directory('app') . 'proto/foo.proto',
    ],
];
