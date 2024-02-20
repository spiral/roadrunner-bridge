<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\Request\RequestInterface;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\InterceptableCore;
use Spiral\Core\ScopeInterface;
use Spiral\Framework\Spiral;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

#[DispatcherScope(scope: Spiral::Centrifugo)]
final class Dispatcher implements DispatcherInterface
{
    /**
     * @var array<non-empty-string, InterceptableCore>
     */
    private array $services = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
    ) {
    }

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Centrifuge;
    }

    public function serve(): void
    {
        /** @var CentrifugoWorker $worker */
        $worker = $this->container->get(CentrifugoWorker::class);
        /**
         * @var ScopeInterface $scope
         *
         * @psalm-suppress DeprecatedInterface
         */
        $scope = $this->container->get(ScopeInterface::class);
        /** @var Interceptor\RegistryInterface $registry */
        $registry = $this->container->get(Interceptor\RegistryInterface::class);
        /** @var RequestHandler $handler */
        $handler = $this->container->get(RequestHandler::class);
        /** @var ErrorHandlerInterface $errorHandler */
        $errorHandler = $this->container->get(ErrorHandlerInterface::class);

        while ($request = $worker->waitRequest()) {
            try {
                $type = RequestType::createFrom($request);
                $service = $this->getService($handler, $registry, $type);
                $scope->runScope([
                    RequestInterface::class => $request,
                ], static fn (): mixed => $service->callAction($request::class, 'handle', [
                    'type' => $type,
                    'request' => $request,
                ]));
            } catch (\Throwable $e) {
                $errorHandler->handle($request, $e);
            }

            $this->finalizer->finalize();
        }
    }

    public function getService(
        RequestHandler $handler,
        Interceptor\RegistryInterface $registry,
        RequestType $type,
    ): InterceptableCore {
        if (isset($this->services[$type->value])) {
            return $this->services[$type->value];
        }

        $service = new InterceptableCore($handler);
        foreach ($registry->getInterceptors($type->value) as $interceptor) {
            $service->addInterceptor($interceptor);
        }

        return $this->services[$type->value] = $service;
    }
}
