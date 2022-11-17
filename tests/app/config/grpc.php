<?php

declare(strict_types=1);

return [
    /**
     * Path to protoc-gen-php-grpc library.
     * Default: null
     */
    'binaryPath' => directory('app') . '../protoc-gen-php-grpc',

    'generatedPath' => null,
    'namespace' => null,
    'servicesBasePath' => directory('app') . 'proto',

    'services' => [
        directory('app') . 'proto/echo.proto',
        directory('app') . 'proto/foo.proto',
    ],
];
