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
use Spiral\Snapshots\SnapshotInterface;
use Spiral\Snapshots\SnapshotterInterface;

final class Dispatcher implements DispatcherInterface
{
    private EnvironmentInterface $env;
    private PSR7WorkerInterface $worker;
    private ContainerInterface $container;
    private FinalizerInterface $finalizer;

    public function __construct(
        EnvironmentInterface $env,
        PSR7WorkerInterface $worker,
        ContainerInterface $container,
        FinalizerInterface $finalizer
    ) {
        $this->env = $env;
        $this->worker = $worker;
        $this->container = $container;
        $this->finalizer = $finalizer;
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->env->getMode() === Mode::MODE_HTTP;
    }

    public function serve()
    {
        /** @var Http $http */
        $http = $this->container->get(Http::class);
        while ($request = $this->worker->waitRequest()) {
            try {
                $response = $http->handle($request);

                $this->worker->respond($response);
            } catch (\Throwable $e) {
                $this->worker->respond($this->errorToResponse($e));
            } finally {
                $this->finalizer->finalize(false);
            }
        }
    }

    protected function errorToResponse(\Throwable $e): ResponseInterface
    {
        $handler = new HtmlHandler();

        try {
            /** @var SnapshotInterface $snapshot */
            $snapshot = $this->container->get(SnapshotterInterface::class)->register($e);
            \file_put_contents('php://stderr', $snapshot->getMessage());

            // on demand
            $state = $this->container->get(StateInterface::class);

            if ($state !== null) {
                $handler = $handler->withState($state);
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
