<?php

declare(strict_types=1);

namespace Spiral\Tests\Http;

use Mockery as m;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Http\Http;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunnerBridge\Http\Dispatcher;
use Spiral\RoadRunnerBridge\Http\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\TestCase;

final class DispatcherTest extends TestCase
{
    public function testCanServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    public function testCanServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Http);

        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    public function testServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Http);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $http = $this->mockContainer(Http::class, RequestHandlerInterface::class);

        $worker = $this->mockContainer(PSR7WorkerInterface::class);

        $worker->shouldReceive('waitRequest')->once()
            ->andReturn($request = m::mock(ServerRequestInterface::class));

        $worker->shouldReceive('waitRequest')->once()
            ->andReturnNull();

        $http->shouldReceive('handle')->once()->with($request)
            ->andReturn($response = m::mock(ResponseInterface::class));

        $worker->shouldReceive('respond')->once()->with($response);

        $this->serveDispatcher(Dispatcher::class);
    }

    public function testServeWithError(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Http);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $errorHandler = $this->mockContainer(ErrorHandlerInterface::class);

        $http = $this->mockContainer(Http::class, RequestHandlerInterface::class);

        $worker = $this->mockContainer(PSR7WorkerInterface::class);

        $responseFactory = $this->mockContainer(ResponseFactoryInterface::class);

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

        $this->serveDispatcher(Dispatcher::class);
    }
}
