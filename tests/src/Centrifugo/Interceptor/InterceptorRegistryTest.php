<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo\Interceptor;

use Spiral\App\Centrifugo\OtherInterceptor;
use Spiral\App\Centrifugo\TestInterceptor;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Exception\ConfigurationException;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\InterceptorRegistry;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

final class InterceptorRegistryTest extends TestCase
{
    public static function interceptorsDataProvider(): \Traversable
    {
        yield [new Autowire(TestInterceptor::class)];
        yield [new TestInterceptor()];
        yield [TestInterceptor::class];
        yield ['alias'];
    }

    #[Test]
    public function getInterceptorFromObject(): void
    {
        $this->updateConfig('centrifugo.interceptors', ['publish' => new TestInterceptor()]);

        Assert::instanceOf($this->getInterceptor(), TestInterceptor::class);
    }

    #[Test]
    public function getInterceptorFromFCQN(): void
    {
        $this->updateConfig('centrifugo.interceptors', ['publish' => TestInterceptor::class]);

        Assert::instanceOf($this->getInterceptor(), TestInterceptor::class);
    }

    #[Test]
    public function getInterceptorFromAlias(): void
    {
        $this->getContainer()->bind('alias', static fn() => new TestInterceptor());

        $this->updateConfig('centrifugo.interceptors', ['publish' => 'alias']);

        Assert::instanceOf($this->getInterceptor(), TestInterceptor::class);
    }

    #[Test]
    public function getInterceptorFromAutowire(): void
    {
        $this->updateConfig('centrifugo.interceptors', ['publish' => new Autowire(TestInterceptor::class)]);

        Assert::instanceOf($this->getInterceptor(), TestInterceptor::class);
    }

    #[Test]
    public function getInterceptorsForAllServices(): void
    {
        $interceptors = [
            new TestInterceptor(),
            new OtherInterceptor(),
        ];

        $this->updateConfig('centrifugo.interceptors', ['*' => $interceptors]);

        $registry = $this->getContainer()->get(RegistryInterface::class);

        Assert::equals($registry->getInterceptors('publish'), $interceptors);
        Assert::equals($registry->getInterceptors('connect'), $interceptors);
    }

    #[Test]
    public function interceptorsForAllServicesShouldBeFirst(): void
    {
        $this->updateConfig('centrifugo.interceptors', [
            '*' => new TestInterceptor(),
            'publish' => new OtherInterceptor(),
        ]);

        $registry = $this->getContainer()->get(RegistryInterface::class);

        Assert::equals($registry->getInterceptors('publish'), [new TestInterceptor(), new OtherInterceptor()]);
    }

    #[DataProvider('interceptorsDataProvider')]
    #[Test]
    public function register(Autowire|CoreInterceptorInterface|string $interceptor): void
    {
        $this->getContainer()->bind('alias', new TestInterceptor());

        $registry = new InterceptorRegistry([], $this->getContainer(), $this->getContainer());

        Assert::same($registry->getInterceptors('publish'), []);

        $registry->register('publish', $interceptor);

        Assert::equals($registry->getInterceptors('publish'), [new TestInterceptor()]);
    }

    #[Test]
    public function registerWrongType(): void
    {
        $registry = new InterceptorRegistry([], $this->getContainer(), $this->getContainer());

        Expect::exception(ConfigurationException::class)->withMessageContaining('The $type value must be one of the `*`, `connect`, `refresh`, `subrefresh`, `publish`, `subscribe`, `rpc`, `invalid` values.');
        $registry->register('foo', new TestInterceptor());
    }

    private function getInterceptor(): CoreInterceptorInterface
    {
        $interceptors = $this->getContainer()->get(RegistryInterface::class)->getInterceptors('publish');

        return \array_shift($interceptors);
    }
}
