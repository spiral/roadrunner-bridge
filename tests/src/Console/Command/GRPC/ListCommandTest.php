<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\GRPC;

use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\Tests\ConsoleTestCase;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

final class ListCommandTest extends ConsoleTestCase
{
    #[Test]
    public function listEmpty(): void
    {
        $locator = $this->mockContainer(LocatorInterface::class);
        $locator->shouldReceive('getServices')->andReturn([]);

        $result = $this->runCommand('grpc:services');

        Assert::string($result)->contains('No GRPC services');
    }

    #[Test]
    public function listAvailableServices(): void
    {
        $this->generateGRPCService();

        $result = $this->runCommand('grpc:services');

        Assert::string($result)->contains('service.Ping')->contains('Spiral\App\GRPC\Ping\PingService')->contains('PingService.php');
    }

    #[AfterTest]
    protected function cleanUp(): void
    {
        $this->deleteGRPCService();
    }
}
