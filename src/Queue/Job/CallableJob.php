<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Job;

use Spiral\Core\Container;
use Spiral\Queue\HandlerInterface;

final class CallableJob implements HandlerInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function handle(string $name, string $id, array $payload): void
    {
        /** @var \Closure $callback */
        $callback = $payload['callback'];

        $reflection = new \ReflectionFunction($callback);

        $reflection->invokeArgs(
            $this->container->resolveArguments($reflection)
        );
    }
}
