<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Psr\Container\ContainerInterface;
use Spiral\Core\Exception\Container\ContainerException;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\Tokenizer\Attribute\TargetClass;
use Spiral\Tokenizer\TokenizationListenerInterface;

#[TargetClass(ServiceInterface::class)]
final class ServiceLocator implements LocatorInterface, TokenizationListenerInterface
{
    private array $services = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getServices(): array
    {
        return $this->services;
    }

    public function listen(\ReflectionClass $class): void
    {
        if (!$class->isInstantiable()) {
            return;
        }

        try {
            $instance = $this->container->get($class->getName());
        } catch (ContainerException) {
            return;
        }

        foreach ($class->getInterfaces() as $interface) {
            if ($interface->isSubclassOf(ServiceInterface::class)) {
                $this->services[$interface->getName()] = $instance;
            }
        }
    }

    public function finalize(): void {}
}
