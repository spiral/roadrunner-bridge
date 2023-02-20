<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Spiral\RoadRunnerBridge\Exception\DispatcherNotFoundException;
use Spiral\RoadRunnerBridge\FallbackDispatcher;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class FallbackDispatcherTest extends TestCase
{
    /**
     * @dataProvider canServeDataProvider
     */
    public function testCanServe(RoadRunnerMode $mode, bool $expected): void
    {
        $dispatcher = new FallbackDispatcher($mode);

        $this->assertSame($expected, $dispatcher->canServe());
    }

    /**
     * @dataProvider exceptionDataProvider
     */
    public function testException(RoadRunnerMode $mode, string $message): void
    {
        $this->expectException(DispatcherNotFoundException::class);
        $this->expectExceptionMessage($message);

        (new FallbackDispatcher($mode))->serve();
    }

    public function canServeDataProvider(): \Traversable
    {
        yield [RoadRunnerMode::Http, true];
        yield [RoadRunnerMode::Temporal, true];
        yield [RoadRunnerMode::Jobs, true];
        yield [RoadRunnerMode::Grpc, true];
        yield [RoadRunnerMode::Tcp, true];
        yield [RoadRunnerMode::Unknown, false];
    }

    public function exceptionDataProvider(): \Traversable
    {
        yield 'http' => [
            RoadRunnerMode::Http,
            'To use RoadRunner in `Http` mode, please register dispatcher `Spiral\RoadRunnerBridge\Http\Dispatcher`.',
        ];
        yield 'jobs' => [
            RoadRunnerMode::Jobs,
            'To use RoadRunner in `Jobs` mode, please register dispatcher `Spiral\RoadRunnerBridge\Queue\Dispatcher`.',
        ];
        yield 'grpc' => [
            RoadRunnerMode::Grpc,
            'To use RoadRunner in `Grpc` mode, please register dispatcher `Spiral\RoadRunnerBridge\GRPC\Dispatcher`.',
        ];
        yield 'tcp' => [
            RoadRunnerMode::Tcp,
            'To use RoadRunner in `Tcp` mode, please register dispatcher `Spiral\RoadRunnerBridge\Tcp\Dispatcher`.',
        ];
        yield 'temporal' => [
            RoadRunnerMode::Temporal,
            'To use Temporal with RoadRunner, please install `spiral/temporal-bridge` package.',
        ];
    }
}
