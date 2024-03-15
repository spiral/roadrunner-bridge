<?php

declare(strict_types=1);

namespace Spiral\App\Tcp;

use Psr\Container\ContainerInterface;
use Spiral\Core\Attribute\Scope;
use Spiral\Core\Internal\Introspector;
use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

#[Scope('tcp.packet')]
final class ScopedTestService implements ServiceInterface
{
    public static array $scopes = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function handle(Request $request): ResponseInterface
    {
        self::$scopes = Introspector::scopeNames($this->container);

        return new RespondMessage($request->getBody(), true);
    }
}
