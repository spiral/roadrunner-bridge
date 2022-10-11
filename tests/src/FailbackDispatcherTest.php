<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Spiral\RoadRunnerBridge\Exception\DispatcherNotFoundException;
use Spiral\RoadRunnerBridge\FailbackDispatcher;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class FailbackDispatcherTest extends TestCase
{
    /**
     * @dataProvider canServeDataProvider
     */
    public function testCanServe(RoadRunnerMode $mode, bool $expected): void
    {
        $dispatcher = new FailbackDispatcher($mode);

        $this->assertSame($expected, $dispatcher->canServe());
    }

    /**
     * @dataProvider exceptionDataProvider
     */
    public function testException(RoadRunnerMode $mode): void
    {
        $dispatcher = new FailbackDispatcher($mode);

        $this->expectException(DispatcherNotFoundException::class);
        $dispatcher->serve();
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
        yield [RoadRunnerMode::Http];
        yield [RoadRunnerMode::Temporal];
        yield [RoadRunnerMode::Jobs];
        yield [RoadRunnerMode::Grpc];
        yield [RoadRunnerMode::Tcp];
    }
}
