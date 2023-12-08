<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use PHPUnit\Framework\Attributes\DataProvider;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\App\Centrifugo\TestService;
use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceRegistry;
use Spiral\Tests\TestCase;

final class ServiceRegistryTest extends TestCase
{
    public function testGetServiceFromObject(): void
    {
        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => new TestService()]);

        $this->assertInstanceOf(TestService::class, $this->getService());
    }

    public function testGetServiceFromFCQN(): void
    {
        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => TestService::class]);

        $this->assertInstanceOf(TestService::class, $this->getService());
    }

    public function testGetServiceFromAlias(): void
    {
        $this->getContainer()->bind('alias', static fn () => new TestService());

        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => 'alias']);

        $this->assertInstanceOf(TestService::class, $this->getService());
    }

    public function testGetServiceFromAutowire(): void
    {
        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => new Autowire(TestService::class)]);

        $this->assertInstanceOf(TestService::class, $this->getService());
    }

    #[DataProvider('servicesDataProvider')]
    public function testRegister(Autowire|ServiceInterface|string $service): void
    {
        $this->getContainer()->bind('alias', new TestService());

        $registry = new ServiceRegistry([], $this->getContainer(), $this->getContainer());

        $this->assertNull($registry->getService(RequestType::Publish));

        $registry->register(RequestType::Publish, $service);

        $this->assertEquals(new TestService(), $registry->getService(RequestType::Publish));
    }

    public static function servicesDataProvider(): \Traversable
    {
        yield [new Autowire(TestService::class)];
        yield [new TestService()];
        yield [TestService::class];
        yield ['alias'];
    }

    private function getService(): ServiceInterface
    {
        return $this->getContainer()->get(RegistryInterface::class)->getService(RequestType::Publish);
    }
}
