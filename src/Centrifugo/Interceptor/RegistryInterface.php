<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Interceptor;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Interceptors\InterceptorInterface;

/**
 * @psalm-type TLegacyInterceptor = Autowire|CoreInterceptorInterface|class-string<CoreInterceptorInterface>
 * @psalm-type TInterceptor = Autowire|InterceptorInterface|class-string<InterceptorInterface>
 *
 * @api
 */
interface RegistryInterface
{
    /**
     * @param non-empty-string $type
     *
     * @return list<TInterceptor|TLegacyInterceptor>
     */
    public function getInterceptors(string $type): array;

    /**
     * @param non-empty-string $type
     * @param TInterceptor|TLegacyInterceptor $interceptor
     */
    public function register(string $type, Autowire|CoreInterceptorInterface|InterceptorInterface|string $interceptor): void;
}
