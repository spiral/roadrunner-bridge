<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Http;

use Psr\Container\ContainerInterface;
use Spiral\Snapshots\SnapshotInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

final class LogErrorHandler implements ErrorHandlerInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function handle(\Throwable $e): void
    {
        /** @var SnapshotInterface $snapshot */
        $snapshot = $this->container->get(ExceptionReporterInterface::class)->report($e);
        \file_put_contents('php://stderr', $snapshot->getMessage());
    }
}
