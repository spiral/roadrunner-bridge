<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp;

use Spiral\App\Tcp\TestInterceptor;
use Spiral\App\Tcp\TestService;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tcp\TcpWorkerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher;
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
                'RR_MODE' => Environment\Mode::MODE_TCP,
            ]);
        });

        $this->assertTrue($this->app->get(Dispatcher::class)->canServe());
    }

    public function testServe(): void
    {
        $worker = \Mockery::mock(WorkerInterface::class);
        $this->container->bind(WorkerInterface::class, $worker);

        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => Environment\Mode::MODE_TCP,
            ]);
        });
        $this->updateConfig('tcp.services', ['tcp-server' => TestService::class]);

        $finalizer = \Mockery::mock(FinalizerInterface::class);
        $this->container->bind(FinalizerInterface::class, $finalizer);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $worker->shouldReceive('waitPayload')->once()->andReturn(
            new Payload(
                'test',
                \json_encode([
                    'remote_addr' => '127.0.0.1',
                    'server' => 'tcp-server',
                    'event' => TcpWorkerInterface::EVENT_DATA,
                    'uuid' => 'test-uuid',
                ])
            )
        );
        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
            return $payload->body === 'test';
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();
        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
            return $payload->header === 'CLOSE';
        });

        $this->app->get(Dispatcher::class)->serve();
    }

    public function testServeWithInterceptor(): void
    {
        $worker = \Mockery::mock(WorkerInterface::class);
        $this->container->bind(WorkerInterface::class, $worker);

        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => Environment\Mode::MODE_TCP,
            ]);
        });
        $this->updateConfig('tcp.services', ['tcp-server' => TestService::class]);
        $this->updateConfig('tcp.interceptors', [TestInterceptor::class]);

        $finalizer = \Mockery::mock(FinalizerInterface::class);
        $this->container->bind(FinalizerInterface::class, $finalizer);
        $finalizer->shouldReceive('finalize')->times(5)->with(false);

        $worker->shouldReceive('waitPayload')->times(5)->andReturn(
            new Payload(
                'test',
                \json_encode([
                    'remote_addr' => '127.0.0.1',
                    'server' => 'tcp-server',
                    'event' => TcpWorkerInterface::EVENT_DATA,
                    'uuid' => 'test-uuid',
                ])
            )
        );
        $worker->shouldReceive('respond')->times(5)->withArgs(function (Payload $payload) {
            if ($payload->header === TcpWorkerInterface::TCP_READ) {
                return true;
            }

            $body = \json_decode($payload->body, true, 512, JSON_THROW_ON_ERROR);

            return \count($body) === 5 || $payload->header === TcpWorkerInterface::TCP_READ;
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();
        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
            return $payload->header === 'CLOSE';
        });

        $this->app->get(Dispatcher::class)->serve();
    }
}
