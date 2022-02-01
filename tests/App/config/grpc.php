<?php

declare(strict_types=1);

return [
    /**
     * Path to protoc-gen-php-grpc library.
     * Default: null
     */
    'binaryPath' => directory('app').'../protoc-gen-php-grpc',

    'services' => [
        directory('app').'proto/echo.proto',
        directory('app').'proto/foo.proto',
    ],
];
