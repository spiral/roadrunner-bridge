<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Psr\Container\ContainerInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\RoadRunnerBridge\Exception\ServiceRegistrationException;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @internal
 */
#[DispatcherScope(scope: 'grpc')]
final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        #[Proxy] private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
    ) {}

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Grpc;
    }

    public function serve(): void
    {
        $container = $this->container->get(ContainerInterface::class);

        /** @var Server $server */
        $server = $container->get(Server::class);
        /** @var WorkerInterface $worker */
        $worker = $container->get(WorkerInterface::class);
        /** @var LocatorInterface $locator */
        $locator = $container->get(LocatorInterface::class);

        foreach ($locator->getServices() as $interface => $service) {
            try {
                $server->registerService($interface, $container->get($service->getName()));
            } catch (\Throwable $e) {
                $this->handleException(new ServiceRegistrationException(
                    "Cannot register service `{$service->getName()}`: {$e->getMessage()}",
                    previous: $e,
                ));
            }
        }

        $server->serve(
            $worker,
            function (?\Throwable $e = null): void {
                if ($e !== null) {
                    $this->handleException($e);
                }

                $this->finalizer->finalize(false);
            },
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
