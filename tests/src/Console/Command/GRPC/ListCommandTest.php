<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\GRPC;

use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\Tests\ConsoleTestCase;

final class ListCommandTest extends ConsoleTestCase
{
    public function testListEmpty(): void
    {
        $locator = $this->mockContainer(LocatorInterface::class);
        $locator->shouldReceive('getServices')->andReturn([]);

        $result = $this->runCommand('grpc:services');

        $this->assertStringContainsString(
            'No GRPC services',
            $result,
        );
    }

    public function testListAvailableServices(): void
    {
        $this->generateGRPCService();

        $result = $this->runCommand('grpc:services');

        $this->assertStringContainsString(
            'service.Ping',
            $result,
        );

        $this->assertStringContainsString(
            'Spiral\App\GRPC\Ping\PingService',
            $result,
        );

        $this->assertStringContainsString(
            'PingService.php',
            $result,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteGRPCService();
    }
}
