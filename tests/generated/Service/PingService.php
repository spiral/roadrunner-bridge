<?php

declare(strict_types=1);

namespace Service;

use Psr\Container\ContainerInterface;
use Spiral\Core\Attribute\Scope;
use Spiral\Core\Internal\Introspector;
use Spiral\RoadRunner\GRPC\ContextInterface;

#[Scope('grpc.request')]
class PingService implements PingInterface
{
    public static array $scopes = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function Ping(ContextInterface $ctx, Message $in): Message
    {
        self::$scopes = Introspector::scopeNames($this->container);

        $out = new Message();

        return $out->setMsg('PONG');
    }
}
