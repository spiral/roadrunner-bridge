<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\App\GRPC\Echo\EchoService;
use Spiral\RoadRunnerBridge\GRPC\ServiceLocator;
use Spiral\Tests\TestCase;

final class ServiceLocatorTest extends TestCase
{
    public function testGetsServices(): void
    {
        $locator = $this->container->get(ServiceLocator::class);

        $this->assertInstanceOf(
            EchoService::class,
            $locator->getServices()[\Spiral\App\GRPC\Echo\EchoInterface::class]
        );
    }
}
