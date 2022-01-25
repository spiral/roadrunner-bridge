<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Http;

interface ErrorHandlerInterface
{
    public function handle(\Throwable $e): void;
}
