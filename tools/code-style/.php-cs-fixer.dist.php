<?php

declare(strict_types=1);

// Lives next to the isolated php-cs-fixer install in tools/code-style/; source paths reach the project root via ../../.
require_once __DIR__ . '/vendor/autoload.php';

// Protobuf-generated code stays as the compilers emit it.
return \Spiral\CodeStyle\Builder::create()
    ->include(__DIR__ . '/../../src')
    ->include(__DIR__ . '/../../tests')
    ->exclude(__DIR__ . '/../../tests/generated')
    ->exclude(__DIR__ . '/../../tests/app/GRPC/Ping/GPBMetadata')
    ->exclude(__DIR__ . '/../../tests/app/GRPC/Ping/PingRequest.php')
    ->exclude(__DIR__ . '/../../tests/app/GRPC/Ping/PingResponse.php')
    ->exclude(__DIR__ . '/../../tests/app/GRPC/Ping/PingServiceInterface.php')
    ->include(__FILE__)
    ->cache(__DIR__ . '/../../runtime/php-cs-fixer.cache')
    ->allowRisky(false)
    ->build();
