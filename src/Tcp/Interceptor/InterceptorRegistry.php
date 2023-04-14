<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;

/**
 * @psalm-import-type TInterceptor from RegistryInterface
 */
final class InterceptorRegistry implements RegistryInterface
{
    /**
     * @var array <non-empty-string, TInterceptor[]>
     */
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

    /**
     * @param non-empty-string $server
     */
    final public function register(string $server, Autowire|CoreInterceptorInterface|string $interceptor): void
    {
        $this->interceptors[$server][] = $interceptor;
    }

    /**
     * @param non-empty-string $server
     *
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(string $server): array
    {
        $interceptors = [];
        foreach ($this->interceptors[$server] ?? [] as $value) {
            $interceptor = match (true) {
                $value instanceof CoreInterceptorInterface => $value,
                $value instanceof Autowire => $value->resolve($this->container->get(FactoryInterface::class)),
                default => $this->container->get($value)
            };

            \assert($interceptor instanceof CoreInterceptorInterface);

            $interceptors[] = $interceptor;
        }

        return $interceptors;
    }
}
