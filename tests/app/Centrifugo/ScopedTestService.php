<?php

declare(strict_types=1);

namespace Spiral\App\Centrifugo;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\Core\Attribute\Scope;
use Spiral\Core\Internal\Introspector;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

#[Scope('centrifugo-request')]
final class ScopedTestService implements ServiceInterface
{
    public static array $scopes = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function handle(RequestInterface $request): void
    {
        self::$scopes = Introspector::scopeNames($this->container);
    }
}
