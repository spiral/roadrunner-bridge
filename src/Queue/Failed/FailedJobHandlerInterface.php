<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Failed;

interface FailedJobHandlerInterface
{
    public function handle(string $connection, string $queue, string $job, array $payload, \Throwable $e): void;
}
