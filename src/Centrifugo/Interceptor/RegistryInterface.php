<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Interceptor;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;

/**
 * @psalm-type TInterceptor = Autowire|CoreInterceptorInterface|class-string<CoreInterceptorInterface>
 */
interface RegistryInterface
{
    /**
     * @param non-empty-string $type
     *
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(string $type): array;

    /**
     * @param non-empty-string $type
     * @param TInterceptor $interceptor
     */
    public function register(string $type, Autowire|CoreInterceptorInterface|string $interceptor): void;
}
