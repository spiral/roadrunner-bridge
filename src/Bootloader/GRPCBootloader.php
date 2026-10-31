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
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\Internal\Invoker;
use Spiral\RoadRunnerBridge\GRPC\Internal\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\Internal\ServiceLocator;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Spiral\Tokenizer\TokenizerListenerRegistryInterface;

/**
 * @api
 */
final class GRPCBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    #[\Override]
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
            GrpcClientBootloader::class,
        ];
    }

    #[\Override]
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
        \assert($listener instanceof TokenizationListenerInterface);
        $listenerRegistry->addListener($listener);
    }

    public function boot(KernelInterface $kernel): void
    {
        /** @psalm-suppress InvalidArgument KernelInterface lacks the class-name form the kernel accepts */
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
                 * Paths to the buf, protoc and protoc-gen-php-grpc binaries used by `grpc:generate`.
                 * Null looks for `<root>/<name>[.exe]` (where `vendor/bin/dload get` puts it), then in PATH.
                 */
                'bufPath' => null,
                'protocPath' => null,
                'pluginPath' => null,
                /**
                 * Path to your own buf.gen.yaml. Null renders a template with the local protoc PHP generator
                 * and protoc-gen-php-grpc. A custom template decides where the files go: `generatedPath` and
                 * `namespace` do not relocate them.
                 */
                'bufTemplate' => null,
                'generatedPath' => null,
                'namespace' => null,
                /**
                 * Root directory of the proto files; all imports are resolved from it and every file
                 * in `services` must be inside it. Null compiles each service from its own directory.
                 */
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

    private function initInvoker(
        GRPCConfig $config,
        #[Proxy] ContainerInterface $container,
        FactoryInterface $factory,
        ?PipelineBuilderInterface $pipelineBuilder = null,
    ): InvokerInterface {
        $pipelineBuilder ??= $container->get(CompatiblePipelineBuilder::class);
        \assert($pipelineBuilder instanceof PipelineBuilderInterface);

        $handler = new AutowireHandler($container);

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

        /** @psalm-suppress ArgumentTypeCoercion The default CompatiblePipelineBuilder also accepts legacy interceptors */
        return $f->make(Invoker::class, [
            'handler' => $pipelineBuilder->withInterceptors(...$list)->build($handler),
        ]);
    }

    /**
     * @psalm-pure
     */
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
