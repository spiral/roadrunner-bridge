<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Interceptor;

use Spiral\Core\CoreInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;

final class Core implements CoreInterface
{
    public function __construct(private readonly InvokerInterface $invoker)
    {
    }

    public function callAction(string $controller, string $action, array $parameters = []): string
    {
        return $this->invoker->invoke(
            $parameters['service'],
            $parameters['method'],
            $parameters['ctx'],
            $parameters['input']
        );
    }
}