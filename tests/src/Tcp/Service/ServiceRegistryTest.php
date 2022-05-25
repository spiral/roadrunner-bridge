<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Service;

use Spiral\App\Tcp\TestService;
use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Tcp\Service\Exception\NotFoundException;
use Spiral\RoadRunnerBridge\Tcp\Service\RegistryInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;
use Spiral\Tests\TestCase;

final class ServiceRegistryTest extends TestCase
{
    public function testGetServerFromObject(): void
    {
        $this->updateConfig('tcp.services', ['test' => new TestService()]);

        $this->assertInstanceOf(ServiceInterface::class, $this->getService('test'));
    }

    public function testGetServerFromFCQN(): void
    {
        $this->updateConfig('tcp.services', ['test' => TestService::class]);

        $this->assertInstanceOf(ServiceInterface::class, $this->getService('test'));
    }

    public function testGetServerFromAlias(): void
    {
        $this->getContainer()->bind('alias', static fn () => new TestService());

        $this->updateConfig('tcp.services', ['test' => 'alias']);

        $this->assertInstanceOf(ServiceInterface::class, $this->getService('test'));
    }

    public function testGetServerFromAutowire(): void
    {
        $this->updateConfig('tcp.services', ['test' => new Autowire(TestService::class)]);

        $this->assertInstanceOf(ServiceInterface::class, $this->getService('test'));
    }

    public function testGetNotExistenceServer(): void
    {
        $this->expectException(NotFoundException::class);
        $this->assertInstanceOf(ServiceInterface::class, $this->getService('bar'));
    }

    private function getService(string $server): ServiceInterface
    {
        return $this->getContainer()->get(RegistryInterface::class)->getService($server);
    }
}
