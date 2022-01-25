<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Bootloader\ServerBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ServiceLocator;

final class GRPCBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        // RoadRunnerBootloader::class,
        ServerBootloader::class,
    ];

    protected const SINGLETONS = [
        Server::class => Server::class,
        InvokerInterface::class => Invoker::class,
        LocatorInterface::class => ServiceLocator::class,
    ];

    private ConfiguratorInterface $config;

    public function __construct(
        ConfiguratorInterface $config
    ) {
        $this->config = $config;
    }

    public function boot(
        EnvironmentInterface $env,
        KernelInterface $kernel,
        FactoryInterface $factory
    ): void {
        $this->initGrpcConfig($env);

        $kernel->addDispatcher($factory->make(Dispatcher::class));
    }

    private function initGrpcConfig(EnvironmentInterface $env): void
    {
        $this->config->setDefaults(
            GRPCConfig::CONFIG,
            [
                /**
                 * Path to protoc-gen-php-grpc library.
                 */
                'binaryPath' => null,

                'services' => [],
            ]
        );
    }
}
