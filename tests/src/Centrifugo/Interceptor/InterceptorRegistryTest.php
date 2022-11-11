<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo\Interceptor;

use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\App\Centrifugo\TestInterceptor;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor\InterceptorRegistry;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor\RegistryInterface;
use Spiral\Tests\TestCase;

final class InterceptorRegistryTest extends TestCase
{
    public function testGetInterceptorFromObject(): void
    {
        $this->updateConfig('centrifugo.interceptors', [RequestType::Publish->value => new TestInterceptor()]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromFCQN(): void
    {
        $this->updateConfig('centrifugo.interceptors', [RequestType::Publish->value => TestInterceptor::class]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAlias(): void
    {
        $this->getContainer()->bind('alias', static fn () => new TestInterceptor());

        $this->updateConfig('centrifugo.interceptors', [RequestType::Publish->value => 'alias']);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAutowire(): void
    {
        $this->updateConfig('centrifugo.interceptors', [RequestType::Publish->value => new Autowire(TestInterceptor::class)]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    /**
     * @dataProvider interceptorsDataProvider
     */
    public function testRegister(Autowire|CoreInterceptorInterface|string $interceptor): void
    {
        $this->getContainer()->bind('alias', new TestInterceptor());

        $registry = new InterceptorRegistry([], $this->getContainer(), $this->getContainer());

        $this->assertSame([], $registry->getInterceptors(RequestType::Publish));

        $registry->register(RequestType::Publish, $interceptor);

        $this->assertEquals([new TestInterceptor()], $registry->getInterceptors(RequestType::Publish));
    }

    public function interceptorsDataProvider(): \Traversable
    {
        yield [new Autowire(TestInterceptor::class)];
        yield [new TestInterceptor()];
        yield [TestInterceptor::class];
        yield ['alias'];
    }

    private function getInterceptor(): CoreInterceptorInterface
    {
        $interceptors = $this->getContainer()->get(RegistryInterface::class)->getInterceptors(RequestType::Publish);

        return \array_shift($interceptors);
    }
}
