<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\GRPC;

use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\Tests\ConsoleTestCase;

final class ListCommandTest extends ConsoleTestCase
{
    public function testListEmpty()
    {
        $locator = $this->mockContainer(LocatorInterface::class);
        $locator->shouldReceive('getServices')->andReturn([]);

        $result = $this->runCommand('grpc:services');

        $this->assertStringContainsString(
            'No GRPC services',
            $result
        );
    }

    public function testListAvailableServices()
    {
        $this->generateGRPCService();

        $result = $this->runCommand('grpc:services');

        $this->assertStringContainsString(
            'service.Echo',
            $result
        );

        $this->assertStringContainsString(
            'Spiral\App\GRPC\EchoService',
            $result
        );

        $this->assertStringContainsString(
            'App/GRPC/EchoService.php',
            $result
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteGRPCService();
    }
}
