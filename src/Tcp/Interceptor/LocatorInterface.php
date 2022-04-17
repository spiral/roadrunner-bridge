<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Spiral\Core\CoreInterceptorInterface;

interface LocatorInterface
{
    /**
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(): array;
}
