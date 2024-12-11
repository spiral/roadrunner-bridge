<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\Tokenizer\Attribute\TargetClass;
use Spiral\Tokenizer\TokenizationListenerInterface;

/**
 * @internal
 */
#[TargetClass(ServiceInterface::class, scope: 'grpc-services')]
final class ServiceLocator implements LocatorInterface, TokenizationListenerInterface
{
    /**
     * Service Interface => Service Implementation
     *
     * @var array<class-string<ServiceInterface>, \ReflectionClass<ServiceInterface>>
     */
    private array $registry = [];

    public function getServices(): array
    {
        return $this->registry;
    }

    /**
     * @param \ReflectionClass<ServiceInterface> $class
     */
    public function listen(\ReflectionClass $class): void
    {
        if (!$class->isInstantiable()) {
            return;
        }

        // Find ServiceInterface interfaces
        /** @var array<class-string<ServiceInterface>, \ReflectionClass<ServiceInterface>> $interfaces */
        $interfaces = [];
        foreach ($class->getInterfaces() as $interface) {
            if (!$interface->isSubclassOf(ServiceInterface::class)) {
                continue;
            }

            // Deduplicate parents
            foreach ($interfaces as $className => $reflection) {
                if ($interface->isSubclassOf($className)) {
                    continue 2;
                }

                if ($reflection->isSubclassOf($interface->getName())) {
                    unset($interfaces[$className]);
                }
            }

            $interfaces[$interface->getName()] = $interface;
        }

        foreach ($interfaces as $className => $reflection) {
            \array_key_exists($className, $this->registry) and throw new \LogicException(
                \sprintf(
                    'Can not register service %s for interface %s because it is already registered for %s.',
                    $class->getName(),
                    $className,
                    $this->registry[$className]->getName(),
                ),
            );

            $this->registry[$className] = $class;
        }
    }

    public function finalize(): void {}
}
