<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Mockery as m;
use Psr\Http\Message\ResponseFactoryInterface;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Broadcast\Broadcast;
use Spiral\RoadRunner\Broadcast\BroadcastInterface;
use Spiral\RoadRunnerBridge\Broadcasting\RoadRunnerGuard;
use Spiral\Tests\TestCase;

final class BroadcastingBootloaderTest extends TestCase
{
    public function testBroadcastInterfaceBinding(): void
    {
        $this->container->bind(RPCInterface::class, $rpc = m::mock(RPCInterface::class));
        $rpc->shouldReceive('withCodec')->andReturnSelf();
        $rpc->shouldReceive('call')->once()->with('informer.List', true)->andReturn(['websockets']);

        $this->assertContainerBoundAsSingleton(
            BroadcastInterface::class,
            Broadcast::class
        );
    }

    public function testRoadRunnerGuardBinding(): void
    {
        $this->container->bind(
            ResponseFactoryInterface::class,
            m::mock(ResponseFactoryInterface::class)
        );

        $this->assertContainerBoundAsSingleton(
            RoadRunnerGuard::class,
            RoadRunnerGuard::class
        );
    }
}
