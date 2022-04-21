<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Tcp\Interceptor\Exception\InvalidException;

final class InterceptorRegistry implements RegistryInterface
{
    private array $interceptors;
    private ContainerInterface $container;

    public function __construct(array $interceptors, ContainerInterface $container)
    {
        foreach ($interceptors as $server => $values) {
            if (!\is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $interceptor) {
                $this->register($server, $interceptor);
            }
        }

        $this->container = $container;
    }

    /**
     * @param Autowire|CoreInterceptorInterface|string $interceptor
     */
    public function register(string $server, $interceptor): void
    {
        $this->validate($interceptor);

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
            $this->validate($value);

            switch (true) {
                case $value instanceof CoreInterceptorInterface:
                    $interceptors[] = $value;
                    break;
                case $value instanceof Autowire:
                    $interceptors[] = $value->resolve($this->container->get(FactoryInterface::class));
                    break;
                default:
                    $interceptors[] = $this->container->get($value);
            }
        }

        return $interceptors;
    }

    /**
     * @param mixed $interceptor
     */
    private function validate($interceptor): void
    {
        if (
            $interceptor instanceof CoreInterceptorInterface ||
            $interceptor instanceof Autowire ||
            \is_string($interceptor)
        ) {
            return;
        }

        throw new InvalidException(\get_debug_type($interceptor));
    }
}
