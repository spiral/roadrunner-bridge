<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\App\GRPC\Ping\PingResponse;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\ConsoleTestCase;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class DispatcherTest extends ConsoleTestCase
{
    #[Test]
    public function canServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    #[Test]
    public function canServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Grpc);
        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    #[Test]
    public function serve(): void
    {
        $worker = $this->mockContainer(WorkerInterface::class, Worker::class);
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Grpc);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $worker->shouldReceive('waitPayload')->once()->andReturn(
            new Payload(
                (new PingResponse())->serializeToString(),
                \json_encode(['service' => 'service.Echo', 'method' => 'Ping', 'context' => []]),
            ),
        );

        $worker->shouldReceive('respond')->once()->withArgs(static function (Payload $payload) {
            Assert::same($payload->body, (new PingResponse())->serializeToString());
            return true;
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->generateGRPCService();
    }

    #[AfterTest]
    protected function cleanUp(): void
    {
        $this->deleteGRPCService();
    }
}
