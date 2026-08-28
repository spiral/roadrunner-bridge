<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Internal;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @internal
 */
#[DispatcherScope(scope: 'centrifugo')]
final readonly class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Centrifuge;
    }

    public function serve(): void
    {
        /** @var Server $server */
        $server = $this->container->get(Server::class);
        /** @var CentrifugoWorker $worker */
        $worker = $this->container->get(CentrifugoWorkerInterface::class);

        $server->serve($worker);
    }
}
