<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Internal;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Exception\ConfigurationException;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor\RegistryInterface;

/**
 * @psalm-import-type TInterceptor from RegistryInterface
 * @psalm-import-type TLegacyInterceptor from RegistryInterface
 * @internal
 */
final class InterceptorRegistry implements RegistryInterface
{
    private const INTERCEPTORS_FOR_ALL_SERVICES = '*';

    /** @var array<non-empty-string, array<TInterceptor|TLegacyInterceptor>> */
    private array $interceptors = [];

    /**
     * @param array<non-empty-string, TInterceptor|TInterceptor[]|TLegacyInterceptor|TLegacyInterceptor[]> $interceptors
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

    public function register(
        string $type,
        Autowire|CoreInterceptorInterface|InterceptorInterface|string $interceptor,
    ): void {
        if ($type !== '*' && RequestType::tryFrom($type) === null) {
            throw new ConfigurationException(\sprintf(
                'The $type value must be one of the `%s`, `%s` values.',
                self::INTERCEPTORS_FOR_ALL_SERVICES,
                \implode('`, `', \array_map(static fn(\UnitEnum $case) => $case->value, RequestType::cases())),
            ));
        }

        /** @var CoreInterceptorInterface $object */
        $object = match (true) {
            $interceptor instanceof CoreInterceptorInterface,
            $interceptor instanceof InterceptorInterface => $interceptor,
            $interceptor instanceof Autowire => $interceptor->resolve($this->factory),
            default => $this->container->get($interceptor),
        };

        $this->interceptors[$type][] = $object;
    }

    public function getInterceptors(string $type): array
    {
        return \array_merge(
            $this->interceptors[self::INTERCEPTORS_FOR_ALL_SERVICES] ?? [],
            $this->interceptors[$type] ?? [],
        );
    }
}
