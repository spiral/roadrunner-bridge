<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Http;

use Psr\Container\ContainerInterface;
use Spiral\Snapshots\SnapshotInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

final class LogErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function handle(\Throwable $e): void
    {
        /** @var SnapshotInterface $snapshot */
        $snapshot = $this->container->get(ExceptionReporterInterface::class)->report($e);
        \file_put_contents('php://stderr', $snapshot->getMessage());
    }
}
