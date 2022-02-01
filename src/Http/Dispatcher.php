<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Http;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Debug\StateInterface;
use Spiral\Exceptions\HtmlHandler;
use Spiral\Http\Http;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;

final class Dispatcher implements DispatcherInterface
{
    private EnvironmentInterface $env;
    private ContainerInterface $container;
    private FinalizerInterface $finalizer;
    private ErrorHandlerInterface $errorHandler;

    public function __construct(
        EnvironmentInterface $env,
        ContainerInterface $container,
        ErrorHandlerInterface $errorHandler,
        FinalizerInterface $finalizer
    ) {
        $this->env = $env;
        $this->container = $container;
        $this->finalizer = $finalizer;
        $this->errorHandler = $errorHandler;
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->env->getMode() === Mode::MODE_HTTP;
    }

    /**
     * @return mixed|void
     */
    public function serve()
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
        $handler = new HtmlHandler();

        try {
            $this->errorHandler->handle($e);

            if ($this->container->has(StateInterface::class)) {
                // on demand
                $state = $this->container->get(StateInterface::class);

                if ($state !== null) {
                    $handler = $handler->withState($state);
                }
            }
        } catch (\Throwable|ContainerExceptionInterface $se) {
            \file_put_contents('php://stderr', (string)$e);
        }

        /** @var ResponseFactoryInterface $responseFactory */
        $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        $response = $responseFactory->createResponse(500);

        // Reporting system (non handled) exception directly to the client
        $response->getBody()->write(
            $handler->renderException($e, HtmlHandler::VERBOSITY_VERBOSE)
        );

        return $response;
    }
}
