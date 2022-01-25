<?php

declare(strict_types=1);

namespace Spiral\Tests\Http;

use Laminas\Diactoros\Response;
use Mockery as m;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Http\Http;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunnerBridge\Http\Dispatcher;
use Spiral\RoadRunnerBridge\Http\ErrorHandlerInterface;
use Spiral\Tests\TestCase;

final class DispatcherTest extends TestCase
{
    public function testCanServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertFalse($this->app->get(Dispatcher::class)->canServe());
    }

    public function testCanServe(): void
    {
        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'http',
            ]);
        });

        $this->assertTrue($this->app->get(Dispatcher::class)->canServe());
    }

    public function testServe(): void
    {
        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'http',
            ]);
        });

        $finalizer = m::mock(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);
        $this->container->bind(FinalizerInterface::class, $finalizer);

        $http = m::mock(RequestHandlerInterface::class);
        $this->container->bind(Http::class, $http);

        $worker = m::mock(PSR7WorkerInterface::class);
        $this->container->bind(PSR7WorkerInterface::class, $worker);

        $worker->shouldReceive('waitRequest')->once()
            ->andReturn($request = m::mock(ServerRequestInterface::class));

        $worker->shouldReceive('waitRequest')->once()
            ->andReturnNull();

        $http->shouldReceive('handle')->once()->with($request)
            ->andReturn($response = m::mock(ResponseInterface::class));

        $worker->shouldReceive('respond')->once()->with($response);

        $this->app->get(Dispatcher::class)->serve();
    }

    public function testServeWithError(): void
    {
        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'http',
            ]);
        });

        $finalizer = m::mock(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);
        $this->container->bind(FinalizerInterface::class, $finalizer);

        $errorHandler = m::mock(ErrorHandlerInterface::class);
        $this->container->bind(ErrorHandlerInterface::class, $errorHandler);


        $http = m::mock(RequestHandlerInterface::class);
        $this->container->bind(Http::class, $http);

        $worker = m::mock(PSR7WorkerInterface::class);
        $this->container->bind(PSR7WorkerInterface::class, $worker);

        $responseFactory = m::mock(ResponseFactoryInterface::class);
        $this->container->bind(ResponseFactoryInterface::class, $responseFactory);

        $worker->shouldReceive('waitRequest')->once()
            ->andReturn($request = m::mock(ServerRequestInterface::class));

        $worker->shouldReceive('waitRequest')->once()
            ->andReturnNull();

        $http->shouldReceive('handle')->once()
            ->andThrow($e = new \Exception('Something went wrong'));

        $errorHandler->shouldReceive('handle')->once()->with($e);

        $responseFactory->shouldReceive('createResponse')
            ->once()
            ->with(500)
            ->andReturn($response = new Response());

        $worker->shouldReceive('respond')
            ->once()
            ->withArgs(function (ResponseInterface $r) use ($response) {
                return $r === $response;
            });

        $this->app->get(Dispatcher::class)->serve();
    }
}
