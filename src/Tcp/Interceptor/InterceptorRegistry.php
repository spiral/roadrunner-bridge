<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;

final class InterceptorRegistry implements RegistryInterface
{
    private array $interceptors;

    public function __construct(
        array $interceptors,
        private readonly ContainerInterface $container
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
     * @psalm-param non-empty-string $server
     */
    public function register(string $server, Autowire|CoreInterceptorInterface|string $interceptor): void
    {
        $this->interceptors[$server][] = $interceptor;
    }

    /**
     * @psalm-param non-empty-string $server
     *
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(string $server): array
    {
        $interceptors = [];
        foreach ($this->interceptors[$server] ?? [] as $value) {
            $interceptors[] = match (true) {
                $value instanceof CoreInterceptorInterface => $value,
                $value instanceof Autowire => $value->resolve($this->container->get(FactoryInterface::class)),
                default => $this->container->get($value)
            };
        }

        return $interceptors;
    }
}
