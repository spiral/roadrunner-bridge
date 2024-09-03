<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use PHPUnit\Framework\Constraint\IsEqual;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use RoadRunner\Centrifugo\Request\Publish;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\App\Centrifugo\ScopedTestService;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Dispatcher;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceRegistry;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Testing\Attribute\Config;
use Spiral\Tests\TestCase;

final class DispatcherTest extends TestCase
{
    public function testCanServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    public function testCanServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Centrifuge);

        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    public function testServe(): void
    {
        $worker = $this->mockContainer(CentrifugoWorker::class, CentrifugoWorkerInterface::class);

        $request = new Publish($this->createMock(WorkerInterface::class), '', '', '', '', '', '', [], [], []);
        $service = $this->createMock(ServiceInterface::class);
        $service
            ->expects($this->once())
            ->method('handle')
            ->with(new IsEqual($request));
        $registry = new ServiceRegistry(
            [RequestType::Publish->value => $service],
            $this->getContainer(),
            $this->getContainer()
        );

        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler
            ->expects($this->never())
            ->method('handle');
        $this->getContainer()->bind(ErrorHandlerInterface::class, $errorHandler);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Centrifuge);
        $this->getContainer()->bind(RegistryInterface::class, $registry);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once();

        $worker->shouldReceive('waitRequest')->once()->andReturn($request);
        $worker->shouldReceive('waitRequest')->once()->with()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    public function testServeWithHandleException(): void
    {
        $worker = $this->mockContainer(CentrifugoWorker::class, CentrifugoWorkerInterface::class);

        $error = new \Error('foo');
        $request = new Publish($this->createMock(WorkerInterface::class), '', '', '', '', '', '', [], [], []);
        $service = $this->createMock(ServiceInterface::class);
        $service
            ->expects($this->once())
            ->method('handle')
            ->with(new IsEqual($request))
            ->willThrowException($error);
        $registry = new ServiceRegistry(
            [RequestType::Publish->value => $service],
            $this->getContainer(),
            $this->getContainer()
        );

        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler
            ->expects($this->once())
            ->method('handle')
            ->with(new IsEqual($request), new IsEqual($error));
        $this->getContainer()->bind(ErrorHandlerInterface::class, $errorHandler);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Centrifuge);
        $this->getContainer()->bind(RegistryInterface::class, $registry);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once();

        $worker->shouldReceive('waitRequest')->once()->andReturn($request);
        $worker->shouldReceive('waitRequest')->once()->with()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    #[Config('centrifugo.services', ['publish' => ScopedTestService::class])]
    public function testCentrifugoScope(): void
    {
        $this->assertEquals([], ScopedTestService::$scopes);

        $worker = $this->mockContainer(CentrifugoWorker::class, CentrifugoWorkerInterface::class);
        $this->getContainer()
            ->getBinder('centrifugo-request')
            ->bindSingleton(ScopedTestService::class, ScopedTestService::class);

        $request = new Publish($this->createMock(WorkerInterface::class), '', '', '', '', '', '', [], [], []);

        $this->mockContainer(ErrorHandlerInterface::class);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Centrifuge);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once();

        $worker->shouldReceive('waitRequest')->once()->andReturn($request);
        $worker->shouldReceive('waitRequest')->once()->with()->andReturnNull();

        $this->getApp()->serve();

        $this->assertEquals(['centrifugo-request', 'centrifugo', 'root'], ScopedTestService::$scopes);
    }
}
