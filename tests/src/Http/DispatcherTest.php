<?php

declare(strict_types=1);

namespace Spiral\Tests\Http;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunnerBridge\Http\Internal\Dispatcher;
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

    /**
     * There two requests sent to the worker.
     * The first request is handled and response is sent to the worker.
     * The second request is handled and exception is thrown. It should stop the worker.
     */
    public function testServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Http);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->twice()->with(false);

        $httpHandler = $this->mockContainer(RequestHandlerInterface::class);

        $worker = $this->mockContainer(PSR7WorkerInterface::class);
        $worker->shouldReceive('waitRequest')->twice()->andReturn(
            $request1 = new ServerRequest('GET', '/'),
            $request2 = new ServerRequest('GET', '/'),
        );

        $httpHandler->shouldReceive('handle')->once()->with($request1)->andReturn($response1 = new Response());
        $httpHandler->shouldReceive('handle')->once()->with($request2)->andReturn($response2 = new Response());

        $worker->shouldReceive('respond')->once()->with($response1)->andReturnNull();
        $worker->shouldReceive('respond')->once()->with($response2)->andThrow(new \Exception());

        $this->serveDispatcher(Dispatcher::class);
    }

    /**
     * Test exit command
     */
    public function testExit(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Http);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldNotReceive('finalize');

        $httpHandler = $this->mockContainer(RequestHandlerInterface::class);
        $httpHandler->shouldNotReceive('handle');

        $worker = $this->mockContainer(PSR7WorkerInterface::class);
        $worker->shouldNotReceive('respond');

        $worker = $this->mockContainer(PSR7WorkerInterface::class);
        $worker->shouldReceive('waitRequest')->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }
}
