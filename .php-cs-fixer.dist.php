<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

return \Spiral\CodeStyle\Builder::create()
    ->include(__DIR__ . '/src')
    ->include(__DIR__ . '/tests')
    ->exclude(__DIR__ . '/tests/generated')
    ->exclude(__DIR__ . '/tests/app/GRPC')
    ->include(__FILE__)
    ->cache('./runtime/php-cs-fixer.cache')
    ->allowRisky()
    ->build();
