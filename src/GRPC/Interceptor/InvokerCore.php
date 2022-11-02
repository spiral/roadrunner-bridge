<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Interceptor;

use Spiral\Core\CoreInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

final class InvokerCore implements CoreInterface
{
    public function __construct(
        private readonly InvokerInterface $invoker
    ) {
    }

    public function callAction(string $controller, string $action, array $parameters = []): string
    {
        \assert($parameters['service'] instanceof ServiceInterface);
        \assert($parameters['method'] instanceof Method);
        \assert($parameters['ctx'] instanceof ContextInterface);
        \assert(\is_string($parameters['input']) || \is_null($parameters['input']));

        return $this->invoker->invoke(
            $parameters['service'],
            $parameters['method'],
            $parameters['ctx'],
            $parameters['input']
        );
    }
}