<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Spiral\Core\CoreInterceptorInterface;

interface LocatorInterface
{
    /**
     * @psalm-param non-empty-string $server
     *
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(string $server): array;
}
