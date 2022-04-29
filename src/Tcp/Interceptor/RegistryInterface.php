<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;

interface RegistryInterface
{
    /**
     * @psalm-param non-empty-string $server
     *
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(string $server): array;

    /**
     * @psalm-param non-empty-string $server
     *
     * @param Autowire|CoreInterceptorInterface|string $interceptor
     */
    public function register(string $server, $interceptor): void;
}
