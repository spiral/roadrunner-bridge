<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Spiral\RoadRunner\GRPC\ServiceInterface;

/**
 * The gRPC service locator.
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
