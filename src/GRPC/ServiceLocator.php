<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Psr\Container\ContainerInterface;
use Spiral\Core\BinderInterface;
use Spiral\Core\Attribute\Proxy as ProxyAttribute;
use Spiral\Core\Config\Proxy;
use Spiral\Core\Exception\Container\ContainerException;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\Tokenizer\ClassesInterface;

final class ServiceLocator implements LocatorInterface
{
    public function __construct(
        private readonly ClassesInterface $classes,
        #[ProxyAttribute] private readonly ContainerInterface $container,
        private readonly BinderInterface $binder,
    ) {
    }

    public function getServices(): array
    {
        $result = [];

        foreach ($this->classes->getClasses(ServiceInterface::class) as $service) {
            if (!$service->isInstantiable()) {
                continue;
            }

            foreach ($service->getInterfaces() as $interface) {
                if ($interface->isSubclassOf(ServiceInterface::class)) {
                    $grpcRequest = $this->binder->getBinder('grpc.request');
                    $grpcRequest->bind($interface->getName(), $service->getName());
                    $grpcRequest->bind($service->getName(), $service->getName());
                    $this->binder->bind($interface->getName(), new Proxy($interface->getName()));

                    try {
                        $instance = $this->container->get($interface->getName());
                    } catch (ContainerException) {
                        continue;
                    }

                    $result[$interface->getName()] = $instance;
                }
            }
        }

        return $result;
    }
}
