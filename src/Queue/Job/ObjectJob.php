<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Job;

use Spiral\Core\Container;
use Spiral\Queue\HandlerInterface;

final class ObjectJob implements HandlerInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function handle(string $name, string $id, array $payload): void
    {
        $job = $payload['object'];
        $handler = new \ReflectionClass($job);

        $method = $handler->getMethod(
            method_exists($handler, 'handle') ? 'handle' : '__invoke'
        );

        $args = $this->container->resolveArguments($method);

        $method->invokeArgs($job, $args);
    }
}
