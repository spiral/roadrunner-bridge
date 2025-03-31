<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Internal;

use Psr\Container\ContainerInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\CompatiblePipelineBuilder;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\AutowireHandler;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\PipelineBuilderInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tcp\RequestInterface;
use Spiral\RoadRunner\Tcp\TcpWorker;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Interceptor\RegistryInterface as InterceptorRegistry;
use Spiral\RoadRunnerBridge\Tcp\Response\CloseConnection;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\RegistryInterface as ServiceRegistry;

final class Server
{
    private readonly PipelineBuilderInterface $pipelineBuilder;
    private readonly HandlerInterface $handler;

    /** @var array<non-empty-string, HandlerInterface> */
    private array $pipelines = [];

    public function __construct(
        private readonly TcpConfig $config,
        private readonly InterceptorRegistry $interceptors,
        private readonly ServiceRegistry $services,
        #[Proxy] private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
        private readonly ExceptionReporterInterface $reporter,
        ?PipelineBuilderInterface $pipelineBuilder = null,
    ) {
        $this->pipelineBuilder = $pipelineBuilder ?? $container->get(CompatiblePipelineBuilder::class);
        $this->handler = new AutowireHandler($container);
    }

    /**
     * @throws \JsonException
     */
    public function serve(?WorkerInterface $worker = null): void
    {
        $worker ??= Worker::create();
        $tcpWorker = new TcpWorker($worker);
        $scope = $this->container->get(ScopeInterface::class);

        while ($request = $tcpWorker->waitRequest()) {
            $e = null;
            try {
                $server = $request->getServer();
                $pipeline = $this->getPipeline($server);
                $services = $this->services;

                /**
                 * @var ResponseInterface $response
                 */
                $response = $scope->runScope(
                    new Scope('tcp-request', [RequestInterface::class => $request]),
                    static fn(): mixed => $pipeline->handle(new CallContext(
                        /** @see \Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface::handle() */
                        Target::fromPair($services->getService($server), 'handle'),
                        ['request' => $request],
                        ['server' => $server],
                    )),
                );
            } catch (\Throwable $e) {
                $worker->error($this->config->isDebugMode() ? (string) $e : $e->getMessage());
                $response = new CloseConnection();
            } finally {
                if (isset($response) && $response instanceof ResponseInterface) {
                    $tcpWorker->getWorker()->respond(
                        new Payload($response->getBody(), $response->getAction()->value),
                    );
                }

                $this->finalize($e);
            }
        }
    }

    /**
     * @param non-empty-string $server
     */
    private function getPipeline(string $server): HandlerInterface
    {
        return $this->pipelines[$server] ??= $this->pipelineBuilder
            ->withInterceptors(...$this->interceptors->getInterceptors($server))
            ->build($this->handler);
    }

    private function finalize(?\Throwable $e): void
    {
        if ($e !== null) {
            try {
                $this->reporter->report($e);
            } catch (\Throwable) {
                // no need to notify when unable to register an exception
            }
        }

        $this->finalizer->finalize(terminate: false);
    }
}
