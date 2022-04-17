<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;

final class InterceptorLocator implements LocatorInterface
{
    private TcpConfig $config;
    private ContainerInterface $container;

    public function __construct(TcpConfig $config, ContainerInterface $container)
    {
        $this->config = $config;
        $this->container = $container;
    }

    /**
     * @return CoreInterceptorInterface[]
     */
    public function getInterceptors(): array
    {
        $interceptors = [];
        foreach ($this->config->getInterceptors() as $value) {
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
}
