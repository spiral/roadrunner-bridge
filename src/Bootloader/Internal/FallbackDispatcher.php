<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader\Internal;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DispatcherInterface;
use Spiral\RoadRunnerBridge\Bootloader\CentrifugoBootloader;
use Spiral\RoadRunnerBridge\Bootloader\GRPCBootloader;
use Spiral\RoadRunnerBridge\Bootloader\HttpBootloader;
use Spiral\RoadRunnerBridge\Bootloader\QueueBootloader;
use Spiral\RoadRunnerBridge\Exception\DispatcherNotFoundException;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @internal
 */
final readonly class FallbackDispatcher implements DispatcherInterface
{
    private const PLUGIN_ERROR = 'To use RoadRunner in `%s` mode, add `%s` to the bootloaders of the application kernel.';
    private const TEMPORAL_ERROR = 'To use Temporal with RoadRunner, please install `spiral/temporal-bridge` package.';

    public function __construct(
        private RoadRunnerMode $mode,
    ) {}

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode !== RoadRunnerMode::Unknown;
    }

    #[\Override]
    public function serve(): void
    {
        match ($this->mode) {
            RoadRunnerMode::Http => $this->throwException(HttpBootloader::class),
            RoadRunnerMode::Jobs => $this->throwException(QueueBootloader::class),
            RoadRunnerMode::Grpc => $this->throwException(GRPCBootloader::class),
            RoadRunnerMode::Centrifuge => $this->throwException(CentrifugoBootloader::class),
            RoadRunnerMode::Temporal => throw new DispatcherNotFoundException(self::TEMPORAL_ERROR),
            RoadRunnerMode::Unknown => null,
        };
    }

    /**
     * @param class-string<Bootloader> $bootloader The bridge bootloader that registers the dispatcher of the mode.
     *
     * @throws DispatcherNotFoundException
     */
    private function throwException(string $bootloader): never
    {
        throw new DispatcherNotFoundException(\sprintf(self::PLUGIN_ERROR, $this->mode->name, $bootloader));
    }
}
