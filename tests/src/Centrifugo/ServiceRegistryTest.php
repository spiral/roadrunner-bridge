<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\App\Centrifugo\TestService;
use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\ServiceRegistry;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

final class ServiceRegistryTest extends TestCase
{
    public static function servicesDataProvider(): \Traversable
    {
        yield [new Autowire(TestService::class)];
        yield [new TestService()];
        yield [TestService::class];
        yield ['alias'];
    }

    #[Test]
    public function getServiceFromObject(): void
    {
        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => new TestService()]);

        Assert::instanceOf($this->getService(), TestService::class);
    }

    #[Test]
    public function getServiceFromFCQN(): void
    {
        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => TestService::class]);

        Assert::instanceOf($this->getService(), TestService::class);
    }

    #[Test]
    public function getServiceFromAlias(): void
    {
        $this->getContainer()->bind('alias', static fn() => new TestService());

        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => 'alias']);

        Assert::instanceOf($this->getService(), TestService::class);
    }

    #[Test]
    public function getServiceFromAutowire(): void
    {
        $this->updateConfig('centrifugo.services', [RequestType::Publish->value => new Autowire(TestService::class)]);

        Assert::instanceOf($this->getService(), TestService::class);
    }

    #[DataProvider('servicesDataProvider')]
    #[Test]
    public function register(Autowire|ServiceInterface|string $service): void
    {
        $this->getContainer()->bind('alias', new TestService());

        $registry = new ServiceRegistry([], $this->getContainer(), $this->getContainer());

        Assert::null($registry->getService(RequestType::Publish));

        $registry->register(RequestType::Publish, $service);

        Assert::equals($registry->getService(RequestType::Publish), new TestService());
    }

    private function getService(): ServiceInterface
    {
        return $this->getContainer()->get(RegistryInterface::class)->getService(RequestType::Publish);
    }
}
