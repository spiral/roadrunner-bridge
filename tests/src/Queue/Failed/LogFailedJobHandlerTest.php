<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue\Failed;

use Mockery as m;
use Spiral\RoadRunnerBridge\Queue\Failed\LogFailedJobHandler;
use Spiral\Snapshots\SnapshotterInterface;
use Spiral\Tests\TestCase;

final class LogFailedJobHandlerTest extends TestCase
{
    public function testHandle()
    {
        $handler = new LogFailedJobHandler(
            $snapshotter = m::mock(SnapshotterInterface::class)
        );

        $e = new \Exception('Something went wrong');

        $snapshotter->shouldReceive('register')->once()->with($e);

        $handler->handle('foo', 'bar', 'baz', [], $e);
    }
}
