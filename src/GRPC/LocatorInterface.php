<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Spiral\RoadRunner\GRPC\ServiceInterface;

/**
 * The gRPC service locator.
 *
 * The bound implementation is registered as a tokenizer listener when it implements
 * {@see \Spiral\Tokenizer\TokenizationListenerInterface}; otherwise it has to know the services itself.
 *
 * @api
 */
interface LocatorInterface
{
    /**
     * Return list of available gRPC services in the form of [interface => object].
     *
     * @return array<class-string<ServiceInterface>, \ReflectionClass<ServiceInterface>>
     */
    public function getServices(): array;
}
