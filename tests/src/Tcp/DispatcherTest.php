<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp;

use Spiral\App\Tcp\ServiceWithException;
use Spiral\App\Tcp\TestInterceptor;
use Spiral\App\Tcp\TestService;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tcp\TcpWorkerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher;
use Spiral\Tests\TestCase;

final class DispatcherTest extends TestCase
{
    public function testCanServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    public function testCanServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Tcp);

        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    public function testServe(): void
    {
        $worker = $this->mockContainer(WorkerInterface::class);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Tcp);
        $this->updateConfig('tcp.services', ['tcp-server' => TestService::class]);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
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

        $this->serveDispatcher(Dispatcher::class);
    }

    public function testServeWithInterceptor(): void
    {
        $worker = $this->mockContainer(WorkerInterface::class);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Tcp);
        $this->updateConfig('tcp.services', ['tcp-server' => TestService::class]);
        $this->updateConfig('tcp.interceptors', ['tcp-server' => TestInterceptor::class]);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
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

        $this->serveDispatcher(Dispatcher::class);
    }

    public function testServeWithHandleExceptionAndClose(): void
    {
        $worker = $this->mockContainer(WorkerInterface::class);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Tcp);
        $this->updateConfig('tcp.services', ['tcp-server' => ServiceWithException::class]);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
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
            return $payload->header === 'CLOSE';
        });
        $worker->shouldReceive('error')->once()->withArgs(function (string $error) {
            return $error === 'some error';
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();
        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
            return $payload->header === 'CLOSE';
        });

        $this->serveDispatcher(Dispatcher::class);
    }
}
