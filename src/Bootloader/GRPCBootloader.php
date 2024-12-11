<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\CompatiblePipelineBuilder;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Grpc\Client\Bridge\GrpcClientBootloader;
use Spiral\Grpc\Client\Config\GrpcClientConfig;
use Spiral\Interceptors\Handler\AutowireHandler;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Interceptors\PipelineBuilderInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\Invoker;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\RoadRunnerBridge\GRPC\ServiceLocator;
use Spiral\Tokenizer\TokenizerListenerRegistryInterface;

final class GRPCBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
            GrpcClientBootloader::class,
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
            GrpcClientConfig::class => [GRPCConfig::class, 'getClientConfig'],
        ];
    }

    public function init(
        TokenizerListenerRegistryInterface $listenerRegistry,
        LocatorInterface $listener,
    ): void {
        $this->initGrpcConfig();
        $listenerRegistry->addListener($listener);
    }

    public function boot(KernelInterface $kernel): void
    {
        /** @psalm-suppress InvalidArgument */
        $kernel->addDispatcher(Dispatcher::class);
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
                'generators' => [],
                'client' => [
                    'interceptors' => [],
                ],
            ],
        );
    }

    /**
     * @psalm-suppress DeprecatedInterface
     */
    private function initInvoker(
        GRPCConfig $config,
        #[Proxy] ContainerInterface $container,
        FactoryInterface $factory,
        ?PipelineBuilderInterface $pipelineBuilder = null,
    ): InvokerInterface {
        /** @var PipelineBuilderInterface $pipelineBuilder */
        $pipelineBuilder ??= $container->get(CompatiblePipelineBuilder::class);

        $handler = new AutowireHandler($container, false);

        /**
         * @var list<InterceptorInterface|CoreInterceptorInterface> $list
         * @var ContainerInterface $c
         * @var FactoryInterface $f
         */
        $list = [];
        $c = $container->get(ContainerInterface::class);
        $f = $c->get(FactoryInterface::class);
        foreach ($config->getInterceptors() as $interceptor) {
            $list[] = $this->resolve($interceptor, $c, $factory);
        }

        return $f->make(Invoker::class, [
            'handler' => $pipelineBuilder->withInterceptors(...$list)->build($handler),
        ]);
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
            default => $dependency,
        };
    }
}
