<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Interceptor;

use Spiral\App\Tcp\TestInterceptor;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\RoadRunnerBridge\Tcp\Interceptor\LocatorInterface;
use Spiral\Tests\TestCase;

final class InterceptorLocatorTest extends TestCase
{
    public function testGetInterceptorFromObject(): void
    {
        $this->updateConfig('tcp.interceptors', [new TestInterceptor()]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromFCQN(): void
    {
        $this->updateConfig('tcp.interceptors', [TestInterceptor::class]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAlias(): void
    {
        $this->container->bind('alias', static fn () => new TestInterceptor());

        $this->updateConfig('tcp.interceptors', ['alias']);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAutowire(): void
    {
        $this->updateConfig('tcp.interceptors', [new Autowire(TestInterceptor::class)]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    private function getInterceptor(): CoreInterceptorInterface
    {
        $interceptors = $this->container->get(LocatorInterface::class)->getInterceptors();

        return \array_shift($interceptors);
    }
}
