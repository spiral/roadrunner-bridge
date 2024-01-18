<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Psr\Container\ContainerInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Framework\ScopeName;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

#[DispatcherScope(scope: ScopeName::Grpc)]
final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
    ) {
    }

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Grpc;
    }

    public function serve(): void
    {
        /** @var Server $server */
        $server = $this->container->get(Server::class);
        /** @var WorkerInterface $worker */
        $worker = $this->container->get(WorkerInterface::class);
        /** @var LocatorInterface $locator */
        $locator = $this->container->get(LocatorInterface::class);

        foreach ($locator->getServices() as $interface => $service) {
            $server->registerService($interface, $service);
        }

        $server->serve(
            $worker,
            function (\Throwable $e = null): void {
                if ($e !== null) {
                    $this->handleException($e);
                }

                $this->finalizer->finalize(false);
            }
        );
    }

    private function handleException(\Throwable $e): void
    {
        try {
            $this->container->get(ExceptionReporterInterface::class)->report($e);
        } catch (\Throwable) {
            // no need to notify when unable to register an exception
        }
    }
}
