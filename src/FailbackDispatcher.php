<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge;

use Spiral\Boot\DispatcherInterface;
use Spiral\RoadRunnerBridge\Exception\DispatcherNotFoundException as Exception;
use Spiral\RoadRunnerBridge\Centrifugo\Dispatcher as Centrifugo;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher as GRPC;
use Spiral\RoadRunnerBridge\Http\Dispatcher as Http;
use Spiral\RoadRunnerBridge\Queue\Dispatcher as Queue;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher as Tcp;

final class FailbackDispatcher implements DispatcherInterface
{
    private const ERROR = 'To use RoadRunner in `%s` mode, please register dispatcher `%s`.';

    public function __construct(
        private readonly RoadRunnerMode $mode
    ) {
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->mode !== RoadRunnerMode::Unknown;
    }

    public function serve(): void
    {
        match ($this->mode) {
            RoadRunnerMode::Http => throw new Exception(\sprintf(self::ERROR, $this->mode->name, Http::class)),
            RoadRunnerMode::Jobs => throw new Exception(\sprintf(self::ERROR, $this->mode->name, Queue::class)),
            RoadRunnerMode::Grpc => throw new Exception(\sprintf(self::ERROR, $this->mode->name, GRPC::class)),
            RoadRunnerMode::Tcp => throw new Exception(\sprintf(self::ERROR, $this->mode->name, Tcp::class)),
            RoadRunnerMode::Centrifuge => throw new Exception(
                \sprintf(self::ERROR, $this->mode->name, Centrifugo::class)
            ),
            RoadRunnerMode::Temporal => throw new Exception(
                'To use Temporal with RoadRunner, please install the package `spiral/temporal-bridge`.'
            ),
            RoadRunnerMode::Unknown => null
        };
    }
}
