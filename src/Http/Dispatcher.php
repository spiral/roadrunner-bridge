<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Exceptions\ExceptionHandlerInterface;
use Spiral\Exceptions\Verbosity;
use Spiral\Framework\Spiral;
use Spiral\Http\Http;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

#[DispatcherScope(scope: Spiral::Http)]
final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ErrorHandlerInterface $errorHandler,
        private readonly FinalizerInterface $finalizer,
    ) {
    }

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Http;
    }

    public function serve(): void
    {
        /** @var PSR7WorkerInterface $worker */
        $worker = $this->container->get(PSR7WorkerInterface::class);

        /** @var Http $http */
        $http = $this->container->get(Http::class);

        while ($request = $worker->waitRequest()) {
            try {
                $response = $http->handle($request);
                $worker->respond($response);
            } catch (\Throwable $e) {
                $worker->respond($this->errorToResponse($e));
            } finally {
                $this->finalizer->finalize(false);
            }
        }
    }

    protected function errorToResponse(\Throwable $e): ResponseInterface
    {
        /** @var ExceptionHandlerInterface $handler */
        $handler = $this->container->get(ExceptionHandlerInterface::class);

        try {
            $this->errorHandler->handle($e);
        } catch (\Throwable) {
            \file_put_contents('php://stderr', (string)$e);
        }

        /** @var ResponseFactoryInterface $responseFactory */
        $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        $response = $responseFactory->createResponse(500);

        // Reporting system (non handled) exception directly to the client
        $response->getBody()->write(
            $handler->render($e, Verbosity::VERBOSE)
        );

        return $response;
    }
}
