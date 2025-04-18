<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Internal;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\RoadRunnerBridge\Tcp\Interceptor\RegistryInterface;

/**
 * @psalm-import-type TInterceptor from RegistryInterface
 * @psalm-import-type TLegacyInterceptor from RegistryInterface
 */
final class InterceptorRegistry implements RegistryInterface
{
    /** @var array<non-empty-string, list<TInterceptor|TLegacyInterceptor>> */
    private array $interceptors = [];

    public function __construct(
        array $interceptors,
        private readonly ContainerInterface $container,
    ) {
        foreach ($interceptors as $server => $values) {
            if (!\is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $interceptor) {
                $this->register($server, $interceptor);
            }
        }
    }

    public function register(
        string $server,
        Autowire|CoreInterceptorInterface|InterceptorInterface|string $interceptor,
    ): void {
        $this->interceptors[$server][] = $interceptor;
    }

    public function getInterceptors(string $server): array
    {
        $interceptors = [];
        foreach ($this->interceptors[$server] ?? [] as $value) {
            $interceptor = match (true) {
                $value instanceof CoreInterceptorInterface, $value instanceof InterceptorInterface => $value,
                $value instanceof Autowire => $value->resolve($this->container->get(FactoryInterface::class)),
                default => $this->container->get($value),
            };

            \assert($interceptor instanceof CoreInterceptorInterface);

            $interceptors[] = $interceptor;
        }

        return $interceptors;
    }
}
