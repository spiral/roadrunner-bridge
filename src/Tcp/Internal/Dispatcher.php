<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Internal;

use Psr\Container\ContainerInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

#[DispatcherScope(scope: 'tcp')]
final readonly class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Tcp;
    }

    public function serve(): void
    {
        /** @var Server $server */
        $server = $this->container->get(Server::class);
        /** @var WorkerInterface $worker */
        $worker = $this->container->get(WorkerInterface::class);

        $server->serve($worker);
    }
}
