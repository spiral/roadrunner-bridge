<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Interceptor;

use Spiral\App\Tcp\TestInterceptor;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\RoadRunnerBridge\Tcp\Interceptor\RegistryInterface;
use Spiral\Tests\TestCase;

final class InterceptorRegistryTest extends TestCase
{
    public function testGetInterceptorFromObject(): void
    {
        $this->updateConfig('tcp.interceptors', ['server' => new TestInterceptor()]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromFCQN(): void
    {
        $this->updateConfig('tcp.interceptors', ['server' => TestInterceptor::class]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAlias(): void
    {
        $this->getContainer()->bind('alias', static fn () => new TestInterceptor());

        $this->updateConfig('tcp.interceptors', ['server' => 'alias']);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    public function testGetInterceptorFromAutowire(): void
    {
        $this->updateConfig('tcp.interceptors', ['server' => new Autowire(TestInterceptor::class)]);

        $this->assertInstanceOf(CoreInterceptorInterface::class, $this->getInterceptor());
    }

    private function getInterceptor(): CoreInterceptorInterface
    {
        $interceptors = $this->getContainer()->get(RegistryInterface::class)->getInterceptors('server');

        return \array_shift($interceptors);
    }
}
