<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Interceptor;

use RoadRunner\Centrifugo\RequestType;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;

/**
 * @psalm-type TInterceptor = Autowire|CoreInterceptorInterface|class-string<CoreInterceptorInterface>
 */
interface RegistryInterface
{
    /**
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(RequestType $type): array;

    /**
     * @param TInterceptor $interceptor
     */
    public function register(RequestType $type, Autowire|CoreInterceptorInterface|string $interceptor): void;
}
