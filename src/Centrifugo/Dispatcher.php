<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\RequestInterface;
use RoadRunner\Centrifugo\RequestType;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\CoreInterface;
use Spiral\Core\InterceptableCore;
use Spiral\Core\ScopeInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;

final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
        private readonly RoadRunnerMode $mode,
        private readonly RequestHandler $handler,
        private readonly Interceptor\RegistryInterface $registry,
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

        while ($request = $worker->waitRequest()) {
            $type = RequestType::createFrom($request);
            $service = $this->createHandler($type, $request);
            if ($service === null) {
                continue;
            }

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

    private function createHandler(RequestType $type, RequestInterface $request): ?CoreInterface
    {
        $core = new InterceptableCore($this->handler);

        foreach ($this->registry->getInterceptors($type) as $interceptor) {
            $core->addInterceptor($interceptor);
        }

        return $core;
    }
}
