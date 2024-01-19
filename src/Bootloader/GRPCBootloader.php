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
use Spiral\RoadRunnerBridge\GRPC\Generator\BootloaderGenerator;
use Spiral\RoadRunnerBridge\GRPC\Generator\ConfigGenerator;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\ServiceClientGenerator;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\Invoker;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\InvokerCore;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\RoadRunnerBridge\GRPC\ServiceLocator;

final class GRPCBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {
    }

    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            Server::class => Server::class,
            InvokerInterface::class => [self::class, 'initInvoker'],
            LocatorInterface::class => ServiceLocator::class,
            ProtoFilesRepositoryInterface::class => [self::class, 'initProtoFilesRepository'],
            GeneratorRegistryInterface::class => [self::class, 'initGeneratorRegistry'],
        ];
    }

    public function init(): void
    {
        $this->initGrpcConfig();
    }

    public function boot(KernelInterface $kernel): void
    {
        $kernel->addDispatcher(Dispatcher::class);
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
                'generatedPath' => null,
                'namespace' => null,
                'servicesBasePath' => null,
                'services' => [],
                'interceptors' => [],
                'generators' => [
                    ServiceClientGenerator::class,
                    ConfigGenerator::class,
                    BootloaderGenerator::class,
                ],
            ],
        );
    }

    /**
     * @param Autowire|class-string<CoreInterceptorInterface>|CoreInterceptorInterface $interceptor
     */
    public function addInterceptor(string|CoreInterceptorInterface|Autowire $interceptor): void
    {
        $this->config->modify(
            GRPCConfig::CONFIG,
            new Append('interceptors', null, $interceptor),
        );
    }

    /**
     * @param Autowire|class-string<GeneratorInterface>|GeneratorInterface $generator
     */
    public function addGenerator(string|GeneratorInterface|Autowire $generator): void
    {
        $this->config->modify(GRPCConfig::CONFIG, new Append('generators', null, $generator));
    }

    private function initInvoker(
        GRPCConfig $config,
        ContainerInterface $container,
        FactoryInterface $factory,
        BaseInvoker $invoker,
    ): InvokerInterface {
        $core = new InterceptableCore(
            new InvokerCore($invoker),
        );

        foreach ($config->getInterceptors() as $interceptor) {
            $interceptor = $this->resolve($interceptor, $container, $factory);

            \assert($interceptor instanceof CoreInterceptorInterface);

            $core->addInterceptor($interceptor);
        }

        return new Invoker($core);
    }

    private function initProtoFilesRepository(GRPCConfig $config): ProtoFilesRepositoryInterface
    {
        return new FileRepository($config->getServices());
    }

    private function initGeneratorRegistry(
        GRPCConfig $config,
        ContainerInterface $container,
        FactoryInterface $factory,
    ): GeneratorRegistryInterface {
        $registry = new GeneratorRegistry();
        foreach ($config->getGenerators() as $generator) {
            $generator = $this->resolve($generator, $container, $factory);

            \assert($generator instanceof GeneratorInterface);

            $registry->addGenerator($generator);
        }

        return $registry;
    }

    private function resolve(mixed $dependency, ContainerInterface $container, FactoryInterface $factory): object
    {
        return match (true) {
            \is_string($dependency) => $container->get($dependency),
            $dependency instanceof Autowire => $dependency->resolve($factory),
            default => $dependency
        };
    }
}
