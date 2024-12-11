<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\App\GRPC\Ping\PingService;
use Spiral\App\GRPC\Ping\PingServiceInterface;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\Tests\TestCase;

final class ServiceLocatorTest extends TestCase
{
    public function testGetsServices(): void
    {
        $locator = $this->getContainer()->get(LocatorInterface::class);

        $result = $locator->getServices()[PingServiceInterface::class] ?? null;
        $this->assertInstanceOf(\ReflectionClass::class, $result);
        $this->assertNotNull($result);
        $this->assertSame(PingService::class, $result->getName());
    }
}
