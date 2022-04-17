<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Service;

use Spiral\App\Tcp\TestService;
use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Tcp\Service\LocatorInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;
use Spiral\Tests\TestCase;

final class ServiceLocatorTest extends TestCase
{
    public function testGetServerFromFCQN(): void
    {
        $this->updateConfig('tcp.services', ['test' => TestService::class]);

        $this->assertInstanceOf(ServiceInterface::class, $this->getService('test'));
    }

    public function testGetServerFromAlias(): void
    {
        $this->container->bind('alias', static fn () => new TestService());

        $this->updateConfig('tcp.services', ['test' => 'alias']);

        $this->assertInstanceOf(ServiceInterface::class, $this->getService('test'));
    }

    public function testGetServerFromAutowire(): void
    {
        $this->updateConfig('tcp.services', ['test' => new Autowire(TestService::class)]);

        $this->assertInstanceOf(ServiceInterface::class, $this->getService('test'));
    }

    private function getService(string $server): ServiceInterface
    {
        return $this->container->get(LocatorInterface::class)->getService($server);
    }
}
