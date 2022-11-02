<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Interceptor;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\RequestType;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;

/**
 * @psalm-import-type TInterceptor from RegistryInterface
 */
final class InterceptorRegistry implements RegistryInterface
{
    /** @var array<string, CoreInterceptorInterface[]> */
    private array $interceptors;

    /**
     * @param array<RequestType, list<TInterceptor>> $interceptors
     */
    public function __construct(
        array $interceptors,
        private readonly ContainerInterface $container,
        private readonly FactoryInterface $factory,
    ) {
        foreach ($interceptors as $type => $values) {
            if (!\is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $interceptor) {
                $this->register($type, $interceptor);
            }
        }
    }

    public function register(RequestType $type, Autowire|CoreInterceptorInterface|string $interceptor): void
    {
        $this->interceptors[$type->value][] = match (true) {
            $interceptor instanceof CoreInterceptorInterface => $interceptor,
            $interceptor instanceof Autowire => $interceptor->resolve($this->factory),
            default => $this->container->get($interceptor)
        };
    }

    public function getInterceptors(RequestType $type): array
    {
        return $this->interceptors[$type->value] ?? [];
    }
}
