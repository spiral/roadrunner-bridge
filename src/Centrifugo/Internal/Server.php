<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Internal;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use RoadRunner\Centrifugo\Request\RequestInterface;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\CompatiblePipelineBuilder;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\AutowireHandler;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\PipelineBuilderInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor\RegistryInterface as InterceptorRegistry;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;

/**
 * @internal
 */
final class Server
{
    private readonly PipelineBuilderInterface $pipelineBuilder;
    private readonly HandlerInterface $handler;

    /** @var array<non-empty-string, HandlerInterface> */
    private array $pipelines = [];

    public function __construct(
        private readonly InterceptorRegistry $interceptors,
        private readonly RegistryInterface $services,
        #[Proxy] private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
        private readonly ErrorHandlerInterface $errorHandler,
        ?PipelineBuilderInterface $pipelineBuilder = null,
    ) {
        $this->pipelineBuilder = $pipelineBuilder ?? $container->get(CompatiblePipelineBuilder::class);
        $this->handler = new AutowireHandler($container);
    }

    /**
     * @throws \JsonException
     */
    public function serve(CentrifugoWorkerInterface $worker): void
    {
        $scope = $this->container->get(ScopeInterface::class);

        while ($request = $worker->waitRequest()) {
            try {
                $type = RequestType::createFrom($request);
                $pipeline = $this->getHandler($type);
                $services = $this->services;

                $scope->runScope(
                    new Scope('centrifugo-request', [RequestInterface::class => $request]),
                    static fn(): mixed => $pipeline->handle(
                        new CallContext(
                            /** @see ServiceInterface::handle() */
                            Target::fromPair($services->getService($type), 'handle'),
                            [$request],
                            [RequestType::class => $type],
                        ),
                    ),
                );
            } catch (\Throwable $e) {
                $this->errorHandler->handle($request, $e);
                unset($e);
            }

            $this->finalizer->finalize();
        }
    }

    public function getHandler(RequestType $type): HandlerInterface
    {
        /** @psalm-suppress PossiblyInvalidArgument */
        return $this->pipelines[$type->value] ??= $this->pipelineBuilder
            ->withInterceptors(...$this->interceptors->getInterceptors($type->value))
            ->build($this->handler);
    }
}
