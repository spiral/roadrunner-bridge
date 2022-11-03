<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use RoadRunner\Centrifugo\RequestInterface;

interface ErrorHandlerInterface
{
    public function handle(RequestInterface $request, \Throwable $e): void;
}