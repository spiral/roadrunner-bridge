<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
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
        RoadRunnerBootloader::class,
    ];

    protected const SINGLETONS = [
        Server::class => Server::class,
        InvokerInterface::class => Invoker::class,
        LocatorInterface::class => ServiceLocator::class,
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config
    ) {
    }

    public function init(): void
    {
        $this->initGrpcConfig();
    }

    public function start(KernelInterface $kernel, FactoryInterface $factory): void
    {
        $kernel->addDispatcher($factory->make(Dispatcher::class));
    }

    private function initGrpcConfig(): void
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
