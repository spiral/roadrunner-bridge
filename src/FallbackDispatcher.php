<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge;

use Spiral\Boot\DispatcherInterface;
use Spiral\RoadRunnerBridge\Exception\DispatcherNotFoundException;
use Spiral\RoadRunnerBridge\Centrifugo\Dispatcher as Centrifugo;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher as GRPC;
use Spiral\RoadRunnerBridge\Http\Dispatcher as Http;
use Spiral\RoadRunnerBridge\Queue\Dispatcher as Queue;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher as Tcp;

final class FallbackDispatcher implements DispatcherInterface
{
    private const ERROR = 'To use RoadRunner in `%s` mode, please register dispatcher `%s`.';

    public function __construct(
        private readonly RoadRunnerMode $mode,
    ) {
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->mode !== RoadRunnerMode::Unknown;
    }

    public function serve(): void
    {
        match ($this->mode) {
            RoadRunnerMode::Http => $this->throwException(Http::class),
            RoadRunnerMode::Jobs => $this->throwException(Queue::class),
            RoadRunnerMode::Grpc => $this->throwException(GRPC::class),
            RoadRunnerMode::Tcp => $this->throwException(Tcp::class),
            RoadRunnerMode::Centrifuge => $this->throwException(Centrifugo::class),
            RoadRunnerMode::Temporal => throw new DispatcherNotFoundException(
                'To use Temporal with RoadRunner, please install `spiral/temporal-bridge` package.'
            ),
            RoadRunnerMode::Unknown => null,
        };
    }

    /**
     * @param class-string<DispatcherInterface> $class
     * @throws DispatcherNotFoundException
     */
    private function throwException(string $class): void
    {
        throw new DispatcherNotFoundException(\sprintf(self::ERROR, $this->mode->name, $class));
    }
}
