<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\InterceptableCore;
use Spiral\RoadRunner\GRPC\Invoker as BaseInvoker;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\Invoker;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\InvokerCore;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\RoadRunnerBridge\GRPC\ServiceLocator;

final class GRPCBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
    ];

    protected const SINGLETONS = [
        Server::class => Server::class,
        InvokerInterface::class => [self::class, 'initInvoker'],
        LocatorInterface::class => ServiceLocator::class,
        ProtoFilesRepositoryInterface::class => [self::class, 'initProtoFilesRepository']
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config
    ) {
    }

    public function init(): void
    {
        $this->initGrpcConfig();
    }

    public function boot(KernelInterface $kernel, FactoryInterface $factory): void
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

                'interceptors' => [],
            ]
        );
    }

    /**
     * @param Autowire|class-string<CoreInterceptorInterface>|CoreInterceptorInterface $interceptor
     */
    public function addInterceptor(string|CoreInterceptorInterface|Autowire $interceptor): void
    {
        $this->config->modify(
            GRPCConfig::CONFIG,
            new Append('interceptors', null, $interceptor)
        );
    }

    private function initInvoker(
        GRPCConfig $config,
        ContainerInterface $container,
        FactoryInterface $factory,
        BaseInvoker $invoker
    ): InvokerInterface {
        $core = new InterceptableCore(
            new InvokerCore($invoker)
        );

        foreach ($config->getInterceptors() as $interceptor) {
            if (\is_string($interceptor)) {
                $interceptor = $container->get($interceptor);
            }

            if ($interceptor instanceof Autowire) {
                $interceptor = $interceptor->resolve($factory);
            }

            \assert($interceptor instanceof CoreInterceptorInterface);

            $core->addInterceptor($interceptor);
        }

        return new Invoker($core);
    }

    private function initProtoFilesRepository(GRPCConfig $config): ProtoFilesRepositoryInterface
    {
        return new FileRepository($config->getServices());
    }
}
