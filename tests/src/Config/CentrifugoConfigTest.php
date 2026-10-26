<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\App\Centrifugo\TestInterceptor;
use Spiral\App\Centrifugo\TestService;
use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Config\CentrifugoConfig;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class CentrifugoConfigTest extends TestCase
{
    #[Test]
    public function getServices(): void
    {
        $services = [
            'string-alias' => 'foo',
            'autowire' => new Autowire(TestService::class),
            'class-string' => TestService::class,
            'instance' => new TestService(),
        ];

        $config = new CentrifugoConfig(['services' => $services]);

        Assert::equals($config->getServices(), $services);
    }

    #[Test]
    public function getNotExistsServices(): void
    {
        $config = new CentrifugoConfig();

        Assert::same($config->getServices(), []);
    }

    #[Test]
    public function getInterceptors(): void
    {
        $interceptors = [
            'string-alias' => 'foo',
            'autowire' => new Autowire(TestInterceptor::class),
            'class-string' => TestInterceptor::class,
            'instance' => new TestInterceptor(),
        ];

        $config = new CentrifugoConfig(['interceptors' => $interceptors]);

        Assert::equals($config->getInterceptors(), $interceptors);
    }

    #[Test]
    public function getNotExistsInterceptors(): void
    {
        $config = new CentrifugoConfig();

        Assert::same($config->getInterceptors(), []);
    }
}
