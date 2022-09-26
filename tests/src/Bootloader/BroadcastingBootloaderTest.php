<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Psr\Http\Message\ResponseFactoryInterface;
use Spiral\RoadRunner\Broadcast\Broadcast;
use Spiral\RoadRunner\Broadcast\BroadcastInterface;
use Spiral\RoadRunnerBridge\Broadcasting\RoadRunnerGuard;
use Spiral\Tests\TestCase;

final class BroadcastingBootloaderTest extends TestCase
{
    public function testBroadcastInterfaceBinding(): void
    {
        $this->assertContainerBoundAsSingleton(
            BroadcastInterface::class,
            Broadcast::class
        );
    }

    public function testRoadRunnerGuardBinding(): void
    {
        $this->mockContainer(ResponseFactoryInterface::class);


        $this->assertContainerBoundAsSingleton(
            RoadRunnerGuard::class,
            RoadRunnerGuard::class
        );
    }
}
