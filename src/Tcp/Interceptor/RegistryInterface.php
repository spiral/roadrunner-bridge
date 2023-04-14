<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;

/**
 * @psalm-type TInterceptor = Autowire|CoreInterceptorInterface|class-string<CoreInterceptorInterface>
 */
interface RegistryInterface
{
    /**
     * @param non-empty-string $server
     *
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(string $server): array;

    /**
     * @param non-empty-string $server
     * @param TInterceptor $interceptor
     */
    public function register(string $server, Autowire|CoreInterceptorInterface|string $interceptor): void;
}
