<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Psr\Container\ContainerInterface;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\Snapshots\SnapshotterInterface;

final class Dispatcher implements DispatcherInterface
{
    private EnvironmentInterface $env;
    private ContainerInterface $container;
    private FinalizerInterface $finalizer;

    public function __construct(
        EnvironmentInterface $env,
        ContainerInterface $container,
        FinalizerInterface $finalizer
    ) {
        $this->env = $env;
        $this->container = $container;
        $this->finalizer = $finalizer;
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->env->getMode() === Mode::MODE_GRPC;
    }

    public function serve()
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

    private function handleException(\Throwable $e)
    {
        try {
            $this->container->get(SnapshotterInterface::class)->register($e);
        } catch (\Throwable $se) {
            // no need to notify when unable to register an exception
        }
    }
}
