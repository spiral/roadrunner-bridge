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
     */
    public function register(string $server, Autowire|CoreInterceptorInterface|string $interceptor): void;
}
