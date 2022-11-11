<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo\Interceptor;

use Spiral\App\Centrifugo\OtherInterceptor;
use Spiral\App\Centrifugo\TestInterceptor;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Exception\ConfigurationException;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor\InterceptorRegistry;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor\RegistryInterface;
use Spiral\Tests\TestCase;

final class InterceptorRegistryTest extends TestCase
{
    public function testGetInterceptorFromObject(): void
    {
        $this->updateConfig('centrifugo.interceptors', ['publish' => new TestInterceptor()]);

        $this->assertInstanceOf(TestInterceptor::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromFCQN(): void
    {
        $this->updateConfig('centrifugo.interceptors', ['publish' => TestInterceptor::class]);

        $this->assertInstanceOf(TestInterceptor::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAlias(): void
    {
        $this->getContainer()->bind('alias', static fn () => new TestInterceptor());

        $this->updateConfig('centrifugo.interceptors', ['publish' => 'alias']);

        $this->assertInstanceOf(TestInterceptor::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAutowire(): void
    {
        $this->updateConfig('centrifugo.interceptors', ['publish' => new Autowire(TestInterceptor::class)]);

        $this->assertInstanceOf(TestInterceptor::class, $this->getInterceptor());
    }

    public function testGetInterceptorsForAllServices(): void
    {
        $interceptors = [
            new TestInterceptor(),
            new OtherInterceptor()
        ];

        $this->updateConfig('centrifugo.interceptors', ['*' => $interceptors]);

        $registry = $this->getContainer()->get(RegistryInterface::class);

        $this->assertEquals($interceptors, $registry->getInterceptors('publish'));
        $this->assertEquals($interceptors, $registry->getInterceptors('connect'));
    }

    public function testInterceptorsForAllServicesShouldBeFirst(): void
    {
        $this->updateConfig('centrifugo.interceptors', [
            '*' => new TestInterceptor(),
            'publish' => new OtherInterceptor()
        ]);

        $registry = $this->getContainer()->get(RegistryInterface::class);

        $this->assertEquals([new TestInterceptor(), new OtherInterceptor()], $registry->getInterceptors('publish'));
    }

    /**
     * @dataProvider interceptorsDataProvider
     */
    public function testRegister(Autowire|CoreInterceptorInterface|string $interceptor): void
    {
        $this->getContainer()->bind('alias', new TestInterceptor());

        $registry = new InterceptorRegistry([], $this->getContainer(), $this->getContainer());

        $this->assertSame([], $registry->getInterceptors('publish'));

        $registry->register('publish', $interceptor);

        $this->assertEquals([new TestInterceptor()], $registry->getInterceptors('publish'));
    }

    public function testRegisterWrongType(): void
    {
        $registry = new InterceptorRegistry([], $this->getContainer(), $this->getContainer());

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'The $type value must be one of the `*`, `connect`, `refresh`, `publish`, `subscribe`, `rpc` values.'
        );
        $registry->register('foo', new TestInterceptor());
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
        $interceptors = $this->getContainer()->get(RegistryInterface::class)->getInterceptors('publish');

        return \array_shift($interceptors);
    }
}
