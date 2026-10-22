<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use RoadRunner\Centrifugo\Request\Publish;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\App\Centrifugo\ScopedTestService;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\ServiceRegistry;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Testing\Attribute\Config;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class DispatcherTest extends TestCase
{
    #[Test]
    public function canServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    #[Test]
    public function canServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Centrifuge);

        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    #[Test]
    public function serve(): void
    {
        $worker = $this->mockContainer(CentrifugoWorker::class, CentrifugoWorkerInterface::class);

        $request = new Publish(\Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing(), '', '', '', '', '', '', [], [], []);
        $service = \Mockery::mock(ServiceInterface::class);
        $service->shouldReceive('handle')->once()->with(\Mockery::isEqual($request), \Mockery::andAnyOtherArgs());
        $registry = new ServiceRegistry(
            [RequestType::Publish->value => $service],
            $this->getContainer(),
            $this->getContainer(),
        );

        $errorHandler = \Mockery::mock(ErrorHandlerInterface::class);
        $errorHandler->shouldReceive('handle')->never();
        $this->getContainer()->bind(ErrorHandlerInterface::class, $errorHandler);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Centrifuge);
        $this->getContainer()->bind(RegistryInterface::class, $registry);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once();

        $worker->shouldReceive('waitRequest')->once()->andReturn($request);
        $worker->shouldReceive('waitRequest')->once()->with()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    #[Test]
    public function serveWithHandleException(): void
    {
        $worker = $this->mockContainer(CentrifugoWorker::class, CentrifugoWorkerInterface::class);

        $error = new \Error('foo');
        $request = new Publish(\Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing(), '', '', '', '', '', '', [], [], []);
        $service = \Mockery::mock(ServiceInterface::class);
        $service->shouldReceive('handle')->once()->with(\Mockery::isEqual($request), \Mockery::andAnyOtherArgs())->andThrow($error);
        $registry = new ServiceRegistry(
            [RequestType::Publish->value => $service],
            $this->getContainer(),
            $this->getContainer(),
        );

        $errorHandler = \Mockery::mock(ErrorHandlerInterface::class);
        $errorHandler->shouldReceive('handle')->once()->with(\Mockery::isEqual($request), \Mockery::isEqual($error), \Mockery::andAnyOtherArgs());
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
    #[Test]
    public function centrifugoScope(): void
    {
        Assert::equals(ScopedTestService::$scopes, []);

        $worker = $this->mockContainer(CentrifugoWorker::class, CentrifugoWorkerInterface::class);
        $this->getContainer()
            ->getBinder('centrifugo-request')
            ->bindSingleton(ScopedTestService::class, ScopedTestService::class);

        $request = new Publish(\Mockery::mock(WorkerInterface::class)->shouldIgnoreMissing(), '', '', '', '', '', '', [], [], []);

        $this->mockContainer(ErrorHandlerInterface::class);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Centrifuge);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once();

        $worker->shouldReceive('waitRequest')->once()->andReturn($request);
        $worker->shouldReceive('waitRequest')->once()->with()->andReturnNull();

        $this->getApp()->serve();

        Assert::equals(ScopedTestService::$scopes, ['centrifugo-request', 'centrifugo', 'root']);
    }
}
