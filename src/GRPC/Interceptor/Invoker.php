<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Interceptor;

use Spiral\Core\CoreInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

/**
 * @internal
 */
final class Invoker implements InvokerInterface
{
    public function __construct(
        private readonly CoreInterface $core
    ) {
    }

    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        return $this->core->callAction($service::class, $method->getName(), [
            'service' => $service,
            'method' => $method,
            'ctx' => $ctx,
            'input' => $input,
        ]);
    }
}
