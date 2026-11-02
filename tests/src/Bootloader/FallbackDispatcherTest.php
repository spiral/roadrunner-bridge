<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Bootloader\Internal\FallbackDispatcher;
use Spiral\RoadRunnerBridge\Exception\DispatcherNotFoundException;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

final class FallbackDispatcherTest extends TestCase
{
    public static function canServeDataProvider(): \Traversable
    {
        yield [RoadRunnerMode::Http, true];
        yield [RoadRunnerMode::Temporal, true];
        yield [RoadRunnerMode::Jobs, true];
        yield [RoadRunnerMode::Grpc, true];
        yield [RoadRunnerMode::Unknown, false];
    }

    public static function exceptionDataProvider(): \Traversable
    {
        yield 'http' => [
            RoadRunnerMode::Http,
            'To use RoadRunner in `Http` mode, add `Spiral\RoadRunnerBridge\Bootloader\HttpBootloader` to the bootloaders of the application kernel.',
        ];
        yield 'jobs' => [
            RoadRunnerMode::Jobs,
            'To use RoadRunner in `Jobs` mode, add `Spiral\RoadRunnerBridge\Bootloader\QueueBootloader` to the bootloaders of the application kernel.',
        ];
        yield 'grpc' => [
            RoadRunnerMode::Grpc,
            'To use RoadRunner in `Grpc` mode, add `Spiral\RoadRunnerBridge\Bootloader\GRPCBootloader` to the bootloaders of the application kernel.',
        ];
        yield 'centrifuge' => [
            RoadRunnerMode::Centrifuge,
            'To use RoadRunner in `Centrifuge` mode, add `Spiral\RoadRunnerBridge\Bootloader\CentrifugoBootloader` to the bootloaders of the application kernel.',
        ];
        yield 'temporal' => [
            RoadRunnerMode::Temporal,
            'To use Temporal with RoadRunner, please install `spiral/temporal-bridge` package.',
        ];
    }

    #[DataProvider('canServeDataProvider')]
    #[Test]
    public function canServe(RoadRunnerMode $mode, bool $expected): void
    {
        Assert::same(FallbackDispatcher::canServe($mode), $expected);
    }

    #[DataProvider('exceptionDataProvider')]
    #[Test]
    public function exception(RoadRunnerMode $mode, string $message): void
    {
        Expect::exception(DispatcherNotFoundException::class)->withMessageContaining($message);

        (new FallbackDispatcher($mode))->serve();
    }
}
