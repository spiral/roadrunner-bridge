<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\App\Centrifugo\TestInterceptor;
use Spiral\App\Centrifugo\TestService;
use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Config\CentrifugoConfig;
use Spiral\Tests\TestCase;

final class CentrifugoConfigTest extends TestCase
{
    public function testGetServices(): void
    {
        $services = [
            'string-alias' => 'foo',
            'autowire' => new Autowire(TestService::class),
            'class-string' => TestService::class,
            'instance' => new TestService(),
        ];

        $config = new CentrifugoConfig(['services' => $services]);

        $this->assertEquals($services, $config->getServices());
    }

    public function testGetNotExistsServices(): void
    {
        $config = new CentrifugoConfig();

        $this->assertSame([], $config->getServices());
    }

    public function testGetInterceptors(): void
    {
        $interceptors = [
            'string-alias' => 'foo',
            'autowire' => new Autowire(TestInterceptor::class),
            'class-string' => TestInterceptor::class,
            'instance' => new TestInterceptor(),
        ];

        $config = new CentrifugoConfig(['interceptors' => $interceptors]);

        $this->assertEquals($interceptors, $config->getInterceptors());
    }

    public function testGetNotExistsInterceptors(): void
    {
        $config = new CentrifugoConfig();

        $this->assertSame([], $config->getInterceptors());
    }
}
