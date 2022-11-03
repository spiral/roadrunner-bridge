<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\RequestInterface;
use RoadRunner\Centrifugo\RequestType;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\InterceptableCore;
use Spiral\Core\ScopeInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;

final class Dispatcher implements DispatcherInterface
{
    /**
     * @var array<non-empty-string, InterceptableCore>
     */
    private array $services = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
        private readonly RoadRunnerMode $mode
    ) {
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->mode === RoadRunnerMode::Centrifuge;
    }

    public function serve(): void
    {
        /** @var CentrifugoWorker $worker */
        $worker = $this->container->get(CentrifugoWorker::class);
        /** @var ScopeInterface $scope */
        $scope = $this->container->get(ScopeInterface::class);
        /** @var Interceptor\RegistryInterface $registry */
        $registry = $this->container->get(Interceptor\RegistryInterface::class);
        /** @var RequestHandler $handler */
        $handler = $this->container->get(RequestHandler::class);

        while ($request = $worker->waitRequest()) {
            try {
                $type = RequestType::createFrom($request);
            } catch (\Throwable $e) {
                $request->error($e->getCode(), $e->getMessage());
                continue;
            }

            $service = $this->getService($handler, $registry, $type);

            try {
                $scope->runScope([
                    RequestInterface::class => $request,
                ], static fn() => $service->callAction($request::class, 'handle', [
                    'type' => $type,
                    'request' => $request,
                ]));
            } catch (\Throwable $e) {
                $request->error($e->getCode(), $e->getMessage());
            }

            $this->finalizer->finalize();
        }
    }

    public function getService(
        RequestHandler $handler,
        Interceptor\RegistryInterface $registry,
        RequestType $type
    ): InterceptableCore {
        if (\isset($this->services[$type->value])) {
            return $this->services[$type->value];
        }

        $service = new InterceptableCore($handler);
        foreach ($registry->getInterceptors($type) as $interceptor) {
            $service->addInterceptor($interceptor);
        }

        return $this->services[$type->value] = $service;
    }
}
